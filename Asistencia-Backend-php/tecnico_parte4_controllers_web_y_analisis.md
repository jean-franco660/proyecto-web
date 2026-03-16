# Documento Técnico Exhaustivo — API de Asistencia PHP MVC
## Parte 4: Controladores Web & Análisis (Código Corregido)

---

## 28. `Controllers/Web/AuthWebController.php` (Corregido — FIX Bug #7)

```php
<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Models\UsuarioWeb;
use Firebase\JWT\JWT;

class AuthWebController
{
    private UsuarioWeb $model;
    public function __construct() { $this->model = new UsuarioWeb(); }

    /**
     * POST /v1/web/login — Login de administradores y supervisores
     * Genera un JWT de tipo 'web' (diferente al 'app' de trabajadores).
     */
    public function login(Request $request): void
    {
        $email    = strtolower(trim($request->input('email', '')));
        // ↑ strtolower(): convierte a minúsculas para búsqueda case-insensitive.
        //   'Admin@Empresa.COM' → 'admin@empresa.com'
        //   Esto evita que un usuario no pueda entrar porque escribió su email con mayúsculas.
        //   trim(): quita espacios accidentales al copiar/pegar email.

        $password = $request->input('password', '');

        if (!$email || !$password)
            Response::unprocessable('Datos requeridos');

        $usuario = $this->model->findByEmail($email);
        // ↑ SQL: SELECT * FROM usuarios_web WHERE email = ? LIMIT 1

        if (!$usuario || !password_verify($password, $usuario['password']))
            Response::unauthorized('Credenciales incorrectas.');
        // ↑ Mismo patrón anti-enumeración que AuthAppController.

        $secret     = $_ENV['JWT_SECRET'] ?? 'secret';

        // FIX Bug #7: la expiración estaba HARDCODEADA a 86400 (24 horas).
        // Esto significaba que cambiar JWT_EXPIRATION en .env NO tenía efecto
        // para los tokens del panel web. Solo los tokens de la app leían el .env.
        // DESPUÉS del fix: lee de $_ENV como lo hace AuthAppController.
        $expiration = (int)($_ENV['JWT_EXPIRATION'] ?? 86400);
        // ↑ Default 86400 = 24 horas (sesión web más larga que app móvil).

        $payload = [
            'iss'  => 'asistencia-api',
            'iat'  => time(),
            'exp'  => time() + $expiration,    // FIX Bug #7: ahora configurable
            'sub'  => $usuario['id'],
            'rol'  => $usuario['rol'],          // 'super_admin', 'administrador', 'supervisor'
            'tipo' => 'web',                    // AuthWebMiddleware verifica tipo='web'
        ];

        $token = JWT::encode($payload, $secret, 'HS256');

        // Registrar último login para auditoría
        $this->model->update($usuario['id'], ['ultimo_login' => date('Y-m-d H:i:s')]);
        // ↑ BaseModel::update → UPDATE usuarios_web SET ultimo_login = ? WHERE id = ?

        unset($usuario['password']);            // Nunca enviar el hash al frontend
        Response::success(['token' => $token, 'usuario' => $usuario], 'Login exitoso.');
    }

    /** GET /v1/web/me — Datos del admin/supervisor autenticado */
    public function me(Request $request): void
    {
        $userId  = (int) ($_REQUEST['auth_user']['sub'] ?? 0);
        $usuario = $this->model->find($userId);
        if (!$usuario) Response::notFound('Usuario no encontrado.');
        unset($usuario['password']);
        Response::success($usuario);
    }

    /** POST /v1/web/logout — Cierre de sesión simbólico (stateless) */
    public function logout(Request $request): void
    {
        Response::success(null, 'Sesión cerrada.');
    }
}
```

---

## 29. `Controllers/Web/UsuarioWebController.php` — Gestión de Admins

