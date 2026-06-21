<?php // phpcs:ignore Generic.Files.LineEndings.InvalidEOLChar

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Models\UsuarioWeb;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthWebController
{
    private UsuarioWeb $model;

    public function __construct()
    {
        $this->model = new UsuarioWeb();
    }

    public function login(Request $req): void
    {
        // Control de Rate Limiting
        \App\Middleware\RateLimitMiddleware::check('login_web');

        $email    = strtolower(trim((string) $req->input('email')));
        $password = (string) $req->input('password');

        if (!$email || !$password) {
            Response::unprocessable('Email y contraseña son requeridos');
        }

        $user = $this->model->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            Response::unauthorized('Credenciales incorrectas');
        }

        // Esquema v2: estado viene del catálogo estados_usuario
        if ($user['estado'] !== 'ACTIVO') {
            Response::error('Cuenta deshabilitada. Contacte al administrador.', 403);
        }

        $disable2FA = false; // Habilitado por defecto

        if (!$disable2FA) {
            // Limpiar intentos de rate limit ya que la contraseña fue correcta
            \App\Middleware\RateLimitMiddleware::clearAttempts('login_web');

            // Generar código 2FA de 6 dígitos
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $this->model->saveVerificationCode((int)$user['id'], $code);

            // Enviar código por correo electrónico
            $this->send2FACodeEmail($user['email'], $code);

            // Generar un token temporal para la verificación
            $tempPayload = [
                'sub'  => $user['id'],
                'tipo' => '2fa',
                'exp'  => time() + 600, // 10 minutos
            ];
            $tempToken = JWT::encode($tempPayload, $_ENV['JWT_SECRET'], 'HS256');

            // Enmascarar el email para privacidad (ej: j****@gmail.com)
            $emailParts = explode('@', $user['email']);
            $maskedEmail = substr($emailParts[0], 0, 1) . str_repeat('*', strlen($emailParts[0]) - 1) . '@' . $emailParts[1];

            $responseData = [
                'requires_2fa' => true,
                'temp_token'   => $tempToken,
                'message'      => "Se ha enviado un código de verificación a $maskedEmail"
            ];
            if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
                $responseData['dev_code'] = $code; // Solo para facilitar pruebas locales
            }

            Response::success($responseData, 'Verificación de dos pasos requerida');
        } else {
            // LOGIN DIRECTO (2FA DESACTIVADO)
            // Limpiar intentos de rate limit
            \App\Middleware\RateLimitMiddleware::clearAttempts('login_web');

            $rolMap = ['ADMIN' => 'administrador', 'SUPERVISOR' => 'supervisor'];
            $rolNormalizado = $rolMap[$user['rol']] ?? strtolower($user['rol']);

            $jwtService = new \App\Services\JwtService();
            $token = $jwtService->generateToken((int)$user['id'], $rolNormalizado, 'web');
            unset($user['password']);
            $user['rol'] = $rolNormalizado;
            if (isset($user['nombre_staff'])) {
                $user['nombre'] = $user['nombre_staff'];
            }

            Response::success([
                'token'   => $token,
                'usuario' => $user,
            ], 'Inicio de sesión exitoso');
        }
    }

    /** POST /v1/web/verify-2fa */
    public function verify2fa(Request $req): void
    {
        $tempToken = (string) $req->input('temp_token');
        $code      = (string) $req->input('code');

        if (!$tempToken || !$code) {
            Response::unprocessable('Token temporal y código son requeridos');
        }

        try {
            $decoded = JWT::decode($tempToken, new Key($_ENV['JWT_SECRET'], 'HS256'));
        } catch (\Exception $e) {
            Response::unauthorized('Token temporal inválido o expirado');
            return;
        }

        if (($decoded->tipo ?? '') !== '2fa') {
            Response::unauthorized('Token inválido para esta operación');
        }

        $userId = (int) $decoded->sub;
        $verData = $this->model->getVerificationCode($userId);

        if (!$verData || !$verData['verification_code']) {
            Response::unauthorized('No hay un código pendiente para este usuario');
        }

        if (strtotime($verData['verification_expires_at']) < time()) {
            Response::unauthorized('El código de verificación ha expirado');
        }

        if ($verData['verification_code'] !== $code) {
            Response::unauthorized('Código de verificación incorrecto');
        }

        // Si es correcto, limpiar código
        $this->model->clearVerificationCode($userId);

        // Obtener usuario completo y generar token final
        $user = $this->model->find($userId);

        $rolMap = ['ADMIN' => 'administrador', 'SUPERVISOR' => 'supervisor'];
        $rolNormalizado = $rolMap[$user['rol']] ?? strtolower($user['rol']);

        $jwtService = new \App\Services\JwtService();
        $token = $jwtService->generateToken((int)$user['id'], $rolNormalizado, 'web');
        unset($user['password']);
        $user['rol'] = $rolNormalizado;
        if (isset($user['nombre_staff'])) {
            $user['nombre'] = $user['nombre_staff'];
        }

        Response::success([
            'token'   => $token,
            'usuario' => $user,
        ], 'Inicio de sesión exitoso');
    }

    /** GET /v1/web/me */
    public function me(Request $req): void
    {
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);
        $user   = $this->model->find($userId);
        if (!$user) {
            Response::notFound('Usuario no encontrado');
        }
        unset($user['password']);
        if (isset($user['rol'])) {
            $rolMap = ['ADMIN' => 'administrador', 'SUPERVISOR' => 'supervisor'];
            $user['rol'] = $rolMap[$user['rol']] ?? strtolower($user['rol']);
        }
        if (isset($user['nombre_staff'])) {
            $user['nombre'] = $user['nombre_staff'];
        }
        Response::success($user);
    }

    /** POST /v1/web/logout */
    public function logout(Request $req): void
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
                $jwtService->blacklistToken($token, (int)$decoded['sub'], (int)$decoded['exp'], 'web');
            } catch (\Exception $e) {
                // Si el token es inválido o ya expiró, se ignora el error
            }
        }

        Response::success(null, 'Sesión cerrada correctamente');
    }

    /** PUT /v1/web/profile — actualizar perfil del usuario autenticado */
    public function updateProfile(Request $req): void
    {
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);
        if (!$userId) {
            Response::unauthorized('Usuario no autenticado');
        }

        $nombre = $req->input('nombre');
        $email = $req->input('email');
        $password = $req->input('password');
        $currentPassword = $req->input('current_password');

        $errors = [];
        if ($nombre !== null && trim((string)$nombre) === '') {
            $errors[] = 'El nombre no puede estar vacío';
        }
        if ($email !== null) {
            $email = strtolower(trim((string)$email));
            if ($email === '') {
                $errors[] = 'El email no puede estar vacío';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido';
            }
        }
        
        $updatingPassword = ($password !== null && trim((string)$password) !== '');
        
        if ($updatingPassword) {
            if (!\App\Core\Validator::isSecurePassword((string)$password)) {
                $errors[] = 'La contraseña nueva debe tener al menos 8 caracteres y contener letras y números';
            }
            if ($currentPassword === null || trim((string)$currentPassword) === '') {
                $errors[] = 'Debes ingresar tu contraseña actual para cambiarla';
            }
        }

        if ($errors) {
            Response::unprocessable('Datos inválidos', $errors);
        }

        // Obtener usuario actual
        $user = $this->model->find($userId);
        if (!$user) {
            Response::notFound('Usuario no encontrado');
        }

        // Si se cambia la contraseña, validar contraseña actual
        if ($updatingPassword) {
            $stmt = $this->model->db()->prepare("SELECT password FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $hash = $stmt->fetchColumn();
            if (!$hash || !password_verify((string)$currentPassword, $hash)) {
                Response::error('La contraseña actual es incorrecta', 422);
            }
        }

        // Validar email único si cambió
        if ($email !== null && $email !== strtolower($user['email'])) {
            $stmt = $this->model->db()->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                Response::error('El email ya está registrado por otro usuario', 422);
            }
        }

        $this->model->db()->beginTransaction();
        try {
            // Actualizar tabla usuarios
            $camposUser = [];
            $paramsUser = [];
            if ($email !== null) {
                $camposUser[] = "`email` = ?";
                $paramsUser[] = $email;
            }
            if ($updatingPassword) {
                $camposUser[] = "`password` = ?";
                $paramsUser[] = password_hash((string)$password, PASSWORD_BCRYPT);
            }
            if ($camposUser) {
                $paramsUser[] = $userId;
                $stmt = $this->model->db()->prepare("UPDATE usuarios SET " . implode(', ', $camposUser) . " WHERE id = ?");
                $stmt->execute($paramsUser);
            }

            // Actualizar tabla usuarios_staff
            if ($nombre !== null) {
                $stmt = $this->model->db()->prepare("
                    INSERT INTO usuarios_staff (usuario_id, nombre) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)
                ");
                $stmt->execute([$userId, trim((string)$nombre)]);
            }

            $this->model->db()->commit();
            Response::success(null, 'Perfil actualizado correctamente');
        } catch (\Exception $e) {
            if ($this->model->db()->inTransaction()) {
                $this->model->db()->rollBack();
            }
            error_log('[AuthWebController::updateProfile] Error: ' . $e->getMessage());
            Response::error('Error al actualizar el perfil. Intente nuevamente.', 500);
        }
    }

    private function send2FACodeEmail(string $toEmail, string $code): void
    {
        $mail = new PHPMailer(true);
        try {
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USERNAME'] ?? 'tu-correo@gmail.com';
            $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? 'tu-password-de-aplicacion';
            $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);

            $encryption = $_ENV['MAIL_ENCRYPTION'] ?? '';
            if (strtolower($encryption) === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif (strtolower($encryption) === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
            }

            // Remitente y destinatario
            $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? $mail->Username;
            $fromName    = $_ENV['MAIL_FROM_NAME'] ?? 'Sistema de Asistencia';
            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($toEmail);

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = '=?UTF-8?B?' . base64_encode('Tu código de verificación de Asistencia') . '?=';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
                    <h2 style='color: #2563eb; text-align: center;'>Verificación de Seguridad</h2>
                    <p>Hola,</p>
                    <p>Has intentado iniciar sesión en el panel web. Para continuar, por favor ingresa el siguiente código de verificación (válido por 10 minutos):</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <span style='font-size: 28px; font-weight: bold; letter-spacing: 5px; background: #f3f4f6; padding: 10px 20px; border-radius: 4px; color: #111827;'>$code</span>
                    </div>
                    <p>Si no has solicitado este código, por favor ignora este correo y asegúrate de que tu contraseña sea segura.</p>
                    <p style='color: #6b7280; font-size: 12px; text-align: center; margin-top: 40px;'>Este es un correo automático, no respondas a este mensaje.</p>
                </div>
            ";

            $mail->send();
        } catch (Exception $e) {
            error_log("No se pudo enviar el correo de 2FA. Error: {$mail->ErrorInfo}");
            if (($_ENV['APP_ENV'] ?? 'development') !== 'development') {
                Response::error('No se pudo enviar el correo de verificación. Contacte soporte.', 500);
            }
        }
    }
}

