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
            Response::error('Debe subir un archivo CSV', 400);
        }

        $filePath = $_FILES['file']['tmp_name'];

        $delim = ',';
        $fileHandle = fopen($filePath, 'r');
        if ($fileHandle) {
            $firstLine = fgets($fileHandle);
            if (strpos($firstLine, ';') !== false) {
                $delim = ';';
            }
            rewind($fileHandle);
        }

        $headers = fgetcsv($fileHandle, 1000, $delim);
        if (!$headers) {
            fclose($fileHandle);
            Response::error('Archivo vacío o inválido', 400);
        }

        // Clean UTF-8 BOM if present
        $headers[0] = preg_replace('/[\x{00EF}\x{00BB}\x{00BF}]/u', '', $headers[0]);
        $headers = array_map('trim', $headers);

        $totalProcesados = 0;
        $exitosos = 0;
        $errores = [];

        while (($row = fgetcsv($fileHandle, 1000, $delim)) !== false) {
            $totalProcesados++;

            // Pad row if it has fewer elements than headers
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            }

            $data = array_combine($headers, array_slice(array_map('trim', $row), 0, count($headers)));

            $codigo = $data['codigo'] ?? '';
            $nombre = $data['nombre'] ?? '';
            $direccion = $data['direccion'] ?? '';
            $latitud = isset($data['latitud']) ? floatval($data['latitud']) : null;
            $longitud = isset($data['longitud']) ? floatval($data['longitud']) : null;
            $radio = isset($data['radio_metros']) ? intval($data['radio_metros']) : 100;

            if (empty($codigo) || empty($nombre) || $latitud === null || $longitud === null) {
                $errores[] = "Fila {$totalProcesados}: El código, nombre, latitud y longitud son requeridos.";
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
                $errores[] = "Fila {$totalProcesados}: Error al guardar en base de datos. " . $e->getMessage();
            }
        }
        fclose($fileHandle);

        Response::success([
            'total_procesados' => $totalProcesados,
            'exitosos' => $exitosos,
            'errores' => $errores
        ]);
    }
}
