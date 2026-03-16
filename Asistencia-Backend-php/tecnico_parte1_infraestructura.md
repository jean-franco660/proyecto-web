# Documento Técnico Exhaustivo — API de Asistencia PHP MVC
## Parte 1: Infraestructura, Core y Middleware (Código Corregido)

> **Nota:** Este documento refleja el código **después** de las correcciones de los 12 bugs identificados. Las líneas corregidas llevan la anotación `// FIX Bug #N`.

---

## 1. Estructura del Proyecto

```
Asistencia-Backend-php/
├── public/                         ← Directorio web público (DocumentRoot de Apache)
│   ├── .htaccess                   ← Reescritura de URLs (Apache mod_rewrite)
│   └── index.php                   ← Front Controller (punto de entrada único)
├── app/                            ← Lógica de la aplicación (fuera del DocumentRoot = no accesible por HTTP)
│   ├── Core/                       ← Clases fundacionales del framework
│   │   ├── Database.php            ← Singleton PDO (conexión a MySQL)
│   │   ├── Router.php              ← Enrutador con regex y middleware
│   │   ├── Request.php             ← Value Object de la petición HTTP
│   │   └── Response.php            ← Emisor de respuestas JSON estandarizadas
│   ├── Middleware/                  ← Middleware de autenticación JWT
│   │   ├── AuthAppMiddleware.php   ← Verifica JWT tipo='app' (trabajadores)
│   │   └── AuthWebMiddleware.php   ← Verifica JWT tipo='web' (admins/supervisores)
│   ├── Controllers/
│   │   ├── App/                    ← Endpoints de la app móvil (trabajadores)
│   │   │   ├── AuthAppController.php
│   │   │   ├── AsistenciaAppController.php
│   │   │   ├── SedeAppController.php
│   │   │   ├── JustificacionAppController.php
│   │   │   └── HorarioAppController.php
│   │   └── Web/                    ← Endpoints del panel web (admins/supervisores)
│   │       ├── AuthWebController.php
│   │       ├── UsuarioAppController.php
│   │       ├── UsuarioWebController.php
│   │       ├── SedeWebController.php
│   │       ├── HorarioWebController.php
│   │       ├── AsistenciaWebController.php
│   │       ├── JustificacionWebController.php
│   │       ├── FeriadoController.php
│   │       └── StatsController.php
│   └── Models/                     ← Capa de acceso a datos (Active Record simplificado)
│       ├── BaseModel.php           ← CRUD genérico con PDO
│       ├── UsuarioApp.php          ← Modelo de tabla usuarios_app
│       ├── UsuarioWeb.php          ← Modelo de tabla usuarios_web
│       ├── Asistencia.php          ← Modelo de tabla asistencias
│       ├── AsistenciaDiaria.php    ← Modelo de tabla asistencias_diarias
│       ├── Feriado.php             ← Modelo de tabla feriados
│       ├── HorarioSede.php         ← Modelo de tabla horarios_sede
│       ├── Justificacion.php       ← Modelo de tabla justificaciones
│       └── Sede.php                ← Modelo de tabla sedes
├── database/
│   └── setup.sql                   ← Esquema completo de BD + datos seed de prueba
├── routes/
│   └── api.php                     ← Registro centralizado de todas las rutas
├── vendor/                         ← Dependencias instaladas por Composer (NO se edita)
├── .env                            ← Variables de entorno (credenciales, secretos)
└── composer.json                   ← Metadatos del proyecto y configuración de autoloading
```

### ¿Por qué esta estructura?

- **`public/` como DocumentRoot:** Solo este directorio es accesible por HTTP. Todo lo que está fuera (`app/`, `.env`, `database/`) queda protegido del acceso directo. Un atacante no puede visitar `http://dominio/app/Core/Database.php` para ver credenciales.
- **`app/` separado por capas:** Core (framework), Middleware (autenticación), Controllers (lógica HTTP), Models (acceso a datos). Cada capa tiene una responsabilidad única (Principio de Responsabilidad Única - SRP).
- **Controllers divididos en `App/` y `Web/`:** Dos audiencias completamente diferentes: trabajadores de la app móvil y administradores del panel web. Cada grupo tiene su middleware JWT propio.

---

## 2. `public/.htaccess` — Reescritura de URLs en Apache

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    # ↑ Activa el motor de reescritura de Apache. Sin esta directiva,
    #   las reglas de abajo no se ejecutan.

    RewriteCond %{REQUEST_FILENAME} !-f
    # ↑ Condición 1: %{REQUEST_FILENAME} es la ruta FÍSICA en el servidor.
    #   !-f significa "NO es un archivo que exista en disco".
    #   Ejemplo: Si alguien pide /favicon.ico y ese archivo existe →
    #   esta condición FALLA → Apache sirve el archivo directamente.

    RewriteCond %{REQUEST_FILENAME} !-d
    # ↑ Condición 2: !-d significa "NO es un directorio que exista".
    #   Ambas condiciones se evalúan con AND implícito.

    RewriteRule ^(.*)$ index.php [QSA,L]
    # ↑ Si AMBAS condiciones se cumplen (no es archivo real, no es directorio real):
    #   ^(.*)$ = Captura TODA la URI con regex (. = cualquier carácter, * = cero o más)
    #   index.php = Reescribe internamente a index.php (el Front Controller)
    #
    #   Flags:
    #   [QSA] = Query String Append. Si la URL original tenía ?page=1&sort=desc,
    #           esos parámetros se CONSERVAN y se pasan a index.php.
    #           Sin QSA, se perderían al reescribir.
    #   [L]   = Last rule. Detiene el procesamiento de más reglas RewriteRule.
    #           Sin L, Apache podría aplicar reglas adicionales de otros .htaccess
    #           que modifiquen la URL de nuevo.
