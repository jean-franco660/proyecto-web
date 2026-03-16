# Documento Técnico Exhaustivo — API de Asistencia PHP MVC
## Parte 2: Base de Datos, Rutas y Capa de Modelos (Código Corregido)

---

## 12. `database/setup.sql` — Esquema Completo de la Base de Datos

### 12.1 Resumen de tablas (11 tablas principales)

| # | Tabla | Propósito | Relaciones |
|---|---|---|---|
| 1 | `usuarios_web` | Admins y supervisores del panel web | — |
| 2 | `sedes` | Centros de trabajo con coordenadas GPS | — |
| 3 | `usuario_web_sede` | Relación N:M entre supervisor y sede | FK → usuarios_web, sedes |
| 4 | `horarios_sede` | Turnos de trabajo definidos por sede | FK → sedes |
| 5 | `usuarios_app` | Trabajadores de la aplicación móvil | — |
| 6 | `usuario_app_sede` | Asignación de trabajador a sede + horario | FK → usuarios_app, sedes, horarios_sede |
| 7 | `horario_cambio_logs` | Auditoría de cambios de horario | FK → usuarios_app, sedes |
| 8 | `asistencias` | Cabecera diaria: 1 registro por trabajador/sede/día | FK → usuarios_app, sedes, horarios_sede |
| 9 | `asistencias_diarias` | Marcaciones individuales con datos GPS | FK → asistencias |
| 10 | `justificaciones` | Solicitudes de justificación de ausencias | FK → usuarios_app, sedes |
| 11 | `feriados` | Días no laborables (nacionales y por sede) | FK → sedes |

### 12.2 Tabla `usuarios_web` — Administradores y Supervisores

```sql
CREATE TABLE IF NOT EXISTS usuarios_web (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- ↑ INT UNSIGNED: entero sin signo (0 a 4,294,967,295). AUTO_INCREMENT: MySQL lo genera.
    --   PRIMARY KEY: índice único + NOT NULL. Es el identificador universal de cada admin.

    nombre          VARCHAR(100)    NOT NULL,
    email           VARCHAR(150)    NOT NULL UNIQUE,
    -- ↑ UNIQUE: MySQL crea un índice único automáticamente. Si se intenta INSERT
    --   con un email duplicado → PDOException con SQLSTATE 23000.
    --   VARCHAR(150) porque emails pueden ser largos: nombre.apellido@subdominio.empresa.com

    password        VARCHAR(255)    NOT NULL,
    -- ↑ 255 caracteres para almacenar hash bcrypt. Un hash bcrypt mide ~60 chars,
    --   pero 255 da margen para futuros algoritmos más largos (Argon2id usa ~100).

    rol             ENUM('super_admin', 'administrador', 'supervisor') NOT NULL DEFAULT 'supervisor',
    -- ↑ ENUM: MySQL restringe los valores posibles a nivel de BD.
    --   Si la API intenta INSERT con rol='hacker' → error SQL (defensa en profundidad).
    --   super_admin: acceso total. administrador: gestión sin usuarios web. supervisor: solo sus sedes.

    estado          ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    -- ↑ Soft delete: en vez de DELETE, se pone INACTIVO. El registro persiste para auditoría.

    ultimo_login    DATETIME        NULL,
    -- ↑ NULL si nunca ha hecho login. Se actualiza en AuthWebController::login().
    --   Útil para auditoría: detectar cuentas abandonadas o accesos sospechosos.

    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    -- ↑ TIMESTAMP: MySQL los maneja automáticamente.
    --   created_at: se llena una vez al INSERT.
    --   updated_at: se actualiza AUTOMÁTICAMENTE con cada UPDATE (ON UPDATE CURRENT_TIMESTAMP).
    --   No requiere que la API los gestione manualmente.
);
```

### 12.3 Tabla `sedes` — Centros de Trabajo con Geofencing

