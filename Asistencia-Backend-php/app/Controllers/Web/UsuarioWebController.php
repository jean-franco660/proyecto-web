<?php

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

/**
 * UsuarioWebController — Gestión de admins y supervisores.
 * Esquema v2: usuarios + usuario_roles + roles + usuarios_staff + estados_usuario.
 * Ya no existe tabla usuarios_web ni usuario_web_sede.
 * Supervisores se asignan a sedes via usuario_sede (mismo que trabajadores).
 */
class UsuarioWebController extends BaseWebController
{
    private function soloAdministrador(): void
    {
        if (!$this->esAdmin()) {
            Response::error('Solo el administrador puede gestionar usuarios web', 403);
        }
    }

    /** GET /v1/web/usuarios-web — listar admins y supervisores */
    public function index(Request $req): void
    {
        $this->soloAdministrador();
        try {
            $stmt = $this->db->query("
                SELECT u.id, u.email, u.estado_id,
                       eu.nombre AS estado,
                       us.nombre AS nombre,
                       r.nombre AS rol,
                       u.created_at,
                       s.id AS sede_id,
                       s.nombre AS sede
                FROM usuarios u
                INNER JOIN usuario_roles ur   ON ur.usuario_id = u.id
                INNER JOIN roles r            ON r.id = ur.rol_id
                INNER JOIN estados_usuario eu  ON eu.id = u.estado_id
                LEFT JOIN usuarios_staff us   ON us.usuario_id = u.id
                LEFT JOIN usuario_sede use_s  ON use_s.usuario_id = u.id AND use_s.estado = 1 AND (use_s.fecha_fin IS NULL OR use_s.fecha_fin >= CURDATE())
                LEFT JOIN sedes s             ON s.id = use_s.sede_id
                WHERE r.nombre IN ('ADMIN', 'SUPERVISOR')
                ORDER BY r.nombre, us.nombre
            ");
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Normalizar roles
            $rolMap = ['ADMIN' => 'administrador', 'SUPERVISOR' => 'supervisor'];
            foreach ($users as &$u) {
                $u['rol'] = $rolMap[$u['rol']] ?? strtolower($u['rol']);
            }

            Response::success($users);
        } catch (\Exception $e) {
            error_log('[UsuarioWebController::index] Error SQL: ' . $e->getMessage());
            Response::error('Error al obtener el listado de usuarios', 500);
        }
    }

    /** GET /v1/web/usuarios-web/{id} */
    public function show(Request $req): void
    {
        $this->soloAdministrador();
        $id   = (int) $req->param('id');
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.estado_id,
                   eu.nombre AS estado,
                   us.nombre AS nombre,
                   r.nombre AS rol,
                   s.id AS sede_id,
                   s.nombre AS sede
            FROM usuarios u
            INNER JOIN usuario_roles ur   ON ur.usuario_id = u.id
            INNER JOIN roles r            ON r.id = ur.rol_id
            INNER JOIN estados_usuario eu  ON eu.id = u.estado_id
            LEFT JOIN usuarios_staff us   ON us.usuario_id = u.id
            LEFT JOIN usuario_sede use_s  ON use_s.usuario_id = u.id AND use_s.estado = 1 AND (use_s.fecha_fin IS NULL OR use_s.fecha_fin >= CURDATE())
            LEFT JOIN sedes s             ON s.id = use_s.sede_id
            WHERE u.id = ? AND r.nombre IN ('ADMIN', 'SUPERVISOR')
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            Response::notFound('Usuario web no encontrado');
        }

        $rolMap = ['ADMIN' => 'administrador', 'SUPERVISOR' => 'supervisor'];
        $user['rol'] = $rolMap[$user['rol']] ?? strtolower($user['rol']);

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
        if (!$nombre) {
            $errors[] = 'nombre es requerido';
        }
        if (!$email) {
            $errors[] = 'email es requerido';
        }
        if (!$password) {
            $errors[] = 'password es requerido';
        }
        if (!in_array($rol, ['administrador', 'supervisor'])) {
            $errors[] = 'rol inválido';
        }
        if ($errors) {
            Response::unprocessable('Datos incompletos', $errors);
        }

        // Validar complejidad de contraseña (mínimo 8 caracteres, letras y números)
        if (!\App\Core\Validator::isSecurePassword($password)) {
            Response::error('La contraseña debe tener al menos 8 caracteres y contener letras y números', 422);
        }

        // Verificar email único
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            Response::error('El email ya está registrado', 422);
        }