</IfModule>
# ↑ <IfModule> es una guardia: si Apache NO tiene mod_rewrite habilitado,
#   simplemente ignora estas directivas en vez de lanzar error 500.
```

### ¿Cómo funciona en la práctica?

1. El usuario solicita `GET /v1/app/login`
2. Apache busca un archivo llamado `/v1/app/login` → **no existe** (condición !-f OK)
3. Apache busca un directorio llamado `/v1/app/login` → **no existe** (condición !-d OK)
4. Ambas condiciones cumplidas → reescribe a `index.php` internamente
5. La variable `$_SERVER['REQUEST_URI']` conserva `/v1/app/login` (la URI original)
6. `index.php` recibe la petición y el `Router` la matchea contra sus rutas registradas

---

## 3. `public/index.php` — Front Controller (Código Corregido)

El Front Controller es el **único punto de entrada** de toda la aplicación. Cada petición HTTP pasa por aquí.

```php
<?php

/**
 * Front Controller
 * Único punto de entrada a la aplicación.
 * Todas las peticiones HTTP son enrutadas aquí por .htaccess.
 */

define('BASE_PATH', dirname(__DIR__));
// ↑ dirname(__DIR__) toma el directorio del archivo actual (public/) y sube
//   un nivel. __DIR__ = 'd:/practicas/Asistencia-Backend-php/public',
//   dirname() quita '/public' → 'd:/practicas/Asistencia-Backend-php'.
//   Se define como CONSTANTE (no variable) porque:
//   1) Es inmutable: nadie puede cambiarla accidentalmente
//   2) Es global: accesible desde cualquier archivo sin pasarla como parámetro
//   3) Convención: BASE_PATH es estándar en frameworks PHP (Laravel, Symfony)

// ═══ FASE 1: AUTOLOADING ═══════════════════════════════════════════
require BASE_PATH . '/vendor/autoload.php';
// ↑ Carga el autoloader generado por Composer. Este archivo registra una función
//   spl_autoload_register() que convierte namespaces en rutas de archivo:
//   - App\Core\Database → busca en app/Core/Database.php (por PSR-4)
//   - Firebase\JWT\JWT → busca en vendor/firebase/php-jwt/src/JWT.php
//   Sin este require, cualquier `use App\Core\Response` daría "Class not found".

// ═══ FASE 2: VARIABLES DE ENTORNO ══════════════════════════════════
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    // ↑ Verifica que .env existe. En producción se pueden usar variables del
    //   sistema operativo en vez de archivo .env, así que no es obligatorio.
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    // ↑ file() lee todo el archivo y devuelve un array donde cada elemento
    //   es una línea. Las flags eliminan:
    //   FILE_IGNORE_NEW_LINES → quita \n del final de cada línea
    //   FILE_SKIP_EMPTY_LINES → ignora líneas vacías
    //   Resultado: ['DB_HOST=localhost', 'DB_PORT=3306', '# comentario', ...]

    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        // ↑ str_starts_with() es PHP 8.0+. trim() quita espacios al inicio.
        //   Si la línea es un comentario (empieza con #), la SALTA.
        //   Ejemplo: '# Este es un comentario' → continue (no procesa)

        if (str_contains($line, '=')) {
            // ↑ Solo procesa líneas que contengan '='. Protege contra líneas
            //   malformadas que podrían causar error en explode().

            [$key, $value] = explode('=', $line, 2);
            // ↑ Destructuración de array (PHP 7.1+). explode('=', ..., 2) divide
            //   la línea en MÁXIMO 2 partes. El tercer parámetro (2) es crucial:
            //   'JWT_SECRET=mi=clave=secreta' → ['JWT_SECRET', 'mi=clave=secreta']
            //   Sin el 2, sería: ['JWT_SECRET', 'mi', 'clave', 'secreta'] → error

            $_ENV[trim($key)] = trim($value);
            // ↑ Guarda en la superglobal $_ENV. trim() quita espacios.
            //   '$_ENV' es accesible desde cualquier archivo PHP.

            putenv(trim($key) . '=' . trim($value));
            // ↑ También registra en el entorno del proceso del SO.
            //   Esto permite que getenv('DB_HOST') funcione además de $_ENV['DB_HOST'].
            //   Algunas librerías (ej: monolog) usan getenv() en vez de $_ENV.
        }
    }
}

// ═══ FASE 3: CABECERAS CORS ════════════════════════════════════════
header('Content-Type: application/json; charset=UTF-8');
// ↑ Le dice al cliente que TODAS las respuestas son JSON con codificación UTF-8.
//   charset=UTF-8 garantiza que caracteres como á, é, ñ se transmitan correctamente.

header('Access-Control-Allow-Origin: *');
// ↑ CORS: permite que CUALQUIER dominio haga peticiones a esta API.
//   '*' = cualquier origen. En producción debería ser el dominio del frontend:
//   header('Access-Control-Allow-Origin: https://panel.miempresa.com');
//   ⚠️ RIESGO: Con '*', cualquier sitio web malicioso podría hacer peticiones
//   a la API desde el navegador del usuario (aunque necesitaría el JWT).

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
// ↑ Métodos HTTP permitidos. PATCH no está listado aquí (se confía en que
//   el router lo maneje), pero debería añadirse para compatibilidad completa.

header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
// ↑ Cabeceras que el cliente puede enviar:
//   Content-Type: para enviar JSON en el body (application/json)
//   Authorization: para enviar el token JWT (Bearer eyJhbG...)
//   X-Requested-With: convención de frameworks JS para identificar AJAX

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // ↑ Petición PREFLIGHT: los navegadores envían OPTIONS automáticamente
    //   ANTES de peticiones POST/PUT/DELETE con cabeceras custom.
    //   Ejemplo: el frontend envía POST /v1/app/login con Authorization header.
    //   El navegador PRIMERO envía OPTIONS /v1/app/login para preguntar:
    //   "¿Este servidor acepta POST con cabecera Authorization?"
    //   Si respondemos 204 con las cabeceras CORS → el navegador procede
    //   a enviar el POST real.
    http_response_code(204);                    // 204 = No Content (sin body)
    exit;                                        // Termina aquí, NO llega al router
}

