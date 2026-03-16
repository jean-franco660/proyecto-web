# Documento Técnico Exhaustivo — API de Asistencia PHP MVC
## Parte 3: Controladores de la App Móvil (Código Corregido)

---

## 23. `Controllers/App/AuthAppController.php` (Corregido — FIX Bug #1, #2, #3)

```php
<?php
namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Models\UsuarioApp;
use Firebase\JWT\JWT;

class AuthAppController
{
    private Response $response;
    // ↑ Instancia de Response para usar métodos no-estáticos.
    //   En la práctica, este proyecto usa methods estáticos (Response::success()),
    //   pero se mantiene la instancia por consistencia con el constructor.

    private UsuarioApp $model;
    // ↑ Instancia del modelo UsuarioApp para acceso a la tabla usuarios_app.

    public function __construct()
    {
        $this->response = new Response();
        $this->model    = new UsuarioApp();
        // ↑ Internamente, UsuarioApp hereda BaseModel::__construct() que llama
        //   a Database::getInstance() → obtiene la conexión PDO Singleton.
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /v1/app/login — Autenticación de trabajadores
    // ═══════════════════════════════════════════════════════════════
    public function login(Request $request): void
    {
        $codigo = trim($request->input('codigo_empleado', ''));
        // ↑ trim() elimina espacios al inicio y final del código.
        //   Los teclados de móvil a veces añaden un espacio extra al final
        //   cuando el usuario selecciona una sugerencia del autocompletado.
        //   Sin trim: 'EMP-001 ' no matchearía con 'EMP-001' en la BD.

        $password = $request->input('password', '');
        // ↑ NO se hace trim() en la contraseña intencionalmente.
        //   Una contraseña PUEDE empezar o terminar con espacios de forma legítima.
        //   trim() modificaría la contraseña del usuario y causaría login fallido.
        //   El '' como default evita null si el campo no se envía.

        // ── VALIDACIÓN DE CAMPOS REQUERIDOS ────────────────────
        // FIX Bug #2: el código original llamaba a Response::validationError()
        // que NO EXISTÍA en la clase Response. Esto causaba un error fatal PHP:
        // "Call to undefined method App\Core\Response::validationError()"
        // → HTTP 500 en vez de un 422 descriptivo.
        // Se reemplazó por Response::unprocessable() que sí existe.
        if (!$codigo || !$password) {
            Response::unprocessable('Datos requeridos', [
                'codigo_empleado' => 'Requerido',
                'password'        => 'Requerido',
            ]);
            // ↑ unprocessable() llama a json() → exit().
            //   El array de errores le dice al frontend EXACTAMENTE qué campos faltan.
            //   HTTP 422 = "Entendí tu petición pero los datos son inválidos".
        }

        // ── BÚSQUEDA DEL USUARIO ──────────────────────────────
        $usuario = $this->model->findByCodigo($codigo);
        // ↑ SQL: SELECT * FROM usuarios_app WHERE codigo_empleado = ? LIMIT 1
        //   Retorna el array del usuario o null si no existe.

        // ── VERIFICACIÓN DE CREDENCIALES ──────────────────────
        if (!$usuario || !password_verify($password, $usuario['password'])) {
            $this->response->unauthorized('Credenciales incorrectas.');
            // ↑ SEGURIDAD: el mensaje es IDÉNTICO tanto si el usuario NO EXISTE
            //   como si la CONTRASEÑA es incorrecta. ¿Por qué?
            //   Si dijéramos "usuario no encontrado" vs "contraseña incorrecta",
            //   un atacante podría ENUMERAR usuarios válidos probando códigos:
            //   - "EMP-001" → "contraseña incorrecta" → ¡este usuario EXISTE!
            //   - "EMP-002" → "usuario no encontrado" → no existe
            //   Con mensaje unificado, el atacante no sabe si es usuario o contraseña.
            //
            //   password_verify() usa COMPARACIÓN DE TIEMPO CONSTANTE internamente:
            //   tarda lo mismo con hash correcto que incorrecto, previniendo timing attacks.
            //   $usuario['password'] contiene el hash bcrypt: $2y$10$abc...xyz
        }

        // ── GENERACIÓN DE TOKEN JWT ───────────────────────────
        $token = $this->generateToken($usuario, 'app');
        // ↑ Genera un JWT con claims del usuario y tipo 'app'.
        //   Ver detalle del método más abajo.

        $this->response->success([
            'token'   => $token,
            'usuario' => $this->sanitize($usuario),
            // ↑ sanitize() elimina el campo 'password' del array antes de enviarlo.
            //   NUNCA se envía el hash de la contraseña al cliente, ni siquiera el hash.
        ], 'Login exitoso.');
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /v1/app/perfil — Perfil del trabajador autenticado
    // ═══════════════════════════════════════════════════════════════
    public function perfil(Request $request): void
    {
        // FIX Bug #3: el código original usaba $request->getAttribute('auth_user_id')
        // que NO EXISTE en nuestra clase Request. Request solo tiene:
        // input(), only(), query(), param(), get(), all(), setParams().
        // El payload JWT lo inyecta el middleware en $_REQUEST['auth_user'].
        $userId  = (int) ($_REQUEST['auth_user']['sub'] ?? 0);
        // ↑ $_REQUEST['auth_user'] = ['sub' => 42, 'rol' => 'trabajador', 'tipo' => 'app', ...]
        //   'sub' (Subject) es el claim JWT estándar que contiene el ID del usuario.
        //   (int) convierte string a entero para seguridad de tipo.
        //   ?? 0: si no existe, usa 0 (que no matcheará ningún usuario en find()).

        $usuario = $this->model->find($userId);
        // ↑ SQL: SELECT * FROM usuarios_app WHERE id = ? LIMIT 1

        if (!$usuario) {
            Response::notFound('Usuario no encontrado.');
            // ↑ Esto solo pasa si el usuario fue eliminado de la BD DESPUÉS
            //   de generar el token JWT (el token tiene un sub inválido).
        }

        Response::success($this->sanitize($usuario));
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /v1/app/logout — Cerrar sesión (simbólico)
    // ═══════════════════════════════════════════════════════════════
    public function logout(Request $request): void
    {
        $this->response->success(null, 'Sesión cerrada.');
        // ↑ JWT es STATELESS: el servidor NO almacena sesiones.
        //   No hay tabla de "tokens activos" que invalidar.
        //   El "logout" es SIMBÓLICO: le dice al frontend que borre el token
        //   de su almacenamiento local (SharedPreferences en Android, Keychain en iOS).
        //   El token sigue siendo válido hasta que expire (exp claim).
        //   Alternativa real: implementar blacklist de tokens (requiere Redis/tabla BD).
    }

    // ═══════════════════════════════════════════════════════════════
    // Método privado: generación de token JWT
    // ═══════════════════════════════════════════════════════════════
    private function generateToken(array $usuario, string $type): string
    {
        $secret     = $_ENV['JWT_SECRET'] ?? 'secret';
        // ↑ Lee la clave de firma del .env. ?? 'secret' es fallback para desarrollo.
        //   En producción, debe ser una clave aleatoria de al menos 256 bits.

        $expiration = (int)($_ENV['JWT_EXPIRATION'] ?? 3600);
        // ↑ Lee la duración del token del .env. Default 3600 = 1 hora.
        //   (int) asegura que es entero (el .env lo devuelve como string).

        $payload = [
            'iss'  => 'asistencia-api',
            // ↑ Issuer: identifica quién EMITIÓ el token. Claim registrado por RFC 7519.
            //   Útil si múltiples APIs comparten el mismo JWT_SECRET:
            //   solo se aceptan tokens del issuer correcto.

            'iat'  => time(),
            // ↑ Issued At: timestamp UNIX de cuándo se generó el token.
            //   time() = segundos desde 1970-01-01 00:00:00 UTC.
            //   Permite saber "hace cuánto se emitió este token".

            'exp'  => time() + $expiration,
            // ↑ Expiration: timestamp UNIX de cuándo EXPIRA.
            //   JWT::decode() verifica automáticamente: if (time() > exp) → excepción.
            //   time() + 3600 = dentro de 1 hora.

            'sub'  => $usuario['id'],
            // ↑ Subject: ID del usuario. Claim registrado por RFC 7519.
            //   Es el claim principal para identificar AL USUARIO en cada petición.
            //   Los controllers lo leen: $_REQUEST['auth_user']['sub']

            'rol'  => $usuario['rol'],
            // ↑ Claim CUSTOM: rol del trabajador ('trabajador', etc.).
            //   Se usa para autorización en algunos endpoints.

            // FIX Bug #1: ESTE ERA EL BUG MÁS CRÍTICO.
            // ─────────────────────────────────────────
            // ANTES: 'type' => $type  (en inglés)
            // AuthAppMiddleware verifica: if ($payload->tipo !== 'app')
            // El claim 'type' NUNCA se verificaba → ($payload->tipo ?? '') = ''
            // '' !== 'app' → TUTTI los tokens eran rechazados → NADIE podía acceder.
            //
            // DESPUÉS: 'tipo' => $type  (en español, coincide con el middleware)
            'tipo' => $type,
        ];

        return JWT::encode($payload, $secret, 'HS256');
        // ↑ JWT::encode() hace:
        //   1. Genera header JSON: {"alg": "HS256", "typ": "JWT"}
        //   2. Genera payload JSON con los claims de arriba
        //   3. Base64URL-encode header y payload
        //   4. Calcula firma: HMAC-SHA256(base64Header + "." + base64Payload, $secret)
        //   5. Retorna: header.payload.signature (3 partes separadas por puntos)
        //
        //   HS256 = HMAC-SHA256: algoritmo SIMÉTRICO (misma clave para firmar y verificar).
        //   Es más rápido que RS256 (asimétrico) y suficiente para un solo servidor.
    }

    /**
     * Elimina campos sensibles antes de enviar datos al cliente.
     * NUNCA se envía el hash de la contraseña, ni siquiera hasheado.
     */
    private function sanitize(array $usuario): array
    {
        unset($usuario['password']);
        // ↑ unset() elimina la clave 'password' del array.
        //   Si el frontend recibiera el hash bcrypt ($2y$10$...),
        //   un atacante podría hacer brute force offline (sin limitaciones de rate).
        return $usuario;
    }
}
```

