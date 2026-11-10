<?php
declare(strict_types=1);

function requireLogin(): void
{
    if (empty($_SESSION['usuario'])) {
        header('Location: ' . url('login.php'));
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();
    if (($_SESSION['usuario']['rol'] ?? '') !== $role) {
        http_response_code(403);
        exit('Acceso denegado.');
    }
}

function csrfToken(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function verifyCsrf(): void
{
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        exit('Token CSRF inválido. Recarga el formulario.');
    }
}