// ═══ FASE 4: ENRUTAMIENTO ══════════════════════════════════════════
$router = new App\Core\Router();
// ↑ Crea una instancia del Router vacía. $router->routes = [] en este punto.

require BASE_PATH . '/routes/api.php';
// ↑ Este archivo llama a $router->get(), $router->authAppPost(), etc.
//   Cuando termina, $router->routes tiene TODAS las rutas registradas.
//   El require usa $router como variable accesible por el scope del archivo incluido.

$request = new App\Core\Request();
// ↑ Crea el objeto Request que ENCAPSULA toda la información de la petición HTTP:
//   - $body = JSON del body (para POST/PUT/PATCH)
//   - $query = parámetros de URL (?sede_id=1&page=2)
//   - $params = vacío (se llena en Router::dispatch con parámetros de ruta como {id})

$router->dispatch($request);
// ↑ FIX Bug #6: ahora pasa el $request creado aquí.
//   ANTES del fix: dispatch() creaba su propio new Request() internamente,
//   lo que significaba que se leía php://input DOS VECES.
//   php://input solo se puede leer UNA VEZ (stream de solo lectura).
//   Resultado: el Request del router tenía el body VACÍO → los controllers
//   no recibían datos del POST/PUT.
//   DESPUÉS del fix: dispatch() recibe y usa el Request de aquí.
```

---

## 4. `.env` — Variables de Entorno

```env
DB_HOST=localhost
# ↑ Host del servidor MySQL. 'localhost' usa socket Unix (más rápido que TCP).
#   En producción con RDS/Cloud SQL: 'mi-instancia.abc123.us-east-1.rds.amazonaws.com'

DB_PORT=3306
# ↑ Puerto estándar de MySQL. Solo cambiar si MySQL corre en puerto custom.

DB_DATABASE=asistencia_db
# ↑ Nombre de la base de datos. Debe coincidir con el CREATE DATABASE del setup.sql.

DB_USERNAME=root
# ↑ ⚠️ SOLO PARA DESARROLLO. En producción:
#   - Crear usuario dedicado: CREATE USER 'api_user'@'%' IDENTIFIED BY '...';
#   - Dar permisos mínimos: GRANT SELECT, INSERT, UPDATE, DELETE ON asistencia_db.* TO 'api_user'@'%';
#   - NUNCA dar DROP, ALTER, CREATE (principio de mínimo privilegio).

DB_PASSWORD=
# ↑ Vacío en desarrollo local. En producción: contraseña de mínimo 16 caracteres
#   con mayúsculas, minúsculas, números y símbolos. Rotarla cada 90 días.

JWT_SECRET=mi_clave_secreta_super_segura_2025
# ↑ Clave usada para FIRMAR y VERIFICAR tokens JWT con algoritmo HMAC-SHA256.
#   Si alguien obtiene esta clave, puede crear tokens falsos para cualquier usuario.
#   RECOMENDACIÓN PRODUCCIÓN: usar openssl rand -base64 64 para generar clave aleatoria.

JWT_EXPIRATION=3600
# ↑ Duración del token en SEGUNDOS. 3600 = 1 hora.
#   Después de 1 hora, el token expira y el usuario debe re-autenticarse.
#   Valores comunes: 3600 (1h app móvil), 86400 (24h panel web).
#   FIX Bug #7: AuthWebController antes ignoraba esta variable y hardcodeaba 86400.
```

---

## 5. `composer.json` — PSR-4 Autoloading y Dependencias

```json
{
    "name": "empresa/asistencia-api",
    "description": "API REST para el sistema de control de asistencia",
    "require": {
        "php": ">=8.0",
        // ↑ Requiere PHP 8.0 mínimo por estas características usadas en el código:
        //   - Union types (int|string en BaseModel::find)
        //   - mixed type hint (Request::input(): mixed)
        //   - str_contains() y str_starts_with() (nativos desde 8.0)
        //   - Named arguments (usado implícitamente)
        //   - match expressions
        //   - Constructor property promotion

        "firebase/php-jwt": "^6.0"
        // ↑ Librería de Google para crear y verificar tokens JWT.
        //   v6+ previene ataques "algorithm none": si un atacante envía un token
        //   con alg:"none", versiones antiguas lo aceptaban sin verificar firma.
        //   v6 REQUIERE que especifiques el algoritmo permitido (Key('secret', 'HS256')).
        //   ^6.0 = acepta 6.0, 6.1, 6.9, pero NO 7.0 (semver compatible).
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
            // ↑ Mapeo namespace → directorio.
            //   Cuando PHP encuentra 'new App\Core\Database()', el autoloader:
            //   1. Toma el namespace: App\Core\Database
            //   2. Reemplaza 'App\' por 'app/' (según esta config)
            //   3. Reemplaza '\' por '/' → app/Core/Database
            //   4. Añade .php → app/Core/Database.php
            //   5. Hace require de ese archivo
            //   Esto elimina la necesidad de require/include manuales.
        }
    }
}
```

---

## 6. `app/Core/Database.php` — Singleton PDO (Conexión a MySQL)

```php
<?php
namespace App\Core;
// ↑ Declara que esta clase pertenece al namespace App\Core.
//   Otros archivos la referencian como: use App\Core\Database;

use PDO;
use PDOException;
// ↑ Importa estas clases del namespace global (raíz de PHP).
//   Sin estos 'use', habría que escribir \PDO y \PDOException cada vez.

class Database
{
    private static ?PDO $instance = null;
    // ↑ Propiedad ESTÁTICA: pertenece a la CLASE, no a instancias individuales.
    //   ?PDO = tipo nullable (puede ser PDO o null).
    //   = null = inicialmente no hay conexión.
    //   private = solo accesible desde dentro de esta clase.
    //
    //   ¿Por qué static? Porque getInstance() es static. Las propiedades static
    //   persisten durante toda la ejecución del script PHP. Si se llama
    //   getInstance() 50 veces (una por cada query), solo se crea UNA conexión.

