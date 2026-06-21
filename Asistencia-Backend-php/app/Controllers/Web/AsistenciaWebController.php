<?php // phpcs:ignore Generic.Files.LineEndings.InvalidEOLChar

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

/**
 * AsistenciaWebController — Gestión de asistencias/marcaciones desde el panel.
 * Esquema v2:
 *   - asistencias.usuario_sede_id (antes: usuario_app_id + sede_id + horario_sede_id)
 *   - asistencias.estado_id FK → estados_asistencia (antes: estado_diario ENUM string)
 *   - marcaciones (antes: asistencias_diarias) con activo BOOLEAN
 *   - usuario_sede (antes: usuario_app_sede)
 *   - usuarios + usuarios_trabajador (antes: usuarios_app)
 */
class AsistenciaWebController extends BaseWebController
{
    /**
     * Helper: obtiene las sedes del supervisor actual.
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

    /** GET /v1/web/asistencias/{id} — detalle de una marcación */
    public function show(Request $req): void
    {
        $id = (int) $req->param('id');

        $sql = "
            SELECT m.*, a.fecha, ea.nombre AS estado_asistencia,
                us.usuario_id, us.sede_id,
                ut.nombres, ut.apellidos,
                u.codigo_empleado,
                s.nombre AS sede_nombre,
                tm.nombre AS tipo_nombre
            FROM marcaciones m
            INNER JOIN asistencias a     ON a.id = m.asistencia_id
            INNER JOIN estados_asistencia ea ON ea.id = a.estado_id
            INNER JOIN usuario_sede us   ON us.id = a.usuario_sede_id
            INNER JOIN usuarios u        ON u.id = us.usuario_id
            LEFT JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
            INNER JOIN sedes s           ON s.id = us.sede_id
            INNER JOIN tipos_marcacion tm ON tm.id = m.tipo_id
            WHERE m.id = ? AND m.activo = 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $marcacion = $stmt->fetch();

        if (!$marcacion) {
            Response::notFound('Marcación no encontrada');
        }

        // Supervisor: solo puede ver sus sedes
        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (!in_array($marcacion['sede_id'], $misSedes)) {
                Response::error('Sin acceso a esta marcación', 403);
            }
        }

