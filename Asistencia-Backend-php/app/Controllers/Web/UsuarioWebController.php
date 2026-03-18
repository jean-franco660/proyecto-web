<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class UsuarioWebController extends BaseWebController
{

    private function soloAdministrador(): void
    {
        if (!$this->esAdmin())
            Response::error('Solo el administrador puede gestionar usuarios web', 403);
    }

    /** GET /v1/web/usuarios-web — listar admins y supervisores */
    public function index(Request $req): void
    {
        $this->soloAdministrador();
        $stmt = $this->db->query("
            SELECT u.id, u.nombre, u.email, u.rol, u.estado, u.created_at,
                   (SELECT s.nombre
                    FROM usuario_web_sede us
                    JOIN sedes s ON us.sede_id = s.id
                    WHERE us.usuario_web_id = u.id AND us.activo = 1
                    ORDER BY us.fecha_inicio DESC
                    LIMIT 1) AS sede
            FROM usuarios_web u
            ORDER BY u.rol, u.nombre
        ");
        Response::success($stmt->fetchAll());
    }

    /** GET /v1/web/usuarios-web/{id} */
    public function show(Request $req): void
    {
        $this->soloAdministrador();
        $id   = (int) $req->param('id');
        $stmt = $this->db->prepare("SELECT id, nombre, email, rol, estado FROM usuarios_web WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) Response::notFound('Usuario web no encontrado');
        Response::success($user);
    }

    /** POST /v1/web/usuarios-web — crear admin o supervisor */
    public function store(Request $req): void
    {
        $this->soloAdministrador();

        $nombre   = (string) $req->input('nombre');
        $email    = strtolower(trim((string) $req->input('email')));
        $password = (string) $req->input('password');
        $rol      = (string) $req->input('rol'); // administrador | supervisor

        $errors = [];
        if (!$nombre)   $errors[] = 'nombre es requerido';
        if (!$email)    $errors[] = 'email es requerido';
        if (!$password) $errors[] = 'password es requerido';
        if (!in_array($rol, ['administrador', 'supervisor'])) $errors[] = 'rol inválido';
        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        $stmt = $this->db->prepare("SELECT id FROM usuarios_web WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) Response::error('El email ya está registrado', 422);

        $estado = strtoupper(trim((string) $req->input('estado')));
        if (!$estado) {
            // El administrador inicia ACTIVO; supervisores inician INACTIVO (el admin los activa)
            $estado = $rol === 'administrador' ? 'ACTIVO' : 'INACTIVO';
        }
        if (!in_array($estado, ['ACTIVO', 'INACTIVO'])) {
            Response::unprocessable('Estado inválido');
        }

        $sedeId = $req->input('sede_id');
        if ($rol === 'supervisor') {
            if (!$sedeId || !is_numeric($sedeId)) {
                Response::unprocessable('sede_id es requerido para supervisor');
            }
            $stmtSede = $this->db->prepare('SELECT id FROM sedes WHERE id = ? AND deleted_at IS NULL');
            $stmtSede->execute([(int)$sedeId]);
            if (!$stmtSede->fetch()) {
                Response::unprocessable('Sede no válida');
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO usuarios_web (nombre, email, password, rol, estado)
            VALUES (:n, :e, :p, :r, :estado)
        ");
        $stmt->execute([
            ':n'      => $nombre,
            ':e'      => $email,
            ':p'      => password_hash($password, PASSWORD_BCRYPT),
            ':r'      => $rol,
            ':estado' => $estado,
        ]);

        $nuevoId = (int) $this->db->lastInsertId();
        if ($rol === 'supervisor') {
            $this->db->prepare("INSERT INTO usuario_web_sede (usuario_web_id, sede_id, activo, fecha_inicio) VALUES (?, ?, 1, CURDATE())")->execute([$nuevoId, (int)$sedeId]);
        }

        Response::success(['id' => $nuevoId], 'Usuario web creado correctamente', 201);
    }

    /** PUT /v1/web/usuarios-web/{id} — actualizar nombre, email o rol */
    public function update(Request $req): void
    {
        $this->soloAdministrador();
        $id = (int) $req->param('id');

        $campos = [];
        $params = [];
        foreach (['nombre', 'email', 'rol', 'estado', 'password'] as $campo) {
            if ($req->input($campo) !== null) {
                if ($campo === 'estado') {
                    $estado = strtoupper(trim((string) $req->input('estado')));
                    if (!in_array($estado, ['ACTIVO', 'INACTIVO'])) {
                        Response::unprocessable('Estado inválido');
                    }
                    $campos[] = "`estado` = ?";
                    $params[] = $estado;
                } elseif ($campo === 'password') {
                    if ($req->input('password') !== '') {
                        $campos[] = "`password` = ?";
                        $params[] = password_hash($req->input('password'), PASSWORD_BCRYPT);
                    }
                } else {
                    $campos[] = "`{$campo}` = ?";
                    $params[] = $req->input($campo);
                }
            }
        }
        if (empty($campos)) Response::unprocessable('No hay campos a actualizar');

        $params[] = $id;
        $this->db->prepare("UPDATE usuarios_web SET " . implode(', ', $campos) . " WHERE id = ?")->execute($params);

        $sedeId = $req->input('sede_id');
        if ($sedeId !== null) {
            if (!is_numeric($sedeId)) {
                Response::unprocessable('sede_id inválido');
            }

            $stmtRole = $this->db->prepare('SELECT rol FROM usuarios_web WHERE id = ?');
            $stmtRole->execute([$id]);
            $currentRole = $stmtRole->fetchColumn();
            if ($req->input('rol') !== null) {
                $currentRole = $req->input('rol');
            }
            if ($currentRole !== 'supervisor') {
                Response::unprocessable('Solo supervisor puede tener sede asignada');
            }
            $stmtSede = $this->db->prepare('SELECT id FROM sedes WHERE id = ? AND deleted_at IS NULL');
            $stmtSede->execute([(int)$sedeId]);
            if (!$stmtSede->fetch()) {
                Response::unprocessable('Sede no válida');
            }
            $this->db->prepare('UPDATE usuario_web_sede SET activo = 0 WHERE usuario_web_id = ?')->execute([$id]);
            $this->db->prepare('INSERT INTO usuario_web_sede (usuario_web_id, sede_id, activo, fecha_inicio) VALUES (?, ?, 1, CURDATE())')->execute([$id, (int)$sedeId]);
        }

        Response::success(null, 'Usuario web actualizado correctamente');
    }

    /** PATCH /v1/web/usuarios-web/{id}/estado — activar/desactivar */
    public function cambiarEstado(Request $req): void
    {
        $this->soloAdministrador();
        $id     = (int) $req->param('id');
        $estado = strtoupper(trim((string) $req->input('estado')));

        if (!in_array($estado, ['ACTIVO', 'INACTIVO']))
            Response::unprocessable('Estado inválido');

        $this->db->prepare("UPDATE usuarios_web SET estado = ? WHERE id = ?")->execute([$estado, $id]);
        Response::success(null, "Estado cambiado a {$estado}");
    }
}