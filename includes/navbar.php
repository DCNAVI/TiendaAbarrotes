<nav class="navbar navbar-expand-lg navbar-dark bg-success d-print-none">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url()) ?>">Tienda de abarrotes</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-controls="menu" aria-expanded="false" aria-label="Abrir navegación"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="menu">
            <div class="navbar-nav me-auto">
                <?php foreach (['productos/index.php' => 'Productos', 'categorias/index.php' => 'Categorías', 'ventas/nueva.php' => 'Punto de venta', 'inventario/index.php' => 'Inventario', 'reportes/ventas.php' => 'Reportes'] as $route => $label): ?>
                    <a class="nav-link" href="<?= e(url($route)) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
                <?php if (($_SESSION['usuario']['rol'] ?? '') === 'admin'): ?>
                    <a class="nav-link" href="<?= e(url('usuarios/index.php')) ?>">Usuarios</a>
                <?php endif; ?>
            </div>
            <?php if (!empty($_SESSION['usuario'])): ?>
                <form method="post" action="<?= e(url('logout.php')) ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                    <button class="btn btn-outline-light btn-sm">Cerrar sesión</button>
                </form>
            <?php else: ?>
                <a class="btn btn-outline-light btn-sm" href="<?= e(url('login.php')) ?>">Iniciar sesión</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
