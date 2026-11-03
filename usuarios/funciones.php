<?php
// Funciones internas de administración; las rutas verifican rol y CSRF primero.
function userInput(array $input, bool $creating): array
{
    $values = [];
    foreach (['nombre', 'email', 'rol', 'activo', 'password'] as $field) {
        $values[$field] = is_string($input[$field] ?? null) ? $input[$field] : '';
    }
    $values['nombre'] = trim($values['nombre']);
    $values['email'] = trim($values['email']);
    $errors = [];
    if ($values['nombre'] === '' || mb_strlen($values['nombre'], 'UTF-8') > 120) {
        $errors[] = 'El nombre es obligatorio y admite hasta 120 caracteres.';
    }
    if (strlen($values['email']) > 190 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Escribe un correo electrónico válido de hasta 190 caracteres.';
    }
    if (!in_array($values['rol'], ['admin', 'cajero'], true)) {
        $errors[] = 'Selecciona un rol válido.';
    }
    if (!in_array($values['activo'], ['0', '1'], true)) {
        $errors[] = 'Selecciona un estado válido.';
    }
    $length = strlen($values['password']);
    if (($creating || $length > 0) && ($length < 12 || $length > 72 || str_contains($values['password'], "\0"))) {
        $errors[] = 'La contraseña debe tener entre 12 y 72 bytes, sin caracteres nulos.';
    }
    return [$values, $errors];
}

function saveUser(PDO $db, array $values, ?int $id): int
{
    $db->beginTransaction();
    try {
        // Serializa cambios de administradores para conservar al menos uno activo.
        $admins = $db->query("SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        if ($id !== null) {
            $find = $db->prepare('SELECT id, rol, activo FROM usuarios WHERE id = ? FOR UPDATE');
            $find->execute([$id]);
            $previous = $find->fetch();
            if (!$previous) {
                throw new DomainException('El usuario ya no existe.');
            }
            if ($id === (int) $_SESSION['usuario']['id'] && ($values['rol'] !== 'admin' || $values['activo'] !== '1')) {
                throw new DomainException('No puedes desactivar tu propia cuenta ni quitarte el rol administrador.');
            }
            if ($previous['rol'] === 'admin' && ($values['rol'] !== 'admin' || $values['activo'] !== '1')) {
                $count = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND activo = 1")->fetchColumn();
                if ($previous['activo'] && (int) $count <= 1) {
                    throw new DomainException('Debe existir al menos un administrador activo.');
                }
            }
        }
        $params = [$values['nombre'], $values['email'], $values['rol'], (int) $values['activo']];
        if ($id === null) {
            $params[] = password_hash($values['password'], PASSWORD_DEFAULT);
            $query = $db->prepare('INSERT INTO usuarios (nombre, email, rol, activo, password_hash) VALUES (?, ?, ?, ?, ?)');
        } else {
            $passwordSql = '';
            if ($values['password'] !== '') {
                $passwordSql = ', password_hash = ?';
                $params[] = password_hash($values['password'], PASSWORD_DEFAULT);
            }
            $params[] = $id;
            $query = $db->prepare('UPDATE usuarios SET nombre = ?, email = ?, rol = ?, activo = ?' . $passwordSql . ' WHERE id = ?');
        }
        $query->execute($params);
        $savedId = $id ?? (int) $db->lastInsertId();
        $db->commit();
        return $savedId;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}