```sql
CREATE TABLE IF NOT EXISTS sedes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_sede     VARCHAR(20)     NOT NULL UNIQUE,
    -- ↑ Código legible (ej: 'SEDE-001'). UNIQUE previene duplicados.

    nombre          VARCHAR(150)    NOT NULL,
    rubro           VARCHAR(100)    NULL,
    distrito        VARCHAR(100)    NULL,
    direccion       TEXT            NULL,

    latitud         DECIMAL(10,8)   NOT NULL,
    longitud        DECIMAL(11,8)   NOT NULL,
    -- ↑ DECIMAL(10,8): 10 dígitos totales, 8 decimales.
    --   Rango latitud: -90.00000000 a 90.00000000 (2 enteros + 8 decimales = ok)
    --   DECIMAL(11,8) para longitud: -180.00000000 a 180.00000000 (3 enteros + 8 decimales)
    --   8 decimales dan precisión de ~1.1mm en el ecuador. Suficiente para geofencing.
    --   ¿Por qué DECIMAL y no FLOAT? FLOAT tiene errores de redondeo por representación
    --   binaria. DECIMAL almacena el número EXACTO como string de dígitos.

    radio           INT UNSIGNED    NOT NULL DEFAULT 100,
    -- ↑ Radio del GEOFENCE en metros. El trabajador debe estar DENTRO de este radio
    --   para poder registrar asistencia. Default 100m = radio razonable para un edificio.

    activa          TINYINT(1)      NOT NULL DEFAULT 1,
    -- ↑ TINYINT(1) = booleano en MySQL (0 o 1). 1 = activa, 0 = desactivada.

    deleted_at      DATETIME        NULL,
    -- ↑ Soft delete: cuando se "elimina" una sede, se pone la fecha aquí.
    --   Las queries filtran con WHERE deleted_at IS NULL para excluir eliminadas.

    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 12.4 Tabla `usuario_app_sede` — Asignación Trabajador ↔ Sede ↔ Horario

```sql
CREATE TABLE IF NOT EXISTS usuario_app_sede (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_app_id      INT UNSIGNED    NOT NULL,
    sede_id             INT UNSIGNED    NOT NULL,
    horario_sede_id     INT UNSIGNED    NULL,
    -- ↑ NULL si el trabajador aún no tiene horario asignado en esa sede.
    --   HorarioWebController::autoAsignarHorario() lo llena automáticamente
    --   al crear un nuevo horario en la sede.

    cargo               VARCHAR(100)    NULL,
    estado              ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    -- ↑ Cuando se reasigna a otra sede, la asignación actual se pone INACTIVO
    --   y se crea una nueva con ACTIVO. FIX Bug #10 envuelve esto en transacción.

    FOREIGN KEY (usuario_app_id)  REFERENCES usuarios_app(id) ON DELETE CASCADE,
    FOREIGN KEY (sede_id)         REFERENCES sedes(id)         ON DELETE CASCADE,
    FOREIGN KEY (horario_sede_id) REFERENCES horarios_sede(id) ON DELETE SET NULL
    -- ↑ ON DELETE CASCADE: si se elimina el trabajador → sus asignaciones se borran.
    --   ON DELETE SET NULL: si se elimina el horario → la asignación queda sin horario (NULL).
    --   SET NULL es más seguro que CASCADE aquí: no queremos perder la asignación a la sede
    --   solo porque se eliminó un horario.
);
```

### 12.5 Tabla `asistencias` — Cabecera del Día

```sql
CREATE TABLE IF NOT EXISTS asistencias (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_app_id      INT UNSIGNED    NOT NULL,
    sede_id             INT UNSIGNED    NOT NULL,
    horario_sede_id     INT UNSIGNED    NULL,
    fecha               DATE            NOT NULL,
    -- ↑ Una sola fecha por cabecera. Formato: '2025-03-10'.

    hora_entrada        TIME            NULL,
    hora_salida         TIME            NULL,
    -- ↑ Se llenan cuando el trabajador marca ENTRADA y SALIDA respectivamente.
    --   NULL si aún no ha marcado. Formato: '08:15:30'.

    minutos_tarde       INT             NULL DEFAULT 0,
    -- ↑ Calculado automáticamente en AsistenciaAppController::store():
    --   minutos_tarde = (hora_marcada - hora_entrada_horario) / 60
    --   Solo se calcula si la marca de ENTRADA supera la tolerancia.

    estado_diario       ENUM('FALTA','PRESENTE','TARDANZA','JUSTIFICADO','PENDIENTE')
                        NOT NULL DEFAULT 'FALTA',
    -- ↑ Estado consolidado del día. La API lo actualiza según las marcaciones:
    --   FALTA: no marcó (default al crear la cabecera del día)
    --   PRESENTE: marcó dentro del horario y tolerancia
    --   TARDANZA: marcó fuera de tolerancia pero dentro del día
    --   JUSTIFICADO: admin aprobó una justificación que cubre este día
    --   PENDIENTE: la cabecera acaba de crearse, esperando ENTRADA

    observacion         TEXT            NULL,
    revisado_por        INT UNSIGNED    NULL,

    UNIQUE KEY uq_asistencia_dia (usuario_app_id, sede_id, fecha, horario_sede_id),
    -- ↑ RESTRICCIÓN CRÍTICA: solo puede existir UNA cabecera por combinación única de
    --   (trabajador, sede, fecha, horario). Si AsistenciaAppController intenta crear un
    --   duplicado → PDOException SQLSTATE 23000 → la API busca el existente en vez de crear.
    --   Esto garantiza idempotencia a nivel de BD.

    INDEX idx_fecha         (fecha),
    -- ↑ Índice para filtrar por fecha rápidamente. Usado por StatsController::dashboard()
    --   que consulta todas las asistencias del día: WHERE fecha = '2025-03-10'.

    INDEX idx_usuario_fecha (usuario_app_id, fecha),
    -- ↑ Índice COMPUESTO para consultas de historial del trabajador:
    --   WHERE usuario_app_id = 42 AND fecha BETWEEN '2025-01-01' AND '2025-03-10'
    --   El índice compuesto es más eficiente que dos índices separados porque
    --   MySQL puede buscar por ambas columnas en un solo B-tree scan.

    INDEX idx_sede_fecha    (sede_id, fecha),
    -- ↑ Para consultas del dashboard por sede: WHERE sede_id = 1 AND fecha = '2025-03-10'.

    FOREIGN KEY (usuario_app_id)  REFERENCES usuarios_app(id)  ON DELETE CASCADE,
    FOREIGN KEY (sede_id)         REFERENCES sedes(id)          ON DELETE CASCADE,
    FOREIGN KEY (horario_sede_id) REFERENCES horarios_sede(id)  ON DELETE SET NULL,
    FOREIGN KEY (revisado_por)    REFERENCES usuarios_web(id)   ON DELETE SET NULL
);
```

### 12.6 Tabla `asistencias_diarias` — Marcaciones GPS Individuales

```sql
CREATE TABLE IF NOT EXISTS asistencias_diarias (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asistencia_id       INT UNSIGNED    NOT NULL,
    -- ↑ FK a la cabecera del día. Cada marcación pertenece a una cabecera.

    tipo                ENUM('ENTRADA','SALIDA') NOT NULL,
    -- ↑ Solo dos valores posibles. No hay 'ALMUERZO' ni otros.

    marcada_en          DATETIME        NOT NULL,
    -- ↑ Timestamp de CUÁNDO se hizo la marcación. Viene del CLIENTE (la app móvil),
    --   no del servidor. Esto es intencional para soportar modo offline: la app guarda
    --   la hora real de marcación y la envía cuando se sincroniza.

    latitud             DECIMAL(10,8)   NOT NULL,
    longitud            DECIMAL(11,8)   NOT NULL,
    -- ↑ Coordenadas GPS del dispositivo al momento de la marcación.
    --   Se usan para validar que el trabajador estaba dentro del radio de la sede.

    dentro_rango        TINYINT(1)      NOT NULL DEFAULT 1,
    -- ↑ Siempre 1 porque la API RECHAZA marcaciones fuera de rango (Response::error 403).
    --   Si está fuera del radio → no se inserta. Este campo es redundante pero histórico.

    distancia_metros    INT             NULL,
    -- ↑ Distancia real calculada (fórmula Haversine) entre el dispositivo y la sede.
    --   Útil para auditoría: "estaba a 45 metros de la sede cuando marcó".

    estado_marcacion    ENUM('VALIDA','OBSERVADA') NOT NULL DEFAULT 'VALIDA',
    -- ↑ VALIDA: la marcación cumple todas las reglas (hora y ubicación).
    --   OBSERVADA: la marcación se hizo FUERA DEL HORARIO PERMITIDO.
    --   Ejemplo: el horario indica entrada a las 8:00 y el trabajador marcó a las 6:00.
    --   La marcación se registra pero queda como OBSERVADA para revisión del admin.

    motivo_observacion  VARCHAR(255)    NULL,
    -- ↑ Razón de la observación. Actualmente solo: 'FUERA_DE_HORARIO'.
    --   NULL si estado_marcacion = 'VALIDA'.

    estado_revision     ENUM('PENDIENTE','APROBADA','MANTENER_OBSERVADA')
                        NOT NULL DEFAULT 'APROBADA',
    -- ↑ Flujo de revisión de marcaciones observadas:
    --   1. Trabajador marca fuera de horario → estado_revision = 'PENDIENTE'
    --   2. Admin revisa en el panel web → cambia a 'APROBADA' o 'MANTENER_OBSERVADA'
    --   Marcaciones VALIDAS van directamente a 'APROBADA' (no requieren revisión).

    offline_uuid        VARCHAR(100)    NULL UNIQUE,
    -- ↑ UUID generado por la APP MÓVIL en modo offline.
    --   UNIQUE: si la app reintenta enviar la misma marcación (sync retry),
    --   el INSERT falla por duplicado → la API verifica primero si ya existe.
    --   Esto implementa el patrón IDEMPOTENT CONSUMER (At-Most-Once):
    --   la misma marcación nunca se registra dos veces.

    registrado_en       ENUM('APP_ONLINE','APP_OFFLINE') NOT NULL DEFAULT 'APP_ONLINE',
    -- ↑ Indica si la marcación se envió con conexión (real-time) o se sincronizó después.
    --   Útil para auditoría y detección de anomalías temporales.

    INDEX idx_offline_uuid (offline_uuid),
    -- ↑ Índice para búsqueda rápida de UUID existente en la verificación de idempotencia.
    --   Sin este índice, cada sync con UUID haría un full table scan.

    FOREIGN KEY (asistencia_id) REFERENCES asistencias(id) ON DELETE CASCADE
    -- ↑ Si se elimina la cabecera del día → todas sus marcaciones se eliminan.
);
```

### 12.7 Tabla `justificaciones` — Solicitudes de Justificación

```sql
CREATE TABLE IF NOT EXISTS justificaciones (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_app_id      INT UNSIGNED    NOT NULL,
    sede_id             INT UNSIGNED    NOT NULL,

    tipo  ENUM('ENFERMEDAD','PERMISO_PERSONAL','LICENCIA','COMISION_SERVICIO',
               'CAPACITACION','DUELO','MATERNIDAD','PATERNIDAD','OLVIDO_MARCACION','OTRO')
          NOT NULL,
    -- ↑ Tipología cerrada de justificaciones. ENUM restringe a nivel de BD.
    --   'OLVIDO_MARCACION' es el más frecuente: el trabajador olvidó marcar asistencia.

    fecha_inicio        DATE            NOT NULL,
    fecha_fin           DATE            NOT NULL,
    dias                INT             GENERATED ALWAYS AS (DATEDIFF(fecha_fin, fecha_inicio) + 1) STORED,
    -- ↑ COLUMNA GENERADA: MySQL calcula automáticamente la cantidad de días.
    --   DATEDIFF('2025-03-15', '2025-03-10') = 5 → + 1 = 6 días (inclusive).
    --   STORED: se almacena físicamente en disco (vs VIRTUAL que se calcula al leer).
    --   STORED permite indexarla si fuera necesario para consultas.
    --   No requiere que la API la calcule ni que el cliente la envíe.

    motivo              TEXT            NOT NULL,
    estado              ENUM('PENDIENTE','APROBADO','RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',

    usuario_web_id      INT UNSIGNED    NULL,
    -- ↑ ID del admin/supervisor que REVISÓ la justificación. NULL mientras está PENDIENTE.

    observaciones       TEXT            NULL,
    -- ↑ Motivo del rechazo (escrito por el admin). Obligatorio al rechazar.

    fecha_revision      DATETIME        NULL,
    -- ↑ Cuándo se revisó. NULL mientras está PENDIENTE.

    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 12.8 Tabla `feriados` — Días No Laborables

```sql
CREATE TABLE IF NOT EXISTS feriados (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    tipo            ENUM('nacional', 'sede') NOT NULL,
    -- ↑ 'nacional': aplica a TODAS las sedes del país.
    --   'sede': aplica solo a una sede específica (feriado local/regional).

    sede_id         INT UNSIGNED  NULL,
    -- ↑ NULL para feriados nacionales (aplican a todas las sedes).
    --   INT para feriados de sede específica.

    descripcion     VARCHAR(200)  NOT NULL,
    dia             INT           NOT NULL,
    mes             INT           NOT NULL,
    -- ↑ día y mes se guardan por separado para buscar feriados anuales recurrentes.
    --   El modelo Feriado::esFeriado() extrae día y mes de la fecha consultada
    --   y busca por (dia, mes), no por fecha completa.

    fecha           DATE          NOT NULL,
    -- ↑ Fecha completa para el año actual. Se puede usar para feriados no recurrentes.

    activo          TINYINT(1)    NOT NULL DEFAULT 1,
    -- ↑ Soft delete: 0 = eliminado. FeriadoController::destroy() pone activo=0.
    --   Solo admins pueden eliminar feriados nacionales.

    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 12.9 Datos Seed de Prueba

El archivo `setup.sql` incluye datos de prueba para facilitar el desarrollo y testing:

- **3 usuarios web**: super_admin (admin@empresa.com), administrador, supervisor
- **3 sedes** con coordenadas GPS reales de Lima, Perú:
  - Sede Central: lat -12.0464, lng -77.0428 (Plaza de Armas)
  - Sede Norte: lat -12.0200, lng -77.0500
  - Sede Sur: lat -12.1100, lng -77.0200
- **5 horarios de turno**: mañana (6:00-14:00), diurno (8:00-17:00), tarde (14:00-22:00), etc.
- **6 trabajadores** con asignaciones activas a sedes
- **13 feriados nacionales** de Perú 2025
- **Contraseña de todos los usuarios**: `"password"` (almacenado como hash bcrypt en la BD)

---

## 13. `routes/api.php` — Registro Centralizado de Rutas (Corregido — FIX Bug #12)

```php
<?php
// ═══════════════════════════════════════════════════════════════════════
// Este archivo se incluye desde index.php con: require BASE_PATH . '/routes/api.php';
// La variable $router (instancia de App\Core\Router) es accesible aquí
// porque PHP comparte el scope de variables con archivos incluidos por require.
// ═══════════════════════════════════════════════════════════════════════

use App\Controllers\App\AuthAppController;
use App\Controllers\App\AsistenciaAppController;
use App\Controllers\App\SedeAppController;
use App\Controllers\App\JustificacionAppController;
use App\Controllers\App\HorarioAppController;
// ↑ use importa los nombres de clase para referirlos como AuthAppController::class
//   en vez de App\Controllers\App\AuthAppController::class (más legible).
//   ::class devuelve el FQCN (Fully Qualified Class Name) como string:
//   AuthAppController::class → 'App\Controllers\App\AuthAppController'

use App\Controllers\Web\AuthWebController;
use App\Controllers\Web\UsuarioAppController;
use App\Controllers\Web\UsuarioWebController;
use App\Controllers\Web\SedeWebController;
use App\Controllers\Web\HorarioWebController;
use App\Controllers\Web\AsistenciaWebController;
use App\Controllers\Web\JustificacionWebController;
use App\Controllers\Web\FeriadoController;
use App\Controllers\Web\StatsController;

// ══════════════════════════════════════════════════════════════════════
// APP MÓVIL — rutas para trabajadores
// ══════════════════════════════════════════════════════════════════════

// ── RUTA PÚBLICA (sin JWT) ─────────────────────────────────────────
$router->post('/v1/app/login', [AuthAppController::class, 'login']);
// ↑ POST porque envía credenciales en el body (no en URL por seguridad).
//   $router->post() → add('POST', '/v1/app/login', handler, auth=null)
//   auth=null → NO se ejecuta middleware → cualquiera puede acceder.

// ── RUTAS PROTEGIDAS JWT tipo 'app' ────────────────────────────────
$router->authAppGet('/v1/app/perfil',  [AuthAppController::class, 'perfil']);
$router->authAppPost('/v1/app/logout', [AuthAppController::class, 'logout']);
// ↑ authAppGet() → add('GET', ..., auth='app') → ejecuta AuthAppMiddleware antes del controller.

$router->authAppGet('/v1/app/sedes', [SedeAppController::class, 'index']);
// ↑ Lista las sedes asignadas al trabajador autenticado.

// Asistencia — IMPORTANTE: rutas ESTÁTICAS antes que DINÁMICAS
$router->authAppPost('/v1/app/asistencia',               [AsistenciaAppController::class, 'store']);
$router->authAppPost('/v1/app/asistencias/sincronizar',   [AsistenciaAppController::class, 'syncMovil']);
$router->authAppGet('/v1/app/estado-dia',                 [AsistenciaAppController::class, 'estadoDia']);
$router->authAppGet('/v1/app/asistencia/{usuarioId}',     [AsistenciaAppController::class, 'historial']);
// ↑ {usuarioId} es un parámetro DINÁMICO. El Router lo convierte en regex: (?P<usuarioId>[^/]+)
//   Si la ruta dinámica estuviera ANTES de /estado-dia, la URL /v1/app/asistencia/estado-dia
//   matchearía la dinámica con usuarioId='estado-dia' → resultado incorrecto.
//   Por eso las rutas ESTÁTICAS (/estado-dia) van PRIMERO.

// Horarios de la app móvil
$router->authAppGet('/v1/app/horarios-sede',         [HorarioAppController::class, 'misHorarios']);
$router->authAppGet('/v1/app/mis-horarios',          [HorarioAppController::class, 'getMisHorarios']);
$router->authAppPost('/v1/app/actualizar-horarios',  [HorarioAppController::class, 'actualizarHorarios']);

// Justificaciones del trabajador
$router->authAppGet('/v1/app/justificaciones',          [JustificacionAppController::class, 'index']);
$router->authAppPost('/v1/app/justificaciones',         [JustificacionAppController::class, 'store']);
$router->authAppGet('/v1/app/justificaciones/{id}',     [JustificacionAppController::class, 'show']);
$router->authAppDelete('/v1/app/justificaciones/{id}',  [JustificacionAppController::class, 'destroy']);

// ══════════════════════════════════════════════════════════════════════
// PANEL WEB — rutas para admins y supervisores
// ══════════════════════════════════════════════════════════════════════

$router->post('/v1/web/login', [AuthWebController::class, 'login']);
// ↑ Ruta pública (sin JWT). El login genera el token JWT.

$router->authWebPost('/v1/web/logout', [AuthWebController::class, 'logout']);
$router->authWebGet('/v1/web/me',      [AuthWebController::class, 'me']);

// Gestión de trabajadores (CRUD completo + acciones especiales)
$router->authWebGet('/v1/web/usuarios-app',               [UsuarioAppController::class, 'index']);
$router->authWebPost('/v1/web/usuarios-app',              [UsuarioAppController::class, 'store']);
$router->authWebGet('/v1/web/usuarios-app/{id}',          [UsuarioAppController::class, 'show']);
$router->authWebPut('/v1/web/usuarios-app/{id}',          [UsuarioAppController::class, 'update']);
$router->authWebDelete('/v1/web/usuarios-app/{id}',       [UsuarioAppController::class, 'destroy']);
$router->authWebPatch('/v1/web/usuarios-app/{id}/estado', [UsuarioAppController::class, 'cambiarEstado']);
$router->authWebPatch('/v1/web/usuarios-app/{id}/horario',[UsuarioAppController::class, 'asignarHorario']);
// ↑ PATCH para acciones PARCIALES sobre el recurso. Diferencia con PUT:
//   PUT reemplaza el recurso completo (update). PATCH modifica UN aspecto (estado, horario).
//   Sigue la semántica REST estricta.

// Gestión de usuarios web (solo super_admin)
$router->authWebGet('/v1/web/usuarios-web',               [UsuarioWebController::class, 'index']);
$router->authWebPost('/v1/web/usuarios-web',              [UsuarioWebController::class, 'store']);
$router->authWebGet('/v1/web/usuarios-web/{id}',          [UsuarioWebController::class, 'show']);
$router->authWebPut('/v1/web/usuarios-web/{id}',          [UsuarioWebController::class, 'update']);
$router->authWebPatch('/v1/web/usuarios-web/{id}/estado', [UsuarioWebController::class, 'cambiarEstado']);

// Sedes (estáticas ANTES que dinámicas)
$router->authWebGet('/v1/web/sedes',             [SedeWebController::class, 'index']);
$router->authWebGet('/v1/web/sedes/mis-sedes',   [SedeWebController::class, 'misSedes']);
$router->authWebPost('/v1/web/sedes',            [SedeWebController::class, 'store']);
$router->authWebGet('/v1/web/sedes/{id}',        [SedeWebController::class, 'show']);
$router->authWebPut('/v1/web/sedes/{id}',        [SedeWebController::class, 'update']);
$router->authWebDelete('/v1/web/sedes/{id}',     [SedeWebController::class, 'destroy']);
// ↑ /mis-sedes está ANTES de /{id} por la misma razón: evitar que el Router
//   interprete "mis-sedes" como un {id} dinámico.

// Horarios
$router->authWebGet('/v1/web/horarios',          [HorarioWebController::class, 'index']);
$router->authWebPost('/v1/web/horarios',         [HorarioWebController::class, 'store']);
$router->authWebPut('/v1/web/horarios/{id}',     [HorarioWebController::class, 'update']);
$router->authWebDelete('/v1/web/horarios/{id}',  [HorarioWebController::class, 'destroy']);

// Feriados
$router->authWebGet('/v1/web/feriados',          [FeriadoController::class, 'index']);
$router->authWebPost('/v1/web/feriados',         [FeriadoController::class, 'store']);
$router->authWebDelete('/v1/web/feriados/{id}',  [FeriadoController::class, 'destroy']);

// Asistencias web (consulta y revisión)
$router->authWebGet('/v1/web/asistencias',               [AsistenciaWebController::class, 'index']);
$router->authWebPatch('/v1/web/asistencias/{id}/revision',[AsistenciaWebController::class, 'updateRevision']);

// Justificaciones web (aprobación y rechazo)
$router->authWebGet('/v1/web/justificaciones',               [JustificacionWebController::class, 'index']);
$router->authWebGet('/v1/web/justificaciones/{id}',          [JustificacionWebController::class, 'show']);
$router->authWebPost('/v1/web/justificaciones/{id}/aprobar', [JustificacionWebController::class, 'aprobar']);
$router->authWebPost('/v1/web/justificaciones/{id}/rechazar',[JustificacionWebController::class, 'rechazar']);
$router->authWebDelete('/v1/web/justificaciones/{id}',       [JustificacionWebController::class, 'destroy']);

// Dashboard — FIX Bug #12:
$router->authWebGet('/v1/web/stats', [StatsController::class, 'dashboard']);
// ↑ FIX Bug #12: ANTES apuntaba a 'index' que NO EXISTE en StatsController.
//   StatsController solo tiene el método dashboard(). Esto causaba que el endpoint
//   devolviera error 500 o un "Method not found" cuando el panel web intentaba
//   cargar las estadísticas del dashboard.
```

---

## 14. `app/Models/BaseModel.php` — Clase Abstracta CRUD (Corregido — FIX Lint)

```php
<?php
namespace App\Models;

use App\Core\Database;
use PDO;

abstract class BaseModel
// ↑ 'abstract' significa que NO se puede instanciar directamente (new BaseModel() → error).
//   Solo se usa como clase PADRE que hereda a UsuarioApp, Sede, etc.
//   Cada hijo define su propia $table y hereda todos los métodos CRUD.
{
    protected PDO $db;
    // ↑ protected: accesible por la clase y sus hijos, NO desde afuera.
    //   Los modelos hijos pueden hacer: $this->db->prepare(...)

    protected string $table;
    // ↑ Cada hijo debe definir: protected string $table = 'usuarios_app';
    //   Los métodos CRUD genéricos usan $this->table para saber qué tabla consultar.

    protected string $primaryKey = 'id';
    // ↑ Por defecto la PK es 'id'. Se puede sobreescribir en el modelo hijo
    //   si la tabla tiene PK diferente (ej: 'uuid' o 'codigo').

    public function __construct()
    {
        $this->db = Database::getInstance();
        // ↑ Obtiene la conexión PDO del Singleton.
        //   Todos los modelos comparten la MISMA conexión PDO.
    }

    /** Devuelve TODOS los registros de la tabla, opcionalmente ordenados. */
    public function all(string $orderBy = ''): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        // ↑ Backticks alrededor del nombre de tabla previenen conflictos con
        //   palabras reservadas de MySQL (ej: si la tabla se llamara 'order').
        if ($orderBy) $sql .= " ORDER BY {$orderBy}";
        // ↑ ⚠️ NOTA: $orderBy viene del código, NO del usuario.
        //   Se llama como: $model->all('nombre ASC'). No hay riesgo de SQL injection
        //   porque el valor lo define el developer, no el request.
        return $this->db->query($sql)->fetchAll();
        // ↑ query() ejecuta la SQL directamente (sin prepared statement).
        //   Es seguro aquí porque no hay input del usuario en la query.
        //   fetchAll() devuelve todos los registros como array de arrays.
    }

    /** Busca UN registro por primary key. Retorna null si no existe. */
    public function find(int|string $id): ?array
    // ↑ int|string (union type, PHP 8.0+): el ID puede ser numérico o string.
    //   ?array (nullable): retorna array si encuentra, null si no.
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1"
        );
        // ↑ prepare() crea un prepared statement con placeholder '?'.
        //   MySQL recibe la query y el valor POR SEPARADO → seguro contra SQL injection.
        //   LIMIT 1: optimización — deja de buscar después del primer match (PK es único).
        $stmt->execute([$id]);
        // ↑ Envía el valor del parámetro. [$id] es un array posicional: ? → $id.
        return $stmt->fetch() ?: null;
        // ↑ fetch() retorna un array asociativo o false si no hay resultado.
        //   ?: null → convierte false en null (más semántico: null = no existe).
    }

    /** Busca UN registro por columna específica. */
    public function findOneBy(string $column, mixed $value): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` = ? LIMIT 1"
        );
        // ↑ Backticks alrededor de $column porque es un nombre de columna dinámico.
        //   Sin backticks, una columna llamada 'order' o 'key' causaría error SQL.
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }

    /** Inserta un registro y retorna el ID auto_increment. */
    public function create(array $data): int|string
    {
        $columns      = implode('`, `', array_keys($data));
        // ↑ Ejemplo: ['nombre' => 'Juan', 'email' => 'j@m.c']
        //   array_keys → ['nombre', 'email']
        //   implode → 'nombre`, `email'

        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        // ↑ Genera tantos '?' como campos: '?, ?' (para 2 campos).
        //   array_fill(0, 2, '?') → ['?', '?'] → implode → '?, ?'

        $stmt = $this->db->prepare(
            "INSERT INTO `{$this->table}` (`{$columns}`) VALUES ({$placeholders})"
        );
        // ↑ Resultado: "INSERT INTO `usuarios_app` (`nombre`, `email`) VALUES (?, ?)"
        $stmt->execute(array_values($data));
        // ↑ array_values extrae solo los VALORES: ['Juan', 'j@m.c']
        //   Cada valor se bindea al '?' correspondiente por posición.
        return $this->db->lastInsertId();
        // ↑ lastInsertId() retorna el ID auto_increment del último INSERT.
    }

    /** Actualiza un registro por ID. Retorna true si se ejecutó correctamente. */
    public function update(int|string $id, array $data): bool
    {
        $sets = implode(', ', array_map(fn($col) => "`{$col}` = ?", array_keys($data)));
        // ↑ Genera la cláusula SET dinámica:
        //   Input: ['nombre' => 'Juan', 'estado' => 'ACTIVO']
        //   array_keys → ['nombre', 'estado']
        //   array_map → ['`nombre` = ?', '`estado` = ?']
        //   implode → '`nombre` = ?, `estado` = ?'
        //   Backticks protegen contra palabras reservadas SQL.

        $stmt = $this->db->prepare(
            "UPDATE `{$this->table}` SET {$sets} WHERE `{$this->primaryKey}` = ?"
        );
        $values   = array_values($data);       // ['Juan', 'ACTIVO']
        $values[] = $id;                         // ['Juan', 'ACTIVO', 42] ← el último ? es el WHERE
        return $stmt->execute($values);
    }

    /** Elimina un registro por ID (DELETE físico, no soft delete). */
    public function delete(int|string $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?"
        );
        return $stmt->execute([$id]);
    }

    /** Ejecuta una query SELECT custom y retorna todos los registros. */
    protected function query(string $sql, array $bindings = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    /** Ejecuta una query DML custom (INSERT, UPDATE, DELETE). */
    protected function execute(string $sql, array $bindings = []): bool
    {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($bindings);
    }

    /**
     * Expone la instancia PDO para controllers que necesitan queries custom.
     * FIX Lint: SedeWebController llamaba $this->model->db() pero este método
     * no existía. Se añadió como público para permitir el acceso a $this->db
     * que es protected y no accesible desde afuera.
     */
    public function db(): \PDO
    {
        return $this->db;
    }
}
```

