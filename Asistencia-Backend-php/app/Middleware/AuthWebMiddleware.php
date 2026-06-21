<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Services\JwtService;
use Firebase\JWT\ExpiredException;

class AuthWebMiddleware
{
    public static function handle(): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = '';
        if (preg_match('/Bearer\s+(.+)/', $header, $m)) {
            $token = $m[1];
        } elseif (isset($_GET['token'])) {
            $token = $_GET['token'];
        }

        if (!$token) {
            Response::unauthorized('Token requerido');
        }

        $jwtService = new JwtService();

        // Verificar blacklist
        if ($jwtService->isBlacklisted($token, 'web')) {
            Response::unauthorized('Token revocado');
        }

        try {
            $payload = $jwtService->decodeToken($token);

            if (($payload['tipo'] ?? '') !== 'web') {
                Response::unauthorized('Token no válido para el panel web');
            }

            $_REQUEST['auth_user'] = $payload;
        } catch (ExpiredException $e) {
            Response::unauthorized('Token expirado');
        } catch (\Exception $e) {
            Response::unauthorized('Token inválido');
        }
    }
}
