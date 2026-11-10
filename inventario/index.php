<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$title = 'Inventario';
require __DIR__ . '/../includes/header.php';

// ======================
// Filtros (I02)
// ======================
$busqueda = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$soloBajo = isset($_GET['bajo']) && $_GET['bajo'] === '1';

$sql = "
    SELECT
        id,
        codigo,
        nombre,
        stock_actual,
        stock_minimo,
        activo
    FROM productos
    WHERE activo = 1
";

$params = [];

// Filtro por texto
if ($busqueda !== '') {
    $sql .= " AND (nombre LIKE ? OR codigo LIKE ?)";
    $params[] = '%' . $busqueda . '%';
    $params[] = '%' . $busqueda . '%';
}

// Filtro solo stock bajo
if ($soloBajo) {
    $sql .= " AND stock_actual <= stock_minimo";
}

$sql .= " ORDER BY nombre ASC";

$stmt = database()->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();
?>

<?php foreach (['flash_error' => 'danger', 'flash_success' => 'success'] as $key => $style): ?>
    <?php if (!empty($_SESSION[$key])): ?>
        <div class="alert alert-<?= e($style) ?>" role="alert"><?= e($_SESSION[$key]) ?></div>
        <?php unset($_SESSION[$key]); ?>
    <?php endif; ?>
<?php endforeach; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Inventario</h1>
    <div>
        <a href="entrada.php" class="btn btn-success btn-sm">+ Entrada</a>
        <a href="ajuste.php" class="btn btn-warning btn-sm">Ajuste</a>
        <a href="historial.php" class="btn btn-outline-secondary btn-sm">Historial</a>
    </div>
</div>

<!-- ======================
     Formulario de filtros
====================== -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="q" class="form-label">Buscar producto</label>
                <input type="text" name="q" id="q" class="form-control"
                       placeholder="Nombre o código..."
                       value="<?= e($busqueda) ?>">
            </div>

            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="bajo" value="1" id="bajo"
                        <?= $soloBajo ? 'checked' : '' ?>>
                    <label class="form-check-label" for="bajo">
                        Solo stock bajo
                    </label>
                </div>
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-success w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- ======================
     Tabla de inventario
====================== -->
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-success">
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th class="text-center">Stock Actual</th>
                        <th class="text-center">Stock Mínimo</th>
                        <th class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($productos) === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No se encontraron productos con esos filtros.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $p): ?>
                            <?php $esBajo = $p['stock_actual'] <= $p['stock_minimo']; ?>
                            <tr>
                                <td><code><?= e($p['codigo']) ?></code></td>
                                <td><?= e($p['nombre']) ?></td>
                                <td class="text-center fw-bold"><?= (int)$p['stock_actual'] ?></td>
                                <td class="text-center"><?= (int)$p['stock_minimo'] ?></td>
                                <td class="text-center">
                                    <?php if ($esBajo): ?>
                                        <span class="badge bg-danger">Stock bajo</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Normal</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
