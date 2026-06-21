<?php

namespace App\Models;

class HorarioSede extends BaseModel
{
    protected string $table = 'horarios_sede';

    public function porSede(int $sedeId): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE sede_id = ? AND activo = 1 ORDER BY hora_entrada",
            [$sedeId]
        );
    }

    /** Obtiene los días asignados a un horario */
    public function diasPorHorario(int $horarioId): array
    {
        return $this->query(
            "SELECT dia FROM horario_dias WHERE horario_id = ? ORDER BY dia",
            [$horarioId]
        );
    }

    public function listarConFiltros(int $sedeId, string $rol, int $userId): array
    {
        $sql    = 'SELECT hs.* FROM horarios_sede hs WHERE 1=1';
        $params = [];

        if ($sedeId) {
            $sql .= ' AND hs.sede_id = :sid';
            $params[':sid'] = $sedeId;
        } elseif ($rol === 'supervisor') {
            $sql .= ' AND hs.sede_id IN (
                SELECT sede_id FROM usuario_sede
                WHERE usuario_id = :uid AND estado = 1
                  AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
            )';
            $params[':uid'] = $userId;
        }

        $sql .= ' ORDER BY hs.hora_entrada';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $horarios = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($horarios) {
            $ids = array_column($horarios, 'id');
            $diasRows = $this->diasPorHorarioIds($ids);
            $diasByHorario = [];
            foreach ($diasRows as $d) {
                $diasByHorario[$d['horario_id']][] = (int) $d['dia'];
            }
            foreach ($horarios as &$h) {
                $h['dias_semana'] = $diasByHorario[$h['id']] ?? [];
            }
        }

        return $horarios;
    }

    public function diasPorHorarioIds(array $ids): array
    {
        $inClause = implode(',', array_map('intval', $ids));
        return $this->query("SELECT horario_id, dia FROM horario_dias WHERE horario_id IN ($inClause) ORDER BY dia");
    }

    public function crearHorario(array $data, array $dias): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO horarios_sede
                    (sede_id, nombre, hora_entrada, hora_salida,
                     tolerancia_entrada, tolerancia_salida, activo)
                VALUES (:sid, :n, :he, :hs, :te, :ts, 1)
            ");
            $stmt->execute([
                ':sid' => $data['sede_id'],
                ':n'   => $data['nombre'],
                ':he'  => $data['hora_entrada'],
                ':hs'  => $data['hora_salida'],
                ':te'  => $data['tolerancia_entrada'],
                ':ts'  => $data['tolerancia_salida'],
            ]);
            $horarioId = (int) $this->db->lastInsertId();

            $stmtDia = $this->db->prepare("INSERT INTO horario_dias (horario_id, dia) VALUES (?, ?)");
            foreach ($dias as $dia) {
                $stmtDia->execute([$horarioId, (int) $dia]);
            }

            $this->db->commit();
            return $horarioId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function actualizarHorario(int $id, array $data, ?array $dias): bool
    {
        $this->db->beginTransaction();
        try {
            if (!empty($data)) {
                $campos = [];
                $params = [];
                foreach ($data as $col => $val) {
                    $campos[] = "`{$col}` = ?";
                    $params[] = $val;
                }
                $params[] = $id;
                $stmt = $this->db->prepare("UPDATE horarios_sede SET " . implode(', ', $campos) . " WHERE id = ?");
                $stmt->execute($params);
            }

            if ($dias !== null) {
                $this->sincronizarDias($id, $dias);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function sincronizarDias(int $id, array $dias): bool
    {
        $this->db->prepare("DELETE FROM horario_dias WHERE horario_id = ?")->execute([$id]);
        $stmtDia = $this->db->prepare("INSERT INTO horario_dias (horario_id, dia) VALUES (?, ?)");
        foreach ($dias as $dia) {
            $stmtDia->execute([$id, (int) $dia]);
        }
        return true;
    }

    public function eliminarHorario(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM horario_dias WHERE horario_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM horarios_sede WHERE id = ?")->execute([$id]);
            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