---

## 15-21. Modelos del Dominio

Cada modelo hereda de `BaseModel` y define `$table`. Algunos añaden queries especializadas.

### 15. `UsuarioApp.php` — Trabajador de App Móvil

```php
class UsuarioApp extends BaseModel
{
    protected string $table = 'usuarios_app';
    // ↑ Todos los métodos heredados (find, create, update, delete, all)
    //   operarán sobre la tabla 'usuarios_app' automáticamente.

    /** Busca trabajador por código de empleado (usado en login). */
    public function findByCodigo(string $codigo): ?array
    {
        return $this->findOneBy('codigo_empleado', $codigo);
        // ↑ Reutiliza el método genérico de BaseModel.
        //   SQL generada: SELECT * FROM usuarios_app WHERE codigo_empleado = ? LIMIT 1
    }

    /** Busca un trabajador con su asignación actual (sede + horario). */
    public function findConAsignacion(int $id): ?array
    {
        return $this->query("
            SELECT u.*, uas.cargo, uas.sede_id, uas.horario_sede_id,
                   s.nombre AS sede_nombre, hs.nombre_turno,
                   hs.hora_entrada, hs.hora_salida
            FROM usuarios_app u
            LEFT JOIN usuario_app_sede uas ON uas.usuario_app_id = u.id AND uas.estado = 'ACTIVO'
            LEFT JOIN sedes s              ON s.id = uas.sede_id
            LEFT JOIN horarios_sede hs     ON hs.id = uas.horario_sede_id
            WHERE u.id = ?
        ", [$id])[0] ?? null;
        // ↑ LEFT JOIN: el trabajador aparece aunque NO tenga asignación activa.
        //   Con INNER JOIN, un trabajador sin sede no aparecería.
        //   AND uas.estado = 'ACTIVO' está en el ON (no en WHERE) para que el LEFT JOIN
        //   funcione correctamente: solo junta con asignaciones activas, pero no excluye
        //   al trabajador si no tiene ninguna.
        //   [0] ?? null: toma el primer resultado o null si la query no retornó registros.
    }

    /** Verifica si un código de empleado ya existe (para validación de unicidad). */
    public function codigoExists(string $codigo, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as cnt FROM {$this->table} WHERE codigo_empleado = ?";
        $params = [$codigo];
        if ($excludeId) {
            $sql .= " AND id != ?";     // Excluye el registro actual (para updates)
            $params[] = $excludeId;
        }
        return $this->query($sql, $params)[0]['cnt'] > 0;
    }
}
```