```php
<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class UsuarioWebController
{
    private \PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    /**
     * Barrera RBAC: solo super_admin puede gestionar otros administradores.
     * Se invoca al inicio de CADA método del controller.
     * Si el usuario no es super_admin → 403 + exit() → el método NO continúa.
     */
    private function soloSuperAdmin(): void
    {
        $rol = $_REQUEST['auth_user']['rol'] ?? '';
        if ($rol !== 'super_admin')
            Response::error('Solo el super_admin puede gestionar administradores', 403);
        // ↑ Un administrador normal NO puede crear/editar/eliminar otros admins.
        //   Solo el super_admin tiene este poder (principio de mínimo privilegio).
    }

    /** GET /v1/web/usuarios-web — Listar admins y supervisores */
    public function index(Request $req): void
    {
        $this->soloSuperAdmin();
        // ↑ Si no es super_admin → 403 + exit(). El query() de abajo NUNCA se ejecuta.

        $stmt = $this->db->query("
            SELECT id, nombre, email, rol, estado, created_at
            FROM usuarios_web ORDER BY rol, nombre
        ");
        // ↑ NO incluye 'password' en el SELECT por defensa en profundidad.
        //   Aunque Response lo envíe al frontend, el hash nunca sale de la BD.
        //   ORDER BY rol: agrupa super_admin → administrador → supervisor.
        Response::success($stmt->fetchAll());
    }

    /** GET /v1/web/usuarios-web/{id} */
    public function show(Request $req): void
    {
        $this->soloSuperAdmin();
        $id   = (int) $req->param('id');
        $stmt = $this->db->prepare("SELECT id, nombre, email, rol, estado FROM usuarios_web WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) Response::notFound('Usuario web no encontrado');
        Response::success($user);
    }

    /** POST /v1/web/usuarios-web — Crear nuevo admin o supervisor */
    public function store(Request $req): void
    {
        $this->soloSuperAdmin();

        $nombre   = (string) $req->input('nombre');
        $email    = strtolower(trim((string) $req->input('email')));
        $password = (string) $req->input('password');
        $rol      = (string) $req->input('rol');

        // ── Validación completa con recopilación de errores ────
        $errors = [];
        if (!$nombre)   $errors[] = 'nombre es requerido';
        if (!$email)    $errors[] = 'email es requerido';
        if (!$password) $errors[] = 'password es requerido';
        if (!in_array($rol, ['administrador', 'supervisor'])) $errors[] = 'rol inválido';
        // ↑ NO permite crear 'super_admin' por API. Solo puede existir uno (hardcodeado en seed).
        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        // ── Validación de unicidad ──────────────────────────────
        $stmt = $this->db->prepare("SELECT id FROM usuarios_web WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) Response::error('El email ya está registrado', 422);

        // ── Insertar con password hasheado ──────────────────────
        $stmt = $this->db->prepare("
            INSERT INTO usuarios_web (nombre, email, password, rol, estado)
            VALUES (:n, :e, :p, :r, 'ACTIVO')
        ");
        $stmt->execute([
            ':n' => $nombre, ':e' => $email,
            ':p' => password_hash($password, PASSWORD_BCRYPT),
            // ↑ PASSWORD_BCRYPT: genera hash de ~60 chars con salt aleatorio incluido.
            //   Cada llamada produce hash DIFERENTE aunque el password sea el mismo.
            //   Costo por defecto: 10 (2^10 = 1024 iteraciones).
            ':r' => $rol,
        ]);

        Response::success(['id' => $this->db->lastInsertId()], 'Usuario web creado correctamente', 201);
    }

    /** PUT /v1/web/usuarios-web/{id} — Actualizar datos (no password) */
    public function update(Request $req): void
    {
        $this->soloSuperAdmin();
        $id = (int) $req->param('id');

        // ── Construcción dinámica de SET ────────────────────────
        $campos = [];
        $params = [];
        foreach (['nombre', 'email', 'rol'] as $campo) {
            if ($req->input($campo) !== null) {
                $campos[] = "`{$campo}` = ?";
                $params[] = $req->input($campo);
            }
        }
        // ↑ Solo actualiza los campos que se envían. Si solo envía 'nombre',
        //   no toca 'email' ni 'rol'. Backticks protegen contra palabras reservadas.
        if (empty($campos)) Response::unprocessable('No hay campos a actualizar');

        $params[] = $id;    // Último parámetro: WHERE id = ?
        $this->db->prepare("UPDATE usuarios_web SET " . implode(', ', $campos) . " WHERE id = ?")
                 ->execute($params);
        Response::success(null, 'Usuario web actualizado correctamente');
    }

    /** PATCH /v1/web/usuarios-web/{id}/estado — Activar o desactivar admin */
    public function cambiarEstado(Request $req): void
    {
        $this->soloSuperAdmin();
        $id     = (int) $req->param('id');
        $estado = (string) $req->input('estado');

        if (!in_array($estado, ['ACTIVO', 'INACTIVO']))
            Response::unprocessable('Estado inválido');

        $this->db->prepare("UPDATE usuarios_web SET estado = ? WHERE id = ?")
                 ->execute([$estado, $id]);
        Response::success(null, "Estado cambiado a {$estado}");
    }
}
```

