<?php

/**
 * Front Controller
 * Único punto de entrada a la aplicación
 */

define('BASE_PATH', dirname(__DIR__));

// Cargar autoloader de Composer
require BASE_PATH . '/vendor/autoload.php';

// Cargar variables de entorno desde .env
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#'))
            continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Headers de seguridad HTTP
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS centralizado (lee ALLOWED_ORIGINS del .env)
App\Middleware\CorsMiddleware::handle();

// Cargar rutas
$router = new App\Core\Router();
require BASE_PATH . '/routes/api.php';

// Despachar la petición
$request = new App\Core\Request();
$router->dispatch($request);