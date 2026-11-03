<?php
require_once __DIR__ . '/includes/bootstrap.php';
$title = 'Iniciar sesión';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Escribe un correo válido y tu contraseña.';
    } else {
        try {
            $query = database()->prepare('SELECT id, nombre, password_hash, rol FROM usuarios WHERE email = ? AND activo = 1');
            $query->execute([$email]);
            $user = $query->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                unset($user['password_hash']);
                $_SESSION['usuario'] = $user;
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                header('Location: ' . url());
                exit;
            }
            $error = 'Correo o contraseña incorrectos.';
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $error = 'No se pudo conectar. Revisa la configuración y la base de datos.';
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-6 col-lg-4">
    <h1 class="h3 mb-3">Iniciar sesión</h1>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <label class="form-label" for="email">Correo electrónico</label>
        <input class="form-control mb-3" type="email" id="email" name="email" autocomplete="username" required maxlength="190">
        <label class="form-label" for="password">Contraseña</label>
        <input class="form-control mb-3" type="password" id="password" name="password" autocomplete="current-password" required>
        <button class="btn btn-success w-100">Entrar</button>
    </form>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