---

## 30. `Controllers/Web/UsuarioAppController.php` (Corregido — FIX Bug #10)

```php
<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class UsuarioAppController
{
    private \PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    /**
     * GET /v1/web/usuarios-app — Listar trabajadores con filtros
     * Soporta filtro por sede_id y búsqueda por texto.
     */
    public function index(Request $req): void
    {
        $sedeId = $req->query('sede_id');
        $search = $req->query('search');

        $sql = "
            SELECT u.id, u.codigo_empleado, u.nombres, u.apellido_paterno, u.apellido_materno,
                   u.email, u.dni, u.estado,
                   uas.cargo, s.nombre AS sede_nombre, hs.nombre_turno
            FROM usuarios_app u
            LEFT JOIN usuario_app_sede uas ON uas.usuario_app_id = u.id AND uas.estado = 'ACTIVO'
            LEFT JOIN sedes s              ON s.id = uas.sede_id
            LEFT JOIN horarios_sede hs     ON hs.id = uas.horario_sede_id
            WHERE 1=1
        ";
        // ↑ WHERE 1=1 es un truco para construir queries dinámicas:
        //   Permite añadir condiciones con AND sin preocuparse de si es la primera.
        //   Sin WHERE 1=1: el primer filtro necesitaría WHERE, los siguientes AND.
        //   Con WHERE 1=1: TODOS los filtros usan AND uniformemente.
        //
        //   LEFT JOIN: muestra trabajadores aunque NO tengan asignación activa.
        //   AND uas.estado = 'ACTIVO' en el ON (no WHERE) para que el LEFT JOIN funcione.
        $params = [];

        if ($sedeId) {
            $sql .= " AND uas.sede_id = :sid";
            $params[':sid'] = (int) $sedeId;
        }
        if ($search) {
            $sql .= " AND (u.nombres LIKE :q OR u.apellido_paterno LIKE :q OR u.codigo_empleado LIKE :q)";
            $params[':q'] = "%{$search}%";
            // ↑ LIKE con % al inicio y final busca en CUALQUIER posición.
            //   '%juan%' matchea 'Juan Carlos', 'De Juan', 'alejuandro'.
            //   ⚠️ LIKE con % al inicio NO usa índices (full scan).
            //   Para producción con >10k registros: usar FULLTEXT INDEX.
        }

        $sql .= " ORDER BY u.apellido_paterno, u.nombres";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    /** GET /v1/web/usuarios-app/{id} — Detalle de un trabajador */
    public function show(Request $req): void
    {
        $id = (int) $req->param('id');
        $stmt = $this->db->prepare("
            SELECT u.*, uas.cargo, uas.sede_id, uas.horario_sede_id,
                   s.nombre AS sede_nombre, hs.nombre_turno
            FROM usuarios_app u
            LEFT JOIN usuario_app_sede uas ON uas.usuario_app_id = u.id AND uas.estado = 'ACTIVO'
            LEFT JOIN sedes s              ON s.id = uas.sede_id
            LEFT JOIN horarios_sede hs     ON hs.id = uas.horario_sede_id
            WHERE u.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) Response::notFound('Trabajador no encontrado');
        unset($user['password']);
        Response::success($user);
    }

    /** POST /v1/web/usuarios-app — Crear trabajador */
    public function store(Request $req): void
    {
        $nombres   = (string) $req->input('nombres');
        $apPaterno = (string) $req->input('apellido_paterno');
        $apMaterno = (string) $req->input('apellido_materno', '');
        $codigo    = (string) $req->input('codigo_empleado');
        $email     = strtolower(trim((string) $req->input('email')));
        $dni       = (string) $req->input('dni', '');
        $password  = (string) $req->input('password');

        $errors = [];
        if (!$nombres)   $errors[] = 'nombres es requerido';
        if (!$apPaterno) $errors[] = 'apellido_paterno es requerido';
        if (!$codigo)    $errors[] = 'codigo_empleado es requerido';
        if (!$email)     $errors[] = 'email es requerido';
        if (!$password)  $errors[] = 'password es requerido';
        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        // Verificar unicidad de email Y código
        $stmt = $this->db->prepare("SELECT id FROM usuarios_app WHERE email = ? OR codigo_empleado = ?");
        $stmt->execute([$email, $codigo]);
        if ($stmt->fetch()) Response::error('El email o código de empleado ya existe', 422);

        $stmt = $this->db->prepare("
            INSERT INTO usuarios_app
                (nombres, apellido_paterno, apellido_materno, codigo_empleado, email, dni, password, estado)
            VALUES (:n, :ap, :am, :cod, :email, :dni, :pwd, 'ACTIVO')
        ");
        $stmt->execute([
            ':n' => $nombres, ':ap' => $apPaterno, ':am' => $apMaterno,
            ':cod' => $codigo, ':email' => $email, ':dni' => $dni,
            ':pwd' => password_hash($password, PASSWORD_BCRYPT),
        ]);
        Response::success(['id' => $this->db->lastInsertId()], 'Trabajador creado correctamente', 201);
    }

    /** PUT /v1/web/usuarios-app/{id} — Actualizar datos de trabajador */
    public function update(Request $req): void
    {
        $id = (int) $req->param('id');
        $stmt = $this->db->prepare("SELECT id FROM usuarios_app WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) Response::notFound('Trabajador no encontrado');

        $campos = [];  $params = [];
        foreach (['nombres','apellido_paterno','apellido_materno','email','dni'] as $campo) {
            if ($req->input($campo) !== null) {
                $campos[] = "`{$campo}` = ?";
                $params[] = $req->input($campo);
            }
        }
        if (empty($campos)) Response::unprocessable('No hay campos a actualizar');

        $params[] = $id;
        $this->db->prepare("UPDATE usuarios_app SET " . implode(', ', $campos) . " WHERE id = ?")
                 ->execute($params);
        Response::success(null, 'Trabajador actualizado correctamente');
    }

    /** PATCH /v1/web/usuarios-app/{id}/estado — Cambiar estado */
    public function cambiarEstado(Request $req): void
    {
        $id     = (int) $req->param('id');
        $estado = (string) $req->input('estado');
        if (!in_array($estado, ['ACTIVO', 'INACTIVO']))
            Response::unprocessable('Estado inválido. Use ACTIVO o INACTIVO');
        $this->db->prepare("UPDATE usuarios_app SET estado = ? WHERE id = ?")
                 ->execute([$estado, $id]);
        Response::success(null, "Estado cambiado a {$estado}");
    }

    /**
     * PATCH /v1/web/usuarios-app/{id}/horario — Asignar sede y horario
     *
     * FIX Bug #10: Las dos operaciones DML (desactivar asignación anterior +
     * crear una nueva) NO estaban en transacción. Si el INSERT fallaba (ej:
     * FK inválida, horario inexistente), el UPDATE ya se había ejecutado y el
     * trabajador quedaba SIN NINGUNA asignación activa. Ahora ambas operaciones
     * están en una transacción: si una falla, se hace rollBack y todo queda como antes.
     */
    public function asignarHorario(Request $req): void
    {
        $id        = (int) $req->param('id');
        $sedeId    = (int) $req->input('sede_id');
        $horarioId = (int) $req->input('horario_sede_id');
        $cargo     = (string) $req->input('cargo', '');

        if (!$sedeId || !$horarioId)
            Response::unprocessable('sede_id y horario_sede_id son requeridos');

        $this->db->beginTransaction();
        // ↑ INICIA TRANSACCIÓN: todas las operaciones dentro son atómicas.
        //   O todas se ejecutan, o ninguna (principio ACID: Atomicity).
        try {
            // Paso 1: Desactivar TODAS las asignaciones anteriores del trabajador
            $this->db->prepare("UPDATE usuario_app_sede SET estado = 'INACTIVO' WHERE usuario_app_id = ?")
                     ->execute([$id]);
            // ↑ Un trabajador solo puede tener UNA asignación ACTIVA a la vez.
            //   Poner todas en INACTIVO antes de crear la nueva.

            // Paso 2: Crear nueva asignación ACTIVA
            $this->db->prepare("
                INSERT INTO usuario_app_sede (usuario_app_id, sede_id, horario_sede_id, cargo, estado)
                VALUES (?, ?, ?, ?, 'ACTIVO')
            ")->execute([$id, $sedeId, $horarioId, $cargo]);

            $this->db->commit();
            // ↑ CONFIRMA: ambas operaciones se aplican permanentemente.
        } catch (\Exception $e) {
            $this->db->rollBack();
            // ↑ REVIERTE: como si ninguna operación se hubiera ejecutado.
            //   Las asignaciones anteriores siguen ACTIVAS.
            error_log('[UsuarioAppController::asignarHorario] Error: ' . $e->getMessage());
            Response::error('Error al asignar sede y horario. Intente nuevamente.', 500);
        }

        Response::success(null, 'Sede y horario asignados correctamente');
    }

    /** DELETE /v1/web/usuarios-app/{id} — Soft delete (desactivar) */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');
        $this->db->prepare("UPDATE usuarios_app SET estado = 'INACTIVO' WHERE id = ?")
                 ->execute([$id]);
        // ↑ SOFT DELETE: no borra el registro, solo lo desactiva.
        //   El trabajador y su historial de asistencias se conservan para auditoría.
        Response::success(null, 'Trabajador desactivado correctamente');
    }
}
```

