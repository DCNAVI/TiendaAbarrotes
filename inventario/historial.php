<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$title = 'Historial de movimientos';
require __DIR__ . '/../includes/header.php';

// ======================
// Filtros
// ======================
$producto_id = (int)($_GET['producto_id'] ?? 0);
$tipo        = $_GET['tipo'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Obtener lista de productos para el filtro
$stmtProd = database()->query("SELECT id, codigo, nombre FROM productos WHERE activo = 1 ORDER BY nombre");
$listaProductos = $stmtProd->fetchAll();

// Armar consulta
$sql = "
    SELECT 
        m.id,
        m.tipo,
        m.cantidad,
        m.motivo,
        m.fecha,
        p.codigo,
        p.nombre AS producto,
        u.nombre AS usuario
    FROM inventario_movimientos m
    INNER JOIN productos p ON p.id = m.producto_id
    LEFT JOIN usuarios u ON u.id = m.usuario_id
    WHERE 1=1
";

$params = [];

if ($producto_id > 0) {
    $sql .= " AND m.producto_id = ?";
    $params[] = $producto_id;
}

if (in_array($tipo, ['entrada', 'ajuste', 'salida'])) {
    $sql .= " AND m.tipo = ?";
    $params[] = $tipo;
}

if ($fecha_desde !== '') {
    $sql .= " AND DATE(m.fecha) >= ?";
    $params[] = $fecha_desde;
}

if ($fecha_hasta !== '') {
    $sql .= " AND DATE(m.fecha) <= ?";
    $params[] = $fecha_hasta;
}

$sql .= " ORDER BY m.fecha DESC LIMIT 200";

$stmt = database()->prepare($sql);
$stmt->execute($params);
$movimientos = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Historial de movimientos</h1>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">← Volver al inventario</a>
</div>

<!-- Filtros -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Producto</label>
                <select name="producto_id" class="form-select">
                    <option value="0">Todos</option>
                    <?php foreach ($listaProductos as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $producto_id == $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['codigo']) ?> - <?= e($p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="entrada" <?= $tipo === 'entrada' ? 'selected' : '' ?>>Entrada</option>
                    <option value="ajuste"  <?= $tipo === 'ajuste'  ? 'selected' : '' ?>>Ajuste</option>
                    <option value="salida"  <?= $tipo === 'salida'  ? 'selected' : '' ?>>Salida</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="<?= e($fecha_desde) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?= e($fecha_hasta) ?>">
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-success me-2">Filtrar</button>
                <a href="historial.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de historial -->
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-success">
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th class="text-center">Cantidad</th>
                        <th>Motivo</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($movimientos) === 0): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No hay movimientos con esos filtros.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movimientos as $m): ?>
                            <tr>
                                <td><?= e(date('d/m/Y H:i', strtotime($m['fecha']))) ?></td>
                                <td>
                                    <code><?= e($m['codigo']) ?></code><br>
                                    <small><?= e($m['producto']) ?></small>
                                </td>
                                <td>
                                    <?php if ($m['tipo'] === 'entrada'): ?>
                                        <span class="badge bg-success">Entrada</span>
                                    <?php elseif ($m['tipo'] === 'ajuste'): ?>
                                        <span class="badge bg-warning text-dark">Ajuste</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Salida</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold">
                                    <?= $m['cantidad'] > 0 ? '+' : '' ?><?= (int)$m['cantidad'] ?>
                                </td>
                                <td><?= e($m['motivo']) ?></td>
                                <td><?= e($m['usuario'] ?? 'Sistema') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>