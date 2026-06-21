<?php

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Models\Departamento;

class DepartamentoWebController extends BaseWebController
{
    private Departamento $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Departamento();
    }

    /** GET /v1/web/departamentos */
    public function index(Request $req): void
    {
        Response::success($this->model->all('nombre ASC'));
    }

    /** POST /v1/web/departamentos */
    public function store(Request $req): void
    {
        $nombre = trim((string) $req->input('nombre'));
        $descripcion = trim((string) $req->input('descripcion', ''));

        if (!$nombre) {
            Response::unprocessable('El nombre del departamento es requerido');
        }

        // Check unique
        if ($this->model->findOneBy('nombre', $nombre)) {
            Response::error('Ya existe un departamento con este nombre', 422);
        }

        $id = $this->model->create([
            'nombre' => $nombre,
            'descripcion' => $descripcion ?: null,
            'activo' => 1
        ]);

        Response::success($this->model->find($id), 'Departamento creado correctamente', 201);
    }

    /** PUT /v1/web/departamentos/{id} */
    public function update(Request $req): void
    {
        $id = (int) $req->param('id');
        $dep = $this->model->find($id);
        if (!$dep) {
            Response::notFound('Departamento no encontrado');
        }

        $nombre = trim((string) $req->input('nombre'));
        $descripcion = trim((string) $req->input('descripcion', ''));
        $activo = $req->input('activo');

        if (!$nombre) {
            Response::unprocessable('El nombre del departamento es requerido');
        }

        // Check unique excluding current ID
        $stmt = $this->model->db()->prepare("SELECT id FROM departamentos WHERE nombre = ? AND id != ?");
        $stmt->execute([$nombre, $id]);
        if ($stmt->fetch()) {
            Response::error('Ya existe otro departamento con este nombre', 422);
        }

        $data = [
            'nombre' => $nombre,
            'descripcion' => $descripcion ?: null
        ];
        if ($activo !== null) {
            $data['activo'] = (int) $activo;
        }

        $this->model->update($id, $data);
        Response::success($this->model->find($id), 'Departamento actualizado correctamente');
    }

    /** DELETE /v1/web/departamentos/{id} */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');
        $dep = $this->model->find($id);
        if (!$dep) {
            Response::notFound('Departamento no encontrado');
        }

        // Soft delete/desactivar
        $this->model->update($id, ['activo' => 0]);
        Response::success(null, 'Departamento desactivado correctamente');
    }
}
