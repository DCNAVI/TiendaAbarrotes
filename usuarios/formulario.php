<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('admin');
require_once __DIR__ . '/funciones.php';
$editing = basename($_SERVER['SCRIPT_FILENAME']) === 'editar.php';
$id = null;
if ($editing) {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        http_response_code(400);
        exit('Identificador de usuario inválido.');
    }
}
$title = $editing ? 'Editar usuario' : 'Crear usuario';
$values = ['nombre' => '', 'email' => '', 'rol' => 'cajero', 'activo' => '1'];
$errors = [];
$available = true;
try {
    if ($editing) {
        $query = database()->prepare('SELECT nombre, email, rol, activo FROM usuarios WHERE id = ?');
        $query->execute([$id]);
        $record = $query->fetch();
        if (!$record) {
            http_response_code(404);
            exit('Usuario no encontrado.');
        }
        $values = $record;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        [$values, $errors] = userInput($_POST, !$editing);
        if (!$errors) {
            saveUser(database(), $values, $id);
            $_SESSION['usuarios_mensaje'] = $editing ? 'Usuario actualizado.' : 'Usuario creado.';
            header('Location: ' . url('usuarios/index.php'));
            exit;
        }
        http_response_code(422);
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST');
        http_response_code(405);
        exit('Método no permitido.');
    }
} catch (DomainException $exception) {
    http_response_code(422);
    $errors[] = $exception->getMessage();
} catch (PDOException $exception) {
    if (($exception->errorInfo[1] ?? null) === 1062) {
        http_response_code(422);
        $errors[] = 'Ese correo electrónico ya está registrado.';
    } else {
        error_log($exception->getMessage());
        http_response_code(503);
        $errors[] = 'No se pudieron guardar o cargar los datos. Intenta nuevamente.';
        $available = false;
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    $errors[] = 'No se pudo conectar con la base de datos.';
    $available = false;
}
require __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-7">
<h1 class="h3 mb-3"><?= e($title) ?></h1>
<?php foreach ($errors as $error): ?>
<div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endforeach; ?>
<?php if ($available): ?>
<form method="post" class="needs-validation" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
    <label for="nombre" class="form-label">Nombre</label>
    <input id="nombre" name="nombre" class="form-control mb-3" value="<?= e($values['nombre']) ?>" maxlength="120" required autocomplete="name">
    <label for="email" class="form-label">Correo electrónico</label>
    <input id="email" name="email" type="email" class="form-control mb-3" value="<?= e($values['email']) ?>" maxlength="190" required autocomplete="email">
    <label for="password" class="form-label"><?= $editing ? 'Nueva contraseña (opcional)' : 'Contraseña' ?></label>
    <input id="password" name="password" type="password" class="form-control" minlength="12" maxlength="72" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
    <p class="form-text">Entre 12 y 72 bytes. Los caracteres acentuados pueden ocupar más de un byte.<?= $editing ? ' Deja vacío para conservar la contraseña.' : '' ?></p>
    <label for="rol" class="form-label">Rol</label>
    <select id="rol" name="rol" class="form-select mb-3" required>
        <?php foreach (['admin' => 'Administrador', 'cajero' => 'Cajero'] as $role => $label): ?>
        <option value="<?= e($role) ?>" <?= $values['rol'] === $role ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="activo" class="form-label">Estado</label>
    <select id="activo" name="activo" class="form-select mb-3" required>
        <option value="1" <?= (string) $values['activo'] === '1' ? 'selected' : '' ?>>Activo</option>
        <option value="0" <?= (string) $values['activo'] === '0' ? 'selected' : '' ?>>Inactivo</option>
    </select>
    <button class="btn btn-success">Guardar usuario</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('usuarios/index.php')) ?>">Cancelar</a>
</form>
<?php endif; ?>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
