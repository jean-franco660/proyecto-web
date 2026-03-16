<?php
namespace App\Models;

class Justificacion extends BaseModel
{
    protected string $table = 'justificaciones';

    public function porUsuario(int $usuarioId): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE usuario_app_id = ? ORDER BY created_at DESC",
            [$usuarioId]
        );
    }

    /**
     * Justificaciones PENDIENTES de trabajadores asignados a una sede.
     * La tabla `justificaciones` no tiene sede_id; se llega por usuario_app_sede.
     */
    public function pendientesDeSede(int $sedeId): array
    {
        return $this->query("
            SELECT j.* FROM `{$this->table}` j
            INNER JOIN usuario_app_sede uas ON uas.usuario_app_id = j.usuario_app_id
                AND uas.sede_id = ? AND uas.estado = 'ACTIVO'
            WHERE j.estado = 'PENDIENTE'
            ORDER BY j.created_at DESC
        ", [$sedeId]);
    }
}