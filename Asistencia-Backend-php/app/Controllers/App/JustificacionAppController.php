<?php

namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

/**
 * JustificacionAppController — Justificaciones desde la app.
 * Esquema v2:
 *   - justificaciones.usuario_id (antes: usuario_app_id)
 *   - justificaciones.estado_id FK → estados_justificacion (antes: estado ENUM)
 *   - estados_justificacion: 1=PENDIENTE, 2=APROBADA, 3=RECHAZADA
 */
class JustificacionAppController extends BaseAppController
{
    // Valida formato de una fecha
    private function esFechaValida(string $fecha): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $fecha);
        return $d !== false;
    }

    // Valida ambas fechas y su relación
    private function validarFechas(string $fInicio, string $fFin): array
    {
        $errors = [];
        $hoy = date('Y-m-d');

        if (!$fInicio)
            $errors[] = 'fecha_inicio es requerida';
        elseif (!$this->esFechaValida($fInicio))
            $errors[] = 'fecha_inicio formato inválido (Y-m-d)';
        elseif ($fInicio > $hoy)
            $errors[] = 'fecha_inicio no puede ser futura'; 

        if (!$fFin)
            $errors[] = 'fecha_fin es requerida';
        elseif (!$this->esFechaValida($fFin))
            $errors[] = 'fecha_fin formato inválido (Y-m-d)';

        if ($fInicio && $fFin && $fFin < $fInicio)
            $errors[] = 'fecha_fin debe ser mayor o igual a fecha_inicio';

        return $errors;
    }

    /**
     * GET /v1/app/justificaciones
     */
    public function index(Request $req): void
    {
        $stmt = $this->db->prepare("
            SELECT j.*, ej.nombre AS estado_nombre
            FROM justificaciones j
            INNER JOIN estados_justificacion ej ON ej.id = j.estado_id
            WHERE j.usuario_id = :uid
            ORDER BY j.created_at DESC
        ");
        $stmt->execute([':uid' => $this->userId()]);
        Response::success($stmt->fetchAll());
    }

    /**
     * POST /v1/app/justificaciones
     */
    public function store(Request $req): void
    {
        $userId  = $this->userId();
        $fInicio = (string) $req->input('fecha_inicio');
        $fFin    = (string) $req->input('fecha_fin');
        $motivo  = (string) $req->input('motivo', '');

        $errors = [];

        $errors = array_merge($errors, $this->validarFechas($fInicio, $fFin));

        if (!$motivo)
            $errors[] = 'motivo es requerido';

        if ($errors)
            Response::unprocessable('Datos incompletos', $errors);

        try {
            $this->db->beginTransaction();

            // estado_id=1 → PENDIENTE
            $stmt = $this->db->prepare("
                INSERT INTO justificaciones
                    (usuario_id, fecha_inicio, fecha_fin, motivo, estado_id)
                VALUES (:uid, :fi, :ff, :motivo, 1)
            ");
            $stmt->execute([
                ':uid'    => $userId,
                ':fi'     => $fInicio,
                ':ff'     => $fFin,
                ':motivo' => $motivo,
            ]);

            $newId = (int) $this->db->lastInsertId();
            $this->db->commit();

            Response::success(
                ['id' => $newId],
                'Justificación enviada. Pendiente de revisión.',
                201
            );

        } catch (\Exception $e) {
            $this->db->rollBack();
            Response::error('Error al guardar la justificación', 500);
        }
    }

    /**
     * GET /v1/app/justificaciones/{id}
     */
    public function show(Request $req): void
    {
        $id   = (int) $req->param('id');
        $stmt = $this->db->prepare("
            SELECT j.*, ej.nombre AS estado_nombre
            FROM justificaciones j
            INNER JOIN estados_justificacion ej ON ej.id = j.estado_id
            WHERE j.id = :id AND j.usuario_id = :uid
        ");
        $stmt->execute([':id' => $id, ':uid' => $this->userId()]);
        $just = $stmt->fetch();

        if (!$just)
            Response::notFound('Justificación no encontrada');

        Response::success($just);
    }

    /**
     * DELETE /v1/app/justificaciones/{id}
     */
    public function destroy(Request $req): void
    {
        $id  = (int) $req->param('id');
        $uid = $this->userId();

        $stmt = $this->db->prepare("
            SELECT j.estado_id, ej.nombre AS estado_nombre
            FROM justificaciones j
            INNER JOIN estados_justificacion ej ON ej.id = j.estado_id
            WHERE j.id = :id AND j.usuario_id = :uid
        ");
        $stmt->execute([':id' => $id, ':uid' => $uid]);
        $just = $stmt->fetch();

        if (!$just)
            Response::notFound('Justificación no encontrada');
        if ($just['estado_nombre'] !== 'PENDIENTE')
            Response::error('Solo se pueden eliminar justificaciones pendientes', 400);

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("DELETE FROM justificaciones WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $this->db->commit();
            Response::success(null, 'Justificación eliminada correctamente');
        } catch (\Exception $e) {
            $this->db->rollBack();
            Response::error('Error al eliminar la justificación', 500);
        }
    }
}
