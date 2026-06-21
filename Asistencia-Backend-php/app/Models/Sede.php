<?php

namespace App\Models;

class Sede extends BaseModel
{
    protected string $table = 'sedes';

    /** Devuelve solo las sedes activas */
    public function activas(): array
    {
        return $this->query("SELECT * FROM `{$this->table}` WHERE activo = 1 ORDER BY nombre");
    }

    /** Calcula la distancia en metros con la fórmula Haversine (en SQL) */
    public function cercanas(float $lat, float $lng, int $radio = 500): array
    {
        return $this->query("
            SELECT *, (6371000 * acos(
                cos(radians(?)) * cos(radians(latitud)) *
                cos(radians(longitud) - radians(?)) +
                sin(radians(?)) * sin(radians(latitud))
            )) AS distancia
            FROM sedes
            WHERE activo = 1
            HAVING distancia <= ?
            ORDER BY distancia
        ", [$lat, $lng, $lat, $radio]);
    }

    public function listarConFiltros(?string $search, string $sortBy, string $order, int $perPage, int $offset, string $rol, int $userId): array
    {
        $where = 'WHERE 1=1';
        $params = [];

        if ($rol === 'supervisor') {
            $where .= " AND id IN (
                SELECT sede_id FROM usuario_sede
                WHERE usuario_id = :uid AND estado = 1
                  AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
            )";
            $params[':uid'] = $userId;
        }

        if ($search) {
            $where .= ' AND (nombre LIKE :s OR codigo LIKE :s2)';
            $params[':s']  = "%$search%";
            $params[':s2'] = "%$search%";
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM sedes $where ORDER BY $sortBy $order LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function obtenerSedesVigentes(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.* FROM sedes s
            INNER JOIN usuario_sede us ON s.id = us.sede_id
            WHERE us.usuario_id = :uid
              AND us.estado = 1
              AND (us.fecha_fin IS NULL OR us.fecha_fin >= CURDATE())
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}