### 16. `UsuarioWeb.php` — Admin/Supervisor del Panel Web

```php
class UsuarioWeb extends BaseModel
{
    protected string $table = 'usuarios_web';

    public function findByEmail(string $email): ?array
    {
        return $this->findOneBy('email', $email);
        // SQL: SELECT * FROM usuarios_web WHERE email = ? LIMIT 1
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        // Misma lógica que UsuarioApp::codigoExists() pero para email.
        $sql = "SELECT COUNT(*) as cnt FROM {$this->table} WHERE email = ?";
        $params = [$email];
        if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
        return $this->query($sql, $params)[0]['cnt'] > 0;
    }
}
```

### 17. `Asistencia.php` — Cabecera Diaria de Asistencia

```php
class Asistencia extends BaseModel
{
    protected string $table = 'asistencias';

    /** Busca la cabecera del día para un trabajador en una sede específica. */
    public function delDia(int $usuarioId, int $sedeId, string $fecha): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM asistencias
            WHERE usuario_app_id = ? AND sede_id = ? AND fecha = ?
        ");
        // ↑ Usa el índice idx_usuario_fecha para búsqueda eficiente.
        $stmt->execute([$usuarioId, $sedeId, $fecha]);
        return $stmt->fetch() ?: null;
    }
}
```

