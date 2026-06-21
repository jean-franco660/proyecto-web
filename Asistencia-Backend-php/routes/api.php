<?php // phpcs:ignore Generic.Files.LineEndings.InvalidEOLChar

use App\Controllers\App\AuthAppController;
use App\Controllers\App\AsistenciaAppController;
use App\Controllers\App\SedeAppController;
use App\Controllers\App\JustificacionAppController;
use App\Controllers\App\HorarioAppController;
use App\Controllers\Web\AuthWebController;
use App\Controllers\Web\UsuarioWebController;
use App\Controllers\Web\UsuarioAppController;
use App\Controllers\Web\SedeWebController;
use App\Controllers\Web\HorarioWebController;
use App\Controllers\Web\AsistenciaWebController;
use App\Controllers\Web\JustificacionWebController;
use App\Controllers\Web\StatsController;
use App\Controllers\Web\DepartamentoWebController;
use App\Controllers\Web\SolicitudesAusenciaWebController;
use App\Middleware\AuthAppMiddleware;
use App\Middleware\AuthWebMiddleware;

// ══════════════════════════════════════════════
// APP MÓVIL
// ══════════════════════════════════════════════

// Públicas
$router->post('/v1/app/login', [AuthAppController::class, 'login']);
$router->post('/v1/app/password/reset-request', [AuthAppController::class, 'requestPasswordReset']);

// Protegidas (JWT tipo 'app')
$router->authAppGet('/v1/app/perfil', [AuthAppController::class, 'perfil']);
$router->authAppPost('/v1/app/logout', [AuthAppController::class, 'logout']);
$router->authAppPost('/v1/app/password/change', [AuthAppController::class, 'changePassword']);
$router->authAppGet('/v1/app/sedes', [SedeAppController::class, 'index']);

// Asistencia (estáticas ANTES que dinámicas)
$router->authAppPost('/v1/app/asistencia', [AsistenciaAppController::class, 'store']);
$router->authAppPost('/v1/app/asistencias/sincronizar', [AsistenciaAppController::class, 'syncMovil']);
$router->authAppGet('/v1/app/estado-dia', [AsistenciaAppController::class, 'estadoDia']);
$router->authAppGet('/v1/app/asistencia/{usuarioId}', [AsistenciaAppController::class, 'historial']);

// Horarios de sede
$router->authAppGet('/v1/app/horarios', [HorarioAppController::class, 'obtenerHorarios']);

// Justificaciones
$router->authAppGet('/v1/app/justificaciones', [JustificacionAppController::class, 'index']);
$router->authAppPost('/v1/app/justificaciones', [JustificacionAppController::class, 'store']);
$router->authAppGet('/v1/app/justificaciones/{id}', [JustificacionAppController::class, 'show']);
$router->authAppDelete('/v1/app/justificaciones/{id}', [JustificacionAppController::class, 'destroy']);

// ══════════════════════════════════════════════
// PANEL WEB
// ══════════════════════════════════════════════

// Públicas
$router->post('/v1/web/login', [AuthWebController::class, 'login']);
$router->post('/v1/web/verify-2fa', [AuthWebController::class, 'verify2fa']);

// Protegidas (JWT tipo 'web')
$router->authWebPost('/v1/web/logout', [AuthWebController::class, 'logout']);
$router->authWebGet('/v1/web/me', [AuthWebController::class, 'me']);
$router->authWebPut('/v1/web/profile', [AuthWebController::class, 'updateProfile']);

// Gestión de usuarios del panel (Solo administrador)
$router->authWebGet('/v1/web/usuarios-web', [UsuarioWebController::class, 'index']);
$router->authWebPost('/v1/web/usuarios-web', [UsuarioWebController::class, 'store']);
$router->authWebGet('/v1/web/usuarios-web/{id}', [UsuarioWebController::class, 'show']);
$router->authWebPut('/v1/web/usuarios-web/{id}', [UsuarioWebController::class, 'update']);
$router->authWebPatch('/v1/web/usuarios-web/{id}/estado', [UsuarioWebController::class, 'cambiarEstado']);
$router->authWebDelete('/v1/web/usuarios-web/{id}', [UsuarioWebController::class, 'destroy']);


// Gestión de trabajadores
$router->authWebGet('/v1/web/usuarios-app', [UsuarioAppController::class, 'index']);
$router->authWebPost('/v1/web/usuarios-app', [UsuarioAppController::class, 'store']);
$router->authWebGet('/v1/web/usuarios-app/{id}', [UsuarioAppController::class, 'show']);
$router->authWebPut('/v1/web/usuarios-app/{id}', [UsuarioAppController::class, 'update']);
$router->authWebDelete('/v1/web/usuarios-app/{id}', [UsuarioAppController::class, 'destroy']);
$router->authWebPatch('/v1/web/usuarios-app/{id}/estado', [UsuarioAppController::class, 'cambiarEstado']);
$router->authWebPatch('/v1/web/usuarios-app/{id}/horario', [UsuarioAppController::class, 'asignarHorario']);
$router->authWebPost('/v1/web/usuario-app-institucion/{id}/inactivar', [UsuarioAppController::class, 'inactivarAsignacion']);
$router->authWebGet('/v1/web/usuarios-app/import/stats', [UsuarioAppController::class, 'importStats']);
$router->authWebGet('/v1/web/usuarios-app/import/template', [UsuarioAppController::class, 'downloadTemplate']);
$router->authWebPost('/v1/web/usuarios-app/import', [UsuarioAppController::class, 'importar']);

