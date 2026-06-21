<?php

namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Models\UsuarioApp;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * AuthAppController - Login y perfil para la app móvil
 * Esquema v2: usuarios unificados + usuario_roles (TRABAJADOR) + usuarios_trabajador
 */
class AuthAppController
{
    private Response $response;
    private UsuarioApp $model;

    public function __construct()
    {
        $this->response = new Response();
        $this->model    = new UsuarioApp();
    }

    /**
     * POST /v1/app/login
     */
    public function login(Request $request): void
    {
        // Control de Rate Limiting
        \App\Middleware\RateLimitMiddleware::check('login_app');

        $codigo   = trim($request->input('codigo_empleado', ''));
        $password = $request->input('password', '');

        // FIX Bug #2: validationError() no existía en Response. Se reemplaza
        // por Response::unprocessable() que sí existe y acepta array de errores.
        if (!$codigo || !$password) {
            Response::unprocessable('Datos requeridos', [
                'codigo_empleado' => 'Requerido',
                'password'        => 'Requerido',
            ]);
        }

        $usuario = $this->model->findByCodigo($codigo);

        if (!$usuario || !password_verify($password, $usuario['password'])) {
            $this->response->unauthorized('Credenciales incorrectas.');
        }

        // Esquema v2: estado viene de catálogo estados_usuario
        if ($usuario['estado'] !== 'ACTIVO') {
            Response::error('Cuenta deshabilitada. Contacte al administrador.', 403);
        }

        // Limpiar rate limiting ya que las credenciales son correctas
        \App\Middleware\RateLimitMiddleware::clearAttempts('login_app');

        $token = $this->generateToken($usuario, 'app');

        $this->response->success([
            'token'   => $token,
            'usuario' => $this->sanitize($usuario),
        ], 'Login exitoso.');
    }

    /**
     * GET /v1/app/perfil  [protegida]
     */
    public function perfil(Request $request): void
    {
        // FIX Bug #3: Request::getAttribute() no existe. El payload JWT
        // lo inyecta el Middleware en $_REQUEST['auth_user']['sub'].
        $userId  = (int) ($_REQUEST['auth_user']['sub'] ?? 0);
        $usuario = $this->model->findConAsignacion($userId);

        if (!$usuario) {
            Response::notFound('Usuario no encontrado.');
        }

        Response::success($this->sanitize($usuario));
    }

    /**
     * POST /v1/app/logout  [protegida]
     * Registrar el token en blacklist al hacer logout.
     */
    public function logout(Request $request): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = '';
        if (preg_match('/Bearer\s+(.+)/', $header, $m)) {
            $token = $m[1];
        }

        if ($token) {
            try {
                $jwtService = new \App\Services\JwtService();
                $decoded = $jwtService->decodeToken($token);
                $jwtService->blacklistToken($token, (int)$decoded['sub'], (int)$decoded['exp'], 'app');
            } catch (\Exception $e) {
                // Ignorar si el token es inválido o ya expiró
            }
        }

        $this->response->success(null, 'Sesión cerrada.');
    }

    /**
     * POST /v1/app/password/reset-request
     */
    public function requestPasswordReset(Request $request): void
    {
        $dni    = trim($request->input('dni', ''));
        $codigo = trim($request->input('codigo_empleado', ''));

        if (!$dni || !$codigo) {
            Response::unprocessable('Datos requeridos', [
                'dni'             => 'Requerido',
                'codigo_empleado' => 'Requerido',
            ]);
        }

        $usuario = $this->model->findByDniAndCodigo($dni, $codigo);

        if (!$usuario) {
            // Retornamos genérico por seguridad
            Response::error('No se encontró un usuario con esos datos.', 404);
        }

        $this->model->createPasswordResetRequest((int)$usuario['id']);

        Response::success(null, 'Solicitud de recuperación enviada al administrador.');
    }

    /**
     * POST /v1/app/password/change [protegida]
     */
    public function changePassword(Request $request): void
    {
        $userId          = (int) ($_REQUEST['auth_user']['sub'] ?? 0);
        $currentPassword = $request->input('current_password', '');
        $newPassword     = $request->input('new_password', '');

        if (!$currentPassword || !$newPassword) {
            Response::unprocessable('Datos requeridos', [
                'current_password' => 'Requerido',
                'new_password'     => 'Requerido',
            ]);
        }

        if (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            Response::unprocessable('La nueva contraseña debe tener al menos 8 caracteres y contener letras y números.');
        }

        $usuario = $this->model->find($userId);
        if (!$usuario || !password_verify($currentPassword, $usuario['password'])) {
            Response::error('La contraseña actual es incorrecta.', 401);
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->model->updatePassword($userId, $hash);

        Response::success(null, 'Contraseña actualizada correctamente.');
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────────

    private function generateToken(array $usuario, string $type): string
    {
        $jwtService = new \App\Services\JwtService();
        return $jwtService->generateToken((int)$usuario['id'], 'trabajador', $type);
    }

    private function sanitize(array $usuario): array
    {
        unset($usuario['password']);
        return $usuario;
    }
}
