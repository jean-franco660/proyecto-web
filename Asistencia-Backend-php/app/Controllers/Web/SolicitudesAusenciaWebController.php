<?php

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;

class SolicitudesAusenciaWebController extends BaseWebController
{
    /**
     * Helper: IDs de sedes del supervisor.
     */
    private function sedesDelSupervisor(): array
    {
        $stmt = $this->db->prepare("
            SELECT sede_id FROM usuario_sede
            WHERE usuario_id = :uid AND estado = 1
              AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
        ");
        $stmt->execute([':uid' => $this->userId()]);
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'sede_id');
    }

    /**
     * GET /v1/web/solicitudes-ausencia
     * Admin ve todas. Supervisor ve solo de sus sedes.
     */
    public function index(Request $req): void
    {
        $sql = "SELECT j.id, j.usuario_id, j.fecha_inicio, j.fecha_fin, j.motivo, j.created_at,
                       TRIM(CONCAT(ut.nombres, ' ', ut.apellidos)) AS trabajador,
                       u.codigo_empleado AS codigo_trabajador,
                       'JUSTIFICACION' AS tipo_codigo,
                       'Justificación' AS tipo_nombre,
                       CASE WHEN ej.nombre = 'APROBADA' THEN 'APROBADO'
                            WHEN ej.nombre = 'RECHAZADA' THEN 'RECHAZADO'
                            ELSE ej.nombre END AS estado
                FROM justificaciones j
                INNER JOIN estados_justificacion ej ON ej.id = j.estado_id
                INNER JOIN usuarios u ON u.id = j.usuario_id
                LEFT JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
                WHERE 1=1";
        $params = [];

        // Supervisor filtra por trabajadores de sus sedes
        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (empty($misSedes)) {
                Response::success([]);
                return;
            }
              $in = implode(',', array_map('intval', $misSedes));
              $sql .= " AND j.usuario_id IN (
                  SELECT us2.usuario_id FROM usuario_sede us2
                  WHERE us2.sede_id IN ({$in})
                    AND us2.estado = 1
                    AND (us2.fecha_fin IS NULL OR us2.fecha_fin >= CURDATE())
              )";
        }

        if ($req->query('estado')) {
            $estadoFiltro = strtoupper(trim($req->query('estado')));
            // Mapear APROBADO -> APROBADA y RECHAZADO -> RECHAZADA para la DB
            if ($estadoFiltro === 'APROBADO') {
                $estadoFiltro = 'APROBADA';
            } elseif ($estadoFiltro === 'RECHAZADO') {
                $estadoFiltro = 'RECHAZADA';
            }
            $sql .= ' AND ej.nombre = :estado';
            $params[':estado'] = $estadoFiltro;
        }

        $sql .= ' ORDER BY j.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    /**
     * POST /v1/web/solicitudes-ausencia/{id}/aprobar
     */
    public function aprobar(Request $req): void
    {
        $id = (int) $req->param('id');

        $stmt = $this->db->prepare("
            SELECT j.*, ej.nombre AS estado_nombre
            FROM justificaciones j
            INNER JOIN estados_justificacion ej ON ej.id = j.estado_id
            WHERE j.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $just = $stmt->fetch();

        if (!$just) {
            Response::notFound('Solicitud no encontrada');
        }
        if ($just['estado_nombre'] !== 'PENDIENTE') {
            Response::error('Solo se pueden aprobar solicitudes pendientes', 400);
        }

        $this->db->beginTransaction();
        try {
            // Aprobar justificación (estado_id=2 → APROBADA)
            $this->db->prepare("
                UPDATE justificaciones SET estado_id = 2 WHERE id = :id
            ")->execute([':id' => $id]);

            // Actualizar asistencias del período a JUSTIFICADO (estado_id=5)
            $this->db->prepare("
                UPDATE asistencias a
                INNER JOIN usuario_sede us ON us.id = a.usuario_sede_id
                SET a.estado_id = 5,
                    a.modified_by = :uid,
                    a.modified_at = NOW()
                WHERE us.usuario_id = :uaid
                  AND a.fecha BETWEEN :fi AND :ff
            ")->execute([
                ':uid'  => $this->userId(),
                ':uaid' => $just['usuario_id'],
                ':fi'   => $just['fecha_inicio'],
                ':ff'   => $just['fecha_fin'],
            ]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[SolicitudesAusenciaWebController::aprobar] Error: ' . $e->getMessage());
            Response::error('Error al aprobar la solicitud. Intente nuevamente.', 500);
        }

        Response::success(null, 'Solicitud aprobada correctamente');
    }

    /**
     * POST /v1/web/solicitudes-ausencia/{id}/rechazar
     */
    public function rechazar(Request $req): void
    {
        $id = (int) $req->param('id');
        $comentario = (string) $req->input('comentario_revision', '');

        if (empty($comentario)) {
            Response::unprocessable('El comentario de revisión es requerido al rechazar');
        }

        $stmt = $this->db->prepare("
            SELECT j.*, ej.nombre AS estado_nombre
            FROM justificaciones j
            INNER JOIN estados_justificacion ej ON ej.id = j.estado_id
            WHERE j.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $just = $stmt->fetch();

        if (!$just) {
            Response::notFound('Solicitud no encontrada');
        }
        if ($just['estado_nombre'] !== 'PENDIENTE') {
            Response::error('Solo se pueden rechazar solicitudes pendientes', 400);
        }

        $this->db->beginTransaction();
        try {
            // Rechazar justificación (estado_id=3 → RECHAZADA)
            $this->db->prepare("
                UPDATE justificaciones SET estado_id = 3 WHERE id = :id
            ")->execute([':id' => $id]);

            // Revertir asistencias a FALTA (estado_id=4)
            $this->db->prepare("
                UPDATE asistencias a
                INNER JOIN usuario_sede us ON us.id = a.usuario_sede_id
                SET a.estado_id = 4,
                    a.modified_by = :uid,
                    a.modified_at = NOW()
                WHERE us.usuario_id = :uaid
                  AND a.fecha BETWEEN :fi AND :ff
            ")->execute([
                ':uid'  => $this->userId(),
                ':uaid' => $just['usuario_id'],
                ':fi'   => $just['fecha_inicio'],
                ':ff'   => $just['fecha_fin'],
            ]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[SolicitudesAusenciaWebController::rechazar] Error: ' . $e->getMessage());
            Response::error('Error al rechazar la solicitud. Intente nuevamente.', 500);
        }

        Response::success(null, 'Solicitud rechazada');
    }
}