### 18. `AsistenciaDiaria.php` — Marcaciones Individuales GPS

```php
class AsistenciaDiaria extends BaseModel
{
    protected string $table = 'asistencias_diarias';

    /** Obtiene todas las marcaciones de una cabecera, ordenadas cronológicamente. */
    public function porAsistencia(int $asistenciaId): array
    {
        return $this->query(
            "SELECT * FROM asistencias_diarias WHERE asistencia_id = ? ORDER BY marcada_en ASC",
            [$asistenciaId]
        );
        // ↑ ASC = orden CRONOLÓGICO: primero ENTRADA, luego SALIDA.
    }

    /** Marcaciones observadas pendientes de revisión para una sede. */
    public function observadasPendientes(int $sedeId): array
    {
        return $this->query("
            SELECT ad.*, a.fecha, u.nombres, u.codigo_empleado
            FROM asistencias_diarias ad
            INNER JOIN asistencias a  ON a.id = ad.asistencia_id
            INNER JOIN usuarios_app u ON u.id = a.usuario_app_id
            WHERE a.sede_id = ?
              AND ad.estado_marcacion = 'OBSERVADA'
              AND ad.estado_revision = 'PENDIENTE'
            ORDER BY ad.marcada_en DESC
        ", [$sedeId]);
        // ↑ INNER JOIN: solo marcaciones que tengan asistencia y usuario válidos.
        //   Filtra por estado_marcacion='OBSERVADA' (marcadas fuera de horario)
        //   y estado_revision='PENDIENTE' (aún no revisadas por admin).
    }
}
```

