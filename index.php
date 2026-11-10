<?php
require_once __DIR__ . '/includes/bootstrap.php';
$title = 'Inicio | Tienda de abarrotes';
require __DIR__ . '/includes/header.php';
?>
<h1 class="mb-3">Punto de venta para tienda de abarrotes</h1>
<p class="lead">Base del proyecto colaborativo. Selecciona un módulo para comenzar.</p>
<div class="alert alert-warning">Las pantallas de los módulos son plantillas. El CRUD, las ventas y los reportes todavía deben implementarse.</div>
<div class="row g-3">
<?php foreach ([
    ['Productos y categorías', 'Giselle', 'productos/index.php'],
    ['Punto de venta', 'Medel', 'ventas/nueva.php'],
    ['Inventario', 'Cristo', 'inventario/index.php'],
    ['Historial y reportes', 'Isacc', 'reportes/ventas.php'],
    ['Usuarios e integración', 'Ivan', 'usuarios/index.php'],
] as [$label, $owner, $route]): ?>
    <div class="col-md-6 col-lg-4"><div class="card h-100"><div class="card-body">
        <h2 class="h5"><?= e($label) ?></h2><p class="text-secondary">Responsable: <?= e($owner) ?></p>
        <a class="btn btn-success" href="<?= e(url($route)) ?>">Abrir módulo</a>
    </div></div></div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