        // Mapear rol a ID del catálogo
        $rolMap = ['administrador' => 1, 'supervisor' => 2]; // ADMIN=1, SUPERVISOR=2
        $rolId = $rolMap[$rol];

        // Mapear estado
        $estadoInput = strtoupper(trim((string) $req->input('estado')));
        if (!$estadoInput) {
            $estadoInput = $rol === 'administrador' ? 'ACTIVO' : 'INACTIVO';
        }
        $estadoMap = ['ACTIVO' => 1, 'INACTIVO' => 2, 'BLOQUEADO' => 3];
        $estadoId = $estadoMap[$estadoInput] ?? null;
        if (!$estadoId) {
            Response::unprocessable('Estado inválido');
        }

        $this->db->beginTransaction();
        try {
            // Crear usuario base
            $stmt = $this->db->prepare("
                INSERT INTO usuarios (email, password, estado_id)
                VALUES (:e, :p, :estado)
            ");
            $stmt->execute([
                ':e'      => $email,
                ':p'      => password_hash($password, PASSWORD_BCRYPT),
                ':estado' => $estadoId,
            ]);
            $nuevoId = (int) $this->db->lastInsertId();

            // Asignar rol
            $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, ?)")
                ->execute([$nuevoId, $rolId]);

            // Crear perfil staff
            $this->db->prepare("INSERT INTO usuarios_staff (usuario_id, nombre) VALUES (?, ?)")
                ->execute([$nuevoId, $nombre]);

            // Asignar Sede si es Supervisor
            if ($rol === 'supervisor') {
                $sedeId = (int) $req->input('sede_id');
                if ($sedeId > 0) {
                    $horarioId = $this->obtenerOCrearHorarioDefecto($sedeId);
                    $this->db->prepare("
                        INSERT INTO usuario_sede (usuario_id, sede_id, horario_id, fecha_inicio, estado)
                        VALUES (?, ?, ?, CURDATE(), 1)
                    ")->execute([$nuevoId, $sedeId, $horarioId]);
                }
            }

            $this->db->commit();
            Response::success(['id' => $nuevoId], 'Usuario web creado correctamente', 201);
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[UsuarioWebController::store] Error: ' . $e->getMessage());
            Response::error('Error al crear el usuario web. Intente nuevamente.', 500);
        }
    }

