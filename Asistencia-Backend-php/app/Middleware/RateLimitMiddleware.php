<?php

namespace App\Middleware;

use App\Core\Database;
use App\Core\Response;

class RateLimitMiddleware
{
    public static function check(string $action, int $maxAttempts = 5, int $decaySeconds = 300): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $db = Database::getInstance();

        try {
            $stmt = $db->prepare("SELECT * FROM login_attempts WHERE ip = ? AND endpoint = ? LIMIT 1");
            $stmt->execute([$ip, $action]);
            $attempt = $stmt->fetch();

            $now = time();

            if ($attempt) {
                $bloqueadoHasta = $attempt['bloqueado_hasta'] ? strtotime($attempt['bloqueado_hasta']) : null;
                $ultimoIntento = strtotime($attempt['ultimo_intento']);

                // Si está bloqueado actualmente
                if ($bloqueadoHasta && $bloqueadoHasta > $now) {
                    $espera = $bloqueadoHasta - $now;
                    Response::error("Demasiados intentos. Por favor, espere {$espera} segundos.", 429);
                    exit();
                }

                // Si ya pasó el tiempo de bloqueo o de decaimiento desde el último intento
                if ($now - $ultimoIntento > $decaySeconds) {
                    // Resetear contador
                    $stmt = $db->prepare("UPDATE login_attempts SET intentos = 1, bloqueado_hasta = NULL, ultimo_intento = NOW() WHERE id = ?");
                    $stmt->execute([$attempt['id']]);
                } else {
                    $nuevosIntentos = $attempt['intentos'] + 1;
                    $bloqueo = null;
                    if ($nuevosIntentos >= $maxAttempts) {
                        $bloqueo = date('Y-m-d H:i:s', $now + $decaySeconds);
                    }

                    $stmt = $db->prepare("UPDATE login_attempts SET intentos = ?, bloqueado_hasta = ?, ultimo_intento = NOW() WHERE id = ?");
                    $stmt->execute([$nuevosIntentos, $bloqueo, $attempt['id']]);

                    if ($nuevosIntentos >= $maxAttempts) {
                        Response::error("Demasiados intentos. Por favor, espere {$decaySeconds} segundos.", 429);
                        exit();
                    }
                }
            } else {
                // Primer intento
                $stmt = $db->prepare("INSERT INTO login_attempts (ip, endpoint, intentos, ultimo_intento) VALUES (?, ?, 1, NOW())");
                $stmt->execute([$ip, $action]);
            }
        } catch (\Throwable $e) {
            // Si la BD falla, no bloquear acceso para no degradar el servicio, pero loguear el error
            error_log("[RateLimitMiddleware] Error: " . $e->getMessage());
        }
    }

    public static function clearAttempts(string $action): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip = ? AND endpoint = ?");
            $stmt->execute([$ip, $action]);
        } catch (\Exception $e) {
            error_log("[RateLimitMiddleware::clearAttempts] Error: " . $e->getMessage());
        }
    }
}