### 19. `Feriado.php` — Días No Laborables

```php
class Feriado extends BaseModel
{
    protected string $table = 'feriados';

    /** Lista feriados activos aplicables a una sede (nacionales + de esa sede). */
    public function activosParaSede(int $sedeId): array
    {
        return $this->query(
            "SELECT * FROM feriados WHERE activo = 1 AND (tipo = 'nacional' OR sede_id = ?)
             ORDER BY mes, dia",
            [$sedeId]
        );
        // ↑ tipo='nacional' → aplica a TODAS las sedes (sede_id es NULL).
        //   sede_id = ? → aplica solo a esta sede específica.
        //   OR combina ambos tipos. Ordenado por mes y día para presentación lógica.
    }

    /** Verifica si una fecha específica es feriado para una sede. */
    public function esFeriado(string $fecha, int $sedeId): bool
    {
        $dia = (int) date('j', strtotime($fecha));  // Día del mes sin cero: 10
        $mes = (int) date('n', strtotime($fecha));  // Mes sin cero: 3
        // ↑ Se busca por día+mes (no por fecha completa) para que los feriados
        //   sean ANUALES RECURRENTES: Año Nuevo (1-enero) aplica cada año.
        $result = $this->query(
            "SELECT COUNT(*) as cnt FROM feriados
             WHERE activo = 1 AND dia = ? AND mes = ? AND (tipo = 'nacional' OR sede_id = ?)",
            [$dia, $mes, $sedeId]
        );
        return $result[0]['cnt'] > 0;
    }
}
```

