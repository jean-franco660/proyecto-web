<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Services\JwtService;
use Firebase\JWT\ExpiredException;

class AuthAppMiddleware
{
    public static function handle(): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s+(.+)/', $header, $m)) {
            Response::unauthorized('Token requerido');
        }

        $token = $m[1];
        $jwtService = new JwtService();

        // Verificar blacklist
        if ($jwtService->isBlacklisted($token, 'app')) {
            Response::unauthorized('Token revocado');
        }

        try {
            $payload = $jwtService->decodeToken($token);

            // Verificar que es token de app
            if (($payload['tipo'] ?? '') !== 'app') {
                Response::unauthorized('Token no válido para la app móvil');
            }

            $_REQUEST['auth_user'] = $payload;
        } catch (ExpiredException $e) {
            Response::unauthorized('Token expirado');
        } catch (\Exception $e) {
            Response::unauthorized('Token inválido');
        }
    }
}
