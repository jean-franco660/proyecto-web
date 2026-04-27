<?php
namespace App\Middleware;

use App\Core\Response;

class RateLimitMiddleware
{
    public static function check(string $action, int $maxAttempts = 5, int $decaySeconds = 300): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $key = 'rate_limit_' . $action;
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'time' => time()];
        }

        if (time() - $_SESSION[$key]['time'] > $decaySeconds) {
            $_SESSION[$key] = ['attempts' => 0, 'time' => time()];
        }

        if ($_SESSION[$key]['attempts'] >= $maxAttempts) {
            Response::error('Demasiados intentos. Por favor, espere unos minutos.', 429);
        }

        $_SESSION[$key]['attempts']++;
    }

    public static function clearAttempts(string $action): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['rate_limit_' . $action]);
    }
}