### 20. `HorarioSede.php` — Turnos de Trabajo por Sede

```php
class HorarioSede extends BaseModel
{
    protected string $table = 'horarios_sede';

    /** Lista horarios activos de una sede, ordenados por hora de entrada. */
    public function porSede(int $sedeId): array
    {
        return $this->query(
            "SELECT * FROM horarios_sede WHERE sede_id = ? AND activo = 1 ORDER BY hora_entrada",
            [$sedeId]
        );
    }
}
```

### 21. `Justificacion.php` — Solicitudes de Justificación

```php
class Justificacion extends BaseModel
{
    protected string $table = 'justificaciones';

    public function porUsuario(int $usuarioId): array
    {
        return $this->query(
            "SELECT * FROM justificaciones WHERE usuario_app_id = ? ORDER BY created_at DESC",
            [$usuarioId]
        );
        // ↑ DESC: las más recientes primero.
    }

    public function pendientesDeSede(int $sedeId): array
    {
        return $this->query(
            "SELECT j.*, ua.codigo_empleado, ua.nombres, ua.apellido_paterno
             FROM justificaciones j
             LEFT JOIN usuarios_app ua ON j.usuario_app_id = ua.id
             WHERE j.sede_id = ? AND j.estado = 'PENDIENTE'
             ORDER BY j.created_at ASC",
            [$sedeId]
        );
        // ↑ ASC: las más antiguas primero (FIFO: atender las que llevan más tiempo esperando).
    }
}
```