---

## 24. `Controllers/App/AsistenciaAppController.php` (Corregido — FIX Bug #4)

Este es el controller más complejo de la aplicación. Maneja el flujo completo de registro de asistencia con validación GPS, ventanas horarias, idempotencia offline, y cálculo de tardanzas.

```php
<?php
namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class AsistenciaAppController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        // ↑ Conexión PDO directa (no usa modelo BaseModel).
        //   Este controller tiene queries complejas con múltiples JOINs que no
        //   encajan bien en el patrón CRUD genérico de BaseModel.
    }

    /** Helper: obtiene el ID del usuario autenticado desde el JWT. */
    private function userId(): int
    {
        return (int) ($_REQUEST['auth_user']['sub'] ?? 0);
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /v1/app/asistencia — Registrar marcación (ENTRADA/SALIDA)
    // ═══════════════════════════════════════════════════════════════
    public function store(Request $req): void
    {
        // ── EXTRAER DATOS DEL REQUEST ──────────────────────────
        $userId      = $this->userId();
        $sedeId      = (int)    $req->input('sede_id');
        $tipo        = (string) $req->input('tipo');            // 'ENTRADA' | 'SALIDA'
        $fechaHora   = (string) $req->input('fecha_hora');      // '2025-03-10 08:05:30'
        $latitud     = (float)  $req->input('latitud');         // -12.04640000
        $longitud    = (float)  $req->input('longitud');        // -77.04280000
        $offlineUuid = $req->input('offline_uuid');              // UUID del dispositivo o null
        // ↑ (int), (string), (float) son type casts de PHP.
        //   Convierten null/string a su tipo esperado.
        //   (int) null → 0, (float) null → 0.0, (string) null → ''.

        // ── VALIDACIÓN BÁSICA ──────────────────────────────────
        if (!$sedeId || !in_array($tipo, ['ENTRADA','SALIDA']) || !$fechaHora || !$latitud || !$longitud)
            Response::unprocessable('Faltan campos requeridos');
        // ↑ in_array() verifica que $tipo sea exactamente 'ENTRADA' o 'SALIDA'.
        //   Un atacante que envíe tipo='DROP TABLE' → rechazado aquí.
        //   !$latitud y !$longitud: latitud 0.0 (ecuador, golfo de Guinea) sería false.
        //   Para este proyecto en Perú, 0.0 es inválido (correcto).

        // ══════════════════════════════════════════════════════════
        // PASO 1: IDEMPOTENCIA — Verificar si esta marcación ya existe
        // ══════════════════════════════════════════════════════════
        if ($offlineUuid) {
            $stmt = $this->db->prepare(
                "SELECT id FROM asistencias_diarias WHERE offline_uuid = :uuid"
            );
            $stmt->execute([':uuid' => $offlineUuid]);
            // ↑ Usa el índice idx_offline_uuid para búsqueda O(log n).

            if ($stmt->fetch())
                Response::success(null, 'Marcación ya registrada (idempotente)');
            // ↑ PATRÓN: IDEMPOTENT CONSUMER / AT-MOST-ONCE PROCESSING
            //
            //   PROBLEMA: la app móvil puede perder conexión al enviar la marcación.
            //   El servidor la recibió y la guardó, pero el ACK no llegó al cliente.
            //   El cliente reintenta enviar la MISMA marcación cuando recupera conexión.
            //   Sin idempotencia: se duplicaría (2 ENTRADAs en el mismo día).
            //
            //   SOLUCIÓN: la app genera un UUID v4 ÚNICO para cada marcación.
            //   Antes de insertar, verificamos si ya existe ese UUID.
            //   Si existe → 200 (ya guardado, no hacer nada).
            //   Si no existe → procedemos al INSERT.
            //
            //   El constraint UNIQUE en offline_uuid es la defensa FINAL:
            //   si por concurrencia dos requests llegaran simultáneamente,
            //   el segundo INSERT fallaría por violación UNIQUE.
        }

        // ══════════════════════════════════════════════════════════
        // PASO 2: VERIFICAR ASIGNACIÓN — ¿El trabajador está asignado a esta sede?
        // ══════════════════════════════════════════════════════════
        $stmt = $this->db->prepare("
            SELECT uas.*, hs.hora_entrada, hs.hora_salida,
                   hs.tolerancia_entrada_minutos, hs.tolerancia_salida_minutos,
                   hs.dias_semana
            FROM usuario_app_sede uas
            INNER JOIN horarios_sede hs ON uas.horario_sede_id = hs.id
            WHERE uas.usuario_app_id = :uid
              AND uas.sede_id = :sid
              AND uas.estado = 'ACTIVO'
        ");
        $stmt->execute([':uid' => $userId, ':sid' => $sedeId]);
        $asignacion = $stmt->fetch();
        // ↑ Busca la asignación ACTIVA del trabajador en la sede solicitada.
        //   INNER JOIN con horarios_sede: necesita los horarios para validar la ventana temporal.
        //   Si el trabajador NO está asignado o su asignación está INACTIVA → null.

        if (!$asignacion)
            Response::error('No estás asignado a esta sede o no tienes horario asignado.', 403);
        // ↑ 403 Forbidden: está autenticado pero no autorizado para esta sede.

        // ══════════════════════════════════════════════════════════
        // PASO 3: VALIDAR GPS — ¿El dispositivo está dentro del radio de la sede?
        // ══════════════════════════════════════════════════════════
        $stmt = $this->db->prepare(
            "SELECT latitud, longitud, radio FROM sedes WHERE id = :sid"
        );
        $stmt->execute([':sid' => $sedeId]);
        $sede = $stmt->fetch();
        // ↑ Obtiene las coordenadas centrales y el radio del geofence de la sede.

        $distancia = $this->calcularDistanciaMetros($latitud, $longitud, $sede['latitud'], $sede['longitud']);
        // ↑ Calcula la distancia en METROS entre el dispositivo y el centro de la sede
        //   usando la fórmula de Haversine (ver método abajo).

        if ($distancia > $sede['radio'])
            Response::error(
                "Debes estar dentro de la sede para registrar asistencia.",
                403,
                ['distancia_metros' => (int) $distancia, 'radio_sede' => $sede['radio']]
                // ↑ FIX Bug #5 (en Response::error): ahora acepta 3er param $data.
                //   La app móvil puede mostrar: "Estás a 250m. Radio permitido: 100m."
            );

        // ══════════════════════════════════════════════════════════
        // PASO 4: EVALUAR VENTANA HORARIA — ¿Marcó en horario válido?
        // ══════════════════════════════════════════════════════════
        $marcadaEn         = new \DateTime($fechaHora);
        // ↑ Crea un objeto DateTime a partir del string enviado por la app.
        //   '2025-03-10 08:05:30' → DateTime representando esa fecha/hora.

        $fechaDia          = $marcadaEn->format('Y-m-d');
        // ↑ Extrae solo la fecha: '2025-03-10'. Se usa para la cabecera del día.

        $estadoMarcacion   = 'VALIDA';          // Asumimos válida a menos que se demuestre lo contrario
        $motivoObservacion = null;
        $estadoDiario      = 'PRESENTE';         // Estado por defecto si marca a tiempo
        $minutosTarde      = 0;

        if ($tipo === 'ENTRADA') {
            $horaEntrada = new \DateTime("$fechaDia {$asignacion['hora_entrada']}");
            // ↑ Construye DateTime de la hora de entrada del horario.
            //   Si hora_entrada es '08:00:00' y fecha es '2025-03-10':
            //   → DateTime('2025-03-10 08:00:00')

            $limiteConTolerancia = (clone $horaEntrada)
                ->modify("+{$asignacion['tolerancia_entrada_minutos']} minutes");
            // ↑ (clone $horaEntrada): crea una COPIA para no modificar el original.
            //   Sin clone, $horaEntrada se modificaría también (DateTime es mutable).
            //   ->modify("+15 minutes"): si tolerancia es 15, límite = 08:15:00.
            //   El trabajador puede llegar hasta 08:15 sin ser marcado como TARDANZA.

            if ($marcadaEn < $horaEntrada) {
                // ↑ Marcó ANTES de la hora de entrada programada.
                //   Ejemplo: entró a las 06:00 pero el horario empieza a las 08:00.
                //   Esto es sospechoso (¿por qué marca 2 horas antes?) → OBSERVADA.
                $estadoMarcacion   = 'OBSERVADA';
                $motivoObservacion = 'FUERA_DE_HORARIO';
                // ↑ La marcación se REGISTRA pero queda pendiente de revisión del admin.
            } elseif ($marcadaEn > $limiteConTolerancia) {
                // ↑ Marcó DESPUÉS del límite de tolerancia → TARDANZA.
                //   Ejemplo: tolerancia 15min, horario 08:00, marcó 08:20 → 20 min tarde.
                $minutosTarde = (int)(($marcadaEn->getTimestamp() - $horaEntrada->getTimestamp()) / 60);
                // ↑ Calcula minutos de retraso:
                //   (timestamp_marcada - timestamp_entrada) / 60 = minutos.
                //   getTimestamp() retorna segundos UNIX → la diferencia es en segundos → /60.
                $estadoDiario = 'TARDANZA';
            }
            // Si no entra en ningún if/elseif: marcó ENTRE hora_entrada y límite_tolerancia
            // → se mantienen los defaults: VALIDA + PRESENTE.

        } elseif ($tipo === 'SALIDA') {
            $horaSalida   = new \DateTime("$fechaDia {$asignacion['hora_salida']}");
            $limiteInicio = (clone $horaSalida)
                ->modify("-{$asignacion['tolerancia_salida_minutos']} minutes");
            // ↑ Para SALIDA, la tolerancia es ANTES de la hora programada.
            //   Si sale a las 17:00 con tolerancia 15min → puede salir desde 16:45.
            //   Antes de 16:45 = salida anticipada → OBSERVADA.

            if ($marcadaEn < $limiteInicio || $marcadaEn > $horaSalida) {
                $estadoMarcacion   = 'OBSERVADA';
                $motivoObservacion = 'FUERA_DE_HORARIO';
                // ↑ Salir MUY TEMPRANO o MUY TARDE son ambos observados.
            }
        }

        // ══════════════════════════════════════════════════════════
        // PASO 5: GET OR CREATE — Cabecera del día
        // ══════════════════════════════════════════════════════════
        $stmt = $this->db->prepare("
            SELECT id FROM asistencias
            WHERE usuario_app_id = :uid AND sede_id = :sid AND fecha = :fecha
        ");
        $stmt->execute([':uid' => $userId, ':sid' => $sedeId, ':fecha' => $fechaDia]);
        $asistencia = $stmt->fetch();
        // ↑ Busca si ya existe una cabecera para (trabajador, sede, hoy).

        if (!$asistencia) {
            // No existe → crear cabecera nueva con estado PENDIENTE
            $stmt = $this->db->prepare("
                INSERT INTO asistencias (usuario_app_id, sede_id, horario_sede_id, fecha, estado_diario)
                VALUES (:uid, :sid, :hid, :fecha, 'PENDIENTE')
            ");
            $stmt->execute([
                ':uid' => $userId, ':sid' => $sedeId,
                ':hid' => $asignacion['horario_sede_id'], ':fecha' => $fechaDia,
            ]);
            $asistenciaId = (int) $this->db->lastInsertId();
            // ↑ lastInsertId() retorna el AUTO_INCREMENT del INSERT.
        } else {
            // FIX Bug #4: ESTE ERA UN BUG CRÍTICO.
            // ─────────────────────────────────────
            // ANTES: $asistenciaId = $asignacion['id'];
            // $asignacion es de la tabla usuario_app_sede (asignación trabajador↔sede).
            // $asistencia es de la tabla asistencias (cabecera del día).
            // ¡Son tablas DIFERENTES con IDs independientes!
            //
            // Si $asignacion['id'] = 5 y $asistencia['id'] = 42:
            //   La marcación se vinculaba a asistencia_id=5 (que podría ser de OTRO trabajador)
            //   en vez de asistencia_id=42 (la correcta para este día).
            //
            // DESPUÉS: $asistenciaId = $asistencia['id'];
            // Ahora se usa el ID correcto de la cabecera del día.
            $asistenciaId = $asistencia['id'];
        }

        // ══════════════════════════════════════════════════════════
        // PASO 6: INSERTAR MARCACIÓN — Registro GPS individual
        // ══════════════════════════════════════════════════════════
        $stmt = $this->db->prepare("
            INSERT INTO asistencias_diarias
                (asistencia_id, tipo, marcada_en, latitud, longitud,
                 dentro_rango, distancia_metros, estado_marcacion, motivo_observacion,
                 estado_revision, offline_uuid, registrado_en)
            VALUES
                (:aid, :tipo, :marcada, :lat, :lng,
                 1, :dist, :estado, :motivo,
                 :revision, :uuid, 'APP_ONLINE')
        ");
        $stmt->execute([
            ':aid'      => $asistenciaId,
            ':tipo'     => $tipo,                    // 'ENTRADA' o 'SALIDA'
            ':marcada'  => $fechaHora,               // Timestamp del cliente
            ':lat'      => $latitud,
            ':lng'      => $longitud,
            ':dist'     => (int) $distancia,         // Metros desde el centro de la sede
            ':estado'   => $estadoMarcacion,         // 'VALIDA' o 'OBSERVADA'
            ':motivo'   => $motivoObservacion,       // null o 'FUERA_DE_HORARIO'
            ':revision' => $estadoMarcacion === 'OBSERVADA' ? 'PENDIENTE' : 'APROBADA',
            // ↑ Si la marca es OBSERVADA → necesita revisión del admin (PENDIENTE).
            //   Si es VALIDA → auto-aprobada (APROBADA).
            ':uuid'     => $offlineUuid,             // UUID del dispositivo o null
        ]);

        // ══════════════════════════════════════════════════════════
        // PASO 7: ACTUALIZAR CABECERA — Estado diario y horas
        // ══════════════════════════════════════════════════════════
        if ($tipo === 'ENTRADA' && $estadoDiario !== 'PENDIENTE') {
            $this->db->prepare("
                UPDATE asistencias
                SET estado_diario = :ed, hora_entrada = :he, minutos_tarde = :mt
                WHERE id = :id
            ")->execute([
                ':ed' => $estadoDiario,                          // 'PRESENTE' o 'TARDANZA'
                ':he' => $marcadaEn->format('H:i:s'),           // '08:05:30'
                ':mt' => $minutosTarde,                          // 0 o número de minutos
                ':id' => $asistenciaId,
            ]);
        } elseif ($tipo === 'SALIDA') {
            $this->db->prepare(
                "UPDATE asistencias SET hora_salida = :hs WHERE id = :id"
            )->execute([
                ':hs' => $marcadaEn->format('H:i:s'),
                ':id' => $asistenciaId,
            ]);
        }

        // ── RESPUESTA EXITOSA ──────────────────────────────────
        Response::success([
            'tipo'               => $tipo,
            'dentro_rango'       => true,
            'distancia_metros'   => (int) $distancia,
            'estado_marcacion'   => $estadoMarcacion,
            'motivo_observacion' => $motivoObservacion,
            'estado_diario'      => $estadoDiario,
        ], 'Asistencia registrada correctamente', 201);
        // ↑ 201 Created: recurso nuevo creado (la marcación).
    }

    // ═══════════════════════════════════════════════════════════════
    // Fórmula Haversine — Distancia entre dos puntos GPS en metros
    // ═══════════════════════════════════════════════════════════════
    private function calcularDistanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R  = 6371000;                  // Radio medio de la Tierra en METROS
        $φ1 = deg2rad($lat1);           // Convierte grados a radianes (la fórmula requiere radianes)
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);   // Diferencia de latitud en radianes
        $Δλ = deg2rad($lng2 - $lng1);   // Diferencia de longitud en radianes

        $a = sin($Δφ/2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ/2) ** 2;
        // ↑ 'a' es el cuadrado de la mitad de la longitud de la cuerda entre los puntos.
        //   Fórmula: a = sin²(Δφ/2) + cos(φ1) · cos(φ2) · sin²(Δλ/2)

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
        // ↑ atan2 calcula el ángulo central. Multiplicado por R da la distancia de arco.
        //   Resultado en METROS. Precisión: ~0.5% para distancias terrestres.
        //   Para geofencing de 100m, la presición es más que suficiente.
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /v1/app/asistencia/{usuarioId} — Historial de asistencias
    // ═══════════════════════════════════════════════════════════════
    public function historial(Request $req): void
    {
        $userId       = $this->userId();
        $solicitadoId = (int) $req->param('usuarioId');
        // ↑ El ID viene del parámetro de ruta: /v1/app/asistencia/42

        // ── PROTECCIÓN IDOR ────────────────────────────────────
        if ($solicitadoId !== $userId)
            Response::forbidden('Solo puedes ver tu propio historial');
        // ↑ IDOR = Insecure Direct Object Reference.
        //   Sin este check, un trabajador con ID=42 podría pedir
        //   GET /v1/app/asistencia/99 y ver el historial del trabajador 99.
        //   Solo se permite ver el propio historial.

        $stmt = $this->db->prepare("
            SELECT a.*, s.nombre AS sede_nombre,
                   hs.nombre_turno, hs.hora_entrada, hs.hora_salida
            FROM asistencias a
            LEFT JOIN sedes s          ON a.sede_id = s.id
            LEFT JOIN horarios_sede hs ON a.horario_sede_id = hs.id
            WHERE a.usuario_app_id = :uid
            ORDER BY a.fecha DESC
            LIMIT 60
        ");
        // ↑ LEFT JOIN: incluye la asistencia aunque la sede o el horario hayan sido eliminados.
        //   LIMIT 60: últimos 2 meses aprox (30 días laborables × 2). Evita enviar miles de registros.
        //   ORDER BY fecha DESC: más recientes primero.
        $stmt->execute([':uid' => $userId]);
        Response::success($stmt->fetchAll());
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /v1/app/estado-dia?sede_id=1 — Estado del día actual
    // ═══════════════════════════════════════════════════════════════
    public function estadoDia(Request $req): void
    {
        $userId = $this->userId();
        $sedeId = (int) $req->query('sede_id');
        // ↑ Viene del query string: /v1/app/estado-dia?sede_id=1
        $hoy    = date('Y-m-d');

        // ── Buscar cabecera del día ────────────────────────────
        $stmt = $this->db->prepare("
            SELECT a.*, hs.hora_entrada, hs.hora_salida, hs.nombre_turno,
                   hs.tolerancia_entrada_minutos
            FROM asistencias a
            LEFT JOIN horarios_sede hs ON a.horario_sede_id = hs.id
            WHERE a.usuario_app_id = :uid AND a.sede_id = :sid AND a.fecha = :fecha
        ");
        $stmt->execute([':uid' => $userId, ':sid' => $sedeId, ':fecha' => $hoy]);
        $asistencia = $stmt->fetch();

        // ── Buscar marcaciones del día ─────────────────────────
        $marcaciones = [];
        if ($asistencia) {
            $stmt2 = $this->db->prepare("
                SELECT tipo, marcada_en, estado_marcacion
                FROM asistencias_diarias
                WHERE asistencia_id = :aid ORDER BY marcada_en ASC
            ");
            // ↑ ASC: orden cronológico (ENTRADA antes que SALIDA).
            $stmt2->execute([':aid' => $asistencia['id']]);
            $marcaciones = $stmt2->fetchAll();
        }

        // ── Determinar siguiente acción ────────────────────────
        $tieneEntrada = in_array('ENTRADA', array_column($marcaciones, 'tipo'));
        $tieneSalida  = in_array('SALIDA',  array_column($marcaciones, 'tipo'));
        // ↑ array_column($marcaciones, 'tipo') extrae todos los valores de 'tipo':
        //   [['tipo'=>'ENTRADA', ...], ['tipo'=>'SALIDA', ...]] → ['ENTRADA', 'SALIDA']
        //   in_array busca si 'ENTRADA'/'SALIDA' existe en el array resultante.

        Response::success([
            'server_now'    => date('c'),
            // ↑ Formato ISO 8601: '2025-03-10T14:30:00-05:00'.
            //   La app compara la hora del servidor con la del dispositivo.
            //   Si difieren mucho → muestra advertencia de reloj desincronizado.

            'tiene_entrada' => $tieneEntrada,
            'tiene_salida'  => $tieneSalida,
            'next_action'   => !$tieneEntrada ? 'ENTRADA' : (!$tieneSalida ? 'SALIDA' : null),
            // ↑ Lógica secuencial: primero ENTRADA, luego SALIDA, luego null (completado).
            //   null = ya marcó ambas → el botón de marcación se desactiva en la app.

            'estado_diario' => $asistencia['estado_diario'] ?? 'SIN_REGISTRO',
            'horario'       => $asistencia ? [
                'nombre_turno' => $asistencia['nombre_turno'],
                'hora_entrada' => $asistencia['hora_entrada'],
                'hora_salida'  => $asistencia['hora_salida'],
            ] : null,
            'marcaciones'   => $marcaciones,
        ]);
    }
}
```

