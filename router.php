<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$rotas = [
    '/'          => 'api/index.php',
    '/index.php' => 'api/index.php',
    '/send.php'  => 'api/send.php',
];

if (isset($rotas[$path])) {
    require __DIR__ . '/' . $rotas[$path];
    return true;
}

return false;