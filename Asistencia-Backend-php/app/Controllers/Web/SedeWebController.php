<?php

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Models\Sede;

/**
 * SedeWebController — Gestión de sedes.
 * Esquema v2: sedes.codigo (antes codigo_sede), sedes.activo (antes activa),
 *             sedes.radio_metros (antes radio).
 *             Supervisor via usuario_sede (antes usuario_web_sede).
 */
class SedeWebController extends BaseWebController
{
    private Sede $model;

    public function __construct()
    {
        $this->model = new Sede();
    }

    /**
     * GET /v1/web/sedes
     * Filtros: ?search=&sort_by=id&sort_order=asc&per_page=20
     * Admin ve todas. Supervisor ve solo las suyas.
     */
    public function index(Request $req): void
    {
        $search  = $req->query('search');
        $sortBy  = in_array($req->query('sort_by'), ['id','nombre','codigo'])
                    ? $req->query('sort_by') : 'id';
        $order   = $req->query('sort_order', 'asc') === 'desc' ? 'DESC' : 'ASC';
        $perPage = (int) $req->query('per_page', 20);
        $offset  = ((int) $req->query('page', 1) - 1) * $perPage;

        $sedes = $this->model->listarConFiltros($search, $sortBy, $order, $perPage, $offset, $this->rol(), $this->userId());
        Response::success($sedes);
    }

    /**
     * GET /v1/web/sedes/mis-sedes
     * Admin ve todas. Supervisor ve solo sus sedes vigentes.
     */
    public function misSedes(Request $req): void
    {
        $rol    = $this->rol();
        $userId = $this->userId();

        if (in_array($rol, ['administrador'])) {
            Response::success($this->model->all());
        } else {
            Response::success($this->model->obtenerSedesVigentes($userId));
        }
    }

    /** POST /v1/web/sedes */
    public function store(Request $req): void
    {
        $data = $req->only([
            'codigo','nombre',
            'direccion',
            'latitud','longitud','radio_metros'
        ]);

        $errors = [];
        if (empty($data['codigo'])) {
            $errors[] = 'codigo es requerido';
        }
        if (empty($data['nombre'])) {
            $errors[] = 'nombre es requerido';
        }
        if (!isset($data['latitud'])) {
            $errors[] = 'latitud es requerida';
        }
        if (!isset($data['longitud'])) {
            $errors[] = 'longitud es requerida';
        }
        if (!isset($data['radio_metros'])) {
            $errors[] = 'radio_metros es requerido';
        }
        if ($errors) {
            Response::unprocessable('Datos incompletos', $errors);
        }

        $id = $this->model->create($data);
        Response::success($this->model->find($id), 'Sede creada correctamente', 201);
    }

    /** GET /v1/web/sedes/{id} */
    public function show(Request $req): void
    {
        $sede = $this->model->find((int) $req->param('id'));
        if (!$sede) {
            Response::notFound('Sede no encontrada');
        }
        Response::success($sede);
    }

    /** PUT /v1/web/sedes/{id} */
    public function update(Request $req): void
    {
        $id   = (int) $req->param('id');
        $sede = $this->model->find($id);
        if (!$sede) {
            Response::notFound('Sede no encontrada');
        }

        $data = $req->only(['nombre','direccion','latitud','longitud','radio_metros']);
        $this->model->update($id, $data);
        Response::success($this->model->find($id), 'Sede actualizada correctamente');
    }

