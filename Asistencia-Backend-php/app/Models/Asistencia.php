<?php

namespace App\Models;

class Asistencia extends BaseModel
{
    protected string $table = 'asistencias';

    /**
     * Busca la asistencia del día para un usuario_sede_id.
     * Esquema v2: asistencias usa usuario_sede_id (no usuario_app_id + sede_id).
     */
    public function delDia(int $usuarioSedeId, string $fecha): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM `{$this->table}`
            WHERE usuario_sede_id = ? AND fecha = ?
            LIMIT 1
        ");
        $stmt->execute([$usuarioSedeId, $fecha]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Registra un cambio en asistencias_log (auditoría v2).
     */
    public function logCambio(int $asistenciaId, int $estadoAnteriorId, int $estadoNuevoId, int $modifiedBy, ?string $observacion = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO asistencias_log
                (asistencia_id, estado_anterior_id, estado_nuevo_id, modified_by, observacion)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$asistenciaId, $estadoAnteriorId, $estadoNuevoId, $modifiedBy, $observacion]);
    }
}