---

## 25. `Controllers/App/SedeAppController.php`

```php
<?php
namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class SedeAppController
{
    private \PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    /**
     * GET /v1/app/sedes — Lista las sedes ASIGNADAS al trabajador autenticado.
     * No muestra TODAS las sedes, solo las que le corresponden.
     */
    public function index(Request $req): void
    {
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);

        $stmt = $this->db->prepare("
            SELECT s.id, s.nombre, s.direccion, s.latitud, s.longitud, s.radio,
                   hs.nombre_turno, hs.hora_entrada, hs.hora_salida, uas.cargo
            FROM usuario_app_sede uas
            INNER JOIN sedes s          ON s.id = uas.sede_id
            LEFT JOIN  horarios_sede hs ON hs.id = uas.horario_sede_id
            WHERE uas.usuario_app_id = :uid AND uas.estado = 'ACTIVO'
            ORDER BY s.nombre
        ");
        // ↑ INNER JOIN sedes: solo junta si la sede existe (no muestra asignaciones huérfanas).
        //   LEFT JOIN horarios_sede: puede no tener horario asignado aún (NULL).
        //   WHERE uas.estado = 'ACTIVO': solo asignaciones vigentes.
        //   s.latitud, s.longitud, s.radio: la app necesita estos datos para
        //   dibujar el GEOFENCE (círculo) en el mapa y verificar GPS antes de marcar.

        $stmt->execute([':uid' => $userId]);
        Response::success($stmt->fetchAll());
    }
}
```

