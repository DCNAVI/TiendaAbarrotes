<?php
declare(strict_types=1);

function requireLogin(): void
{
    header('Cache-Control: no-store');
    if (!empty($_SESSION['usuario'])) {
        try {
            $query = database()->prepare('SELECT id, nombre, email, rol FROM usuarios WHERE id = ? AND activo = 1');
            $query->execute([$_SESSION['usuario']['id']]);
            $current = $query->fetch();
            if ($current) {
                $_SESSION['usuario'] = $current;
            } else {
                unset($_SESSION['usuario'], $_SESSION['csrf']);
                session_regenerate_id(true);
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            http_response_code(503);
            exit('No se pudo verificar la sesión. Intenta nuevamente.');
        }
    }
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