### 22. `Sede.php` — Centros de Trabajo con GPS

```php
class Sede extends BaseModel
{
    protected string $table = 'sedes';

    public function activas(): array
    {
        return $this->query(
            "SELECT * FROM sedes WHERE activa = 1 ORDER BY nombre"
        );
    }

    /** Busca sedes cercanas a una coordenada GPS usando fórmula Haversine. */
    public function cercanas(float $lat, float $lng, int $radio = 500): array
    {
        return $this->query("
            SELECT *, (
                6371000 * acos(
                    cos(radians(?)) * cos(radians(latitud))
                    * cos(radians(longitud) - radians(?))
                    + sin(radians(?)) * sin(radians(latitud))
                )
            ) AS distancia
            FROM sedes
            WHERE activa = 1
            HAVING distancia <= ?
            ORDER BY distancia
        ", [$lat, $lng, $lat, $radio]);
        // ↑ FÓRMULA HAVERSINE ejecutada EN MySQL:
        //   6371000 = radio de la Tierra en METROS.
        //   Calcula distancia del gran círculo entre dos puntos en la esfera terrestre.
        //   HAVING (no WHERE) porque 'distancia' es un ALIAS calculado:
        //   MySQL no permite usar alias en WHERE (no existe aún al evaluar WHERE).
        //   HAVING se evalúa DESPUÉS de calcular la columna → puede usar el alias.
    }
}
```