---

## 26. `Controllers/App/JustificacionAppController.php`

```php
<?php
namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class JustificacionAppController
{
    private \PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }
    private function userId(): int { return (int) ($_REQUEST['auth_user']['sub'] ?? 0); }

    /**
     * GET /v1/app/justificaciones — Lista MIS justificaciones
     * Solo muestra las del trabajador autenticado, nunca las de otros.
     */
    public function index(Request $req): void
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM justificaciones WHERE usuario_app_id = :uid ORDER BY created_at DESC"
        );
        // ↑ WHERE usuario_app_id = :uid → AISLAMIENTO: cada trabajador solo ve las suyas.
        //   DESC: las más recientes primero.
        $stmt->execute([':uid' => $this->userId()]);
        Response::success($stmt->fetchAll());
    }

    /**
     * GET /v1/app/justificaciones/{id} — Ver detalle de UNA justificación
     */
    public function show(Request $req): void
    {
        $id = (int) $req->param('id');
        $stmt = $this->db->prepare(
            "SELECT * FROM justificaciones WHERE id = :id AND usuario_app_id = :uid"
        );
        // ↑ AND usuario_app_id = :uid → PROTECCIÓN IDOR: solo puede ver SUS justificaciones.
        //   Sin este AND, un trabajador podría ver justificaciones de otro cambiando el ID.
        $stmt->execute([':id' => $id, ':uid' => $this->userId()]);
        $just = $stmt->fetch();
        if (!$just) Response::notFound('Justificación no encontrada');
        Response::success($just);
    }

    /**
     * POST /v1/app/justificaciones — Crear nueva justificación
     */
    public function store(Request $req): void
    {
        $userId = $this->userId();
        $sedeId = (int) $req->input('sede_id');
        $tipo   = (string) $req->input('tipo');

        // ── Validación del tipo contra lista blanca ────────────
        $tipos_validos = [
            'ENFERMEDAD','PERMISO_PERSONAL','LICENCIA','COMISION_SERVICIO',
            'CAPACITACION','DUELO','MATERNIDAD','PATERNIDAD','OLVIDO_MARCACION','OTRO'
        ];
        // ↑ Lista blanca (whitelist) hardcodeada que COINCIDE con el ENUM de la tabla.
        //   Validar en PHP además de MySQL por defensa en profundidad:
        //   si un atacante envía tipo='DROP TABLE', PHP lo rechaza ANTES de llegar a MySQL.

        $errors = [];
        if (!$sedeId) $errors[] = 'sede_id es requerido';
        if (!in_array($tipo, $tipos_validos)) $errors[] = 'tipo de justificación inválido';
        if (!$req->input('fecha_inicio')) $errors[] = 'fecha_inicio es requerida';
        if (!$req->input('fecha_fin')) $errors[] = 'fecha_fin es requerida';
        if (!$req->input('motivo')) $errors[] = 'motivo es requerido';
        if ($errors) Response::unprocessable('Datos incompletos', $errors);
        // ↑ Recopila TODOS los errores antes de retornar, en vez de retornar
        //   al primer error. Así el cliente corrige todo de una vez.

        // ── Verificar que el trabajador está asignado a la sede ──
        $stmt = $this->db->prepare("
            SELECT id FROM usuario_app_sede
            WHERE usuario_app_id = :uid AND sede_id = :sid AND estado = 'ACTIVO'
        ");
        $stmt->execute([':uid' => $userId, ':sid' => $sedeId]);
        if (!$stmt->fetch()) Response::error('No estás asignado a esa sede', 403);
        // ↑ No puede crear justificaciones para sedes donde NO trabaja.

        // ── Insertar justificación con estado PENDIENTE ────────
        $stmt = $this->db->prepare("
            INSERT INTO justificaciones
                (usuario_app_id, sede_id, tipo, fecha_inicio, fecha_fin, motivo, estado)
            VALUES (:uid, :sid, :tipo, :fi, :ff, :motivo, 'PENDIENTE')
        ");
        $stmt->execute([
            ':uid' => $userId, ':sid' => $sedeId, ':tipo' => $tipo,
            ':fi' => $req->input('fecha_inicio'), ':ff' => $req->input('fecha_fin'),
            ':motivo' => $req->input('motivo'),
        ]);
        // ↑ estado='PENDIENTE': todas las justificaciones empiezan en PENDIENTE.
        //   Un admin debe aprobar o rechazar en el panel web.

        Response::success(['id' => $this->db->lastInsertId()], 'Justificación creada correctamente', 201);
    }

    /**
     * DELETE /v1/app/justificaciones/{id} — Eliminar justificación propia
     * Solo se permite eliminar las que están en estado PENDIENTE.
     */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');
        $stmt = $this->db->prepare(
            "SELECT * FROM justificaciones WHERE id = :id AND usuario_app_id = :uid"
        );
        $stmt->execute([':id' => $id, ':uid' => $this->userId()]);
        $just = $stmt->fetch();

        if (!$just) Response::notFound('Justificación no encontrada');
        // ↑ AND usuario_app_id = :uid → IDOR protection (mismo patrón que show())

        if ($just['estado'] !== 'PENDIENTE')
            Response::error('Solo se pueden eliminar justificaciones pendientes', 400);
        // ↑ Una vez APROBADA o RECHAZADA, no se puede eliminar.
        //   Esto porque aprobar/rechazar modifica registros de asistencia:
        //   eliminar la justificación dejaría esas asistencias en estado inconsistente.

        $this->db->prepare("DELETE FROM justificaciones WHERE id = :id")->execute([':id' => $id]);
        Response::success(null, 'Justificación eliminada correctamente');
    }
}
```