    public static function getInstance(): PDO
    // ↑ Método estático: se llama como Database::getInstance() sin crear objeto.
    //   Retorna tipo PDO (la conexión a la base de datos).
    {
        if (self::$instance === null) {
        // ↑ self:: referencia la clase actual (Database).
        //   $instance es static, así que solo hay UNA en toda la ejecución.
        //   === null verifica identidad estricta (no solo igualdad).
        //   Si ya existe una conexión → salta al return directo.

            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $_ENV['DB_HOST'],      // localhost
                    $_ENV['DB_PORT'],      // 3306
                    $_ENV['DB_DATABASE']   // asistencia_db
                );
                // ↑ DSN (Data Source Name) es la cadena de conexión estándar de PDO.
                //   Formato: driver:host=...;port=...;dbname=...;charset=...
                //   charset=utf8mb4 es CRUCIAL: utf8 de MySQL solo soporta 3 bytes
                //   (no emojis). utf8mb4 soporta 4 bytes = Unicode completo.

                self::$instance = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    // ↑ COMPORTAMIENTO ANTE ERRORES SQL:
                    //   ERRMODE_SILENT (default) = falla en silencio, $stmt->execute() retorna false
                    //   ERRMODE_WARNING = emite PHP warning pero continúa
                    //   ERRMODE_EXCEPTION = LANZA excepción PDOException → podemos hacer try/catch
                    //   Sin EXCEPTION, un INSERT fallido pasa desapercibido y el código continúa
                    //   como si todo estuviera bien. SIEMPRE usar EXCEPTION en producción.

                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // ↑ FORMATO DE RESULTADOS:
                    //   FETCH_BOTH (default) = array DOBLE: [0 => 'Juan', 'nombre' => 'Juan']
                    //                         Desperdicia memoria duplicando cada valor.
                    //   FETCH_ASSOC = array SOLO asociativo: ['nombre' => 'Juan']
                    //   FETCH_OBJ = objeto stdClass: $row->nombre
                    //   Usamos ASSOC porque los controllers acceden con $row['campo'].

                    PDO::ATTR_EMULATE_PREPARES   => false,
                    // ↑ CRÍTICO PARA SEGURIDAD — Prepared Statements:
                    //
                    //   Con true (default): PDO EMULA los prepared statements.
                    //     PHP construye la query final internamente:
                    //     "SELECT * FROM users WHERE id = '1; DROP TABLE users'"
                    //     → RIESGO de SQL injection si el escape falla.
                    //
                    //   Con false (nuestro caso): PDO usa prepared statements NATIVOS de MySQL.
                    //     PHP envía a MySQL DOS cosas SEPARADAS:
                    //     1) La query con placeholder: "SELECT * FROM users WHERE id = ?"
                    //     2) El valor: "1; DROP TABLE users"
                    //     MySQL NUNCA interpreta el valor como SQL → IMPOSIBLE inyectar.
                    //
                    //   Bonus: con false, MySQL puede reutilizar el plan de ejecución
                    //   si la misma query se ejecuta varias veces con distintos valores.
                ]);
            } catch (PDOException $e) {
                die("Error de conexión: " . $e->getMessage());
                // ↑ die() termina la ejecución y muestra el mensaje.
                //   ⚠️ MEJORA PRODUCCIÓN: no exponer el mensaje de error al cliente.
                //   Debería ser: error_log($e->getMessage()); Response::error('Error interno', 500);
            }
        }
        return self::$instance;
        // ↑ Retorna la misma instancia PDO siempre. Esto es el patrón SINGLETON:
        //   una sola conexión a MySQL compartida por todos los modelos y controllers.
    }
}
```

### ¿Por qué Singleton y no inyección de dependencias (DI)?

| Aspecto | Singleton | DI Container |
|---|---|---|
| Complejidad | Mínima: 1 clase, 1 método | Requiere Pimple, PHP-DI o similar |
| Testing | Difícil: no se puede inyectar mock PDO | Fácil: se inyecta mock en constructor |
| Rendimiento | Excelente: 0 overhead | Mínimo overhead por resolución |
| Proyectos | ≤15 controllers | >15 controllers o con testing |

Para este proyecto de ~14 controllers, Singleton es pragmático y suficiente.

---

## 7. `app/Core/Router.php` — Enrutador con Regex (CORREGIDO — FIX Bug #6)

```php
<?php
namespace App\Core;

class Router
{
    private array $routes = [];
    // ↑ Almacena TODAS las rutas registradas como array de arrays.
    //   Cada ruta tiene: method, pattern, handler, auth, params.
    //   Se llena cuando api.php llama a $router->get(), $router->authAppPost(), etc.

    // ═══ REGISTRO DE RUTAS PÚBLICAS (sin middleware JWT) ═══════════
    // Cada método es un shortcut que llama a add() con el método HTTP correspondiente.
    // Son públicos porque api.php los usa: $router->get('/v1/app/login', [...])
    public function get(string $path, array $handler): void    { $this->add('GET',    $path, $handler); }
    public function post(string $path, array $handler): void   { $this->add('POST',   $path, $handler); }
    public function put(string $path, array $handler): void    { $this->add('PUT',    $path, $handler); }
    public function patch(string $path, array $handler): void  { $this->add('PATCH',  $path, $handler); }
    public function delete(string $path, array $handler): void { $this->add('DELETE', $path, $handler); }

    // ═══ RUTAS PROTEGIDAS JWT TIPO 'app' (trabajadores móviles) ════
    // Pasan 'app' como 4to argumento a add(). El dispatcher ejecutará AuthAppMiddleware.
    public function authAppGet(string $p, array $h): void    { $this->add('GET',    $p, $h, 'app'); }
    public function authAppPost(string $p, array $h): void   { $this->add('POST',   $p, $h, 'app'); }
    public function authAppPut(string $p, array $h): void    { $this->add('PUT',    $p, $h, 'app'); }
    public function authAppPatch(string $p, array $h): void  { $this->add('PATCH',  $p, $h, 'app'); }
    public function authAppDelete(string $p, array $h): void { $this->add('DELETE', $p, $h, 'app'); }

