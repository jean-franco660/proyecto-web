<?php // phpcs:ignore Generic.Files.LineEndings.InvalidEOLChar

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class AsistenciaWebController extends BaseWebController
{

    public function show(Request $req): void
    {
        $id = (int) $req->param('id');

        $sql = "
            SELECT ad.*, a.fecha, a.estado_diario, a.usuario_app_id,
                u.nombres, u.apellido_paterno, u.codigo_empleado,
                s.nombre AS sede_nombre
            FROM asistencias_diarias ad
            INNER JOIN asistencias a ON a.id = ad.asistencia_id
            INNER JOIN usuarios_app u ON u.id = a.usuario_app_id
            INNER JOIN sedes s ON s.id = a.sede_id
            WHERE ad.id = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $marcacion = $stmt->fetch();

        if (!$marcacion) {
            Response::notFound('Marcación no encontrada');
        }

        // Supervisor: solo puede ver sus sedes
        if ($this->rol() === 'supervisor') {
            $stmtChk = $this->db->prepare("
                SELECT id FROM usuario_web_sede
                WHERE usuario_web_id = ? AND sede_id = ? AND activo = 1
            ");
            $stmtChk->execute([$this->userId(), $marcacion['sede_id']]);
            if (!$stmtChk->fetch()) {
                Response::error('Sin acceso a esta marcación', 403);
            }
        }

        unset($marcacion['latitud'], $marcacion['longitud']); // Datos sensibles (opcional)
        Response::success($marcacion);
    }
    public function index(Request $req): void
    {
        $sql = "
            SELECT ad.id, ad.tipo, ad.marcada_en, ad.distancia_metros,
                   ad.estado_marcacion, ad.motivo_observacion, ad.estado_revision,
                   a.fecha, a.estado_diario,
                   u.nombres, u.apellido_paterno, u.codigo_empleado,
                   s.nombre AS sede_nombre
            FROM asistencias_diarias ad
            INNER JOIN asistencias a  ON a.id = ad.asistencia_id
            INNER JOIN usuarios_app u ON u.id = a.usuario_app_id
            INNER JOIN sedes s        ON s.id = a.sede_id
            WHERE 1=1
        ";
        $params = [];

        // Supervisor solo ve su sede
        if ($this->rol() === 'supervisor') {
            $sql .= " AND a.sede_id IN (
                SELECT sede_id FROM usuario_web_sede
                WHERE usuario_web_id = :uid AND activo = 1
            )";
            $params[':uid'] = $this->userId();
        }

        if ($req->query('sede_id')) {
            $sql .= " AND a.sede_id = :sid";
            $params[':sid'] = (int) $req->query('sede_id');
        }
        if ($req->query('fecha_inicio')) {
            $sql .= " AND DATE(ad.marcada_en) >= :fi";
            $params[':fi'] = $req->query('fecha_inicio');
        }
        if ($req->query('fecha_fin')) {
            $sql .= " AND DATE(ad.marcada_en) <= :ff";
            $params[':ff'] = $req->query('fecha_fin');
        }
        if ($req->query('estado_marcacion')) {
            $sql .= " AND ad.estado_marcacion = :em";
            $params[':em'] = $req->query('estado_marcacion');
        }
        if ($req->query('estado_revision')) {
            $sql .= " AND ad.estado_revision = :er";
            $params[':er'] = $req->query('estado_revision');
        }

        $sql .= " ORDER BY ad.marcada_en DESC LIMIT 200";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }


    /**
     * PUT /v1/web/asistencias/{id}/review
     * El admin/supervisor revisa una marcación OBSERVADA.
     * Body: { "estado_revision": "APROBADA" | "MANTENER_OBSERVADA", "observacion": "..." }
     */
    public function updateReview(Request $req): void
    {
        $id             = (int) $req->param('id');
        $estadoRevision = (string) $req->input('estado_revision');
        $observacion    = (string) $req->input('observacion', '');

        $validos = ['APROBADA', 'MANTENER_OBSERVADA'];
        if (!in_array($estadoRevision, $validos)) {
            Response::unprocessable('estado_revision inválido. Use: ' . implode(' | ', $validos));
        }

        // Verificar que existe y que el rol tiene acceso
        $stmt = $this->db->prepare("
            SELECT ad.id, a.sede_id
            FROM asistencias_diarias ad
            INNER JOIN asistencias a ON a.id = ad.asistencia_id
            WHERE ad.id = ?
        ");
        $stmt->execute([$id]);
        $marcacion = $stmt->fetch();
        if (!$marcacion) {
            Response::notFound('Marcación no encontrada');
        }

        // Supervisor: solo puede revisar su sede
        if ($this->rol() === 'supervisor') {
            $stmtChk = $this->db->prepare("
                SELECT id FROM usuario_web_sede
                WHERE usuario_web_id = ? AND sede_id = ? AND activo = 1
            ");
            $stmtChk->execute([$this->userId(), $marcacion['sede_id']]);
            if (!$stmtChk->fetch()) {
                Response::error('Sin acceso a esta marcación', 403);
            }
        }

        $this->db->prepare("
            UPDATE asistencias_diarias
            SET estado_revision = ?, motivo_observacion = ?, revisado_por = ?, revisado_en = NOW()
            WHERE id = ?
        ")->execute([$estadoRevision, $observacion ?: null, $this->userId(), $id]);

        Response::success(null, 'Revisión guardada correctamente');
    }

    /**
     * GET /v1/web/asistencias/semana
     * Resumen de asistencias agrupado por día para la semana actual (o semana de ?fecha=YYYY-MM-DD).
     * Filtros: ?sede_id=1  ?fecha=2025-06-10
     */
    public function resumenSemanal(Request $req): void
    {
        $sedeId = $req->query('sede_id');
        // Si vienen una fecha, usar su semana; si no, la semana actual
        $fechaRef = $req->query('fecha', date('Y-m-d'));
        $dt = new \DateTime($fechaRef);

        // Calcular lunes y domingo de la semana
        $dow    = (int) $dt->format('N'); // 1=lunes … 7=domingo
        $lunes  = (clone $dt)->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');
        $domingo = (clone $dt)->modify('+' . (7 - $dow) . ' days')->format('Y-m-d');

        $whereSede = '1=1';
        $params    = [':fi' => $lunes, ':ff' => $domingo];

        // Supervisor: solo sus sedes
        if ($this->rol() === 'supervisor') {
            $whereSede = "a.sede_id IN (
                SELECT sede_id FROM usuario_web_sede
                WHERE usuario_web_id = :uid AND activo = 1
            )";
            $params[':uid'] = $this->userId();
        }

        if ($sedeId) {
            $whereSede .= ' AND a.sede_id = :sid';
            $params[':sid'] = (int) $sedeId;
        }

        // Totales por día de la semana
        $stmt = $this->db->prepare("
            SELECT
                a.fecha,
                DAYNAME(a.fecha)                          AS dia_nombre,
                COUNT(*)                                  AS total,
                SUM(a.estado_diario = 'PRESENTE')         AS presentes,
                SUM(a.estado_diario = 'TARDANZA')         AS tardanzas,
                SUM(a.estado_diario = 'FALTA')            AS faltas,
                SUM(a.estado_diario = 'JUSTIFICADO')      AS justificados,
                SUM(a.estado_diario = 'PENDIENTE')        AS pendientes,
                SUM(ad.estado_marcacion = 'OBSERVADA'
                    AND ad.estado_revision = 'PENDIENTE') AS observadas_pendientes
            FROM asistencias a
            LEFT JOIN asistencias_diarias ad ON ad.asistencia_id = a.id
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

        // ROL SUPERVISOR
        if ($this->rol() === 'supervisor') {
            $whereSede = "sede_id IN (
                SELECT sede_id FROM usuario_web_sede
                WHERE usuario_web_id = :uid AND activo = 1
            )";
            $params[':uid'] = $this->userId();
        }

        // Si mandan sede por parametro
        if ($req->query('sede_id')) {
            $whereSede .= ' AND sede_id = :sid';
            $params[':sid'] = (int) $req->query('sede_id');
        }

        $stmt = $this->db->prepare("
            SELECT
                DAY(fecha) as dia,
                SUM(estado_diario IN ('PRESENTE', 'TARDANZA', 'JUSTIFICADO', 'SALIDA ANTES')) as asistencias,
                SUM(estado_diario = 'FALTA') as faltas
            FROM asistencias
            WHERE {$whereSede} AND MONTH(fecha) = :mes AND YEAR(fecha) = :anio
            GROUP BY fecha
            ORDER BY fecha ASC
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

    /**
     * GET /v1/web/asistencias/exportar
     */
    public function exportar(Request $req): void
    {
        $params = [];
        $where  = [];

        // Scope de supervisor
        if ($this->rol() === 'supervisor') {
            $where[]        = "a.sede_id IN (
                SELECT sede_id FROM usuario_web_sede
                WHERE usuario_web_id = :uid AND activo = 1
            )";
            $params[':uid'] = $this->userId();
        }

        if ($req->query('sede_id')) {
            $where[]          = 'a.sede_id = :sid';
            $params[':sid']   = (int) $req->query('sede_id');
        }
        if ($req->query('fecha_inicio')) {
            $where[]          = 'a.fecha >= :fi';
            $params[':fi']    = $req->query('fecha_inicio');
        }
        if ($req->query('fecha_fin')) {
            $where[]          = 'a.fecha <= :ff';
            $params[':ff']    = $req->query('fecha_fin');
        }
        if ($req->query('estado_diario')) {
            $where[]          = 'a.estado_diario = :ed';
            $params[':ed']    = $req->query('estado_diario');
        }

        $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $this->db->prepare("
            SELECT
                u.codigo_empleado,
                CONCAT(u.apellido_paterno, ' ', u.apellido_materno, ', ', u.nombres) AS trabajador,
                u.dni,
                s.nombre          AS sede,
                hs.nombre_turno   AS turno,
                a.fecha,
                a.hora_entrada,
                a.hora_salida,
                a.minutos_tarde,
                a.estado_diario,
                a.observacion,
                entrada.marcada_en        AS entrada_marcada_en,
                entrada.estado_marcacion  AS entrada_estado_marcacion,
                entrada.distancia_metros  AS entrada_distancia_metros,
                salida.marcada_en         AS salida_marcada_en,
                salida.estado_marcacion   AS salida_estado_marcacion,
                salida.distancia_metros   AS salida_distancia_metros
            FROM asistencias a
            INNER JOIN usuarios_app u  ON u.id = a.usuario_app_id
            INNER JOIN sedes s         ON s.id = a.sede_id
            INNER JOIN horarios_sede hs ON hs.id = a.horario_sede_id
            LEFT JOIN asistencias_diarias entrada
                   ON entrada.asistencia_id = a.id AND entrada.tipo = 'ENTRADA'
            LEFT JOIN asistencias_diarias salida
                   ON salida.asistencia_id = a.id  AND salida.tipo = 'SALIDA'
            {$whereSQL}
            ORDER BY a.fecha DESC, u.apellido_paterno ASC
            LIMIT 5000
        ");
        $stmt->execute($params);
        $registros = $stmt->fetchAll();

        Response::success([
            'total'      => count($registros),
            'registros'  => $registros,
        ]);
    }
}
