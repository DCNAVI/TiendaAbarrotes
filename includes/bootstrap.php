<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Funciona en la raíz del servidor y en /TiendaAbarrotes dentro de htdocs.
$projectRoot = str_replace('\\', '/', dirname(__DIR__));
$documentRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? $projectRoot), '/');
define('BASE_URL', str_starts_with($projectRoot, $documentRoot . '/')
    ? substr($projectRoot, strlen($documentRoot)) : '');

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