    // ═══ RUTAS PROTEGIDAS JWT TIPO 'web' (admins/supervisores) ═════
    // Pasan 'web' como 4to argumento. El dispatcher ejecutará AuthWebMiddleware.
    public function authWebGet(string $p, array $h): void    { $this->add('GET',    $p, $h, 'web'); }
    public function authWebPost(string $p, array $h): void   { $this->add('POST',   $p, $h, 'web'); }
    public function authWebPut(string $p, array $h): void    { $this->add('PUT',    $p, $h, 'web'); }
    public function authWebPatch(string $p, array $h): void  { $this->add('PATCH',  $p, $h, 'web'); }
    public function authWebDelete(string $p, array $h): void { $this->add('DELETE', $p, $h, 'web'); }

    private function add(string $method, string $path, array $handler, ?string $auth = null): void
    // ↑ Método PRIVADO que realmente registra la ruta. Los métodos públicos son solo wrappers.
    //   $method: 'GET', 'POST', 'PUT', 'PATCH', 'DELETE'
    //   $path: '/v1/app/asistencia/{id}' (con placeholders {nombre})
    //   $handler: [AuthAppController::class, 'login'] → [nombre de clase, nombre de método]
    //   $auth: null = pública, 'app' = requiere JWT app, 'web' = requiere JWT web
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        // ↑ Convierte los placeholders {param} en grupos de captura de regex con nombre.
        //
        //   ENTRADA: '/v1/app/asistencia/{usuarioId}'
        //   REGEX:   /\{(\w+)\}/  → busca {palabraAlfanumérica}
        //   REEMPLAZO: (?P<$1>[^/]+)
        //     (?P<usuarioId> = grupo de captura NOMBRADO (named capture group)
        //     [^/]+ = uno o más caracteres que NO sean / (captura el valor del parámetro)
        //   RESULTADO: '/v1/app/asistencia/(?P<usuarioId>[^/]+)'
        //
        //   Cuando la URL /v1/app/asistencia/42 haga match:
        //   $matches['usuarioId'] = '42'

        $this->routes[] = [
            'method'  => $method,                              // 'GET', 'POST', etc.
            'pattern' => '#^' . $pattern . '$#',               // Regex completa con delimitadores
            // ↑ Se usa # como delimitador en vez del habitual / para evitar
            //   conflictos con las / de las URLs. ^ = inicio, $ = final.
            //   '#^/v1/app/login$#' solo matchea EXACTAMENTE /v1/app/login
            //   (no matchea /v1/app/login/extra ni /prefix/v1/app/login)
            'handler' => $handler,                              // [Clase::class, 'método']
            'auth'    => $auth,                                 // null | 'app' | 'web'
            'params'  => [],                                    // Se usa internamente
        ];
    }

    /**
     * Despacha la petición HTTP actual: busca la ruta que coincida,
     * ejecuta el middleware si es necesario, e invoca el controller.
     *
     * FIX Bug #6: Se añadió el parámetro Request $request para evitar que
     * dispatch() creara un segundo objeto Request, perdiendo el body.
     */
    public function dispatch(Request $request): void
    {
        // ═══ MANEJO DE PREFLIGHT CORS ══════════════════════════════
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Authorization, Content-Type');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            http_response_code(204);    // 204 = sin body, petición exitosa
            exit();                      // Termina aquí, no busca ruta
        }
        // ↑ Las peticiones OPTIONS son AUTOMÁTICAS del navegador (preflight).
        //   No debemos buscar una ruta para ellas; solo responder con headers CORS.
        //   Si no respondemos correctamente, el navegador BLOQUEA la petición real.

        $method = $_SERVER['REQUEST_METHOD'];
        // ↑ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' — viene del servidor Apache.

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        // ↑ $_SERVER['REQUEST_URI'] = '/v1/app/login?foo=bar'
        //   parse_url(..., PHP_URL_PATH) extrae SOLO la ruta: '/v1/app/login'
        //   Sin esto, el query string interferiría con el regex matching.

        $req = $request;
        // ↑ FIX Bug #6: Usamos el Request que nos INYECTÓ index.php.
        //   ANTES del fix, aquí se hacía: $req = new Request();
        //   El problema: php://input (body) solo se puede leer UNA VEZ.
        //   El primer new Request() en index.php ya lo leyó.
        //   El segundo new Request() aquí recibía body VACÍO → datos perdidos.

        // ═══ BÚSQUEDA DE RUTA ══════════════════════════════════════
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            // ↑ Filtro rápido: si la ruta es POST y la petición es GET, la salta.
            //   Evita ejecutar preg_match() innecesariamente (regex es costoso).

            if (preg_match($route['pattern'], $uri, $matches)) {
            // ↑ Intenta matchear la URI contra el regex de la ruta.
            //   Ejemplo: '#^/v1/app/asistencia/(?P<usuarioId>[^/]+)$#'
            //   vs URI: '/v1/app/asistencia/42'
            //   Resultado: $matches = ['0' => '/v1/app/asistencia/42',
            //              'usuarioId' => '42', 1 => '42']

                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                // ↑ Filtra $matches para quedarse SOLO con las claves STRING (nombradas).
                //   Elimina las claves numéricas (0, 1, 2...) que preg_match genera.
                //   Resultado: ['usuarioId' => '42'] — solo los parámetros de ruta.

                $req->setParams($params);
                // ↑ Inyecta los parámetros en el Request.
                //   Ahora el controller puede hacer: $req->param('usuarioId') → '42'

                // ═══ EJECUCIÓN DE MIDDLEWARE ════════════════════════
                if ($route['auth'] === 'app') {
                    \App\Middleware\AuthAppMiddleware::handle();
                } elseif ($route['auth'] === 'web') {
                    \App\Middleware\AuthWebMiddleware::handle();
                }
                // ↑ Si la ruta es protegida, ejecuta el middleware correspondiente.
                //   El middleware verifica el token JWT del header Authorization.
                //   Si el token es INVÁLIDO → Response::unauthorized() → exit()
                //   → el código de abajo NUNCA se ejecuta.
                //   Si el token es VÁLIDO → inyecta payload en $_REQUEST['auth_user']
                //   → el código continúa normalmente.

                // ═══ INSTANCIAR CONTROLLER Y LLAMAR MÉTODO ═════════
                [$class, $method] = $route['handler'];
                // ↑ Destructuración: [AuthAppController::class, 'login']
                //   $class = 'App\Controllers\App\AuthAppController'
                //   $method = 'login'

                $controller = new $class();
                // ↑ Instanciación DINÁMICA: PHP interpreta el string como nombre de clase.
                //   Equivale a: new App\Controllers\App\AuthAppController();
                //   El constructor del controller inicializa sus dependencias (model, etc.)

                $controller->$method($req);
                // ↑ Llamada DINÁMICA: PHP interpreta $method como nombre de función.
                //   Equivale a: $controller->login($req);
                //   El controller procesa la petición y llama a Response::success/error.
                //   Response llama a exit() → la ejecución TERMINA aquí.

                return; // Ruta encontrada, salir del foreach.
            }
        }

        Response::notFound('Ruta no encontrada');
        // ↑ Ninguna ruta coincidió con la URI → 404 JSON.
        //   Esto es DIFERENTE del 404 de Apache (que devuelve HTML).
        //   Nuestra API siempre responde en JSON, incluso en errores.
    }
}
```

---

## 8. `app/Core/Request.php` — Value Object de la Petición HTTP

```php
<?php
namespace App\Core;

