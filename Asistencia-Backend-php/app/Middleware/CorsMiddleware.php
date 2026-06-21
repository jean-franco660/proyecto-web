<?php

namespace App\Middleware;

class CorsMiddleware
{
    public static function handle(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = explode(',', $_ENV['ALLOWED_ORIGINS'] ?? 'http://localhost:5173,http://localhost:3000');

        if (in_array($origin, $allowedOrigins) || empty($allowedOrigins[0])) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
        } else {
            header("Access-Control-Allow-Origin: *");
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
}
