<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('admin');
$title = 'Usuarios';
$users = [];
$error = '';
try {
    $users = database()->query('SELECT id, nombre, email, rol, activo FROM usuarios ORDER BY nombre, id')->fetchAll();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    $error = 'No se pudieron cargar los usuarios. Revisa la conexión con la base de datos.';
}
$message = $_SESSION['usuarios_mensaje'] ?? '';
unset($_SESSION['usuarios_mensaje']);
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Usuarios</h1>
    <a class="btn btn-success" href="<?= e(url('usuarios/crear.php')) ?>">Crear usuario</a>
</div>
<?php if ($message): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="table-responsive"><table class="table table-striped align-middle">
    <thead><tr><th scope="col">Nombre</th><th scope="col">Correo</th><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($users as $user): ?>
    <tr>
        <td><?= e($user['nombre']) ?></td><td><?= e($user['email']) ?></td>
        <td><?= $user['rol'] === 'admin' ? 'Administrador' : 'Cajero' ?></td>
        <td><span class="badge <?= $user['activo'] ? 'bg-success' : 'bg-secondary' ?>"><?= $user['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
        <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('usuarios/editar.php?id=' . $user['id'])) ?>" aria-label="Editar a <?= e($user['nombre']) ?>">Editar</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$users && !$error): ?><tr><td colspan="5">No hay usuarios registrados.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
