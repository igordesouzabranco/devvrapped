<?php

// Carrega o arquivo .env manualmente se ele existir (ambiente local)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $_ENV[$name] = $value;
        putenv("$name=$value");
    }
}

define('GITHUB_TOKEN', $_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN') ?: '');
define('TIMEZONE', $_ENV['TIMEZONE'] ?? getenv('TIMEZONE') ?: 'America/Sao_Paulo');

define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '');
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? getenv('SMTP_FROM_EMAIL') ?: '');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Devvrapped');