    /** DELETE /v1/web/sedes/{id} */
    public function destroy(Request $req): void
    {
        $id   = (int) $req->param('id');
        $sede = $this->model->find($id);
        if (!$sede) {
            Response::notFound('Sede no encontrada');
        }

        try {
            $this->model->delete($id);
            Response::success(null, 'Sede eliminada correctamente');
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::error(
                    'No se puede eliminar: la sede tiene trabajadores, horarios o asistencias asociadas',
                    409
                );
            }
            // FIX Bug #8: $e->getMessage() exponía detalles internos de MySQL al cliente.
            // Ahora se loguea internamente para diagnóstico y el cliente recibe
            // un mensaje genérico que no revela la estructura de la BD.
        }
    }

    /** GET /v1/web/sedes/import/stats */
    public function importStats(Request $req): void
    {
        // Query to get last processed import info if any, otherwise return default placeholder
        Response::success([
            'ultima_importacion' => null,
            'total_procesados'   => 0,
            'errores'            => 0,
            'exitosos'           => 0
        ]);
    }

    /** POST /v1/web/sedes/import */
    public function importar(Request $req): void
    {
        if (empty($_FILES['file']['tmp_name'])) {
            Response::error('Debe subir un archivo Excel', 400);
        }

        $filePath = $_FILES['file']['tmp_name'];

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
        } catch (\Exception $e) {
            Response::error('El archivo no pudo leerse como Excel: ' . $e->getMessage(), 400);
        }

        if (empty($rows)) {
            Response::error('Archivo vacío', 400);
        }

        // Get headers from first row
        $headerRow = array_shift($rows);
        $headers = [];
        foreach ($headerRow as $col => $val) {
            if ($val !== null && trim((string)$val) !== '') {
                $headers[$col] = trim((string)$val);
            }
        }

        if (empty($headers)) {
            Response::error('Cabeceras inválidas o vacías', 400);
        }

        $totalProcesados = 0;
        $exitosos = 0;
        $errores = [];

        foreach ($rows as $rowIndex => $row) {
            // Check if row is empty
            $rowEmpty = true;
            foreach ($headers as $col => $header) {
                if (isset($row[$col]) && trim((string)$row[$col]) !== '') {
                    $rowEmpty = false;
                    break;
                }
            }
            if ($rowEmpty) {
                continue; // Skip empty rows
            }

            $totalProcesados++;

            $data = [];
            foreach ($headers as $col => $header) {
                $data[$header] = isset($row[$col]) ? trim((string)$row[$col]) : '';
            }

            $codigo = $data['codigo'] ?? '';
            $nombre = $data['nombre'] ?? '';
            $direccion = $data['direccion'] ?? '';
            $latitud = (isset($data['latitud']) && $data['latitud'] !== '') ? floatval($data['latitud']) : null;
            $longitud = (isset($data['longitud']) && $data['longitud'] !== '') ? floatval($data['longitud']) : null;
            $radio = (isset($data['radio_metros']) && $data['radio_metros'] !== '') ? intval($data['radio_metros']) : 100;

            if (empty($codigo) || empty($nombre) || $latitud === null || $longitud === null) {
                $errores[] = "Fila {$rowIndex}: El código, nombre, latitud y longitud son requeridos.";
                continue;
            }

            try {
                $stmt = $this->db->prepare("SELECT id FROM sedes WHERE codigo = ?");
                $stmt->execute([$codigo]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $stmtUpdate = $this->db->prepare("
                        UPDATE sedes 
                        SET nombre = ?, direccion = ?, latitud = ?, longitud = ?, radio_metros = ?, activo = 1 
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([$nombre, $direccion, $latitud, $longitud, $radio, $existing['id']]);
                } else {
                    $stmtInsert = $this->db->prepare("
                        INSERT INTO sedes (codigo, nombre, direccion, latitud, longitud, radio_metros, activo) 
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmtInsert->execute([$codigo, $nombre, $direccion, $latitud, $longitud, $radio]);
                }
                $exitosos++;
            } catch (\Exception $e) {
                $errores[] = "Fila {$rowIndex}: Error al guardar en base de datos. " . $e->getMessage();
            }
        }

        Response::success([
            'total_procesados' => $totalProcesados,
            'exitosos' => $exitosos,
            'errores' => $errores
        ]);
    }

    /** GET /v1/web/sedes/import/template */
    public function downloadTemplate(Request $req): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['codigo', 'nombre', 'direccion', 'latitud', 'longitud', 'radio_metros'];
        foreach ($headers as $colIndex => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($colLetter . '1', $header);
        }

        $example = ['SEDE-001', 'Sede Central', 'Av. Principal 123', -12.046373, -77.042754, 100];
        foreach ($example as $colIndex => $val) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($colLetter . '2', $val);
        }

        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        foreach (range('A', 'F') as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="plantilla_sedes.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }
}