---

## 27. `Controllers/App/HorarioAppController.php`

```php
<?php
namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class HorarioAppController
{
    private \PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    /**
     * GET /v1/app/horarios-sede — Horarios disponibles de mi sede
     * La app muestra estos horarios para que el trabajador sepa su turno.
     */
    public function misHorarios(Request $request): void
    {
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);

        // Paso 1: obtener la sede donde el trabajador tiene asignación activa
        $stmt = $this->db->prepare("
            SELECT sede_id FROM usuario_app_sede
            WHERE usuario_app_id = :uid AND estado = 'ACTIVO' LIMIT 1
        ");
        $stmt->execute([':uid' => $userId]);
        $asig = $stmt->fetch();

        if (!$asig) Response::success([]);
        // ↑ Si no tiene asignación activa → array vacío (no es error, solo no tiene sede).

        // Paso 2: obtener todos los horarios activos de esa sede
        $stmt = $this->db->prepare("
            SELECT * FROM horarios_sede WHERE sede_id = :sid AND activo = 1 ORDER BY hora_entrada
        ");
        $stmt->execute([':sid' => $asig['sede_id']]);
        Response::success($stmt->fetchAll());
        // ↑ Devuelve TODOS los horarios de la sede, no solo el del trabajador.
        //   La app los muestra como lista informativa de turnos disponibles.
    }

    /**
     * GET /v1/app/mis-horarios — Alias de misHorarios()
     * Ruta alternativa para la misma funcionalidad.
     */
    public function getMisHorarios(Request $request): void
    {
        $this->misHorarios($request);
    }

    /**
     * POST /v1/app/actualizar-horarios — Sincronizar caché local de horarios
     * La app llama a este endpoint periódicamente para actualizar su caché
     * local de horarios (SharedPreferences/SQLite en el dispositivo).
     */
    public function actualizarHorarios(Request $request): void
    {
        $this->misHorarios($request);
        // ↑ Misma respuesta que misHorarios(). La app lo trata como "sync" de datos.
    }
}
```