class Request
{
    private array $body;
    // ↑ Almacena los datos del BODY de la petición HTTP.
    //   Se usa en POST/PUT/PATCH. Viene del JSON que envía el cliente.
    //   Ejemplo: {"codigo_empleado": "EMP-001", "password": "123456"}

    private array $query;
    // ↑ Almacena los QUERY PARAMETERS de la URL.
    //   Ejemplo: GET /v1/web/sedes?search=lima&page=2
    //   $query = ['search' => 'lima', 'page' => '2']

    private array $params;
    // ↑ Almacena los PARÁMETROS DE RUTA (dinámicos).
    //   Ejemplo: GET /v1/app/asistencia/42 → $params = ['usuarioId' => '42']
    //   Se llena en Router::dispatch() después del regex match.

    public function __construct()
    {
        $this->body = json_decode(file_get_contents('php://input'), true) ?? [];
        // ↑ PASO A PASO:
        //   1. file_get_contents('php://input'): Lee el BODY RAW de la petición HTTP.
        //      php://input es un stream de solo lectura que da el body HTTP completo.
        //      Para POST con JSON body: '{"email": "admin@empresa.com", "password": "secret"}'
        //      ⚠️ IMPORTANTE: solo se puede leer UNA VEZ. Lecturas posteriores dan string vacío.
        //      (Esto causó el Bug #6 cuando Router creaba un segundo Request.)
        //
        //   2. json_decode(..., true): Convierte el string JSON en array PHP asociativo.
        //      El segundo parámetro 'true' fuerza arrays asociativos.
        //      Sin true: devolvería objetos stdClass ($obj->email).
        //      Con true: devuelve arrays (['email' => 'admin@empresa.com']).
        //      Si el body NO es JSON válido (ej: form-urlencoded) → retorna null.
        //
        //   3. ?? []: Operador null coalescence. Si json_decode retorna null
        //      (body vacío, no es JSON, o GET sin body), usa array vacío.
        //      Esto previene errores de tipo: "Cannot access offset on null".

        $this->query = $_GET;
        // ↑ $_GET es superglobal de PHP que contiene los query parameters.
        //   Ya está parseado automáticamente por PHP.
        //   URL: /v1/web/sedes?search=lima → $_GET = ['search' => 'lima']

        $this->params = [];
        // ↑ Inicialmente vacío. Lo llenará Router::dispatch() con setParams()
        //   después de hacer match con la regex de la ruta.
    }

    /** Lee un campo del body JSON. Si no existe, devuelve $default. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
        // ↑ ?? (null coalescence): si $this->body[$key] existe Y no es null → lo retorna.
        //   Si no existe o es null → retorna $default.
        //   Ejemplo: $req->input('password') → 'secret' (del body JSON)
        //   Ejemplo: $req->input('campo_inexistente') → null
        //   Ejemplo: $req->input('cargo', '') → '' (string vacío como default)
    }

    /**
     * Devuelve solo los campos especificados del body (WHITELIST).
     * Previene que el cliente envíe campos no permitidos.
     * Ejemplo: $req->only(['nombre', 'email']) ignora 'rol' si viene en el body.
     */
    public function only(array $keys): array
    {
        return array_filter(
            $this->body,
            fn($key) => in_array($key, $keys),    // Solo las claves en la whitelist
            ARRAY_FILTER_USE_KEY                    // Filtrar por CLAVE, no por valor
        );
        // ↑ array_filter con ARRAY_FILTER_USE_KEY aplica el callback a las CLAVES del array.
        //   fn($key) => in_array($key, $keys): retorna true si la clave está en la lista.
        //   Input body: {'nombre': 'Juan', 'email': 'j@m.c', 'rol': 'admin', 'password': '123'}
        //   $req->only(['nombre', 'email'])
        //   Resultado: ['nombre' => 'Juan', 'email' => 'j@m.c'] ← 'rol' y 'password' excluidos
        //
        //   ¿Por qué? SEGURIDAD: si el atacante envía {"rol": "super_admin"} en el body
        //   de un update de sede, only() lo IGNORA porque 'rol' no está en la whitelist.
    }

