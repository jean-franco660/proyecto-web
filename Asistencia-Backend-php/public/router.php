<?php

// public/router.php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Si el archivo existe físicamente, servirlo directamente (css, js, imágenes, etc.)
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Todo lo demás va al front controller
require __DIR__ . '/index.php';
