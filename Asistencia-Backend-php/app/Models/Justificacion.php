<?php
namespace App\Models;

class Justificacion extends BaseModel
{
    protected string $table = 'justificaciones';

    /**
     * Justificaciones de un usuario.
     * Esquema v2: usuario_id en vez de usuario_app_id
     */
    public function porUsuario(int $usuarioId): array
    {
        return $this->query(
            "SELECT j.*, ej.nombre AS estado_nombre
             FROM `{$this->table}` j
             INNER JOIN estados_justificacion ej ON ej.id = j.estado_id
             WHERE j.usuario_id = ?
             ORDER BY j.created_at DESC",
            [$usuarioId]
        );
    }

    /**
     * Justificaciones PENDIENTES de trabajadores asignados a una sede.
     * Esquema v2: usuario_sede en vez de usuario_app_sede, estado_id = 1 (PENDIENTE)
     */
    public function pendientesDeSede(int $sedeId): array
    {
        return $this->query("
            SELECT j.*, ej.nombre AS estado_nombre FROM `{$this->table}` j
            INNER JOIN estados_justificacion ej ON ej.id = j.estado_id
            INNER JOIN usuario_sede us ON us.usuario_id = j.usuario_id
                AND us.sede_id = ? AND us.estado = 1
                AND (us.fecha_fin IS NULL OR us.fecha_fin >= CURDATE())
            WHERE j.estado_id = 1
            ORDER BY j.created_at DESC
        ", [$sedeId]);
    }
}