<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

/**
 * JustificacionWebController — Gestión de justificaciones.
 * Esquema v2:
 *   - justificaciones.usuario_id (antes: usuario_app_id)
 *   - justificaciones.estado_id FK → estados_justificacion (antes: estado ENUM string)
 *   - usuario_sede (antes: usuario_app_sede)
 *   - usuarios + usuarios_trabajador (antes: usuarios_app)
 */
class JustificacionWebController extends BaseWebController
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
     * GET /v1/web/justificaciones
     * Admin ve todas. Supervisor ve solo de sus sedes.
     */
    public function index(Request $req): void
    {
        $sql = "SELECT j.*, ej.nombre AS estado_nombre,
                       ut.nombres, ut.apellidos,
                       u.codigo_empleado
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
            $sql .= ' AND ej.nombre = :estado';
            $params[':estado'] = strtoupper(trim($req->query('estado')));
        }
        if ($req->query('usuario_id')) {
            $sql .= ' AND j.usuario_id = :uid2';
            $params[':uid2'] = (int) $req->query('usuario_id');
        }

        $sql .= ' ORDER BY j.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    /**
     * POST /v1/web/justificaciones/{id}/aprobar
     * Al aprobar → actualiza asistencias del período a JUSTIFICADO (estado_id=5)
     */
    public function aprobar(Request $req): void
    {
        $id   = (int) $req->param('id');
        $obs  = (string) $req->input('observaciones', '');

        $stmt = $this->db->prepare("
            SELECT j.*, ej.nombre AS estado_nombre
            FROM justificaciones j
            INNER JOIN estados_justificacion ej ON ej.id = j.estado_id
            WHERE j.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $just = $stmt->fetch();

        if (!$just) Response::notFound('Justificación no encontrada');
        if ($just['estado_nombre'] !== 'PENDIENTE')
            Response::error('Solo se pueden aprobar justificaciones pendientes', 400);

        $this->db->beginTransaction();
        try {
            // Aprobar justificación (estado_id=2 → APROBADA)
            $this->db->prepare("
                UPDATE justificaciones SET estado_id = 2 WHERE id = :id
            ")->execute([':id' => $id]);

            // Actualizar asistencias del período a JUSTIFICADO (estado_id=5)
            // Las asistencias ahora usan usuario_sede_id, hay que buscar
            // todos los usuario_sede_id del usuario
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
            error_log('[JustificacionWebController::aprobar] Error: ' . $e->getMessage());
            Response::error('Error al aprobar la justificación. Intente nuevamente.', 500);
        }

        Response::success(null, 'Justificación aprobada correctamente');
    }

    /**
     * POST /v1/web/justificaciones/{id}/rechazar
     * Al rechazar → asistencias del período quedan como FALTA (estado_id=4)
     */
    public function rechazar(Request $req): void
    {
        $id   = (int) $req->param('id');
        $obs  = (string) $req->input('observaciones', '');

        if (empty($obs)) Response::unprocessable('Las observaciones son requeridas al rechazar');

        $stmt = $this->db->prepare("
            SELECT j.*, ej.nombre AS estado_nombre
            FROM justificaciones j
            INNER JOIN estados_justificacion ej ON ej.id = j.estado_id
            WHERE j.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $just = $stmt->fetch();

        if (!$just) Response::notFound('Justificación no encontrada');
        if ($just['estado_nombre'] !== 'PENDIENTE')
            Response::error('Solo se pueden rechazar justificaciones pendientes', 400);

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
            error_log('[JustificacionWebController::rechazar] Error: ' . $e->getMessage());
            Response::error('Error al rechazar la justificación. Intente nuevamente.', 500);
        }

        Response::success(null, 'Justificación rechazada');
    }

    /** GET /v1/web/justificaciones/{id} */
    public function show(Request $req): void
    {
        $id   = (int) $req->param('id');
        $stmt = $this->db->prepare("
            SELECT j.*, ej.nombre AS estado_nombre,
                   ut.nombres, ut.apellidos,
                   u.codigo_empleado
            FROM justificaciones j
            INNER JOIN estados_justificacion ej ON ej.id = j.estado_id
            INNER JOIN usuarios u ON u.id = j.usuario_id
            LEFT JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
            WHERE j.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $just = $stmt->fetch();
        if (!$just) Response::notFound('Justificación no encontrada');
        Response::success($just);
    }

    /** DELETE /v1/web/justificaciones/{id} */
    public function destroy(Request $req): void
    {
        $id   = (int) $req->param('id');
        $stmt = $this->db->prepare("
            SELECT j.estado_id, ej.nombre AS estado_nombre
            FROM justificaciones j
            INNER JOIN estados_justificacion ej ON ej.id = j.estado_id
            WHERE j.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $just = $stmt->fetch();

        if (!$just) Response::notFound('Justificación no encontrada');
        if ($just['estado_nombre'] !== 'PENDIENTE')
            Response::error('Solo se pueden eliminar justificaciones pendientes', 400);

        $stmt = $this->db->prepare("DELETE FROM justificaciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        Response::success(null, 'Justificación eliminada correctamente');
    }
}