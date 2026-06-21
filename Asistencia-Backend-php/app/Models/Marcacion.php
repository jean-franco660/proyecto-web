<?php
namespace App\Models;

/**
 * Modelo Marcacion — reemplaza AsistenciaDiaria en v2.
 * Tabla: marcaciones
 */
class Marcacion extends BaseModel
{
    protected string $table = 'marcaciones';

    /** Marcaciones activas de una asistencia */
    public function porAsistencia(int $asistenciaId): array
    {
        return $this->query(
            "SELECT m.*, tm.nombre AS tipo_nombre
             FROM `{$this->table}` m
             INNER JOIN tipos_marcacion tm ON tm.id = m.tipo_id
             WHERE m.asistencia_id = ? AND m.activo = 1
             ORDER BY m.fecha_hora ASC",
            [$asistenciaId]
        );
    }

    /**
     * Busca una marcación activa de un tipo específico en una asistencia.
     * tipo_id: 1=ENTRADA, 2=SALIDA
     */
    public function activaPorTipo(int $asistenciaId, int $tipoId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM `{$this->table}`
            WHERE asistencia_id = ? AND tipo_id = ? AND activo = 1
            LIMIT 1
        ");
        $stmt->execute([$asistenciaId, $tipoId]);
        return $stmt->fetch() ?: null;
    }
}