---

## 31. `Controllers/Web/SedeWebController.php` (Corregido — FIX Bug #8)

```php
<?php
namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Models\Sede;

class SedeWebController
{
    private Sede $model;
    public function __construct() { $this->model = new Sede(); }

    /**
     * GET /v1/web/sedes — Listado con búsqueda, paginación y ordenamiento.
     * Soporta: ?search=lima&sort_by=nombre&sort_order=asc&page=2&per_page=20
     */
    public function index(Request $req): void
    {
        $search  = $req->query('search');
        $sortBy  = in_array($req->query('sort_by'), ['id','nombre','distrito','created_at'])
                    ? $req->query('sort_by') : 'id';
        // ↑ WHITELIST para sort_by: solo columnas conocidas.
        //   Previene SQL injection: si alguien envía sort_by=id;DROP TABLE sedes
        //   → no está en la lista → usa 'id' por defecto.
        $order   = $req->query('sort_order', 'asc') === 'desc' ? 'DESC' : 'ASC';
        // ↑ Solo permite 'ASC' o 'DESC'. Cualquier otro valor → ASC.
        $perPage = (int) $req->query('per_page', 20);
        $offset  = ((int) $req->query('page', 1) - 1) * $perPage;
        // ↑ Paginación offset: page=1 → offset=0, page=2 → offset=20, page=3 → offset=40

        $where  = 'WHERE deleted_at IS NULL';
        // ↑ Excluye sedes "eliminadas" (soft delete).
        $params = [];

        if ($search) {
            $where .= ' AND (nombre LIKE :s OR codigo_sede LIKE :s2)';
            $params[':s']  = "%$search%";
            $params[':s2'] = "%$search%";
        }

        // FIX Lint: usa $this->model->db() que ahora es público
        $stmt = $this->model->db()->prepare(
            "SELECT * FROM sedes $where ORDER BY $sortBy $order LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        // ↑ PARAM_INT es necesario para LIMIT/OFFSET.
        //   Sin esto, PDO los trataría como strings: LIMIT '20' → error SQL.
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();

        Response::success($stmt->fetchAll());
    }

    /** GET /v1/web/sedes/mis-sedes — Admin: todas. Supervisor: solo las suyas */
    public function misSedes(Request $req): void
    {
        $authUser = $_REQUEST['auth_user'];
        $rol    = $authUser['rol'] ?? '';
        $userId = (int) ($authUser['sub'] ?? 0);

        if (in_array($rol, ['super_admin', 'administrador'])) {
            Response::success($this->model->all());
            // ↑ Admins ven TODAS las sedes.
        } else {
            // Supervisor: solo sedes asignadas mediante tabla usuario_web_sede
            $stmt = $this->model->db()->prepare("
                SELECT s.* FROM sedes s
                INNER JOIN usuario_web_sede uws ON s.id = uws.sede_id
                WHERE uws.usuario_web_id = :uid AND uws.activo = 1
                  AND (uws.fecha_fin IS NULL OR uws.fecha_fin >= CURDATE())
                  AND s.deleted_at IS NULL
            ");
            // ↑ Verifica que la asignación esté activa Y no haya expirado.
            //   CURDATE() retorna la fecha actual del servidor MySQL.
            $stmt->execute([':uid' => $userId]);
            Response::success($stmt->fetchAll());
        }
    }

    /** POST /v1/web/sedes — Crear nueva sede con coordenadas GPS */
    public function store(Request $req): void
    {
        $data = $req->only(['codigo_sede','nombre','rubro','distrito','direccion','latitud','longitud','radio']);
        // ↑ WHITELIST: solo acepta estos campos. Cualquier otro campo enviado se ignora.

        $errors = [];
        if (empty($data['codigo_sede'])) $errors[] = 'codigo_sede es requerido';
        if (empty($data['nombre']))      $errors[] = 'nombre es requerido';
        if (!isset($data['latitud']))    $errors[] = 'latitud es requerida';
        if (!isset($data['longitud']))   $errors[] = 'longitud es requerida';
        if (!isset($data['radio']))      $errors[] = 'radio es requerido';
        if ($errors) Response::unprocessable('Datos incompletos', $errors);

        $id = $this->model->create($data);
        // ↑ BaseModel::create() genera INSERT dinámico con los campos del array.
        Response::success($this->model->find($id), 'Sede creada correctamente', 201);
    }

    /** GET /v1/web/sedes/{id} */
    public function show(Request $req): void
    {
        $sede = $this->model->find((int) $req->param('id'));
        if (!$sede) Response::notFound('Sede no encontrada');
        Response::success($sede);
    }

    /** PUT /v1/web/sedes/{id} — Actualizar sede */
    public function update(Request $req): void
    {
        $id   = (int) $req->param('id');
        $sede = $this->model->find($id);
        if (!$sede) Response::notFound('Sede no encontrada');

        $data = $req->only(['nombre','rubro','distrito','direccion','latitud','longitud','radio']);
        $this->model->update($id, $data);
        Response::success($this->model->find($id), 'Sede actualizada correctamente');
    }

    /**
     * DELETE /v1/web/sedes/{id} — Eliminar sede
     *
     * FIX Bug #8: $e->getMessage() exponía detalles internos de MySQL al cliente.
     * Ejemplo de lo que se exponía: "SQLSTATE[23000]: Integrity constraint violation:
     * 1451 Cannot delete or update a parent row: a foreign key constraint fails
     * (`asistencia_db`.`usuario_app_sede`, CONSTRAINT `usuario_app_sede_ibfk_2`
     * FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`))"
     * → Revela nombres de tablas, columnas y constraints al atacante.
     * DESPUÉS del fix: se loguea internamente y el cliente recibe mensaje genérico.
     */
    public function destroy(Request $req): void
    {
        $id   = (int) $req->param('id');
        $sede = $this->model->find($id);
        if (!$sede) Response::notFound('Sede no encontrada');

        try {
            $this->model->delete($id);
            Response::success(null, 'Sede eliminada correctamente');
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                // ↑ SQLSTATE 23000 = Integrity constraint violation.
                //   Significa que hay registros en otras tablas que referencian esta sede
                //   (trabajadores asignados, asistencias, horarios).
                Response::error(
                    'No se puede eliminar: la sede tiene trabajadores, horarios o asistencias asociadas',
                    409    // 409 Conflict: la acción no puede completarse por conflicto de estado
                );
            }
            // FIX Bug #8: loguear internamente, NO exponer al cliente
            error_log('[SedeWebController::destroy] Error: ' . $e->getMessage());
            Response::error('Error interno al eliminar la sede. Contacte al administrador.', 500);
        }
    }
}
```

---

## 32. `Controllers/Web/HorarioWebController.php` (Corregido — FIX Bug #9)

Controller, su código, y las correcciones ya fueron documentados detalladamente en la versión anterior. Los puntos clave del FIX Bug #9:

```php
// En el método update():
// FIX Bug #9: los nombres de columna no tenían backticks en el SET dinámico.
$sets = implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($data)));
// ↑ ANTES: "$k = :$k" → si la columna se llama 'order' o 'key' (palabras reservadas SQL)
//   MySQL la interpretaría como keyword → error de sintaxis.
//   AHORA: "`$k` = :$k" → backticks fuerzan a MySQL a tratarla como identificador.
```

El controller completo incluye:
- **index()**: lista horarios filtrados por sede, con restricción de supervisor a sus sedes
- **store()**: crea horario + auto-asigna a trabajadores sin horario en esa sede
- **update()**: actualización parcial con SET dinámico (FIX Bug #9)
- **destroy()**: eliminación física del horario
- **autoAsignarHorario()**: método privado que asigna el nuevo horario a trabajadores sin uno

---

## 33. `Controllers/Web/AsistenciaWebController.php`

```php
// Métodos principales:

