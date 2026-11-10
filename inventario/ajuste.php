<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$title = 'Ajuste de inventario';
require __DIR__ . '/../includes/header.php';

// Obtener productos
$stmt = database()->query("
    SELECT id, codigo, nombre, stock_actual
    FROM productos
    WHERE activo = 1
    ORDER BY nombre ASC
");
$productos = $stmt->fetchAll();
?>

<?php foreach (['flash_error' => 'danger', 'flash_success' => 'success'] as $key => $style): ?>
    <?php if (!empty($_SESSION[$key])): ?>
        <div class="alert alert-<?= e($style) ?>" role="alert"><?= e($_SESSION[$key]) ?></div>
        <?php unset($_SESSION[$key]); ?>
    <?php endif; ?>
<?php endforeach; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Ajuste de inventario</h1>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">← Volver al inventario</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">

                <form action="guardar_movimiento.php" method="POST">

                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="tipo" value="ajuste">

                    <!-- Producto -->
                    <div class="mb-3">
                        <label for="producto_id" class="form-label">Producto <span class="text-danger">*</span></label>
                        <select name="producto_id" id="producto_id" class="form-select" required>
                            <option value="">Selecciona un producto...</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?= (int)$p['id'] ?>">
                                    <?= e($p['codigo']) ?> - <?= e($p['nombre']) ?>
                                    (Stock actual: <?= (int)$p['stock_actual'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tipo de ajuste -->
                    <div class="mb-3">
                        <label class="form-label">Tipo de ajuste <span class="text-danger">*</span></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="direccion" id="suma" value="suma" checked>
                            <label class="form-check-label" for="suma">
                                Sumar al stock (entrada de ajuste)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="direccion" id="resta" value="resta">
                            <label class="form-check-label" for="resta">
                                Restar del stock (salida de ajuste)
                            </label>
                        </div>
                    </div>

                    <!-- Cantidad -->
                    <div class="mb-3">
                        <label for="cantidad" class="form-label">Cantidad <span class="text-danger">*</span></label>
                        <input type="number"
                               name="cantidad"
                               id="cantidad"
                               class="form-control"
                               min="1"
                               step="1"
                               required
                               placeholder="Ej: 3">
                    </div>

                    <!-- Motivo (obligatorio) -->
                    <div class="mb-4">
                        <label for="motivo" class="form-label">Motivo del ajuste <span class="text-danger">*</span></label>
                        <input type="text"
                               name="motivo"
                               id="motivo"
                               class="form-control"
                               required
                               maxlength="255"
                               placeholder="Ej: Producto dañado, conteo físico, error de captura...">
                        <div class="form-text">El motivo es obligatorio para auditoría.</div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning">
                            Registrar ajuste
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