    /** Lee un parámetro de query string (?key=value). */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
        // Ejemplo: GET /v1/web/sedes?page=3 → $req->query('page') → '3'
    }

    /** Lee un parámetro dinámico de ruta ({id}, {usuarioId}). */
    public function param(string $key): mixed
    {
        return $this->params[$key] ?? null;
        // Ejemplo: GET /v1/web/sedes/42 (ruta: /v1/web/sedes/{id})
        //   → $req->param('id') → '42'
    }

    /** Inyecta los parámetros de ruta (llamado por Router::dispatch). */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /** Busca un valor en body → query → params (en ese orden de prioridad). */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $this->params[$key] ?? $default;
        // ↑ Cadena de ?? : busca en body primero, luego query, luego params.
        //   Si ninguno tiene el campo → retorna $default.
        //   Útil para métodos genéricos que no saben de dónde viene el dato.
    }

    /** Devuelve TODO el body como array (sin filtrar). */
    public function all(): array
    {
        return $this->body;
    }
}
```

---

## 9. `app/Core/Response.php` — Emisor de Respuestas JSON (CORREGIDO — FIX Bug #2 y #5)

```php
<?php
namespace App\Core;

class Response
{
    /**
     * Respuesta EXITOSA. Estructura: { "success": true, "message": "OK", "data": {...} }
     *
     * @param mixed  $data    Datos a enviar (array, null, o cualquier tipo serializable)
     * @param string $message Mensaje descriptivo para el cliente
     * @param int    $status  Código HTTP (200 OK, 201 Created)
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): void
    {
        self::json([
            'success' => true,       // Siempre true en respuestas exitosas
            'message' => $message,   // Mensaje legible para el frontend
            'data'    => $data,      // Los datos solicitados (puede ser null en POST/DELETE)
        ], $status);
        // ↑ self::json() envía la respuesta y ejecuta exit(). NADA después de esta línea se ejecuta.
    }

    /**
     * Error genérico con código HTTP personalizado.
     * Estructura: { "success": false, "message": "Error", "data": {...} }
     *
     * FIX Bug #5: Se añadió el parámetro opcional $data para permitir contexto
     * adicional en errores. Por ejemplo, cuando la validación GPS falla:
     * Response::error("Fuera de rango", 403, ['distancia_metros' => 250, 'radio_sede' => 100])
     * ANTES del fix: error() solo aceptaba message y status → el frontend no sabía
     * la distancia real ni el radio configurado.
     */
    public static function error(string $message, int $status = 400, mixed $data = null): void
    {
        $body = ['success' => false, 'message' => $message];
        if ($data !== null) {
            $body['data'] = $data;
            // ↑ Solo incluye 'data' si se proporcionó. Evita enviar "data": null en errores
            //   simples como Response::error("No encontrado", 404).
        }
        self::json($body, $status);
    }

    /**
     * FIX Bug #2: Alias de unprocessable() para compatibilidad.
     * AuthAppController llamaba Response::validationError() que NO EXISTÍA.
     * Esto causaba: "Call to undefined method Response::validationError()"
     * → Error fatal 500 → la app se caía al hacer login sin credenciales.
     * Se añadió como alias que delega a unprocessable().
     */
    public static function validationError(array $errors, string $message = 'Datos requeridos'): void
    {
        self::unprocessable($message, $errors);
    }

    /** 401 Unauthorized — token inválido, expirado, o no proporcionado. */
    public static function unauthorized(string $message = 'No autorizado'): void
    {
        self::error($message, 401);
        // ↑ 401 = el cliente NO está autenticado. Diferente de 403.
        //   El cliente debería re-autenticarse (hacer login de nuevo).
    }

    /** 403 Forbidden — autenticado pero sin permisos para esta acción. */
    public static function forbidden(string $message = 'Acceso denegado'): void
    {
        self::error($message, 403);
        // ↑ 403 = el cliente SÍ está autenticado pero NO tiene permisos.
        //   Ejemplo: un supervisor intenta eliminar un feriado nacional.
        //   Re-autenticarse NO resolverá el problema; necesita otro rol.
    }

    /** 404 Not Found — recurso no existe en la BD o ruta no existe. */
    public static function notFound(string $message = 'Recurso no encontrado'): void
    {
        self::error($message, 404);
    }

    /**
     * 422 Unprocessable Entity — validación de datos fallida.
     * Estructura especial: { "success": false, "message": "...", "errors": [...] }
     * @param array $errors Array de errores específicos de cada campo.
     *   Ejemplo: ['email' => 'formato inválido', 'password' => 'mínimo 8 caracteres']
     */
    public static function unprocessable(string $message = 'Datos inválidos', array $errors = []): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,    // Array DETALLADO de errores por campo
        ], 422);
        // ↑ 422 es específico para "entendí tu petición, pero los datos son inválidos".
        //   Diferente de 400 (Bad Request: no entendí la petición en absoluto).
    }

    /**
     * Método base PRIVADO: envía JSON y TERMINA la ejecución con exit().
     *
     * ⚠️ IMPORTANTE: exit() detiene TODO el script PHP. NINGÚN código después
     * de una llamada a Response::success() o Response::error() se ejecutará.
     * Esto es INTENCIONAL: garantiza que solo se envía UNA respuesta por petición.
     */
    private static function json(array $data, int $status): void
    {
        http_response_code($status);
        // ↑ Establece el código HTTP de la respuesta (200, 201, 400, 401, 404, 422, 500).

        header('Content-Type: application/json; charset=UTF-8');
        // ↑ Le dice al cliente que el body es JSON. charset=UTF-8 para acentos.

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        // ↑ Cabeceras CORS en CADA respuesta (no solo en preflight).
        //   Necesario porque el navegador verifica CORS en la respuesta real también.

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        // ↑ Convierte el array PHP en string JSON y lo envía al cliente.
        //   JSON_UNESCAPED_UNICODE: NO escapa caracteres UTF-8.
        //     Sin flag: "Justificación" → "Justificaci\u00f3n" (escaped)
        //     Con flag: "Justificación" → "Justificación" (legible)
        //   Importante para un sistema en español con muchos acentos y ñ.

        exit();
        // ↑ TERMINA la ejecución. Previene que código posterior envíe otra respuesta
        //   o que un error posterior corrompa el JSON ya enviado.
    }
}
```

---

## 10. `app/Middleware/AuthAppMiddleware.php` — JWT para App Móvil

```php
<?php
namespace App\Middleware;