// Recuperación de contraseñas de trabajadores (App)
$router->authWebGet('/v1/web/password-resets-app', [UsuarioAppController::class, 'listarPasswordResets']);
$router->authWebPost('/v1/web/password-resets-app/{id}/aprobar', [UsuarioAppController::class, 'aprobarPasswordReset']);
$router->authWebPost('/v1/web/password-resets-app/{id}/rechazar', [UsuarioAppController::class, 'rechazarPasswordReset']);

// Sedes
$router->authWebGet('/v1/web/sedes', [SedeWebController::class, 'index']);
$router->authWebGet('/v1/web/sedes/mis-sedes', [SedeWebController::class, 'misSedes']);
$router->authWebGet('/v1/web/sedes/import/stats', [SedeWebController::class, 'importStats']);
$router->authWebGet('/v1/web/sedes/import/template', [SedeWebController::class, 'downloadTemplate']);
$router->authWebPost('/v1/web/sedes/import', [SedeWebController::class, 'importar']);
$router->authWebPost('/v1/web/sedes', [SedeWebController::class, 'store']);
$router->authWebGet('/v1/web/sedes/{id}', [SedeWebController::class, 'show']);
$router->authWebPut('/v1/web/sedes/{id}', [SedeWebController::class, 'update']);
$router->authWebDelete('/v1/web/sedes/{id}', [SedeWebController::class, 'destroy']);

// Departamentos
$router->authWebGet('/v1/web/departamentos', [DepartamentoWebController::class, 'index']);
$router->authWebPost('/v1/web/departamentos', [DepartamentoWebController::class, 'store']);
$router->authWebPut('/v1/web/departamentos/{id}', [DepartamentoWebController::class, 'update']);
$router->authWebDelete('/v1/web/departamentos/{id}', [DepartamentoWebController::class, 'destroy']);

// Horarios
$router->authWebGet('/v1/web/horarios', [HorarioWebController::class, 'index']);
$router->authWebPost('/v1/web/horarios', [HorarioWebController::class, 'store']);
$router->authWebPut('/v1/web/horarios/{id}', [HorarioWebController::class, 'update']);
$router->authWebPut('/v1/web/horarios/{id}/dias', [HorarioWebController::class, 'syncDias']);
$router->authWebDelete('/v1/web/horarios/{id}', [HorarioWebController::class, 'destroy']);

// Asistencias (estáticas ANTES que dinámicas)
$router->authWebGet('/v1/web/asistencias/semana', [AsistenciaWebController::class, 'resumenSemanal']);
$router->authWebGet('/v1/web/asistencias/mes-grafico', [AsistenciaWebController::class, 'mesGrafico']);
$router->authWebGet('/v1/web/asistencias', [AsistenciaWebController::class, 'index']);
$router->authWebGet('/v1/web/asistencias/{id}', [AsistenciaWebController::class, 'show']);
$router->authWebPut('/v1/web/asistencias/{id}/review', [AsistenciaWebController::class, 'updateReview']);

// Reportes
$router->authWebGet('/v1/web/reportes/consolidado', [AsistenciaWebController::class, 'reporteConsolidado']);
$router->authWebGet('/v1/web/reportes/individual', [AsistenciaWebController::class, 'reporteIndividual']);
$router->authWebGet('/v1/web/reportes/sedes', [AsistenciaWebController::class, 'reporteSedes']);
$router->authWebGet('/v1/web/reportes/mensual', [AsistenciaWebController::class, 'reporteMensual']);

// Feriados
$router->authWebGet('/v1/web/feriados', [\App\Controllers\Web\FeriadoController::class, 'index']);
$router->authWebPost('/v1/web/feriados', [\App\Controllers\Web\FeriadoController::class, 'store']);
$router->authWebPut('/v1/web/feriados/{id}', [\App\Controllers\Web\FeriadoController::class, 'update']);
$router->authWebDelete('/v1/web/feriados/{id}', [\App\Controllers\Web\FeriadoController::class, 'destroy']);

// Justificaciones
$router->authWebGet('/v1/web/justificaciones', [JustificacionWebController::class, 'index']);
$router->authWebGet('/v1/web/justificaciones/{id}', [JustificacionWebController::class, 'show']);
$router->authWebPost('/v1/web/justificaciones/{id}/aprobar', [JustificacionWebController::class, 'aprobar']);
$router->authWebPost('/v1/web/justificaciones/{id}/rechazar', [JustificacionWebController::class, 'rechazar']);
$router->authWebDelete('/v1/web/justificaciones/{id}', [JustificacionWebController::class, 'destroy']);

// Solicitudes de Ausencia
$router->authWebGet('/v1/web/solicitudes-ausencia', [SolicitudesAusenciaWebController::class, 'index']);
$router->authWebPost('/v1/web/solicitudes-ausencia/{id}/aprobar', [SolicitudesAusenciaWebController::class, 'aprobar']);
$router->authWebPost('/v1/web/solicitudes-ausencia/{id}/rechazar', [SolicitudesAusenciaWebController::class, 'rechazar']);

// Estadísticas
// FIX Bug #12: StatsController solo tiene el método 'dashboard', no 'index'.
$router->authWebGet('/v1/web/stats', [StatsController::class, 'dashboard']);
