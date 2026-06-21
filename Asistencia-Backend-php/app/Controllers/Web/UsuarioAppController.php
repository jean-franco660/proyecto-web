<?php

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

/**
 * UsuarioAppController — Gestión de trabajadores.
 * Esquema v2: usuarios + usuario_roles (TRABAJADOR) + usuarios_trabajador + usuario_sede.
 * Ya no existen: usuarios_app, usuario_app_sede.
 */
class UsuarioAppController extends BaseWebController
{
    /**
     * Helper: IDs de sedes del supervisor actual.
     * Esquema v2: supervisor también usa usuario_sede.
     */
    private function sedesDelSupervisor(): array
    {
        $stmt = $this->db->prepare("
            SELECT us.sede_id FROM usuario_sede us
            WHERE us.usuario_id = :uid AND us.estado = 1
              AND (us.fecha_fin IS NULL OR us.fecha_fin >= CURDATE())
        ");
        $stmt->execute([':uid' => $this->userId()]);
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'sede_id');
    }

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

        // Filtrar solo trabajadores
        $whereClause .= " AND EXISTS (
            SELECT 1 FROM usuario_roles ur2
            INNER JOIN roles r2 ON r2.id = ur2.rol_id
            WHERE ur2.usuario_id = u.id AND r2.nombre = 'TRABAJADOR'
        )";

        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (empty($misSedes)) {
                Response::success(['current_page' => $page, 'data' => [], 'total' => 0, 'last_page' => 1, 'per_page' => $perPage]);
                return;
            }
            $in = implode(',', array_map('intval', $misSedes));
            $whereClause .= " AND EXISTS (
                SELECT 1 FROM usuario_sede usf
                WHERE usf.usuario_id = u.id AND usf.sede_id IN ({$in})
                  AND usf.estado = 1 AND (usf.fecha_fin IS NULL OR usf.fecha_fin >= CURDATE())
            )";
        }

        if ($sedeId) {
            $whereClause .= " AND EXISTS (
                SELECT 1 FROM usuario_sede usf
                WHERE usf.usuario_id = u.id AND usf.sede_id = :sid
                  AND usf.estado = 1 AND (usf.fecha_fin IS NULL OR usf.fecha_fin >= CURDATE())
            )";
            $params[':sid'] = (int) $sedeId;
        }
        if ($search) {
            $whereClause .= " AND (ut.nombres LIKE :q OR ut.apellidos LIKE :q OR u.codigo_empleado LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        // Count totals
        $stmtCount = $this->db->prepare("
            SELECT COUNT(*)
            FROM usuarios u
            LEFT JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
            INNER JOIN estados_usuario eu ON eu.id = u.estado_id
            WHERE {$whereClause}
        ");
        foreach ($params as $k => $v) {
            $stmtCount->bindValue($k, $v);
        }
        $stmtCount->execute();
        $total = (int) $stmtCount->fetchColumn();

        // Query data
        $sql = "
            SELECT u.id, u.codigo_empleado AS codigo,
                   CONCAT(IFNULL(ut.nombres,''), ' ', IFNULL(ut.apellidos,'')) AS nombre_completo,
                   ut.nombres, ut.apellidos,
                   ut.dni, eu.nombre AS estado,
                   u.estado_id,
                   d.id AS departamento_id, d.nombre AS departamento_nombre
            FROM usuarios u
            LEFT JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
            INNER JOIN estados_usuario eu ON eu.id = u.estado_id
            LEFT JOIN departamentos d ON d.id = ut.departamento_id
            WHERE {$whereClause}
            ORDER BY ut.apellidos, ut.nombres
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Query sede assignments
        if ($users) {
            $userIds = array_column($users, 'id');
            $inClause = implode(',', $userIds);
            $stmtSedes = $this->db->query("
                SELECT us.usuario_id, s.id, s.nombre, s.codigo,
                       us.estado, us.fecha_inicio, us.fecha_fin, us.id AS pivot_id,
                       hs.nombre AS horario_nombre
                FROM usuario_sede us
                JOIN sedes s ON s.id = us.sede_id
                LEFT JOIN horarios_sede hs ON hs.id = us.horario_id
                WHERE us.usuario_id IN ($inClause)
            ");
            $sedesList = $stmtSedes->fetchAll(\PDO::FETCH_ASSOC);
            $sedesByUser = [];
            foreach ($sedesList as $s) {
                $sedesByUser[$s['usuario_id']][] = [
                    'id' => $s['id'],
                    'nombre' => $s['nombre'],
                    'codigo' => $s['codigo'],
                    'pivot' => [
                        'id' => $s['pivot_id'],
                        'estado' => $s['estado'],
                        'fecha_inicio' => $s['fecha_inicio'],
                        'fecha_fin' => $s['fecha_fin'],
                        'horario_nombre' => $s['horario_nombre']
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
            SELECT u.id, u.codigo_empleado AS codigo,
                   CONCAT(IFNULL(ut.nombres,''), ' ', IFNULL(ut.apellidos,'')) AS nombre_completo,
                   ut.nombres, ut.apellidos,
                   ut.dni, ut.telefono, eu.nombre AS estado,
                   u.estado_id,
                   d.id AS departamento_id, d.nombre AS departamento_nombre
            FROM usuarios u
            LEFT JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
            INNER JOIN estados_usuario eu ON eu.id = u.estado_id
            LEFT JOIN departamentos d ON d.id = ut.departamento_id
            INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
            INNER JOIN roles r ON r.id = ur.rol_id
            WHERE u.id = :id AND r.nombre = 'TRABAJADOR'
        ");
        $stmt->execute([':id' => $id]);
        $u = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$u) {
            Response::notFound('Trabajador no encontrado');
        }

        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (empty($misSedes)) {
                Response::error('Sin acceso a este trabajador', 403);
            }
            $in = implode(',', array_map('intval', $misSedes));
            $stmtChk = $this->db->prepare("
                SELECT 1 FROM usuario_sede
                WHERE usuario_id = ? AND sede_id IN ({$in})
                  AND estado = 1 AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
            ");
            $stmtChk->execute([$id]);
            if (!$stmtChk->fetch()) {
                Response::error('Sin acceso a este trabajador', 403);
            }
        }

        $stmtSedes = $this->db->prepare("
            SELECT s.id, s.nombre, s.codigo,
                   us.estado, us.fecha_inicio, us.fecha_fin, us.id AS pivot_id,
                   hs.nombre AS horario_nombre
            FROM usuario_sede us
            JOIN sedes s ON s.id = us.sede_id
            LEFT JOIN horarios_sede hs ON hs.id = us.horario_id
            WHERE us.usuario_id = :id
        ");
        $stmtSedes->execute([':id' => $id]);
        $sedesList = $stmtSedes->fetchAll(\PDO::FETCH_ASSOC);
        $sedes = [];
        foreach ($sedesList as $s) {
            $sedes[] = [
                'id' => $s['id'],
                'nombre' => $s['nombre'],
                'codigo' => $s['codigo'],
                'pivot' => [
                    'id' => $s['pivot_id'],
                    'estado' => $s['estado'],
                    'fecha_inicio' => $s['fecha_inicio'],
                    'fecha_fin' => $s['fecha_fin'],
                    'horario_nombre' => $s['horario_nombre']
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
        $apellidos  = (string) $req->input('apellidos');
        $codigo     = (string) $req->input('codigo_modular');
        $email      = strtolower(trim((string) $req->input('email', '')));
        $dni        = (string) $req->input('dni', '');
        $telefono   = (string) $req->input('telefono', '');
        $password   = (string) $req->input('password');

        $errors = [];
        if (!$nombres) {
            $errors[] = 'nombres es requerido';
        }
        if (!$apellidos) {
            $errors[] = 'apellidos es requerido';
        }
        if (!$codigo) {
            $errors[] = 'codigo_modular es requerido';
        }
        if ($errors) {
            Response::unprocessable('Datos incompletos', $errors);
        }

        // Si no hay email, generar uno basado en código
        if (!$email) {
            $email = $codigo . '@asistencia.local';
        }

        // Verificar unicidad en tabla usuarios
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = ? OR codigo_empleado = ?");
        $stmt->execute([$email, $codigo]);
        if ($stmt->fetch(\PDO::FETCH_ASSOC)) {
            Response::error('El email o código ya existe', 422);
        }

        $this->db->beginTransaction();
        try {
            // Crear usuario base (estado_id=1 → ACTIVO)
            $pw = $password ? password_hash($password, PASSWORD_BCRYPT) : password_hash($dni ?: $codigo, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("
                INSERT INTO usuarios (email, codigo_empleado, password, estado_id)
                VALUES (:email, :cod, :pwd, 1)
            ");
            $stmt->execute([
                ':email' => $email,
                ':cod'   => $codigo,
                ':pwd'   => $pw,
            ]);
            $userId = (int) $this->db->lastInsertId();

            // Asignar rol TRABAJADOR (id=3)
            $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, 3)")
                ->execute([$userId]);

            // Crear perfil de trabajador
            $departamentoId = $req->input('departamento_id') ? (int)$req->input('departamento_id') : null;
            $this->db->prepare("
                INSERT INTO usuarios_trabajador (usuario_id, nombres, apellidos, dni, telefono, departamento_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$userId, $nombres, $apellidos, $dni ?: null, $telefono ?: null, $departamentoId]);

            // Crear asignaciones a sedes
            $asignaciones = $req->input('asignaciones', []);
            if (is_array($asignaciones)) {
                $stmtAsig = $this->db->prepare("
                    INSERT INTO usuario_sede (usuario_id, sede_id, horario_id, fecha_inicio, fecha_fin, estado)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                foreach ($asignaciones as $asig) {
                    $sedeId = (int)($asig['institucion_id'] ?? 0);
                    $horarioId = (int)($asig['horario_id'] ?? 0);
                    if (!$sedeId || !$horarioId) {
                        continue;
                    }
                    $stmtAsig->execute([
                        $userId,
                        $sedeId,
                        $horarioId,
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

        // Verificar existencia
        $stmt = $this->db->prepare("
            SELECT u.id FROM usuarios u
            INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
            INNER JOIN roles r ON r.id = ur.rol_id
            WHERE u.id = ? AND r.nombre = 'TRABAJADOR'
        ");
        $stmt->execute([$id]);
        if (!$stmt->fetch(\PDO::FETCH_ASSOC)) {
            Response::notFound('Trabajador no encontrado');
        }

        if ($this->rol() === 'supervisor') {
            $misSedes = $this->sedesDelSupervisor();
            if (empty($misSedes)) {
                Response::error('Sin acceso a este trabajador', 403);
            }
            $in = implode(',', array_map('intval', $misSedes));
            $stmtChk = $this->db->prepare("
                SELECT 1 FROM usuario_sede
                WHERE usuario_id = ? AND sede_id IN ({$in})
                  AND estado = 1 AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
            ");
            $stmtChk->execute([$id]);
            if (!$stmtChk->fetch()) {
                Response::error('Sin acceso a este trabajador', 403);
            }
        }

        $this->db->beginTransaction();
        try {
            // Actualizar tabla usuarios
            $camposUser = [];
            $paramsUser = [];
            $codigo = $req->input('codigo_modular');
            $email  = $req->input('email');
            $pass   = $req->input('password');

            if ($codigo !== null) {
                $camposUser[] = "`codigo_empleado` = ?";
                $paramsUser[] = $codigo;
            }
            if ($email !== null) {
                $camposUser[] = "`email` = ?";
                $paramsUser[] = strtolower(trim($email));
            }
            if ($pass !== null && trim($pass) !== '') {
                $camposUser[] = "`password` = ?";
                $paramsUser[] = password_hash($pass, PASSWORD_BCRYPT);
            }
            if ($camposUser) {
                $paramsUser[] = $id;
                $this->db->prepare("UPDATE usuarios SET " . implode(', ', $camposUser) . " WHERE id = ?")
                    ->execute($paramsUser);
            }

            // Actualizar perfil de trabajador
            $camposPerfil = [];
            $paramsPerfil = [];
            $nombres   = $req->input('nombres');
            $apellidos = $req->input('apellidos');
            $dni       = $req->input('dni');
            $telefono  = $req->input('telefono');

            if ($nombres !== null) {
                $camposPerfil[] = "`nombres` = ?";
                $paramsPerfil[] = $nombres;
            }
            if ($apellidos !== null) {
                $camposPerfil[] = "`apellidos` = ?";
                $paramsPerfil[] = $apellidos;
            }
            if ($dni !== null) {
                $camposPerfil[] = "`dni` = ?";
                $paramsPerfil[] = $dni;
            }
            if ($telefono !== null) {
                $camposPerfil[] = "`telefono` = ?";
                $paramsPerfil[] = $telefono;
            }
            $departamentoId = $req->input('departamento_id');
            if ($departamentoId !== null) {
                $camposPerfil[] = "`departamento_id` = ?";
                $paramsPerfil[] = $departamentoId !== '' ? (int)$departamentoId : null;
            }

            if ($camposPerfil) {
                $paramsPerfil[] = $id;
                $this->db->prepare("UPDATE usuarios_trabajador SET " . implode(', ', $camposPerfil) . " WHERE usuario_id = ?")
                    ->execute($paramsPerfil);
            }

            // Asignaciones de sedes
            $asignaciones = $req->input('asignaciones');
            if (is_array($asignaciones)) {
                $stmtAsig = $this->db->prepare("
                    INSERT INTO usuario_sede (usuario_id, sede_id, horario_id, fecha_inicio, fecha_fin, estado)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                foreach ($asignaciones as $asig) {
                    $sedeId = (int)($asig['institucion_id'] ?? 0);
                    $horarioId = (int)($asig['horario_id'] ?? 0);
                    if (!$sedeId || !$horarioId) {
                        continue;
                    }
                    $stmtAsig->execute([
                        $id,
                        $sedeId,
                        $horarioId,
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
        $estado = strtoupper(trim((string) $req->input('estado')));

        $estadoMap = ['ACTIVO' => 1, 'INACTIVO' => 2, 'BLOQUEADO' => 3];
        $estadoId = $estadoMap[$estado] ?? null;
        if (!$estadoId) {
            Response::unprocessable('Estado inválido. Use ACTIVO, INACTIVO o BLOQUEADO');
        }

        $stmt = $this->db->prepare("UPDATE usuarios SET estado_id = ? WHERE id = ?");
        $stmt->execute([$estadoId, $id]);
        Response::success(null, "Estado cambiado a {$estado}");
    }

    /** PATCH /v1/web/usuarios-app/{id}/horario — asignar sede y horario */
    public function asignarHorario(Request $req): void
    {
        $id        = (int) $req->param('id');
        $sedeId    = (int) $req->input('sede_id');
        $horarioId = (int) $req->input('horario_id');

        if (!$sedeId) {
            Response::unprocessable('sede_id es requerido');
        }
        if (!$horarioId) {
            Response::unprocessable('horario_id es requerido');
        }

        $this->db->beginTransaction();
        try {
            // Cerrar asignación anterior (fecha_fin = hoy)
            $this->db->prepare("
                UPDATE usuario_sede SET estado = 0, fecha_fin = CURDATE()
                WHERE usuario_id = ? AND sede_id = ? AND estado = 1
            ")->execute([$id, $sedeId]);

            // Crear nueva asignación
            $this->db->prepare("
                INSERT INTO usuario_sede (usuario_id, sede_id, horario_id, fecha_inicio, estado)
                VALUES (?, ?, ?, CURDATE(), 1)
            ")->execute([$id, $sedeId, $horarioId]);

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
        $this->db->prepare("UPDATE usuario_sede SET estado = 0, fecha_fin = CURDATE() WHERE id = ?")
            ->execute([$pivotId]);
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
        // Soft delete: cambiar estado a INACTIVO (id=2)
        $this->db->prepare("UPDATE usuarios SET estado_id = 2 WHERE id = ?")->execute([$id]);
        Response::success(null, 'Trabajador desactivado correctamente');
    }

    /** GET /v1/web/password-resets-app */
    public function listarPasswordResets(Request $req): void
    {
        $estado = $req->query('estado', 'PENDIENTE');

        $sql = "
            SELECT pra.id, pra.usuario_id, pra.estado, pra.created_at,
                   u.codigo_empleado, ut.nombres, ut.apellidos, ut.dni
            FROM password_resets_app pra
            INNER JOIN usuarios u ON u.id = pra.usuario_id
            INNER JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
            WHERE pra.estado = :estado
            ORDER BY pra.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':estado' => $estado]);
        Response::success($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** POST /v1/web/password-resets-app/{id}/aprobar */
    public function aprobarPasswordReset(Request $req): void
    {
        $id = (int) $req->param('id');

        $stmt = $this->db->prepare("SELECT * FROM password_resets_app WHERE id = ?");
        $stmt->execute([$id]);
        $reset = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$reset) {
            Response::notFound('Solicitud de recuperación no encontrada');
        }
        if ($reset['estado'] !== 'PENDIENTE') {
            Response::error('Esta solicitud ya fue procesada', 400);
        }

        // Generar clave temporal de 6 dígitos
        $tempPassword = (string) rand(100000, 999999);
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

        $this->db->beginTransaction();
        try {
            // Actualizar estado de solicitud
            $this->db->prepare("UPDATE password_resets_app SET estado = 'APROBADA' WHERE id = ?")->execute([$id]);

            // Actualizar contraseña del trabajador y forzar cambio
            $this->db->prepare("
                UPDATE usuarios 
                SET password = ?, debe_cambiar_password = 1 
                WHERE id = ?
            ")->execute([$hash, $reset['usuario_id']]);

            $this->db->commit();

            Response::success([
                'temp_password' => $tempPassword
            ], 'Solicitud aprobada. Proporcione la contraseña temporal al trabajador.');
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[UsuarioAppController::aprobarPasswordReset] Error: ' . $e->getMessage());
            Response::error('Error al procesar la aprobación.', 500);
        }
    }

    /** POST /v1/web/password-resets-app/{id}/rechazar */
    public function rechazarPasswordReset(Request $req): void
    {
        $id = (int) $req->param('id');

        $stmt = $this->db->prepare("SELECT * FROM password_resets_app WHERE id = ?");
        $stmt->execute([$id]);
        $reset = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$reset) {
            Response::notFound('Solicitud de recuperación no encontrada');
        }
        if ($reset['estado'] !== 'PENDIENTE') {
            Response::error('Esta solicitud ya fue procesada', 400);
        }

        $this->db->prepare("UPDATE password_resets_app SET estado = 'RECHAZADA' WHERE id = ?")->execute([$id]);
        Response::success(null, 'Solicitud rechazada correctamente');
    }

    /** POST /v1/web/usuarios-app/import */
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

        // Si es supervisor, pre-cargar sus sedes permitidas (código => id)
        $esSupervisor = ($this->rol() === 'supervisor');
        $sedesPermitidas = [];
        if ($esSupervisor) {
            $misSedeIds = $this->sedesDelSupervisor();
            if (empty($misSedeIds)) {
                Response::error('No tienes sedes asignadas para realizar importaciones.', 403);
            }
            $in = implode(',', array_map('intval', $misSedeIds));
            $stmtSedesCodigo = $this->db->query("SELECT id, codigo FROM sedes WHERE id IN ({$in})");
            foreach ($stmtSedesCodigo->fetchAll(\PDO::FETCH_ASSOC) as $s) {
                $sedesPermitidas[$s['codigo']] = (int)$s['id'];
            }
        }

        // Leer cabeceras desde la primera fila
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
            $rowEmpty = true;
            foreach ($headers as $col => $header) {
                if (isset($row[$col]) && trim((string)$row[$col]) !== '') {
                    $rowEmpty = false;
                    break;
                }
            }
            if ($rowEmpty) {
                continue;
            }

            $totalProcesados++;

            $data = [];
            foreach ($headers as $col => $header) {
                $data[$header] = isset($row[$col]) ? trim((string)$row[$col]) : '';
            }

            $codigo        = $data['codigo_empleado'] ?? '';
            $nombres       = $data['nombres'] ?? '';
            $apellidos     = $data['apellidos'] ?? '';
            $dni           = $data['dni'] ?? '';
            $email         = isset($data['email']) ? strtolower(trim((string)$data['email'])) : '';
            $telefono      = $data['telefono'] ?? '';
            $password      = $data['password'] ?? '';
            $cargo         = $data['cargo'] ?? 'DOCENTE';
            $condicion     = $data['condicion_laboral'] ?? '';
            $sedeCodigo    = $data['sede_codigo'] ?? '';
            $horarioNombre = $data['horario_nombre'] ?? '';

            if (empty($codigo) || empty($nombres) || empty($apellidos) || empty($dni)) {
                $errores[] = "Fila {$rowIndex}: código_empleado, nombres, apellidos y DNI son obligatorios.";
                continue;
            }

            // Supervisor: validar que la sede del Excel le pertenezca
            if ($esSupervisor && !empty($sedeCodigo) && !isset($sedesPermitidas[$sedeCodigo])) {
                $errores[] = "Fila {$rowIndex}: No tienes permiso para asignar trabajadores a la sede '{$sedeCodigo}'.";
                continue;
            }

            if (empty($email)) {
                $email = $codigo . '@asistencia.local';
            }

            $this->db->beginTransaction();
            try {
                $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE codigo_empleado = ? OR email = ?");
                $stmt->execute([$codigo, $email]);
                $existingUser = $stmt->fetch();

                if ($existingUser) {
                    $userId = $existingUser['id'];

                    // Supervisor: verificar que el trabajador existente sea de sus sedes
                    if ($esSupervisor) {
                        $inSup = implode(',', array_values($sedesPermitidas));
                        $stmtChk = $this->db->prepare("
                            SELECT 1 FROM usuario_sede
                            WHERE usuario_id = ? AND sede_id IN ({$inSup})
                              AND estado = 1 AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
                        ");
                        $stmtChk->execute([$userId]);
                        if (!$stmtChk->fetch()) {
                            $this->db->rollBack();
                            $errores[] = "Fila {$rowIndex}: El trabajador '{$codigo}' no pertenece a tus sedes asignadas.";
                            continue;
                        }
                    }

                    $this->db->prepare("
                        UPDATE usuarios_trabajador 
                        SET nombres = ?, apellidos = ?, DNI = ?, telefono = ? 
                        WHERE usuario_id = ?
                    ")->execute([$nombres, $apellidos, $dni, $telefono ?: null, $userId]);

                    if (!empty($password)) {
                        $this->db->prepare("UPDATE usuarios SET password = ? WHERE id = ?")
                            ->execute([password_hash($password, PASSWORD_BCRYPT), $userId]);
                    }
                } else {
                    $pw = $password
                        ? password_hash($password, PASSWORD_BCRYPT)
                        : password_hash($dni ?: $codigo, PASSWORD_BCRYPT);

                    $this->db->prepare("
                        INSERT INTO usuarios (email, codigo_empleado, password, estado_id) VALUES (?, ?, ?, 1)
                    ")->execute([$email, $codigo, $pw]);
                    $userId = (int) $this->db->lastInsertId();

                    $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, 3)")
                        ->execute([$userId]);

                    $this->db->prepare("
                        INSERT INTO usuarios_trabajador (usuario_id, nombres, apellidos, dni, telefono) 
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$userId, $nombres, $apellidos, $dni, $telefono ?: null]);
                }

                // Asignación de Sede & Horario
                if (!empty($sedeCodigo)) {
                    if ($esSupervisor) {
                        $sedeId = $sedesPermitidas[$sedeCodigo] ?? null;
                    } else {
                        $stmtSede = $this->db->prepare("SELECT id FROM sedes WHERE codigo = ?");
                        $stmtSede->execute([$sedeCodigo]);
                        $sedeRow = $stmtSede->fetch();
                        $sedeId = $sedeRow ? (int)$sedeRow['id'] : null;
                    }

                    if ($sedeId) {
                        $horarioId = null;
                        if (!empty($horarioNombre)) {
                            $stmtHorario = $this->db->prepare("SELECT id FROM horarios_sede WHERE sede_id = ? AND nombre = ?");
                            $stmtHorario->execute([$sedeId, $horarioNombre]);
                            $horario = $stmtHorario->fetch();
                            if ($horario) {
                                $horarioId = (int)$horario['id'];
                            }
                        }

                        if ($horarioId) {
                            $stmtActive = $this->db->prepare("
                                SELECT id FROM usuario_sede 
                                WHERE usuario_id = ? AND sede_id = ? AND horario_id = ? AND estado = 1
                            ");
                            $stmtActive->execute([$userId, $sedeId, $horarioId]);
                            if (!$stmtActive->fetch()) {
                                $this->db->prepare("
                                    UPDATE usuario_sede SET estado = 0, fecha_fin = CURDATE() 
                                    WHERE usuario_id = ? AND estado = 1
                                ")->execute([$userId]);

                                $this->db->prepare("
                                    INSERT INTO usuario_sede (usuario_id, sede_id, horario_id, fecha_inicio, estado) 
                                    VALUES (?, ?, ?, CURDATE(), 1)
                                ")->execute([$userId, $sedeId, $horarioId]);
                            }
                        }
                    }
                }

                $this->db->commit();
                $exitosos++;
            } catch (\Exception $e) {
                $this->db->rollBack();
                $errores[] = "Fila {$rowIndex}: " . $e->getMessage();
            }
        }

        Response::success([
            'total_procesados' => $totalProcesados,
            'exitosos'         => $exitosos,
            'errores'          => $errores
        ]);
    }

    /** GET /v1/web/usuarios-app/import/template */
    public function downloadTemplate(Request $req): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $headers = [
            'codigo_empleado', 'nombres', 'apellidos', 'dni', 'email', 
            'telefono', 'password', 'cargo', 'condicion_laboral', 
            'sede_codigo', 'horario_nombre'
        ];
        foreach ($headers as $colIndex => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($colLetter . '1', $header);
        }
        
        $example = [
            'EMP-001', 'Juan', 'Perez Garcia', '12345678', 'juan.perez@empresa.com', 
            '987654321', 'password123', 'DOCENTE', 'Nombrado', 
            'SEDE-001', 'Horario General'
        ];
        foreach ($example as $colIndex => $val) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($colLetter . '2', $val);
        }
        
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        foreach (range('A', 'K') as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="plantilla_trabajadores.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }
}
