<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class UsuarioAppController extends BaseWebController
{
    /** GET /v1/web/usuarios-app — listar trabajadores con su sede y horario */
    public function index(Request $req): void
    {
        $sedeId = $req->query('sede_id');
        $search = $req->query('search');
        $page    = (int) $req->query('page', 1);
        $perPage = (int) $req->query('per_page', 20);
        $offset  = ($page - 1) * $perPage;

        $whereClause = "1=1";
        $params = [];

        if ($this->rol() === 'supervisor') {
            $whereClause .= " AND EXISTS (SELECT 1 FROM usuario_app_sede uas WHERE uas.usuario_app_id = u.id AND uas.sede_id IN (SELECT sede_id FROM usuario_web_sede WHERE usuario_web_id = :uid AND activo = 1) AND uas.estado = 'ACTIVO')";
            $params[':uid'] = $this->userId();
        }

        if ($sedeId) {
            $whereClause .= " AND EXISTS (SELECT 1 FROM usuario_app_sede uas WHERE uas.usuario_app_id = u.id AND uas.sede_id = :sid AND uas.estado = 'ACTIVO')";
            $params[':sid'] = (int) $sedeId;
        }
        if ($search) {
            $whereClause .= " AND (u.nombres LIKE :q OR u.apellido_paterno LIKE :q OR u.codigo_empleado LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        // Count totals
        $stmtCount = $this->db->prepare("SELECT COUNT(*) FROM usuarios_app u WHERE {$whereClause}");
        foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
        $stmtCount->execute();
        $total = (int) $stmtCount->fetchColumn();

        // Query data
        $sql = "
            SELECT u.id, u.codigo_empleado AS codigo,
                   CONCAT(u.nombres, ' ', u.apellido_paterno, ' ', IFNULL(u.apellido_materno, '')) AS nombre_completo,
                   u.nombres, u.apellido_paterno, u.apellido_materno,
                   u.dni, u.estado, u.cargo,
                   IF(u.estado='ACTIVO', 1, 0) AS acceso_habilitado
            FROM usuarios_app u
            WHERE {$whereClause}
            ORDER BY u.apellido_paterno, u.nombres
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Query institutions/assignments
        if ($users) {
            $userIds = array_column($users, 'id');
            $inClause = implode(',', $userIds);
            $stmtSedes = $this->db->query("
                SELECT uas.usuario_app_id, s.id, s.nombre, s.codigo_sede AS codigo_modular_ie, uas.cargo, uas.estado, uas.fecha_inicio, uas.fecha_fin, uas.id AS pivot_id
                FROM usuario_app_sede uas
                JOIN sedes s ON s.id = uas.sede_id
                WHERE uas.usuario_app_id IN ($inClause)
            ");
            $sedesList = $stmtSedes->fetchAll(\PDO::FETCH_ASSOC);
            $sedesByUser = [];
            foreach ($sedesList as $s) {
                $sedesByUser[$s['usuario_app_id']][] = [
                    'id' => $s['id'],
                    'nombre' => $s['nombre'],
                    'codigo_modular_ie' => $s['codigo_modular_ie'],
                    'pivot' => [
                        'id' => $s['pivot_id'],
                        'cargo' => $s['cargo'],
                        'estado' => $s['estado'],
                        'fecha_inicio' => $s['fecha_inicio'],
                        'fecha_fin' => $s['fecha_fin']
                    ]
                ];
            }
            foreach ($users as &$u) {
                $u['instituciones'] = $sedesByUser[$u['id']] ?? [];
            }
        }

        Response::success([
            'current_page' => $page,
            'data'         => $users,
            'total'        => $total,
            'last_page'    => ceil($total / $perPage),
            'per_page'     => $perPage
        ]);
    }

    /** GET /v1/web/usuarios-app/{id} */
    public function show(Request $req): void
    {
        $id   = (int) $req->param('id');
        $stmt = $this->db->prepare("
            SELECT u.id, u.codigo_empleado AS codigo, u.codigo_empleado AS codigo_modular,
                   CONCAT(u.nombres, ' ', u.apellido_paterno, ' ', IFNULL(u.apellido_materno, '')) AS nombre_completo,
                   u.nombres, u.apellido_paterno, u.apellido_materno,
                   u.dni, u.estado,
                   IF(u.estado='ACTIVO', 1, 0) AS acceso_habilitado
            FROM usuarios_app u
            WHERE u.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $u = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$u) Response::notFound('Trabajador no encontrado');

        if ($this->rol() === 'supervisor') {
            $stmtChk = $this->db->prepare("SELECT 1 FROM usuario_app_sede uas WHERE uas.usuario_app_id = ? AND uas.sede_id IN (SELECT sede_id FROM usuario_web_sede WHERE usuario_web_id = ? AND activo = 1) AND uas.estado = 'ACTIVO'");
            $stmtChk->execute([$id, $this->userId()]);
            if (!$stmtChk->fetch()) {
                Response::error('Sin acceso a este trabajador', 403);
            }
        }
        
        $stmtSedes = $this->db->prepare("
            SELECT s.id, s.nombre, s.codigo_sede AS codigo_modular_ie, uas.cargo, uas.estado, uas.fecha_inicio, uas.fecha_fin, uas.id AS pivot_id
            FROM usuario_app_sede uas
            JOIN sedes s ON s.id = uas.sede_id
            WHERE uas.usuario_app_id = :id
        ");
        $stmtSedes->execute([':id' => $id]);
        $sedesList = $stmtSedes->fetchAll(\PDO::FETCH_ASSOC);
        $sedes = [];
        foreach ($sedesList as $s) {
            $sedes[] = [
                'id' => $s['id'],
                'nombre' => $s['nombre'],
                'codigo_modular_ie' => $s['codigo_modular_ie'],
                'pivot' => [
                    'id' => $s['pivot_id'],
                    'cargo' => $s['cargo'],
                    'estado' => $s['estado'],
                    'fecha_inicio' => $s['fecha_inicio'],
                    'fecha_fin' => $s['fecha_fin']
                ]
            ];
        }
        $u['instituciones'] = $sedes;
        Response::success($u);
    }

    /** POST /v1/web/usuarios-app — crear trabajador */
    public function store(Request $req): void
    {
        $nombres    = (string) $req->input('nombres');
        $apPaterno  = (string) $req->input('apellido_paterno');
        $apMaterno  = (string) $req->input('apellido_materno', '');
        $codigo     = (string) $req->input('codigo_modular');
        $email      = strtolower(trim((string) $req->input('email', $codigo.'@asistencia.com')));
        $dni        = (string) $req->input('dni', '');
        $password   = (string) $req->input('password');

        $errors = [];
        if (!$nombres)   $errors[] = 'nombres es requerido';
        if (!$apPaterno) $errors[] = 'apellido_paterno es requerido';
        if (!$codigo)    $errors[] = 'codigo_modular es requerido';
        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        // Verificar unicidad
        $stmt = $this->db->prepare("SELECT id FROM usuarios_app WHERE email = ? OR codigo_empleado = ?");
        $stmt->execute([$email, $codigo]);
        if ($stmt->fetch(\PDO::FETCH_ASSOC)) Response::error('El email o código modular ya existe', 422);

        $asignaciones = $req->input('asignaciones', []);
        if ($this->rol() === 'supervisor' && is_array($asignaciones)) {
            foreach ($asignaciones as $asig) {
                if (empty($asig['institucion_id']) || !is_numeric($asig['institucion_id'])) {
                    Response::unprocessable('institucion_id inválido en asignaciones');
                }
                $stmtChk = $this->db->prepare("SELECT 1 FROM usuario_web_sede WHERE usuario_web_id = ? AND sede_id = ? AND activo = 1");
                $stmtChk->execute([$this->userId(), (int)$asig['institucion_id']]);
                if (!$stmtChk->fetch()) {
                    Response::error('No tienes permiso para asignar trabajadores a esta sede', 403);
                }
            }
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO usuarios_app
                    (nombres, apellido_paterno, apellido_materno, codigo_empleado, email, dni, password, estado)
                VALUES (:n, :ap, :am, :cod, :email, :dni, :pwd, 'ACTIVO')
            ");
            $pw = $password ? password_hash($password, PASSWORD_BCRYPT) : password_hash($dni ?: $codigo, PASSWORD_BCRYPT);
            $stmt->execute([
                ':n'    => $nombres,
                ':ap'   => $apPaterno,
                ':am'   => $apMaterno,
                ':cod'  => $codigo,
                ':email'=> $email,
                ':dni'  => $dni,
                ':pwd'  => $pw,
            ]);
            
            $userId = (int) $this->db->lastInsertId();

            $asignaciones = $req->input('asignaciones', []);
            if (is_array($asignaciones)) {
                $stmtAsig = $this->db->prepare("
                    INSERT INTO usuario_app_sede (usuario_app_id, sede_id, cargo, estado, fecha_inicio, fecha_fin)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                foreach ($asignaciones as $asig) {
                    $stmtAsig->execute([
                        $userId,
                        $asig['institucion_id'],
                        $asig['cargo'] ?? 'DOCENTE',
                        $asig['estado'] ?? 'ACTIVO',
                        $asig['fecha_inicio'] ?? date('Y-m-d'),
                        $asig['fecha_fin'] ?? null
                    ]);
                }
            }

            $this->db->commit();
            Response::success(['id' => $userId], 'Trabajador creado correctamente', 201);
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[UsuarioAppController::store] Error: ' . $e->getMessage());
            Response::error('Error al crear el trabajador. Intente nuevamente.', 500);
        }
    }

    /** PUT /v1/web/usuarios-app/{id} — actualizar datos */
    public function update(Request $req): void
    {
        $id = (int) $req->param('id');
        $stmt = $this->db->prepare("SELECT id FROM usuarios_app WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch(\PDO::FETCH_ASSOC)) Response::notFound('Trabajador no encontrado');

        if ($this->rol() === 'supervisor') {
            $stmtChk = $this->db->prepare("SELECT 1 FROM usuario_app_sede uas WHERE uas.usuario_app_id = ? AND uas.sede_id IN (SELECT sede_id FROM usuario_web_sede WHERE usuario_web_id = ? AND activo = 1) AND uas.estado = 'ACTIVO'");
            $stmtChk->execute([$id, $this->userId()]);
            if (!$stmtChk->fetch()) {
                Response::error('Sin acceso a este trabajador', 403);
            }
        }

        $campos = [];
        $params = [];
        
        $nombres    = $req->input('nombres');
        $apPaterno  = $req->input('apellido_paterno');
        $apMaterno  = $req->input('apellido_materno');
        $codigo     = $req->input('codigo_modular');
        $email      = $req->input('email');
        $dni        = $req->input('dni');
        $password   = $req->input('password');
        
        if ($nombres !== null) { $campos[] = "`nombres` = ?"; $params[] = $nombres; }
        if ($apPaterno !== null) { $campos[] = "`apellido_paterno` = ?"; $params[] = $apPaterno; }
        if ($apMaterno !== null) { $campos[] = "`apellido_materno` = ?"; $params[] = $apMaterno; }
        if ($codigo !== null) { $campos[] = "`codigo_empleado` = ?"; $params[] = $codigo; }
        if ($email !== null) { $campos[] = "`email` = ?"; $params[] = $email; }
        if ($dni !== null) { $campos[] = "`dni` = ?"; $params[] = $dni; }
        if ($password !== null && trim($password) !== '') {
            $campos[] = "`password` = ?";
            $params[] = password_hash($password, PASSWORD_BCRYPT);
        }

        $this->db->beginTransaction();
        try {
            if (!empty($campos)) {
                $params[] = $id;
                $this->db->prepare("UPDATE usuarios_app SET " . implode(', ', $campos) . " WHERE id = ?")->execute($params);
            }

            $asignaciones = $req->input('asignaciones');
            if (is_array($asignaciones)) {
                if ($this->rol() === 'supervisor') {
                    foreach ($asignaciones as $asig) {
                        if (empty($asig['institucion_id']) || !is_numeric($asig['institucion_id'])) {
                            Response::unprocessable('institucion_id inválido en asignaciones');
                        }
                        $stmtChk = $this->db->prepare("SELECT 1 FROM usuario_web_sede WHERE usuario_web_id = ? AND sede_id = ? AND activo = 1");
                        $stmtChk->execute([$this->userId(), (int)$asig['institucion_id']]);
                        if (!$stmtChk->fetch()) {
                            Response::error('No tienes permiso para asignar trabajadores a esta sede', 403);
                        }
                    }
                }
                $stmtAsig = $this->db->prepare("INSERT INTO usuario_app_sede (usuario_app_id, sede_id, cargo, estado, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($asignaciones as $asig) {
                    $stmtAsig->execute([
                        $id,
                        (int)$asig['institucion_id'],
                        $asig['cargo'] ?? 'DOCENTE',
                        $asig['estado'] ?? 'ACTIVO',
                        $asig['fecha_inicio'] ?? date('Y-m-d'),
                        $asig['fecha_fin'] ?? null
                    ]);
                }
            }

            $this->db->commit();
            Response::success(null, 'Trabajador actualizado correctamente');
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[UsuarioAppController::update] Error: ' . $e->getMessage());
            Response::error('Error al actualizar el trabajador. Intente nuevamente.', 500);
        }
    }

    /** PATCH /v1/web/usuarios-app/{id}/estado — activar/desactivar */
    public function cambiarEstado(Request $req): void
    {
        $id     = (int) $req->param('id');
        $estado = (string) $req->input('estado'); // ACTIVO | INACTIVO

        if (!in_array($estado, ['ACTIVO', 'INACTIVO']))
            Response::unprocessable('Estado inválido. Use ACTIVO o INACTIVO');

        $stmt = $this->db->prepare("UPDATE usuarios_app SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
        Response::success(null, "Estado cambiado a {$estado}");
    }

    /** PATCH /v1/web/usuarios-app/{id}/horario — asignar sede y horario */
    public function asignarHorario(Request $req): void
    {
        $id        = (int) $req->param('id');
        $sedeId    = (int) $req->input('sede_id');
        $horarioId = $req->input('horario_sede_id') ? (int) $req->input('horario_sede_id') : null;
        $cargo     = (string) $req->input('cargo', '');

        if (!$sedeId) Response::unprocessable('sede_id es requerido');

        $this->db->beginTransaction();
        try {
            // Desactivar asignación anterior
            $this->db->prepare("UPDATE usuario_app_sede SET estado = 'INACTIVO' WHERE usuario_app_id = ? AND sede_id = ?")->execute([$id, $sedeId]);

            // Crear nueva asignación
            $this->db->prepare("
                INSERT INTO usuario_app_sede (usuario_app_id, sede_id, horario_sede_id, cargo, estado)
                VALUES (?, ?, ?, ?, 'ACTIVO')
            ")->execute([$id, $sedeId, $horarioId, $cargo]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[UsuarioAppController::asignarHorario] Error: ' . $e->getMessage());
            Response::error('Error al asignar sede y horario. Intente nuevamente.', 500);
        }

        Response::success(null, 'Sede y horario asignados correctamente');
    }

    /** POST /v1/web/usuario-app-institucion/{id}/inactivar */
    public function inactivarAsignacion(Request $req): void
    {
        $pivotId = (int) $req->param('id');
        $this->db->prepare("UPDATE usuario_app_sede SET estado = 'INACTIVO', fecha_fin = CURRENT_DATE WHERE id = ?")->execute([$pivotId]);
        Response::success(null, 'Asignación inactivada correctamente');
    }

    /** GET /v1/web/usuarios-app/import/stats */
    public function importStats(Request $req): void
    {
        // Placeholder temporal para no romper el frontend
        Response::success([
            'ultima_importacion' => null,
            'total_procesados'   => 0,
            'errores'            => 0,
            'exitosos'           => 0
        ]);
    }

    /** DELETE /v1/web/usuarios-app/{id} */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');
        $this->db->prepare("UPDATE usuarios_app SET estado = 'INACTIVO' WHERE id = ?")->execute([$id]);
        Response::success(null, 'Trabajador desactivado correctamente');
    }
}