use App\Core\Response;
use Firebase\JWT\JWT;       // Clase principal para encode/decode JWT
use Firebase\JWT\Key;       // Wrapper que asocia una clave con un algoritmo

class AuthAppMiddleware
{
    /**
     * Verifica que la petición tiene un JWT válido de tipo 'app'.
     * Se ejecuta ANTES del controller en rutas protegidas.
     * Si falla → Response::unauthorized() → exit() (el controller NUNCA se ejecuta).
     * Si pasa → inyecta el payload en $_REQUEST['auth_user'].
     */
    public static function handle(): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        // ↑ Apache convierte el header "Authorization: Bearer eyJ..." en
        //   $_SERVER['HTTP_AUTHORIZATION']. El prefijo HTTP_ se añade automáticamente.
        //   ?? '' = si no existe el header, usa string vacío (evita undefined index).

        if (!str_starts_with($header, 'Bearer '))
            Response::unauthorized('Token no proporcionado');
        // ↑ El estándar RFC 6750 define el formato: "Bearer {token}".
        //   str_starts_with() (PHP 8.0+) verifica que el header empiece con "Bearer ".
        //   Si no → 401 + exit(). El código de abajo NO se ejecuta.
        //   Casos que fallan: header vacío, header con "Basic ...", header sin espacio.

        $token = substr($header, 7);
        // ↑ Extrae el token JWT puro (sin "Bearer ").
        //   "Bearer eyJhbGciOiJIUzI1NiJ9.eyJzdWIi..." → "eyJhbGciOiJIUzI1NiJ9.eyJzdWIi..."
        //   7 = longitud de "Bearer " (con el espacio).

        try {
            $payload = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            // ↑ JWT::decode() hace TODO esto:
            //   1. Divide el token en 3 partes (header.payload.signature)
            //   2. Decodifica el header JSON → verifica que alg = 'HS256'
            //   3. Decodifica el payload JSON → extrae claims (sub, exp, tipo, etc.)
            //   4. Recalcula la firma HMAC-SHA256 usando JWT_SECRET
            //   5. Compara la firma recalculada con la del token
            //      → Si no coincide: token MANIPULADO → lanza excepción
            //   6. Verifica claim 'exp': si time() > exp → token EXPIRADO → excepción
            //
            //   new Key($_ENV['JWT_SECRET'], 'HS256'):
            //   - La clase Key FUERZA a especificar el algoritmo permitido.
            //   - Esto previene el ataque "algorithm confusion": si un atacante envía
            //     un token con alg:"none", la librería lo RECHAZA porque solo acepta HS256.
            //
            //   $payload es un objeto stdClass con los claims del token:
            //   $payload->sub = ID del usuario (ej: 42)
            //   $payload->rol = rol del usuario (ej: 'trabajador')
            //   $payload->tipo = tipo de token (ej: 'app')
            //   $payload->exp = timestamp de expiración
            //   $payload->iat = timestamp de emisión

            if (($payload->tipo ?? '') !== 'app')
                Response::unauthorized('Token no válido para esta aplicación');
            // ↑ SEGREGACIÓN DE AUDIENCIA: verificamos que el claim 'tipo' sea 'app'.
            //   Si un admin del panel web (tipo='web') intenta usar su token en la app
            //   móvil → RECHAZADO. Y viceversa.
            //   ?? '' = si el claim 'tipo' no existe en el payload, usa '' (string vacío).
            //
            //   NOTA: Este check fue el problema del Bug #1. AuthAppController generaba
            //   el token con 'type' (inglés) pero este middleware verificaba 'tipo' (español).
            //   Resultado: TODOS los tokens eran rechazados. Se corrigió en el controller.

            $_REQUEST['auth_user'] = (array) $payload;
            // ↑ Inyecta el payload decodificado en $_REQUEST (superglobal).
            //   (array) $payload convierte stdClass a array asociativo.
            //   Resultado: $_REQUEST['auth_user'] = [
            //     'iss' => 'asistencia-api', 'iat' => 1709500000,
            //     'exp' => 1709503600, 'sub' => 42, 'rol' => 'trabajador', 'tipo' => 'app'
            //   ]
            //   Los controllers acceden así:
            //     $_REQUEST['auth_user']['sub'] → 42 (ID del usuario)
            //     $_REQUEST['auth_user']['rol'] → 'trabajador'
            //
            //   ¿Por qué $_REQUEST? Es una superglobal accesible desde CUALQUIER archivo
            //   sin pasar parámetros. No es el uso "estándar" de $_REQUEST (que normalmente
            //   contiene $_GET + $_POST), pero funciona como mecanismo simple de inyección.

        } catch (\Exception $e) {
            Response::unauthorized('Token inválido o expirado');
            // ↑ Atrapa CUALQUIER excepción: firma incorrecta, token expirado,
            //   formato inválido, etc. NO expone el mensaje técnico ($e->getMessage())
            //   al cliente por seguridad. El atacante no sabrá si el token era inválido,
            //   estaba expirado, o tenía formato incorrecto.
        }
    }
}
```

---

## 11. `app/Middleware/AuthWebMiddleware.php` — JWT para Panel Web

Es **idéntico** a `AuthAppMiddleware` con una sola diferencia:

```php
if (($payload->tipo ?? '') !== 'web')
    Response::unauthorized('Token no válido para el panel web');
```

La verificación es `'web'` en vez de `'app'`. Esto garantiza que:
- Un **trabajador** con token `tipo='app'` **NO puede** acceder a rutas `/v1/web/*` (panel de admin)
- Un **admin** con token `tipo='web'` **NO puede** acceder a rutas `/v1/app/*` (app móvil)

Esta segregación previene escalamiento de privilegios horizontal entre los dos sistemas.
