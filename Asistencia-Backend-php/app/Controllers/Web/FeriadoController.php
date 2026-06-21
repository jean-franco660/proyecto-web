<?php

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Models\Feriado;

class FeriadoController extends BaseWebController
{
    private Feriado $model;

    public function __construct()
    {
        $this->model = new Feriado();
    }

    /** GET /v1/web/feriados */
    public function index(Request $req): void
    {
        Response::success($this->model->getAll());
    }

    /** POST /v1/web/feriados */
    public function store(Request $req): void
    {
        $data = $req->only(['fecha', 'nombre', 'tipo', 'sede_id', 'activo']);

        $errors = [];
        if (empty($data['fecha'])) {
            $errors[] = 'fecha es requerida';
        }
        if (empty($data['nombre'])) {
            $errors[] = 'nombre es requerido';
        }
        if ($errors) {
            Response::unprocessable('Datos incompletos', $errors);
        }

        if (!isset($data['tipo'])) {
            $data['tipo'] = 'NACIONAL';
        }
        if (!isset($data['activo'])) {
            $data['activo'] = 1;
        }
        if (empty($data['sede_id'])) {
            $data['sede_id'] = null;
        }

        $id = $this->model->create($data);
        Response::success($this->model->find($id), 'Feriado creado correctamente', 201);
    }

    /** PUT /v1/web/feriados/{id} */
    public function update(Request $req): void
    {
        $id = (int) $req->param('id');
        $feriado = $this->model->find($id);
        if (!$feriado) {
            Response::notFound('Feriado no encontrado');
        }

        $data = $req->only(['fecha', 'nombre', 'tipo', 'sede_id', 'activo']);
        if (empty($data['sede_id'])) {
            $data['sede_id'] = null;
        }

        $this->model->update($id, $data);
        Response::success($this->model->find($id), 'Feriado actualizado correctamente');
    }

    /** DELETE /v1/web/feriados/{id} */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');
        $feriado = $this->model->find($id);
        if (!$feriado) {
            Response::notFound('Feriado no encontrado');
        }

        $this->model->delete($id);
        Response::success(null, 'Feriado eliminado correctamente');
    }
}