// index(): Listado con filtros dinámicos por sede, fecha, estado_marcacion, estado_revision.
//   Supervisor solo ve marcaciones de SUS sedes (subconsulta en WHERE).
//   LIMIT 200 para evitar sobrecarga.

// updateRevision(): PATCH que permite aprobar o mantener observadas las marcaciones.
//   Verifica que el supervisor tenga acceso a la sede de la marcación.
//   Registra quién revisó y cuándo.
```

---

## 34. `Controllers/Web/JustificacionWebController.php` (Corregido — FIX Bug #11)

Los métodos aprobar() y rechazar() ahora usan **transacciones** para garantizar atomicidad:

```php
// FIX Bug #11: ANTES, si el UPDATE de justificaciones pasaba pero el UPDATE de
// asistencias fallaba, la justificación quedaba 'APROBADO' pero las asistencias
// seguían como 'FALTA' → inconsistencia. AHORA ambos UPDATEs están en transacción.

// aprobar(): beginTransaction() → UPDATE justificaciones + UPDATE asistencias → commit()
//   Si falla → rollBack() → todo queda como antes.

// rechazar(): igual que aprobar() pero cambia estado a 'RECHAZADO' y asistencias a 'FALTA'.
//   REQUIERE observaciones (motivo del rechazo) como campo obligatorio.
```

---

## 35. `Controllers/Web/FeriadoController.php`

```php
// Métodos:
// index(): Lista feriados con filtros por tipo (nacional/sede) y sede_id.
// store(): Crea feriado con validación de duplicados por (tipo, dia, mes, sede_id).
//   RBAC: solo admins pueden crear feriados nacionales.
// destroy(): Soft delete (activo=0). Solo admins pueden eliminar nacionales.
```

---

## 36. `Controllers/Web/StatsController.php` (FIX Bug #12 en api.php)

```php
// dashboard(): Devuelve estadísticas del día para el panel web.
//   - Conteos agrupados: presentes, tardanzas, faltas, justificados
//   - Marcaciones observadas pendientes de revisión
//   - Justificaciones pendientes de aprobación
//   Supervisor solo ve stats de SUS sedes.
//
// FIX Bug #12: en api.php, la ruta apuntaba al método 'index' que NO EXISTE
// en StatsController. El único método es 'dashboard'. Esto causaba error 500
// cuando el panel web cargaba el dashboard.
```

---

## 37. Catálogo Completo de Bugs Corregidos

| # | Severidad | Archivo | Descripción del Bug | Impacto Real | Corrección |
|---|---|---|---|---|---|
| 1 | 🔴 CRÍTICO | AuthAppController | JWT claim `'type'` no coincide con verificación `'tipo'` en middleware | **Todos** los tokens de la app eran rechazados. Nadie podía usar la app. | Cambiado `'type'` → `'tipo'` en generateToken() |
| 2 | 🔴 CRÍTICO | Response + AuthAppController | `validationError()` no existía como método | Error fatal 500 al intentar login sin credenciales | Añadido método como alias de unprocessable() |
| 3 | 🔴 CRÍTICO | AuthAppController | `getAttribute()` no existe en clase Request | Error fatal 500 en endpoint /perfil | Reemplazado por `$_REQUEST['auth_user']['sub']` |
| 4 | 🔴 CRÍTICO | AsistenciaAppController | Usaba `$asignacion['id']` (tabla incorrecta) en vez de `$asistencia['id']` | Marcaciones vinculadas a cabecera INCORRECTA (otro trabajador) | Corregido a `$asistencia['id']` |
| 5 | 🟠 ALTO | Response | `error()` no aceptaba parámetro `$data` para contexto | Imposible enviar distancia_metros y radio_sede en errores GPS | Añadido 3er parámetro opcional `$data` |
| 6 | 🟡 MEDIO | Router | dispatch() creaba nuevo Request (perdía body) | Datos del POST/PUT se perdían al despachar | dispatch() ahora recibe Request de index.php |
| 7 | 🟡 MEDIO | AuthWebController | JWT expiración hardcodeada 86400 | JWT_EXPIRATION del .env no tenía efecto para tokens web | Lee de $_ENV como AuthAppController |
| 8 | 🟡 MEDIO | SedeWebController | `$e->getMessage()` exponía SQL internals | Nombres de tablas, columnas y constraints visibles al atacante | error_log() interno + mensaje genérico al cliente |
| 9 | 🟡 MEDIO | HorarioWebController | Sin backticks en SET dinámico del UPDATE | Fallo con columnas que son palabras reservadas SQL | Añadidos backticks: `` `columna` = :valor `` |
| 10 | 🟡 MEDIO | UsuarioAppController | Sin transacción en asignarHorario() | Trabajador sin asignación si INSERT falla tras UPDATE | beginTransaction/commit/rollBack |
| 11 | 🟡 MEDIO | JustificacionWebController | Sin transacción en aprobar()/rechazar() | Justificación aprobada pero asistencias sin actualizar | beginTransaction/commit/rollBack |
| 12 | 🟠 ALTO | routes/api.php | Ruta stats apuntaba a método inexistente 'index' | Endpoint /stats devolvía error 500 al cargar dashboard | Corregido a método 'dashboard' |
| — | 🔧 LINT | BaseModel | Faltaba método público db() | SedeWebController no podía acceder a PDO del modelo | Añadido método público db() |
