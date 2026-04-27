# SKILL — Sistema de Asistencia Web

> Documento de referencia para el asistente de IA.
> Actualizar cuando cambien rutas, modelos, convenciones o flujos de negocio.
> **Versión:** 2.0 — Revisada y ampliada con instrucciones al modelo y roadmap de mejoras.

---

## 🤖 Instrucciones para el Asistente de IA

> Estas reglas son **obligatorias**. Aplícalas en todo código generado para este proyecto.

### Reglas Generales
- **NUNCA** uses Laravel, Symfony ni ningún framework externo. Este proyecto usa MVC artesanal.
- **NUNCA** uses `echo` directo en controladores. Usa siempre `Response::json(...)`.
- **NUNCA** escribas SQL crudo en controladores. Toda consulta va en el Model correspondiente.
- **NUNCA** uses Options API en Vue. Siempre `<script setup>` con Composition API.
- Antes de generar código, identifica si corresponde al contexto **App** (móvil) o **Web** (panel).

### Reglas de Backend (PHP)
- Controladores Web → extienden `BaseWebController` (en `App\Controllers\Web\`)
- Controladores App → extienden `BaseAppController` (en `App\Controllers\App\`)
- Formato de respuesta exitosa: `{ "success": true, "data": { ... } }`
- Formato de respuesta de error: `{ "success": false, "error": "mensaje legible" }`
- Siempre usar PDO con consultas preparadas (nunca interpolación directa de variables en SQL).
- Las rutas **estáticas** deben declararse **ANTES** que las dinámicas con `{id}` en `routes/api.php`.
- Rutas protegidas con JWT de tipo 'web' → `$router->authWebGet(...)` / `authWebPost(...)` etc.
- Rutas protegidas con JWT de tipo 'app' → `$router->authAppGet(...)` / `authAppPost(...)` etc.
- Al aplicar soft-delete, la columna `deleted_at` es `NULL` cuando el registro está activo.
- Passwords siempre con `password_hash($plain, PASSWORD_BCRYPT)` y verificar con `password_verify()`.

### Reglas de Frontend (Vue 3)
- Siempre `<script setup>`. Importar explícitamente: `import { ref, computed, onMounted } from 'vue'`.
- Los formularios y modales van integrados dentro de la `View` correspondiente (no componentes separados).
- Clases Tailwind: paleta `primary-*` para acentos, `slate-*` para fondos y neutros.
- Las llamadas HTTP se hacen con el cliente en `src/api/axios.js` (ya incluye interceptor Bearer).
- El estado de autenticación se lee desde el store Pinia en `src/store/auth.js`.
- Para verificar si es admin usar el getter `authStore.isAdmin`.

### Reglas de Base de Datos
- Respetar los ENUMs definidos exactamente (case-sensitive): ver sección "ENUMs Clave".
- Al insertar en `tokens_sesion`, el campo `tipo_usuario` debe ser `'APP'` o `'WEB'` (mayúsculas).
- La tabla `asistencias` es la cabecera diaria; `asistencias_diarias` guarda cada marcación individual.

### Correcciones de Bugs Pendientes
Al tocar los archivos afectados, aplicar estas correcciones:

**Bug 1 — `DashboardLayout.vue`:**
```vue
<!-- INCORRECTO (actual) -->
<script setup>
const sidebarOpen = ref(false) // ref no está importado
</script>

<!-- CORRECTO -->
<script setup>
import { ref } from 'vue'
const sidebarOpen = ref(false)
</script>
```

**Bug 2 — `StatsController.php`:**
El método se llama `dashboard`, no `index`. La ruta ya lo llama correctamente.
No renombrar el método; si se agrega un `index()`, que esté vacío o redirija a `dashboard()`.

**Bug 3 — `store/auth.js`:**
El store maneja dos estructuras de respuesta del login. La solución canónica es normalizar
en el backend para que siempre devuelva `{ success: true, data: { token, usuario } }`.
Mientras tanto, el store sigue manejando ambas variantes con optional chaining.

---

## Descripción General

Sistema de gestión de asistencia laboral con dos clientes:

1. **App móvil** (repo separado): trabajadores marcan entrada/salida con validación GPS.
2. **Panel web** (Vue 3): administradores y supervisores gestionan trabajadores, sedes, horarios, asistencias y justificaciones.

Entorno de desarrollo: **XAMPP** (Apache + PHP + MySQL local).

---

## Stack Tecnológico

### Backend (`Asistencia-Backend-php/`)

| Aspecto | Detalle |
|---|---|
| Lenguaje | PHP ≥ 8.0 |
| Framework | MVC **artesanal** (sin Laravel/Symfony) |
| Autenticación | JWT dual — `firebase/php-jwt ^7.0` |
| Base de datos | MySQL vía PDO (`App\Core\Database`) |
| Routing | Router custom en `App\Core\Router` |
| Punto de entrada | `public/index.php` |
| Namespacing | PSR-4: `App\` → `app/` |
| Config | `.env` (en .gitignore), `.env.example` como plantilla |

### Frontend (`asistencia-frontend/`)

| Aspecto | Detalle |
|---|---|
| Framework | Vue 3 (Composition API con `<script setup>`) |
| Build tool | Vite (verificar versión exacta en `package.json`) |
| Estilos | **Tailwind CSS 3** (paleta `primary-*` y `slate-*`) |
| Estado global | **Pinia** (`src/store/auth.js`) |
| Router | Vue Router 4 (`src/router/index.js`) |
| HTTP client | **Axios** (`src/api/axios.js`) |
| Dev server | `npm run dev` |

---

## Variables de Entorno

### Backend (`.env`)

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=asistencia_db
DB_USERNAME=root
DB_PASSWORD=
JWT_SECRET=your_super_secret_key_here
JWT_EXPIRATION=3600
JWT_REFRESH_EXPIRATION=604800
APP_ENV=development
APP_DEBUG=true
ALLOWED_ORIGINS=http://localhost:5173
RATE_LIMIT_MAX=60
RATE_LIMIT_WINDOW=60
```

### Frontend (`.env`)

```env
VITE_API_URL=http://localhost/asistencia-backend/public
```

El axios base URL lee de `VITE_API_URL`. El interceptor agrega `Authorization: Bearer <token>` automáticamente.

---

## Arquitectura del Backend

```
Asistencia-Backend-php/
├── public/index.php              ← Punto de entrada, instancia Router
├── routes/api.php                ← Todas las rutas REST
├── app/
│   ├── Core/
│   │   ├── Database.php          ← Singleton PDO
│   │   ├── Router.php            ← Router custom con métodos auth*
│   │   ├── Request.php
│   │   └── Response.php
│   ├── Middleware/
│   │   ├── AuthAppMiddleware.php  ← Valida JWT tipo 'app'
│   │   ├── AuthWebMiddleware.php  ← Valida JWT tipo 'web'
│   │   ├── CorsMiddleware.php     ← [MEJORA] Headers CORS centralizados
│   │   └── RateLimitMiddleware.php← [MEJORA] Limitación de peticiones
│   ├── Models/
│   │   ├── BaseModel.php          ← Métodos comunes: find(), findAll(), create(), update(), softDelete()
│   │   ├── UsuarioApp.php
│   │   ├── UsuarioWeb.php
│   │   ├── Sede.php
│   │   ├── HorarioSede.php
│   │   ├── Asistencia.php
│   │   ├── AsistenciaDiaria.php
│   │   ├── Justificacion.php
│   │   └── Feriado.php
│   └── Controllers/
│       ├── App/
│       │   ├── BaseAppController.php
│       │   ├── AuthAppController.php
│       │   ├── AsistenciaAppController.php
│       │   ├── SedeAppController.php
│       │   ├── HorarioAppController.php
│       │   └── JustificacionAppController.php
│       └── Web/
│           ├── BaseWebController.php
│           ├── AuthWebController.php
│           ├── UsuarioAppController.php    ← Más grande (~17KB)
│           ├── UsuarioWebController.php
│           ├── SedeWebController.php
│           ├── HorarioWebController.php
│           ├── AsistenciaWebController.php
│           ├── JustificacionWebController.php
│           ├── FeriadoController.php
│           └── StatsController.php         ← Solo método `dashboard` (no `index`)
└── database/setup.sql            ← Schema completo + seed data
```

### BaseModel — Métodos Disponibles

```php
// Disponibles en todos los modelos que extienden BaseModel:
$model->find($id)                    // Busca por PK, retorna array|null
$model->findAll($conditions, $limit) // SELECT con WHERE opcional
$model->create($data)                // INSERT, retorna lastInsertId
$model->update($id, $data)           // UPDATE por PK
$model->softDelete($id)              // UPDATE deleted_at = NOW()
$model->db                           // Instancia PDO disponible en subclases
```

### Convención de Métodos del Router

```php
$router->get(...)              // Ruta pública GET
$router->post(...)             // Ruta pública POST
$router->authAppGet(...)       // GET — requiere JWT tipo 'app'
$router->authWebGet(...)       // GET — requiere JWT tipo 'web'
// Equivalentes: authAppPost, authWebPost, authWebPut, authWebPatch, authWebDelete, etc.
```

> ⚠️ Las rutas **estáticas deben declararse ANTES que las dinámicas**.
> Ej: `/sedes/mis-sedes` debe ir antes de `/sedes/{id}`.

### Estructura del JWT

```
// Payload JWT tipo 'app' (AuthAppMiddleware)
{
  "sub": <usuario_app.id>,
  "tipo": "app",
  "iat": <timestamp>,
  "exp": <timestamp>
}

// Payload JWT tipo 'web' (AuthWebMiddleware)
{
  "sub": <usuario_web.id>,
  "rol": "administrador" | "supervisor",
  "tipo": "web",
  "iat": <timestamp>,
  "exp": <timestamp>
}
```

Los middlewares inyectan el payload decodificado en `$request->authUser` para que
los controladores lo lean sin volver a decodificar el token.

---

## Rutas API

### App Móvil (`/v1/app/`)

| Método | Endpoint | Controlador::método | Auth |
|---|---|---|---|
| POST | `/v1/app/login` | `AuthAppController::login` | Pública |
| GET | `/v1/app/perfil` | `AuthAppController::perfil` | JWT app |
| POST | `/v1/app/logout` | `AuthAppController::logout` | JWT app |
| GET | `/v1/app/sedes` | `SedeAppController::index` | JWT app |
| POST | `/v1/app/asistencia` | `AsistenciaAppController::store` | JWT app |
| POST | `/v1/app/asistencias/sincronizar` | `AsistenciaAppController::syncMovil` | JWT app |
| GET | `/v1/app/estado-dia` | `AsistenciaAppController::estadoDia` | JWT app |
| GET | `/v1/app/asistencia/{usuarioId}` | `AsistenciaAppController::historial` | JWT app |
| GET | `/v1/app/horarios` | `HorarioAppController::obtenerHorarios` | JWT app |
| GET | `/v1/app/justificaciones` | `JustificacionAppController::index` | JWT app |
| POST | `/v1/app/justificaciones` | `JustificacionAppController::store` | JWT app |
| GET | `/v1/app/justificaciones/{id}` | `JustificacionAppController::show` | JWT app |
| DELETE | `/v1/app/justificaciones/{id}` | `JustificacionAppController::destroy` | JWT app |

**Respuesta de `GET /v1/app/estado-dia`:**
```json
{
  "success": true,
  "data": {
    "tiene_entrada": true,
    "tiene_salida": false,
    "asistencia_id": 42,
    "estado_diario": "PRESENTE"
  }
}
```

### Panel Web (`/v1/web/`)

| Método | Endpoint | Controlador::método | Auth |
|---|---|---|---|
| POST | `/v1/web/login` | `AuthWebController::login` | Pública |
| POST | `/v1/web/logout` | `AuthWebController::logout` | JWT web |
| GET | `/v1/web/me` | `AuthWebController::me` | JWT web |
| GET | `/v1/web/usuarios-web` | `UsuarioWebController::index` | JWT web |
| POST | `/v1/web/usuarios-web` | `UsuarioWebController::store` | JWT web |
| GET | `/v1/web/usuarios-web/{id}` | `UsuarioWebController::show` | JWT web |
| PUT | `/v1/web/usuarios-web/{id}` | `UsuarioWebController::update` | JWT web |
| PATCH | `/v1/web/usuarios-web/{id}/estado` | `UsuarioWebController::cambiarEstado` | JWT web |
| GET | `/v1/web/usuarios-app` | `UsuarioAppController::index` | JWT web |
| POST | `/v1/web/usuarios-app` | `UsuarioAppController::store` | JWT web |
| GET | `/v1/web/usuarios-app/import/stats` ⚑ | `UsuarioAppController::importStats` | JWT web |
| GET | `/v1/web/usuarios-app/{id}` | `UsuarioAppController::show` | JWT web |
| PUT | `/v1/web/usuarios-app/{id}` | `UsuarioAppController::update` | JWT web |
| DELETE | `/v1/web/usuarios-app/{id}` | `UsuarioAppController::destroy` | JWT web |
| PATCH | `/v1/web/usuarios-app/{id}/estado` | `UsuarioAppController::cambiarEstado` | JWT web |
| PATCH | `/v1/web/usuarios-app/{id}/horario` | `UsuarioAppController::asignarHorario` | JWT web |
| POST | `/v1/web/usuario-app-institucion/{id}/inactivar` | `UsuarioAppController::inactivarAsignacion` | JWT web |
| GET | `/v1/web/sedes` | `SedeWebController::index` | JWT web |
| GET | `/v1/web/sedes/mis-sedes` ⚑ | `SedeWebController::misSedes` | JWT web |
| GET | `/v1/web/sedes/import/stats` ⚑ | `SedeWebController::importStats` | JWT web |
| POST | `/v1/web/sedes` | `SedeWebController::store` | JWT web |
| GET | `/v1/web/sedes/{id}` | `SedeWebController::show` | JWT web |
| PUT | `/v1/web/sedes/{id}` | `SedeWebController::update` | JWT web |
| DELETE | `/v1/web/sedes/{id}` | `SedeWebController::destroy` | JWT web |
| GET | `/v1/web/horarios` | `HorarioWebController::index` | JWT web |
| POST | `/v1/web/horarios` | `HorarioWebController::store` | JWT web |
| PUT | `/v1/web/horarios/{id}` | `HorarioWebController::update` | JWT web |
| DELETE | `/v1/web/horarios/{id}` | `HorarioWebController::destroy` | JWT web |
| GET | `/v1/web/feriados` | `FeriadoController::index` | JWT web |
| POST | `/v1/web/feriados` | `FeriadoController::store` | JWT web |
| PUT | `/v1/web/feriados/{id}` | `FeriadoController::update` | JWT web |
| DELETE | `/v1/web/feriados/{id}` | `FeriadoController::destroy` | JWT web |
| GET | `/v1/web/asistencias/semana` ⚑ | `AsistenciaWebController::resumenSemanal` | JWT web |
| GET | `/v1/web/asistencias/exportar` ⚑ | `AsistenciaWebController::exportar` | JWT web |
| GET | `/v1/web/asistencias/mes-grafico` ⚑ | `AsistenciaWebController::mesGrafico` | JWT web |
| GET | `/v1/web/asistencias` | `AsistenciaWebController::index` | JWT web |
| GET | `/v1/web/asistencias/{id}` | `AsistenciaWebController::show` | JWT web |
| PUT | `/v1/web/asistencias/{id}/review` | `AsistenciaWebController::updateReview` | JWT web |
| GET | `/v1/web/justificaciones` | `JustificacionWebController::index` | JWT web |
| GET | `/v1/web/justificaciones/{id}` | `JustificacionWebController::show` | JWT web |
| POST | `/v1/web/justificaciones/{id}/aprobar` | `JustificacionWebController::aprobar` | JWT web |
| POST | `/v1/web/justificaciones/{id}/rechazar` | `JustificacionWebController::rechazar` | JWT web |
| DELETE | `/v1/web/justificaciones/{id}` | `JustificacionWebController::destroy` | JWT web |
| GET | `/v1/web/stats` | `StatsController::dashboard` | JWT web |

> ⚑ Rutas estáticas: declaradas antes de sus equivalentes dinámicas con `{id}`.

---

## Esquema de Base de Datos (`asistencia_db`)

### Tablas

| Tabla | Propósito |
|---|---|
| `usuarios_web` | Admins y supervisores del panel |
| `sedes` | Sucursales con coordenadas GPS y radio de marcación |
| `usuario_web_sede` | Relación Supervisor ↔ Sedes (M:N) |
| `horarios_sede` | Turnos de trabajo por sede (días en JSON) |
| `usuarios_app` | Trabajadores (usuarios de la app móvil) |
| `usuario_app_sede` | Trabajador ↔ Sede ↔ Turno asignado |
| `asistencias` | Cabecera diaria de asistencia por trabajador |
| `asistencias_diarias` | Marcaciones ENTRADA/SALIDA con GPS y foto |
| `justificaciones` | Solicitudes de justificación de faltas |
| `feriados` | Feriados nacionales, locales o de empresa |
| `password_resets_web` | Reset por email para supervisores |
| `password_resets_app` | Reset con aprobación del admin para empleados |
| `tokens_sesion` | JWTs activos (tipo APP o WEB) |

### ENUMs Clave

```
usuarios_web.rol             → 'administrador' | 'supervisor'
usuarios_web.estado          → 'ACTIVO' | 'INACTIVO'
usuarios_app.estado          → 'ACTIVO' | 'INACTIVO' | 'BLOQUEADO'
asistencias.estado_diario    → 'FALTA' | 'PRESENTE' | 'TARDANZA' | 'JUSTIFICADO' | 'PENDIENTE'
asistencias_diarias.tipo     → 'ENTRADA' | 'SALIDA'
asistencias_diarias.estado_marcacion  → 'VALIDA' | 'OBSERVADA'
asistencias_diarias.estado_revision   → 'PENDIENTE' | 'APROBADA' | 'MANTENER_OBSERVADA'
justificaciones.tipo         → 'ENFERMEDAD' | 'PERMISO_PERSONAL' | 'LICENCIA' |
                               'COMISION_SERVICIO' | 'CAPACITACION' | 'DUELO' |
                               'MATERNIDAD' | 'PATERNIDAD' | 'OLVIDO_MARCACION' | 'OTRO'
justificaciones.estado       → 'PENDIENTE' | 'APROBADO' | 'RECHAZADO'
feriados.tipo                → 'NACIONAL' | 'LOCAL' | 'EMPRESA'
tokens_sesion.tipo_usuario   → 'APP' | 'WEB'
```

### Seed Data

| Tipo | Credenciales | Estado |
|---|---|---|
| Admin | `admin@empresa.com` / `password` | ACTIVO |
| Supervisor | `supervisor@empresa.com` / `password` | INACTIVO |
| Empleados | `EMP-001` a `EMP-006` / `password` | ACTIVO |

---

## Arquitectura del Frontend

```
asistencia-frontend/src/
├── App.vue
├── main.js
├── style.css
├── api/axios.js               ← Axios + interceptor Bearer + manejo 401
├── store/auth.js              ← Pinia: user, token, login(), logout()
├── router/index.js            ← Guards de auth y rol
├── layouts/
│   └── DashboardLayout.vue    ← Sidebar + header (Bug 1 pendiente)
└── views/
    ├── auth/LoginView.vue
    ├── dashboard/DashboardView.vue
    ├── admin/
    │   ├── UsuariosAppView.vue    ← ~16KB, la más compleja
    │   ├── UsuariosWebView.vue
    │   ├── SupervisoresView.vue
    │   ├── SedesView.vue
    │   ├── HorariosView.vue
    │   └── FeriadosView.vue
    └── operaciones/
        ├── AsistenciasView.vue
        └── JustificacionesView.vue
```

### Rutas y Roles (Vue Router)

| Ruta | Nombre | Roles permitidos |
|---|---|---|
| `/login` | `login` | Solo guests |
| `/` | `dashboard` | Cualquier autenticado |
| `/sedes` | `sedes` | administrador, supervisor |
| `/horarios` | `horarios` | administrador, supervisor |
| `/feriados` | `feriados` | administrador, supervisor |
| `/usuarios-web` | `usuarios-web` | **administrador** |
| `/supervisores` | `supervisores` | **administrador** |
| `/usuarios-app` | `usuarios-app` | administrador, supervisor |
| `/asistencias` | `asistencias` | administrador, supervisor |
| `/justificaciones` | `justificaciones` | administrador, supervisor |

### Auth Store (Pinia)

```js
// Estado persistido en localStorage
{ user, token, loading, error }

// Getters
isAuthenticated  →  !!token
isAdmin          →  user?.rol === 'administrador'

// Normalización de respuesta del login (Bug 3 — en proceso de unificación)
// El store acepta ambas variantes hasta que el backend se unifique:
// Variante 1: { data: { token, usuario } }
// Variante 2: { token, usuario }
```

---

## Convenciones y Patrones

### PHP (Backend)
- Todos los controladores Web heredan de `BaseWebController`.
- Todos los controladores App heredan de `BaseAppController`.
- Respuesta exitosa: `{ "success": true, "data": { ... } }`
- Respuesta de error: `{ "success": false, "error": "mensaje" }`
- Soft-delete: `deleted_at IS NULL` → registro activo. Nunca DELETE físico salvo casos específicos.
- Validar inputs siempre en el controlador antes de pasarlos al modelo.
- Usar `intval()` / `htmlspecialchars()` / `filter_var()` para sanitizar entradas.

### Vue (Frontend)
- Siempre `<script setup>` con Composition API.
- Importar explícitamente `ref`, `computed`, `onMounted`, etc. desde `'vue'`.
- Los formularios/modales están integrados dentro de cada `View`, no son componentes separados.
- Clases Tailwind con paleta `primary-*` (acento) y `slate-*` (neutro/fondo).
- El interceptor de Axios en `axios.js` debe manejar el error 401 haciendo logout automático.

---

## Flujos de Negocio

### Marcación de Asistencia (App)
1. Empleado hace **login** con `codigo_empleado` + `password`
2. Consulta `GET /v1/app/estado-dia` → sabe si ya marcó hoy
3. Selecciona sede → `POST /v1/app/asistencia` con coordenadas GPS
4. Si está **fuera del radio** → API retorna `403`, no se guarda registro
5. Si hubo **modo offline** → `POST /v1/app/asistencias/sincronizar`

### Gestión de Justificaciones (Web)
1. Empleado crea justificación desde la app
2. Panel web lista pendientes en `/justificaciones`
3. Admin/Supervisor aprueba o rechaza con motivo opcional

### Reset de Password — Empleados
1. Empleado solicita reset con `DNI` + `codigo_empleado`
2. Se crea solicitud `PENDIENTE` en `password_resets_app`
3. Admin aprueba desde el panel → genera password temporal
4. Empleado ingresa con la temporal → la app fuerza el cambio (`debe_cambiar_password = 1`)

### Roles Web
- **Administrador**: acceso total, ve todas las sedes, puede crear/activar supervisores.
- **Supervisor**: acceso restringido a sedes en `usuario_web_sede`, no gestiona usuarios web.

---

## 🗺️ Roadmap de Mejoras

> Prioridades definidas para el desarrollo activo del proyecto.
> Ordenadas de mayor a menor impacto / urgencia.

---

### 🔴 PRIORIDAD 1 — Seguridad

#### 1.1 CORS centralizado
**Problema actual:** Los headers CORS probablemente están seteados ad-hoc o ausentes.
**Solución:** Crear `App\Middleware\CorsMiddleware.php` y ejecutarlo en `public/index.php` antes del router.

```php
// app/Middleware/CorsMiddleware.php
class CorsMiddleware {
    public static function handle(): void {
        $allowed = getenv('ALLOWED_ORIGINS') ?: 'http://localhost:5173';
        header("Access-Control-Allow-Origin: $allowed");
        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Max-Age: 86400");
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
```

#### 1.2 Rate Limiting en login
**Problema actual:** El endpoint `/v1/app/login` y `/v1/web/login` no tienen límite de intentos → vulnerables a fuerza bruta.
**Solución:** Tabla `login_attempts` o APCu para contar intentos por IP.

```sql
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    endpoint VARCHAR(50) NOT NULL,
    intentos INT DEFAULT 1,
    ultimo_intento DATETIME DEFAULT CURRENT_TIMESTAMP,
    bloqueado_hasta DATETIME NULL,
    INDEX idx_ip_endpoint (ip, endpoint)
);
```

Lógica: máximo 5 intentos en 5 minutos → bloquear IP por 15 minutos → retornar HTTP 429.

#### 1.3 Refresh Token
**Problema actual:** El JWT expira en 1 hora (JWT_EXPIRATION=3600) y no hay forma de renovarlo sin hacer login.
**Solución:** Emitir un `refresh_token` de larga duración (7 días) al hacer login. Agregar endpoint:

```
POST /v1/web/refresh   → AuthWebController::refresh
POST /v1/app/refresh   → AuthAppController::refresh
```

El refresh token se guarda en `tokens_sesion` con tipo `REFRESH_WEB` / `REFRESH_APP`.

#### 1.4 Validación de inputs centralizada
**Problema actual:** Cada controlador valida (o no valida) a su manera.
**Solución:** Crear `App\Core\Validator.php` con métodos reutilizables.

```php
class Validator {
    public static function required(array $data, array $fields): array // retorna errores
    public static function email(string $value): bool
    public static function minLength(string $value, int $min): bool
    public static function isPositiveInt(mixed $value): bool
    public static function isEnum(string $value, array $allowed): bool
}
```

#### 1.5 Headers de seguridad HTTP
Agregar en `public/index.php` o en `.htaccess`:

```php
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");
// No usar HSTS en desarrollo local
```

---

### 🟠 PRIORIDAD 2 — Calidad de Código

#### 2.1 Refactorizar `UsuarioAppController.php` (~17KB)
Es el controlador más grande. Dividir responsabilidades:
- `UsuarioAppController` → CRUD básico
- `UsuarioAppAsignacionController` → lógica de asignación sede/horario
- `UsuarioAppImportController` → importación masiva (si existe)

#### 2.2 Unificar respuesta del login (Bug 3)
El backend debe responder siempre con la misma estructura:
```json
{ "success": true, "data": { "token": "...", "usuario": { ... } } }
```
Eliminar la doble variante del store auth.js una vez unificado.

#### 2.3 Manejo de errores global en Frontend
El interceptor de respuesta en `axios.js` debe:
- En 401 → llamar `authStore.logout()` y redirigir a `/login`
- En 403 → mostrar toast "Sin permisos"
- En 422 → mostrar errores de validación del servidor
- En 500 → mostrar toast genérico de error

```js
// src/api/axios.js — interceptor de respuesta
axiosInstance.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      authStore.logout()
      router.push('/login')
    }
    return Promise.reject(error)
  }
)
```

#### 2.4 Composables reutilizables en Vue
Las Views grandes repiten lógica de paginación, búsqueda y modales. Extraer:
- `src/composables/usePagination.js`
- `src/composables/useSearch.js`
- `src/composables/useModal.js`
- `src/composables/useToast.js`

#### 2.5 Constantes centralizadas
Crear `src/constants/enums.js` para no repetir strings de ENUMs en el frontend:

```js
export const ESTADO_DIARIO = {
  FALTA: 'FALTA', PRESENTE: 'PRESENTE', TARDANZA: 'TARDANZA',
  JUSTIFICADO: 'JUSTIFICADO', PENDIENTE: 'PENDIENTE'
}
export const ROL_WEB = { ADMIN: 'administrador', SUPERVISOR: 'supervisor' }
// etc.
```

---

### 🟡 PRIORIDAD 3 — Frontend UX/UI

#### 3.1 Componente Toast/Notificación global
Actualmente cada View maneja sus propios mensajes. Implementar un sistema global:
- `src/components/ToastContainer.vue` montado en `App.vue`
- Store Pinia `src/store/toast.js` con `addToast(message, type)`
- Tipos: `success`, `error`, `warning`, `info`

#### 3.2 Estados de carga y error en tablas
Las tablas de datos deben mostrar:
- **Loading:** skeleton o spinner mientras carga
- **Empty state:** mensaje amigable cuando no hay datos
- **Error state:** mensaje con botón "Reintentar"

#### 3.3 Confirmación antes de acciones destructivas
Antes de eliminar un registro o cambiar estado a INACTIVO, mostrar modal de confirmación.
Usar un composable `useConfirm()` reutilizable.

#### 3.4 Paginación en tablas grandes
`UsuariosAppView`, `AsistenciasView` y `JustificacionesView` probablemente devuelven
todos los registros. Implementar paginación server-side:
- Backend: recibir `?page=1&per_page=20`
- Frontend: componente `Pagination.vue` reutilizable

#### 3.5 Indicador visual de rol en el sidebar
El sidebar debe mostrar visualmente si el usuario es Administrador o Supervisor,
y ocultar/deshabilitar los ítems no permitidos según el rol (ya controlado en el router,
pero también debe reflejarse en la UI).

---

### 🟢 PRIORIDAD 4 — Testing y Documentación API

#### 4.1 Documentación API con OpenAPI/Swagger
Crear `docs/openapi.yaml` documentando todos los endpoints.
Herramienta sugerida: Swagger UI servido como página estática o usando Stoplight.

Estructura base:
```yaml
openapi: 3.0.3
info:
  title: Asistencia API
  version: 1.0.0
servers:
  - url: http://localhost/asistencia-backend/public/v1
paths:
  /app/login:
    post:
      summary: Login de empleado (app móvil)
      # ...
```

#### 4.2 Colección Postman / Bruno
Crear `docs/asistencia.postman_collection.json` con todos los endpoints
organizados por carpetas (App Auth, App Asistencia, Web Auth, Web Usuarios, etc.)
con variables de entorno para `base_url` y `token`.

#### 4.3 Tests básicos de endpoints críticos
Para un proyecto de prácticas, priorizar tests de integración sobre unitarios.
Usar PHPUnit para los endpoints más críticos:
- Login exitoso / credenciales incorrectas
- Marcación dentro/fuera de radio GPS
- Aprobación de justificación sin permisos

#### 4.4 README.md completo
El README debe cubrir:
- Prerequisitos (XAMPP, Node, PHP ≥ 8.0)
- Pasos de instalación backend y frontend
- Configuración del `.env`
- Cómo correr el seed SQL
- Credenciales de prueba
- Descripción de los roles y sus permisos

---

## Bugs Conocidos

| # | Archivo | Descripción | Solución |
|---|---|---|---|
| 1 | `DashboardLayout.vue` | Usa `ref(false)` sin importar `ref` | Agregar `import { ref } from 'vue'` |
| 2 | `StatsController.php` | El método es `dashboard`, no `index` | No renombrar; la ruta ya es correcta |
| 3 | `store/auth.js` | Maneja dos estructuras de respuesta del login | Unificar respuesta en el backend (ver mejora 2.2) |