        Response::success($marcacion);
    }

    /** GET /v1/web/asistencias — listar marcaciones */
    public function index(Request $req): void
    {
        $page    = (int) $req->query('page', 1);
        $perPage = (int) $req->query('per_page', 25);
        $offset  = ($page - 1) * $perPage;

        $where = "m.activo = 1";
        $params = [];

        // Supervisor solo ve sus sedes
        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (empty($misSedes)) {
                Response::success([
                    'current_page' => $page,
                    'data'         => [],
                    'total'        => 0,
                    'last_page'    => 1,
                    'per_page'     => $perPage
                ]);
                return;
            }
            $in = implode(',', array_map('intval', $misSedes));
            $where .= " AND us.sede_id IN ({$in})";
        }

        if ($req->query('sede_id')) {
            $where .= " AND us.sede_id = :sid";
            $params[':sid'] = (int) $req->query('sede_id');
        }
        if ($req->query('fecha_inicio')) {
            $where .= " AND DATE(m.fecha_hora) >= :fi";
            $params[':fi'] = $req->query('fecha_inicio');
        }
        if ($req->query('fecha_fin')) {
            $where .= " AND DATE(m.fecha_hora) <= :ff";
            $params[':ff'] = $req->query('fecha_fin');
        }
        if ($req->query('estado')) {
            $where .= " AND ea.nombre = :estado";
            $params[':estado'] = $req->query('estado');
        }

        // Count totals
        $stmtCount = $this->db->prepare("
            SELECT COUNT(*)
            FROM marcaciones m
            INNER JOIN asistencias a ON a.id = m.asistencia_id
            INNER JOIN estados_asistencia ea ON ea.id = a.estado_id
            INNER JOIN usuario_sede us ON us.id = a.usuario_sede_id
            WHERE {$where}
        ");
        foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
        $stmtCount->execute();
        $total = (int) $stmtCount->fetchColumn();

        // Select records
        $sql = "
            SELECT m.id, tm.nombre AS tipo, m.fecha_hora,
                   m.distancia, m.observacion, m.activo,
                   a.fecha, ea.nombre AS estado_asistencia,
                   ut.nombres, ut.apellidos,
                   u.codigo_empleado,
                   s.nombre AS sede_nombre,
                   us.sede_id
            FROM marcaciones m
            INNER JOIN tipos_marcacion tm ON tm.id = m.tipo_id
            INNER JOIN asistencias a  ON a.id = m.asistencia_id
            INNER JOIN estados_asistencia ea ON ea.id = a.estado_id
            INNER JOIN usuario_sede us ON us.id = a.usuario_sede_id
            INNER JOIN usuarios u     ON u.id = us.usuario_id
            LEFT JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
            INNER JOIN sedes s        ON s.id = us.sede_id
            WHERE {$where}
            ORDER BY m.fecha_hora DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        Response::success([
            'current_page' => $page,
            'data'         => $records,
            'total'        => $total,
            'last_page'    => ceil($total / $perPage),
            'per_page'     => $perPage
        ]);
    }

    /**
     * PUT /v1/web/asistencias/{id}/review
     * El admin/supervisor cambia el estado de una asistencia y registra en el log de auditoría.
     * Body: { "estado": "PRESENTE" | "TARDANZA" | "FALTA" | "JUSTIFICADO", "observacion": "..." }
     */
    public function updateReview(Request $req): void
    {
        $id          = (int) $req->param('id');
        $nuevoEstado = strtoupper(trim((string) $req->input('estado')));
        $observacion = (string) $req->input('observacion', '');

        // Obtener el ID del nuevo estado desde el catálogo
        $stmtEst = $this->db->prepare("SELECT id FROM estados_asistencia WHERE nombre = ?");
        $stmtEst->execute([$nuevoEstado]);
        $nuevoEstadoId = $stmtEst->fetchColumn();
        if (!$nuevoEstadoId) {
            Response::unprocessable('Estado inválido. Use: PENDIENTE, PRESENTE, TARDANZA, FALTA, JUSTIFICADO');
        }

        // Obtener la asistencia actual
        $stmt = $this->db->prepare("
            SELECT a.id, a.estado_id, us.sede_id
            FROM asistencias a
            INNER JOIN usuario_sede us ON us.id = a.usuario_sede_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $asistencia = $stmt->fetch();
        if (!$asistencia) {
            Response::notFound('Asistencia no encontrada');
        }

        // Supervisor: solo puede revisar su sede
        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (!in_array($asistencia['sede_id'], $misSedes)) {
                Response::error('Sin acceso a esta asistencia', 403);
            }
        }

        $estadoAnteriorId = (int) $asistencia['estado_id'];

        $this->db->beginTransaction();
        try {
            // Actualizar estado de la asistencia
            $this->db->prepare("
                UPDATE asistencias
                SET estado_id = ?, modified_by = ?, modified_at = NOW()
                WHERE id = ?
            ")->execute([$nuevoEstadoId, $this->userId(), $id]);

            // Registrar en asistencias_log (auditoría v2)
            $this->db->prepare("
                INSERT INTO asistencias_log
                    (asistencia_id, estado_anterior_id, estado_nuevo_id, modified_by, observacion)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$id, $estadoAnteriorId, $nuevoEstadoId, $this->userId(), $observacion ?: null]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[AsistenciaWebController::updateReview] Error: ' . $e->getMessage());
            Response::error('Error al actualizar la revisión. Intente nuevamente.', 500);
        }

        Response::success(null, 'Revisión guardada correctamente');
    }

    /**
     * GET /v1/web/asistencias/semana
     * Resumen semanal.
     */
    public function resumenSemanal(Request $req): void
    {
        $sedeId = $req->query('sede_id');
        $fechaRef = $req->query('fecha', date('Y-m-d'));
        $dt = new \DateTime($fechaRef);

        $dow    = (int) $dt->format('N');
        $lunes  = (clone $dt)->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');
        $domingo = (clone $dt)->modify('+' . (7 - $dow) . ' days')->format('Y-m-d');

        $whereSede = '1=1';
        $params    = [':fi' => $lunes, ':ff' => $domingo];

        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (empty($misSedes)) {
                Response::success(['semana_inicio' => $lunes, 'semana_fin' => $domingo, 'dias' => []]);
                return;
            }
            $in = implode(',', array_map('intval', $misSedes));
            $whereSede = "us.sede_id IN ({$in})";
        }

        if ($sedeId) {
            $whereSede .= ' AND us.sede_id = :sid';
            $params[':sid'] = (int) $sedeId;
        }

        $stmt = $this->db->prepare("
            SELECT
                a.fecha,
                DAYNAME(a.fecha) AS dia_nombre,
                COUNT(*)                               AS total,
                SUM(ea.nombre = 'PRESENTE')            AS presentes,
                SUM(ea.nombre = 'TARDANZA')            AS tardanzas,
                SUM(ea.nombre = 'FALTA')               AS faltas,
                SUM(ea.nombre = 'JUSTIFICADO')         AS justificados,
                SUM(ea.nombre = 'PENDIENTE')           AS pendientes
            FROM asistencias a
            INNER JOIN estados_asistencia ea ON ea.id = a.estado_id
            INNER JOIN usuario_sede us ON us.id = a.usuario_sede_id
            WHERE {$whereSede}
              AND a.fecha BETWEEN :fi AND :ff
            GROUP BY a.fecha
            ORDER BY a.fecha ASC
        ");
        $stmt->execute($params);
        $dias = $stmt->fetchAll();

        Response::success([
            'semana_inicio' => $lunes,
            'semana_fin'    => $domingo,
            'dias'          => $dias,
        ]);
    }

    /**
     * GET /v1/web/asistencias/mes-grafico
     */
    public function mesGrafico(Request $req): void
    {
        $fechaRef = $req->query('fecha', date('Y-m-d'));
        $dt = new \DateTime($fechaRef);
        $mes = (int) $dt->format('m');
        $anio = (int) $dt->format('Y');

        $whereSede = '1=1';
        $params    = [':mes' => $mes, ':anio' => $anio];

        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (empty($misSedes)) {
                Response::success(['labels' => [], 'asistencias' => [], 'faltas' => [], 'periodo' => ['mes' => '']]);
                return;
            }
            $in = implode(',', array_map('intval', $misSedes));
            $whereSede = "us.sede_id IN ({$in})";
        }

        if ($req->query('sede_id')) {
            $whereSede .= ' AND us.sede_id = :sid';
            $params[':sid'] = (int) $req->query('sede_id');
        }

        $stmt = $this->db->prepare("
            SELECT
                DAY(a.fecha) as dia,
                SUM(ea.nombre IN ('PRESENTE', 'TARDANZA', 'JUSTIFICADO')) as asistencias,
                SUM(ea.nombre = 'FALTA') as faltas
            FROM asistencias a
            INNER JOIN estados_asistencia ea ON ea.id = a.estado_id
            INNER JOIN usuario_sede us ON us.id = a.usuario_sede_id
            WHERE {$whereSede} AND MONTH(a.fecha) = :mes AND YEAR(a.fecha) = :anio
            GROUP BY a.fecha
            ORDER BY a.fecha ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $labels = [];
        $asistencias = [];
        $faltas = [];
        
        $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        
        $datosPorDia = [];
        foreach ($rows as $r) {
            $datosPorDia[(int)$r['dia']] = $r;
        }

        for ($i = 1; $i <= $diasEnMes; $i++) {
            $labels[] = sprintf("%02d-%02d", $i, $mes);
            if (isset($datosPorDia[$i])) {
                $asistencias[] = (int) $datosPorDia[$i]['asistencias'];
                $faltas[]      = (int) $datosPorDia[$i]['faltas'];
            } else {
                $asistencias[] = 0;
                $faltas[]      = 0;
            }
        }

        $meses = ['', 'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        Response::success([
            'labels'      => $labels,
            'asistencias' => $asistencias,
            'faltas'      => $faltas,
            'periodo'     => ['mes' => $meses[$mes] . " " . $anio]
        ]);
    }

    private function enviarExcel(array $registros, string $filename, array $columnas, callable $rowCallback): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        
        echo "<table border='1' style='border-collapse:collapse; font-family:Arial, sans-serif;'>";
        echo "<tr style='background-color:#1e293b; color:white; font-weight:bold;'>";
        foreach ($columnas as $col) {
            echo "<th style='padding:8px;'>" . htmlspecialchars($col) . "</th>";
        }
        echo "</tr>";
        
        if ($registros) {
            foreach ($registros as $r) {
                echo "<tr>";
                $rowCallback($r);
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='" . count($columnas) . "' style='text-align:center; padding:12px; color:#94a3b8;'>No hay registros que coincidan con la búsqueda</td></tr>";
        }
        echo "</table>";
        exit;
    }

    /** GET /v1/web/reportes/consolidado */
    public function reporteConsolidado(Request $req): void
    {
        $params = [];
        $where  = [];

        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (empty($misSedes)) {
                $registros = [];
            } else {
                $in = implode(',', array_map('intval', $misSedes));
                $where[] = "us.sede_id IN ({$in})";
            }
        }

        if (!isset($registros)) {
            if ($req->query('sede_id')) {
                $where[]        = 'us.sede_id = :sid';
                $params[':sid'] = (int) $req->query('sede_id');
            }
            if ($req->query('fecha_inicio')) {
                $where[]        = 'a.fecha >= :fi';
                $params[':fi']  = $req->query('fecha_inicio');
            }
            if ($req->query('fecha_fin')) {
                $where[]        = 'a.fecha <= :ff';
                $params[':ff']  = $req->query('fecha_fin');
            }

            $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

            $stmt = $this->db->prepare("
                SELECT
                    u.codigo_empleado,
                    CONCAT(IFNULL(ut.apellidos,''), ', ', IFNULL(ut.nombres,'')) AS trabajador,
                    ut.dni,
                    s.nombre          AS sede,
                    hs.nombre         AS turno,
                    a.fecha,
                    a.minutos_tarde,
                    ea.nombre         AS estado,
                    entrada.fecha_hora AS entrada_fecha_hora,
                    salida.fecha_hora  AS salida_fecha_hora
                FROM asistencias a
                INNER JOIN estados_asistencia ea ON ea.id = a.estado_id
                INNER JOIN usuario_sede us  ON us.id = a.usuario_sede_id
                INNER JOIN usuarios u       ON u.id = us.usuario_id
                LEFT JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
                INNER JOIN sedes s          ON s.id = us.sede_id
                INNER JOIN horarios_sede hs ON hs.id = us.horario_id
                LEFT JOIN marcaciones entrada
                       ON entrada.asistencia_id = a.id AND entrada.tipo_id = 1 AND entrada.activo = 1
                LEFT JOIN marcaciones salida
                       ON salida.asistencia_id = a.id  AND salida.tipo_id = 2 AND salida.activo = 1
                {$whereSQL}
                ORDER BY a.fecha DESC, ut.apellidos ASC
                LIMIT 5000
            ");
            $stmt->execute($params);
            $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $columnas = ['Código Empleado', 'Trabajador', 'DNI', 'Sede', 'Turno', 'Fecha', 'Minutos Tarde', 'Estado', 'Entrada', 'Salida'];
        $filename = 'reporte_consolidado_' . date('Ymd_His');

        $this->enviarExcel($registros, $filename, $columnas, function($r) {
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['codigo_empleado']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['trabajador']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['dni']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['sede']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['turno'] ?? 'Sin turno') . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['fecha']) . "</td>";
            echo "<td style='padding:8px; text-align:right;'>" . (int)$r['minutos_tarde'] . "</td>";
            
            $color = '#475569'; $bg = '#f1f5f9';
            if ($r['estado'] === 'PRESENTE') { $color = '#15803d'; $bg = '#dcfce7'; }
            elseif ($r['estado'] === 'TARDANZA') { $color = '#b45309'; $bg = '#fef3c7'; }
            elseif ($r['estado'] === 'FALTA') { $color = '#b91c1c'; $bg = '#fee2e2'; }
            elseif ($r['estado'] === 'JUSTIFICADO') { $color = '#4338ca'; $bg = '#e0e7ff'; }
            
            echo "<td style='padding:8px; background-color:$bg; color:$color; font-weight:bold; text-align:center;'>" . htmlspecialchars($r['estado']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['entrada_fecha_hora'] ?? '—') . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['salida_fecha_hora'] ?? '—') . "</td>";
        });
    }

    /** GET /v1/web/reportes/individual */
    public function reporteIndividual(Request $req): void
    {
        $usuarioId = (int)$req->query('usuario_id');
        if (!$usuarioId) {
            Response::error('El ID del trabajador es requerido', 400);
        }

        $params = [':uid' => $usuarioId];
        $where  = ['us.usuario_id = :uid'];

        if ($req->query('fecha_inicio')) {
            $where[]        = 'a.fecha >= :fi';
            $params[':fi']  = $req->query('fecha_inicio');
        }
        if ($req->query('fecha_fin')) {
            $where[]        = 'a.fecha <= :ff';
            $params[':ff']  = $req->query('fecha_fin');
        }

        $whereSQL = 'WHERE ' . implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT
                a.fecha,
                s.nombre          AS sede,
                hs.nombre         AS turno,
                a.minutos_tarde,
                ea.nombre         AS estado,
                entrada.fecha_hora AS entrada_fecha_hora,
                salida.fecha_hora  AS salida_fecha_hora
            FROM asistencias a
            INNER JOIN estados_asistencia ea ON ea.id = a.estado_id
            INNER JOIN usuario_sede us  ON us.id = a.usuario_sede_id
            INNER JOIN sedes s          ON s.id = us.sede_id
            INNER JOIN horarios_sede hs ON hs.id = us.horario_id
            LEFT JOIN marcaciones entrada
                   ON entrada.asistencia_id = a.id AND entrada.tipo_id = 1 AND entrada.activo = 1
            LEFT JOIN marcaciones salida
                   ON salida.asistencia_id = a.id  AND salida.tipo_id = 2 AND salida.activo = 1
            {$whereSQL}
            ORDER BY a.fecha DESC
            LIMIT 5000
        ");
        $stmt->execute($params);
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmtUser = $this->db->prepare("
            SELECT CONCAT(IFNULL(nombres,''), ' ', IFNULL(apellidos,'')) AS nombre_completo, dni 
            FROM usuarios_trabajador WHERE usuario_id = ?
        ");
        $stmtUser->execute([$usuarioId]);
        $userInfo = $stmtUser->fetch();
        $nombreTrabajador = $userInfo ? $userInfo['nombre_completo'] : 'Trabajador';

        $columnas = ['Fecha', 'Sede', 'Turno', 'Entrada', 'Salida', 'Minutos Tarde', 'Estado'];
        $filename = 'reporte_individual_' . str_replace(' ', '_', strtolower($nombreTrabajador)) . '_' . date('Ymd_His');

        $this->enviarExcel($registros, $filename, $columnas, function($r) {
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['fecha']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['sede']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['turno'] ?? 'Sin turno') . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['entrada_fecha_hora'] ?? '—') . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['salida_fecha_hora'] ?? '—') . "</td>";
            echo "<td style='padding:8px; text-align:right;'>" . (int)$r['minutos_tarde'] . "</td>";

            $color = '#475569'; $bg = '#f1f5f9';
            if ($r['estado'] === 'PRESENTE') { $color = '#15803d'; $bg = '#dcfce7'; }
            elseif ($r['estado'] === 'TARDANZA') { $color = '#b45309'; $bg = '#fef3c7'; }
            elseif ($r['estado'] === 'FALTA') { $color = '#b91c1c'; $bg = '#fee2e2'; }
            elseif ($r['estado'] === 'JUSTIFICADO') { $color = '#4338ca'; $bg = '#e0e7ff'; }

            echo "<td style='padding:8px; background-color:$bg; color:$color; font-weight:bold; text-align:center;'>" . htmlspecialchars($r['estado']) . "</td>";
        });
    }

    /** GET /v1/web/reportes/sedes */
    public function reporteSedes(Request $req): void
    {
        $params = [];
        $where  = [];

        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (empty($misSedes)) {
                $registros = [];
            } else {
                $in = implode(',', array_map('intval', $misSedes));
                $where[] = "us.sede_id IN ({$in})";
            }
        }

        if (!isset($registros)) {
            if ($req->query('sede_id')) {
                $where[]        = 'us.sede_id = :sid';
                $params[':sid'] = (int) $req->query('sede_id');
            }
            if ($req->query('fecha_inicio')) {
                $where[]        = 'a.fecha >= :fi';
                $params[':fi']  = $req->query('fecha_inicio');
            }
            if ($req->query('fecha_fin')) {
                $where[]        = 'a.fecha <= :ff';
                $params[':ff']  = $req->query('fecha_fin');
            }

            $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

            $stmt = $this->db->prepare("
                SELECT
                    s.nombre          AS sede,
                    u.codigo_empleado,
                    CONCAT(IFNULL(ut.apellidos,''), ', ', IFNULL(ut.nombres,'')) AS trabajador,
                    ut.dni,
                    a.fecha,
                    hs.nombre         AS turno,
                    entrada.fecha_hora AS entrada_fecha_hora,
                    salida.fecha_hora  AS salida_fecha_hora,
                    a.minutos_tarde,
                    ea.nombre         AS estado
                FROM asistencias a
                INNER JOIN estados_asistencia ea ON ea.id = a.estado_id
                INNER JOIN usuario_sede us  ON us.id = a.usuario_sede_id
                INNER JOIN usuarios u       ON u.id = us.usuario_id
                LEFT JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
                INNER JOIN sedes s          ON s.id = us.sede_id
                INNER JOIN horarios_sede hs ON hs.id = us.horario_id
                LEFT JOIN marcaciones entrada
                       ON entrada.asistencia_id = a.id AND entrada.tipo_id = 1 AND entrada.activo = 1
                LEFT JOIN marcaciones salida
                       ON salida.asistencia_id = a.id  AND salida.tipo_id = 2 AND salida.activo = 1
                {$whereSQL}
                ORDER BY s.nombre ASC, a.fecha DESC, ut.apellidos ASC
                LIMIT 5000
            ");
            $stmt->execute($params);
            $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $columnas = ['Sede', 'Código Empleado', 'Trabajador', 'DNI', 'Fecha', 'Turno', 'Entrada', 'Salida', 'Minutos Tarde', 'Estado'];
        $filename = 'reporte_por_sedes_' . date('Ymd_His');

        $this->enviarExcel($registros, $filename, $columnas, function($r) {
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['sede']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['codigo_empleado']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['trabajador']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['dni']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['fecha']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['turno'] ?? 'Sin turno') . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['entrada_fecha_hora'] ?? '—') . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['salida_fecha_hora'] ?? '—') . "</td>";
            echo "<td style='padding:8px; text-align:right;'>" . (int)$r['minutos_tarde'] . "</td>";

            $color = '#475569'; $bg = '#f1f5f9';
            if ($r['estado'] === 'PRESENTE') { $color = '#15803d'; $bg = '#dcfce7'; }
            elseif ($r['estado'] === 'TARDANZA') { $color = '#b45309'; $bg = '#fef3c7'; }
            elseif ($r['estado'] === 'FALTA') { $color = '#b91c1c'; $bg = '#fee2e2'; }
            elseif ($r['estado'] === 'JUSTIFICADO') { $color = '#4338ca'; $bg = '#e0e7ff'; }

            echo "<td style='padding:8px; background-color:$bg; color:$color; font-weight:bold; text-align:center;'>" . htmlspecialchars($r['estado']) . "</td>";
        });
    }

    /** GET /v1/web/reportes/mensual */
    public function reporteMensual(Request $req): void
    {
        $mes = (int)$req->query('mes', date('m'));
        $anio = (int)$req->query('anio', date('Y'));

        $params = [':mes' => $mes, ':anio' => $anio];
        $where  = ['MONTH(a.fecha) = :mes', 'YEAR(a.fecha) = :anio'];

        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (empty($misSedes)) {
                $registros = [];
            } else {
                $in = implode(',', array_map('intval', $misSedes));
                $where[] = "us.sede_id IN ({$in})";
            }
        }

        if (!isset($registros)) {
            if ($req->query('sede_id')) {
                $where[]        = 'us.sede_id = :sid';
                $params[':sid'] = (int) $req->query('sede_id');
            }

            $whereSQL = 'WHERE ' . implode(' AND ', $where);

            $stmt = $this->db->prepare("
                SELECT
                    u.codigo_empleado,
                    CONCAT(IFNULL(ut.apellidos,''), ', ', IFNULL(ut.nombres,'')) AS trabajador,
                    ut.dni,
                    s.nombre          AS sede,
                    hs.nombre         AS turno,
                    a.fecha,
                    a.minutos_tarde,
                    ea.nombre         AS estado,
                    entrada.fecha_hora AS entrada_fecha_hora,
                    salida.fecha_hora  AS salida_fecha_hora
                FROM asistencias a
                INNER JOIN estados_asistencia ea ON ea.id = a.estado_id
                INNER JOIN usuario_sede us  ON us.id = a.usuario_sede_id
                INNER JOIN usuarios u       ON u.id = us.usuario_id
                LEFT JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
                INNER JOIN sedes s          ON s.id = us.sede_id
                INNER JOIN horarios_sede hs ON hs.id = us.horario_id
                LEFT JOIN marcaciones entrada
                       ON entrada.asistencia_id = a.id AND entrada.tipo_id = 1 AND entrada.activo = 1
                LEFT JOIN marcaciones salida
                       ON salida.asistencia_id = a.id  AND salida.tipo_id = 2 AND salida.activo = 1
                {$whereSQL}
                ORDER BY a.fecha DESC, ut.apellidos ASC
                LIMIT 5000
            ");
            $stmt->execute($params);
            $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $columnas = ['Código Empleado', 'Trabajador', 'DNI', 'Sede', 'Turno', 'Fecha', 'Minutos Tarde', 'Estado', 'Entrada', 'Salida'];
        $filename = "reporte_mensual_{$anio}_{$mes}_" . date('Ymd_His');

        $this->enviarExcel($registros, $filename, $columnas, function($r) {
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['codigo_empleado']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['trabajador']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['dni']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['sede']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['turno'] ?? 'Sin turno') . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['fecha']) . "</td>";
            echo "<td style='padding:8px; text-align:right;'>" . (int)$r['minutos_tarde'] . "</td>";

            $color = '#475569'; $bg = '#f1f5f9';
            if ($r['estado'] === 'PRESENTE') { $color = '#15803d'; $bg = '#dcfce7'; }
            elseif ($r['estado'] === 'TARDANZA') { $color = '#b45309'; $bg = '#fef3c7'; }
            elseif ($r['estado'] === 'FALTA') { $color = '#b91c1c'; $bg = '#fee2e2'; }
            elseif ($r['estado'] === 'JUSTIFICADO') { $color = '#4338ca'; $bg = '#e0e7ff'; }

            echo "<td style='padding:8px; background-color:$bg; color:$color; font-weight:bold; text-align:center;'>" . htmlspecialchars($r['estado']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['entrada_fecha_hora'] ?? '—') . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($r['salida_fecha_hora'] ?? '—') . "</td>";
        });
    }
}
