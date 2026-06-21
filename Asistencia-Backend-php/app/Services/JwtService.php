<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Core\Database;

class JwtService
{
    private string $secret;
    private int $expiration;

    public function __construct()
    {
        $this->secret = $_ENV['JWT_SECRET'] ?? 'secret';
        $this->expiration = (int)($_ENV['JWT_EXPIRATION'] ?? 3600);
    }

    /**
     * Genera un token JWT.
     */
    public function generateToken(int $userId, string $rol, string $type): string
    {
        $payload = [
            'iss'  => 'asistencia-api',
            'iat'  => time(),
            'exp'  => time() + $this->expiration,
            'sub'  => $userId,
            'rol'  => $rol,
            'tipo' => $type,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * Decodifica un token JWT. Retorna array asociativo.
     */
    public function decodeToken(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        return (array) $decoded;
    }

    /**
     * Verifica si el token está registrado en la lista negra.
     */
    public function isBlacklisted(string $token, string $type): bool
    {
        $table = $type === 'web' ? 'tokens_web' : 'tokens_app';
        $db = Database::getInstance();

        try {
            $stmt = $db->prepare("SELECT id FROM `{$table}` WHERE token = ? LIMIT 1");
            $stmt->execute([$token]);
            return (bool) $stmt->fetch();
        } catch (\Exception $e) {
            error_log("[JwtService::isBlacklisted] Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registra un token en la lista negra.
     */
    public function blacklistToken(string $token, int $userId, int $exp, string $type): bool
    {
        $table = $type === 'web' ? 'tokens_web' : 'tokens_app';
        $expiresAt = date('Y-m-d H:i:s', $exp);
        $db = Database::getInstance();

        try {
            $stmt = $db->prepare("INSERT INTO `{$table}` (usuario_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)");
            return $stmt->execute([$userId, $token, $expiresAt]);
        } catch (\Exception $e) {
            error_log("[JwtService::blacklistToken] Error: " . $e->getMessage());
            return false;
        }
    }
}
