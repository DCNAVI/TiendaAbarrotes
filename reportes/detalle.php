<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_comun.php';

$auth = reportes_autorizar();   // R09

$venta = null;
$detalles = [];
$mensaje = null;
$tipoMensaje = 'danger';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$id) {
    http_response_code(400);
    $mensaje = 'Folio de venta inválido.';
} else {
    try {
        $pdo = database();
        $st = $pdo->prepare(
            "SELECT v.id, v.usuario_id, v.fecha, v.total, v.metodo_pago, v.efectivo_recibido,
                    v.cambio, v.estado, u.nombre AS cajero
               FROM ventas v
               LEFT JOIN usuarios u ON u.id = v.usuario_id
              WHERE v.id = :id"
        );
        $st->execute([':id' => $id]);
        $venta = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        // Un cajero (si se acuerda) solo puede ver sus ventas: se responde igual que "no existe".
        if ($venta && $auth['solo_propias'] && (int)$venta['usuario_id'] !== $auth['id']) {
            $venta = null;
        }

        if (!$venta) {
            http_response_code(404);
            $mensaje = 'La venta solicitada no existe.';
        } else {
            $st = $pdo->prepare(
                "SELECT p.codigo, p.nombre, d.cantidad, d.precio_unitario, d.subtotal
                   FROM venta_detalles d
                   JOIN productos p ON p.id = d.producto_id
                  WHERE d.venta_id = :id
                  ORDER BY d.id"
            );
            $st->execute([':id' => $id]);
            $detalles = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $ex) {
        error_log('[reportes/detalle] ' . $ex->getMessage());
        $venta = null;
        $mensaje = 'No fue posible consultar la venta. Intente de nuevo más tarde.';
    }
}

// Criterio R03: la suma de subtotales debe coincidir con el total (comparado en centavos).
$sumaCentavos = 0;
foreach ($detalles as $d) {
    $sumaCentavos += (int)round(((float)$d['subtotal']) * 100);
}
$coincide = $venta && $sumaCentavos === (int)round(((float)$venta['total']) * 100);

$pageTitle = $titulo = 'Detalle de venta';
require __DIR__ . '/../includes/header.php';
reportes_render_estilos_impresion();
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Detalle de venta<?= $venta ? ' #' . e(reportes_folio($venta['id'])) : '' ?></h1>
        <div class="d-flex gap-2 no-imprimir">
            <a href="ventas.php" class="btn btn-outline-secondary">Volver al historial</a>
            <?php if ($venta): ?>
                <button type="button" class="btn btn-outline-dark" onclick="window.print()">Imprimir</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= e($tipoMensaje) ?>"><?= e($mensaje) ?></div>
    <?php elseif ($venta): ?>
        <div class="card mb-4">
            <div class="card-body row g-3">
                <div class="col-6 col-md-3"><div class="text-muted small">Fecha</div><?= e((string)$venta['fecha']) ?></div>
                <div class="col-6 col-md-3"><div class="text-muted small">Cajero</div><?= e((string)($venta['cajero'] ?? '—')) ?></div>
                <div class="col-6 col-md-3"><div class="text-muted small">Método de pago</div><?= e(reportes_etiqueta_metodo((string)$venta['metodo_pago'])) ?></div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">Estado</div>
                    <span class="badge <?= $venta['estado'] === REPORTES_ESTADO_CANCELADA ? 'bg-danger' : 'bg-success' ?>"><?= e((string)$venta['estado']) ?></span>
                </div>
                <?php if ($venta['metodo_pago'] === 'efectivo'): ?>
                    <div class="col-6 col-md-3"><div class="text-muted small">Efectivo recibido</div><?= e(reportes_dinero($venta['efectivo_recibido'])) ?></div>
                    <div class="col-6 col-md-3"><div class="text-muted small">Cambio</div><?= e(reportes_dinero($venta['cambio'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Precio unitario</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$detalles): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Esta venta no tiene renglones de detalle.</td></tr>
                <?php endif; ?>
                <?php foreach ($detalles as $d): ?>
                    <tr>
                        <td><?= e((string)$d['codigo']) ?></td>
                        <td><?= e((string)$d['nombre']) ?></td>
                        <td class="text-end"><?= e((string)(int)$d['cantidad']) ?></td>
                        <td class="text-end"><?= e(reportes_dinero($d['precio_unitario'])) ?></td>
                        <td class="text-end"><?= e(reportes_dinero($d['subtotal'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="4" class="text-end">Total de la venta</td>
                        <td class="text-end"><?= e(reportes_dinero($venta['total'])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (!$coincide): ?>
            <div class="alert alert-warning">
                Inconsistencia: la suma de los subtotales (<?= e(reportes_dinero($sumaCentavos / 100)) ?>)
                no coincide con el total registrado. Revisar el guardado de la venta.
            </div>
        <?php else: ?>
            <p class="text-success small no-imprimir">✔ La suma de los subtotales coincide con el total.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>