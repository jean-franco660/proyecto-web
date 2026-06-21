<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Models\HorarioSede;

/**
 * HorarioWebController — Gestión de horarios de sede.
 * Delegando consultas SQL inline al modelo HorarioSede.
 */
class HorarioWebController extends BaseWebController
{
    private HorarioSede $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new HorarioSede();
    }

    /**
     * GET /v1/web/horarios?sede_id=2
     * Supervisor solo ve horarios de sus sedes.
     */
    public function index(Request $req): void
    {
        $sedeId = (int) $req->query('sede_id', 0);
        $rol    = $_REQUEST['auth_user']['rol'] ?? '';
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);

        Response::success($this->model->listarConFiltros($sedeId, $rol, $userId));
    }

    /**
     * POST /v1/web/horarios
     * Al crear, inserta los días en horario_dias.
     */
    public function store(Request $req): void
    {
        $sedeId   = (int) $req->input('sede_id');
        $nombre   = (string) $req->input('nombre');
        $hEntrada = (string) $req->input('hora_entrada');
        $hSalida  = (string) $req->input('hora_salida');
        $tolEnt   = (int) ($req->input('tolerancia_entrada') ?? 0);
        $tolSal   = (int) ($req->input('tolerancia_salida') ?? 0);
        $dias     = $req->input('dias_semana', []);

        $errors = [];
        if (!$sedeId)    $errors[] = 'sede_id es requerido';
        if (!$nombre)    $errors[] = 'nombre es requerido';
        if (!$hEntrada)  $errors[] = 'hora_entrada es requerido';
        if (!$hSalida)   $errors[] = 'hora_salida es requerido';
        if (empty($dias) || !is_array($dias)) $errors[] = 'dias_semana es requerido';
        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        try {
            $horarioId = $this->model->crearHorario([
                'sede_id' => $sedeId,
                'nombre' => $nombre,
                'hora_entrada' => $hEntrada,
                'hora_salida' => $hSalida,
                'tolerancia_entrada' => $tolEnt,
                'tolerancia_salida' => $tolSal,
            ], $dias);

            $result = $this->model->find($horarioId);
            $result['dias_semana'] = array_map('intval', $dias);

            Response::success($result, 'Horario creado correctamente', 201);
        } catch (\Exception $e) {
            error_log('[HorarioWebController::store] Error: ' . $e->getMessage());
            Response::error('Error al crear el horario. Intente nuevamente.', 500);
        }
    }

    public function update(Request $req): void
    {
        $id = (int) $req->param('id');

        $data = [];
        $nombre   = $req->input('nombre');
        $hEntrada = $req->input('hora_entrada');
        $hSalida  = $req->input('hora_salida');
        $tolEnt   = $req->input('tolerancia_entrada');
        $tolSal   = $req->input('tolerancia_salida');
        $activo   = $req->input('activo');

        if ($nombre !== null)   $data['nombre'] = $nombre;
        if ($hEntrada !== null) $data['hora_entrada'] = $hEntrada;
        if ($hSalida !== null)  $data['hora_salida'] = $hSalida;
        if ($tolEnt !== null)   $data['tolerancia_entrada'] = (int) $tolEnt;
        if ($tolSal !== null)   $data['tolerancia_salida'] = (int) $tolSal;
        if ($activo !== null)   $data['activo'] = (int) $activo;

        $dias = $req->input('dias_semana');

        try {
            $this->model->actualizarHorario($id, $data, is_array($dias) ? $dias : null);
        } catch (\Exception $e) {
            error_log('[HorarioWebController::update] Error: ' . $e->getMessage());
            Response::error('Error al actualizar el horario. Intente nuevamente.', 500);
        }

        $result = $this->model->find($id);
        $result['dias_semana'] = array_map('intval', array_column($this->model->diasPorHorario($id), 'dia'));

        Response::success($result, 'Horario actualizado correctamente');
    }

    /** PUT /v1/web/horarios/{id}/dias — sync días del horario */
    public function syncDias(Request $req): void
    {
        $id   = (int) $req->param('id');
        $dias = $req->input('dias', []);

        if (!is_array($dias)) Response::unprocessable('dias debe ser un array');

        try {
            $this->model->sincronizarDias($id, $dias);
        } catch (\Exception $e) {
            error_log('[HorarioWebController::syncDias] Error: ' . $e->getMessage());
            Response::error('Error al sincronizar días. Intente nuevamente.', 500);
        }

        Response::success(null, 'Días actualizados correctamente');
    }

    /** DELETE /v1/web/horarios/{id} */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');
        try {
            $this->model->eliminarHorario($id);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::error('No se puede eliminar: hay trabajadores asignados a este horario', 409);
            }
            error_log('[HorarioWebController::destroy] Error: ' . $e->getMessage());
            Response::error('Error al eliminar el horario', 500);
        }
        Response::success(null, 'Horario eliminado correctamente');
    }
}