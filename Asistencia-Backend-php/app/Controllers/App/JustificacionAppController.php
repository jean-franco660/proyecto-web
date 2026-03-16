<?php

namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

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
            SELECT j.*
            FROM justificaciones j
            WHERE j.usuario_app_id = :uid
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
        $tipo    = (string) $req->input('tipo');
        $fInicio = (string) $req->input('fecha_inicio');
        $fFin    = (string) $req->input('fecha_fin');
        $motivo  = (string) $req->input('motivo', '');

        $tipos_validos = [
            'ENFERMEDAD','PERMISO_PERSONAL','LICENCIA','COMISION_SERVICIO',
            'CAPACITACION','DUELO','MATERNIDAD','PATERNIDAD','OLVIDO_MARCACION','OTRO'
        ];

        $errors = [];

        if (!in_array($tipo, $tipos_validos))
            $errors[] = 'tipo inválido';

        $errors = array_merge($errors, $this->validarFechas($fInicio, $fFin));

        if (!$motivo)
            $errors[] = 'motivo es requerido';

        if ($errors)
            Response::unprocessable('Datos incompletos', $errors);

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO justificaciones
                    (usuario_app_id, tipo, fecha_inicio, fecha_fin, motivo, estado)
                VALUES (:uid, :tipo, :fi, :ff, :motivo, 'PENDIENTE')
            ");
            $stmt->execute([
                ':uid'    => $userId,
                ':tipo'   => $tipo,
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
        $stmt = $this->db->prepare(
            "SELECT * FROM justificaciones WHERE id = :id AND usuario_app_id = :uid"
        );
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

        $stmt = $this->db->prepare(
            "SELECT estado FROM justificaciones WHERE id = :id AND usuario_app_id = :uid"
        );
        $stmt->execute([':id' => $id, ':uid' => $uid]);
        $just = $stmt->fetch();

        if (!$just)
            Response::notFound('Justificación no encontrada');
        if ($just['estado'] !== 'PENDIENTE')
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
