<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/database.php';
echo "Nombre del administrador: ";
$name = trim((string) fgets(STDIN));
echo "Correo: ";
$email = trim((string) fgets(STDIN));
echo "Contraseña (se mostrará en esta terminal, mínimo 12 caracteres): ";
$password = rtrim((string) fgets(STDIN), "\r\n");
if ($name === '' || strlen($name) > 120 || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Datos inválidos. Revisa nombre, correo y longitud de contraseña.\n");
    exit(1);
}
try {
    $statement = database()->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)');
    $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
    echo "Administrador creado.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "No se pudo crear: revisa configuración, esquema y que el correo no exista.\n");
    exit(1);
}