    /** PUT /v1/web/usuarios-web/{id} — actualizar nombre, email o rol */
    public function update(Request $req): void
    {
        $this->soloAdministrador();
        $id = (int) $req->param('id');

        // Validar si el usuario a modificar es el único administrador activo
        $stmt = $this->db->prepare("
            SELECT u.estado_id, r.nombre AS rol
            FROM usuarios u
            INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
            INNER JOIN roles r ON r.id = ur.rol_id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $currentUser = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($currentUser && $currentUser['rol'] === 'ADMIN' && (int)$currentUser['estado_id'] === 1) {
            $changingRoleToNonAdmin = ($req->input('rol') !== null && $req->input('rol') !== 'administrador');
            $changingStateToInactive = false;
            if ($req->input('estado') !== null) {
                $estadoMap = ['ACTIVO' => 1, 'INACTIVO' => 2, 'BLOQUEADO' => 3];
                $estadoId = $estadoMap[strtoupper(trim($req->input('estado')))] ?? null;
                if ($estadoId && $estadoId !== 1) {
                    $changingStateToInactive = true;
                }
            }

            if ($changingRoleToNonAdmin || $changingStateToInactive) {
                $countStmt = $this->db->query("
                    SELECT COUNT(*) 
                    FROM usuario_roles ur 
                    JOIN roles r ON r.id = ur.rol_id 
                    JOIN usuarios u ON u.id = ur.usuario_id 
                    WHERE r.nombre = 'ADMIN' AND u.estado_id = 1
                ");
                $activeAdminsCount = (int)$countStmt->fetchColumn();

                if ($activeAdminsCount <= 1) {
                    Response::error('No puedes degradar o desactivar al único administrador activo', 422);
                }
            }
        }

        $this->db->beginTransaction();
        try {
            // Actualizar campos de usuarios
            $camposUser = [];
            $paramsUser = [];
            if ($req->input('email') !== null) {
                $camposUser[] = "`email` = ?";
                $paramsUser[] = strtolower(trim($req->input('email')));
            }
            if ($req->input('password') !== null && trim($req->input('password')) !== '') {
                $camposUser[] = "`password` = ?";
                $paramsUser[] = password_hash($req->input('password'), PASSWORD_BCRYPT);
            }
            if ($req->input('estado') !== null) {
                $estadoMap = ['ACTIVO' => 1, 'INACTIVO' => 2, 'BLOQUEADO' => 3];
                $estadoId = $estadoMap[strtoupper(trim($req->input('estado')))] ?? null;
                if (!$estadoId) {
                    Response::unprocessable('Estado inválido');
                }
                $camposUser[] = "`estado_id` = ?";
                $paramsUser[] = $estadoId;
            }
            if ($camposUser) {
                $paramsUser[] = $id;
                $this->db->prepare("UPDATE usuarios SET " . implode(', ', $camposUser) . " WHERE id = ?")
                    ->execute($paramsUser);
            }

            // Actualizar nombre staff
            if ($req->input('nombre') !== null) {
                $this->db->prepare("
                    INSERT INTO usuarios_staff (usuario_id, nombre) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)
                ")->execute([$id, $req->input('nombre')]);
            }

            // Actualizar rol
            if ($req->input('rol') !== null) {
                $rolMap = ['administrador' => 1, 'supervisor' => 2];
                $nuevoRolId = $rolMap[$req->input('rol')] ?? null;
                if (!$nuevoRolId) {
                    Response::unprocessable('Rol inválido');
                }
                $this->db->prepare("DELETE FROM usuario_roles WHERE usuario_id = ? AND rol_id IN (1,2)")
                    ->execute([$id]);
                $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, ?)")
                    ->execute([$id, $nuevoRolId]);
            }

            // Actualizar sede si es/sigue siendo supervisor
            $nuevoRol = $req->input('rol') !== null ? $req->input('rol') : ($currentUser ? strtolower($currentUser['rol']) : '');
            if ($nuevoRol === 'supervisor') {
                $sedeId = (int) $req->input('sede_id');
                if ($sedeId > 0) {
                    // Inactivar asignaciones anteriores
                    $this->db->prepare("
                        UPDATE usuario_sede SET estado = 0, fecha_fin = CURDATE()
                        WHERE usuario_id = ? AND estado = 1
                    ")->execute([$id]);

                    $horarioId = $this->obtenerOCrearHorarioDefecto($sedeId);
                    $this->db->prepare("
                        INSERT INTO usuario_sede (usuario_id, sede_id, horario_id, fecha_inicio, estado)
                        VALUES (?, ?, ?, CURDATE(), 1)
                    ")->execute([$id, $sedeId, $horarioId]);
                }
            } else if ($nuevoRol === 'administrador') {
                // Inactivar asignaciones si ahora es admin
                $this->db->prepare("
                    UPDATE usuario_sede SET estado = 0, fecha_fin = CURDATE()
                    WHERE usuario_id = ? AND estado = 1
                ")->execute([$id]);
            }

            $this->db->commit();
            Response::success(null, 'Usuario web actualizado correctamente');
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[UsuarioWebController::update] Error: ' . $e->getMessage());
            Response::error('Error al actualizar el usuario web. Intente nuevamente.', 500);
        }
    }

    /** PATCH /v1/web/usuarios-web/{id}/estado — activar/desactivar */
    public function cambiarEstado(Request $req): void
    {
        $this->soloAdministrador();
        $id     = (int) $req->param('id');
        $estado = strtoupper(trim((string) $req->input('estado')));

        $estadoMap = ['ACTIVO' => 1, 'INACTIVO' => 2, 'BLOQUEADO' => 3];
        $estadoId = $estadoMap[$estado] ?? null;
        if (!$estadoId) {
            Response::unprocessable('Estado inválido');
        }

        // Evitar desactivar al único administrador activo
        if ($estadoId !== 1) {
            $stmt = $this->db->prepare("
                SELECT u.estado_id, r.nombre AS rol
                FROM usuarios u
                INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
                INNER JOIN roles r ON r.id = ur.rol_id
                WHERE u.id = ?
            ");
            $stmt->execute([$id]);
            $currentUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($currentUser && $currentUser['rol'] === 'ADMIN' && (int)$currentUser['estado_id'] === 1) {
                $countStmt = $this->db->query("
                    SELECT COUNT(*) 
                    FROM usuario_roles ur 
                    JOIN roles r ON r.id = ur.rol_id 
                    JOIN usuarios u ON u.id = ur.usuario_id 
                    WHERE r.nombre = 'ADMIN' AND u.estado_id = 1
                ");
                $activeAdminsCount = (int)$countStmt->fetchColumn();

                if ($activeAdminsCount <= 1) {
                    Response::error('No puedes desactivar al único administrador activo', 422);
                }
            }
        }

        $this->db->prepare("UPDATE usuarios SET estado_id = ? WHERE id = ?")->execute([$estadoId, $id]);
        Response::success(null, "Estado cambiado a {$estado}");
    }

    /** DELETE /v1/web/usuarios-web/{id} — eliminar un administrador o supervisor */
    public function destroy(Request $req): void
    {
        $this->soloAdministrador();
        $id = (int) $req->param('id');

        // Validar si el usuario a eliminar es el único administrador activo
        $stmt = $this->db->prepare("
            SELECT u.estado_id, r.nombre AS rol
            FROM usuarios u
            INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
            INNER JOIN roles r ON r.id = ur.rol_id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $currentUser = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($currentUser && $currentUser['rol'] === 'ADMIN' && (int)$currentUser['estado_id'] === 1) {
            $countStmt = $this->db->query("
                SELECT COUNT(*) 
                FROM usuario_roles ur 
                JOIN roles r ON r.id = ur.rol_id 
                JOIN usuarios u ON u.id = ur.usuario_id 
                WHERE r.nombre = 'ADMIN' AND u.estado_id = 1
            ");
            $activeAdminsCount = (int)$countStmt->fetchColumn();

            if ($activeAdminsCount <= 1) {
                Response::error('No puedes eliminar al único administrador activo', 422);
            }
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
            $this->db->commit();
            Response::success(null, 'Usuario web eliminado correctamente');
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[UsuarioWebController::destroy] Error: ' . $e->getMessage());
            Response::error('Error al eliminar el usuario web. Intente nuevamente.', 500);
        }
    }

    private function obtenerOCrearHorarioDefecto(int $sedeId): int
    {
        $stmt = $this->db->prepare("SELECT id FROM horarios_sede WHERE sede_id = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$sedeId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stmtInsert = $this->db->prepare("
            INSERT INTO horarios_sede (sede_id, nombre, hora_entrada, hora_salida, tolerancia_entrada, tolerancia_salida, activo)
            VALUES (?, 'Horario General', '08:00:00', '17:00:00', 15, 15, 1)
        ");
        $stmtInsert->execute([$sedeId]);
        $nuevoHorarioId = (int) $this->db->lastInsertId();

        $stmtDia = $this->db->prepare("INSERT INTO horario_dias (horario_id, dia) VALUES (?, ?)");
        for ($d = 1; $d <= 5; $d++) {
            $stmtDia->execute([$nuevoHorarioId, $d]);
        }

        return $nuevoHorarioId;
    }
}
