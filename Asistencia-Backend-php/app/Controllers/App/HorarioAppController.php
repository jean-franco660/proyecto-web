<?php

namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Models\HorarioSede;
use App\Models\UsuarioApp;

/**
 * HorarioAppController - Consulta de horarios de la sede desde la app
 */
class HorarioAppController extends BaseAppController
{
    private HorarioSede $model;
    private UsuarioApp $usuarioModel; // ✅ instanciado una sola vez

    public function __construct()
    {
        parent::__construct();
        $this->model        = new HorarioSede();
        $this->usuarioModel = new UsuarioApp(); // ✅ no más "new" dentro de métodos
    }

    /**
     * GET /v1/app/horarios
     */
    public function obtenerHorarios(Request $request): void
    {
        $data = $this->obtenerHorariosPorUsuario(); // ✅ usa el método privado
        Response::success($data, 'Horarios obtenidos.');
    }

    private function obtenerHorariosPorUsuario(): array
    {
        $userId  = $this->userId();
        $usuario = $this->usuarioModel->findConAsignacion($userId);

        if (!$usuario || empty($usuario['sede_id'])) {
            Response::error('El usuario no tiene sede asignada.', 422);
        }

        return $this->model->porSede((int)$usuario['sede_id']);
    }
}