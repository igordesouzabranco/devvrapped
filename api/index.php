<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/send.php' || $path === '/send') {
    require __DIR__ . '/../send.php';
} else {
    require __DIR__ . '/../index.php';
}
