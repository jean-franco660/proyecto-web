<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

/**
 * StatsController — Estadísticas del dashboard.
 * Esquema v2:
 *   - asistencias.estado_id FK → estados_asistencia (antes: estado_diario ENUM)
 *   - asistencias.usuario_sede_id FK → usuario_sede (antes: sede_id directo)
 *   - justificaciones.estado_id FK → estados_justificacion (antes: estado ENUM)
 *   - usuario_sede (antes: usuario_app_sede / usuario_web_sede)
 */
class StatsController extends BaseWebController
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
     * GET /v1/web/stats
     * Resumen del día de hoy para el panel principal.
     * Filtros: ?sede_id=1&fecha=2025-03-04
     */
    public function dashboard(Request $req): void
    {
        $sedeId = $req->query('sede_id');
        $fecha  = $req->query('fecha', date('Y-m-d'));

        $whereSede = '1=1';
        $params    = [':fecha' => $fecha];

        // Supervisor: solo sus sedes
        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (empty($misSedes)) {
                Response::success([
                    'fecha'                     => $fecha,
                    'presentes'                 => 0,
                    'tardanzas'                 => 0,
                    'faltas'                    => 0,
                    'justificados'              => 0,
                    'total_registrados'         => 0,
                    'justificaciones_pendientes' => 0,
                ]);
                return;
            }
            $in = implode(',', array_map('intval', $misSedes));
            $whereSede = "us.sede_id IN ({$in})";
        }

        if ($sedeId) {
            $whereSede .= ' AND us.sede_id = :sid';
            $params[':sid'] = (int) $sedeId;
        }

        // Totales del día
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(ea.nombre = 'PRESENTE')    AS presentes,
                SUM(ea.nombre = 'TARDANZA')    AS tardanzas,
                SUM(ea.nombre = 'FALTA')       AS faltas,
                SUM(ea.nombre = 'JUSTIFICADO') AS justificados
            FROM asistencias a
            INNER JOIN estados_asistencia ea ON ea.id = a.estado_id
            INNER JOIN usuario_sede us ON us.id = a.usuario_sede_id
            WHERE {$whereSede} AND a.fecha = :fecha
        ");
        $stmt->execute($params);
        $totales = $stmt->fetch();

        // Justificaciones pendientes (estado_id=1 → PENDIENTE)
        $whereJust = 'j.estado_id = 1';
        $params3 = [];

        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            $in = implode(',', array_map('intval', $misSedes));
            $whereJust .= " AND j.usuario_id IN (
                SELECT us2.usuario_id FROM usuario_sede us2
                WHERE us2.sede_id IN ({$in})
                  AND us2.estado = 1
                  AND (us2.fecha_fin IS NULL OR us2.fecha_fin >= CURDATE())
            )";
        }

        if ($sedeId) {
            $whereJust .= " AND j.usuario_id IN (
                SELECT us3.usuario_id FROM usuario_sede us3
                WHERE us3.sede_id = :sid3 AND us3.estado = 1
                  AND (us3.fecha_fin IS NULL OR us3.fecha_fin >= CURDATE())
            )";
            $params3[':sid3'] = (int) $sedeId;
        }

        $stmt3 = $this->db->prepare("
            SELECT COUNT(*) AS justificaciones_pendientes
            FROM justificaciones j
            WHERE {$whereJust}
        ");
        $stmt3->execute($params3);
        $justPendientes = $stmt3->fetch();

        Response::success([
            'fecha'                      => $fecha,
            'presentes'                  => (int) ($totales['presentes'] ?? 0),
            'tardanzas'                  => (int) ($totales['tardanzas'] ?? 0),
            'faltas'                     => (int) ($totales['faltas'] ?? 0),
            'justificados'               => (int) ($totales['justificados'] ?? 0),
            'total_registrados'          => (int) ($totales['total'] ?? 0),
            'justificaciones_pendientes' => (int) ($justPendientes['justificaciones_pendientes'] ?? 0),
        ]);
    }
}