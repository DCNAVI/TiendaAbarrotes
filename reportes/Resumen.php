<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_comun.php';

$auth = reportes_autorizar();   // R09
$f = reportes_filtros();        // R02 (mismos filtros que el historial)

$top = $porDia = $porMetodo = [];
$totalGeneral = 0.0;
$errorBD = null;

try {
    $pdo = database();
    [$where, $params] = reportes_condiciones($f, $auth, true);   // sin canceladas

    // R05: productos más vendidos por cantidad (JOIN + GROUP BY)
    $st = $pdo->prepare(
        "SELECT p.codigo, p.nombre,
                SUM(d.cantidad) AS unidades,
                SUM(d.subtotal) AS importe
           FROM venta_detalles d
           JOIN ventas v    ON v.id = d.venta_id
           JOIN productos p ON p.id = d.producto_id
          WHERE $where
          GROUP BY p.id, p.codigo, p.nombre
          ORDER BY unidades DESC, p.nombre ASC
          LIMIT 10"
    );
    $st->execute($params);
    $top = $st->fetchAll(PDO::FETCH_ASSOC);

    // R06: ventas por día
    $st = $pdo->prepare(
        "SELECT DATE(v.fecha) AS dia, COUNT(*) AS ventas, SUM(v.total) AS total
           FROM ventas v
          WHERE $where
          GROUP BY DATE(v.fecha)
          ORDER BY dia ASC"
    );
    $st->execute($params);
    $porDia = $st->fetchAll(PDO::FETCH_ASSOC);

    // R06: ventas por método de pago
    $st = $pdo->prepare(
        "SELECT v.metodo_pago, COUNT(*) AS ventas, SUM(v.total) AS total
           FROM ventas v
          WHERE $where
          GROUP BY v.metodo_pago
          ORDER BY total DESC"
    );
    $st->execute($params);
    $porMetodo = $st->fetchAll(PDO::FETCH_ASSOC);

    // Total general para comprobar que los grupos suman lo mismo
    $st = $pdo->prepare("SELECT COALESCE(SUM(v.total), 0) FROM ventas v WHERE $where");
    $st->execute($params);
    $totalGeneral = (float)$st->fetchColumn();
} catch (Throwable $ex) {
    error_log('[reportes/resumen] ' . $ex->getMessage());
    $errorBD = 'No fue posible generar el resumen. Intente de nuevo más tarde.';
}

$centavos = static fn($n): int => (int)round(((float)$n) * 100);
$sumaDias = array_sum(array_map(static fn($r) => $centavos($r['total']), $porDia));
$sumaMetodos = array_sum(array_map(static fn($r) => $centavos($r['total']), $porMetodo));
$totalCent = $centavos($totalGeneral);

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$chartLabels = json_encode(array_column($porDia, 'dia'), $jsonFlags);
$chartData = json_encode(array_map(static fn($r) => round((float)$r['total'], 2), $porDia), $jsonFlags);

$pageTitle = $titulo = 'Resumen de ventas';
require __DIR__ . '/../includes/header.php';
reportes_render_estilos_impresion();
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Resumen y rankings</h1>
        <button type="button" class="btn btn-outline-dark no-imprimir" onclick="window.print()">Imprimir reporte</button>
    </div>

    <?php reportes_render_nav('resumen', $f); ?>
    <?php reportes_render_errores($f); ?>
    <?php reportes_render_filtros($f, 'resumen.php'); ?>

    <p class="solo-imprimir fw-semibold"><?= e(reportes_texto_periodo($f)) ?></p>

    <?php if ($errorBD): ?>
        <div class="alert alert-danger"><?= e($errorBD) ?></div>
    <?php else: ?>

        <!-- R05 -->
        <h2 class="h5 mt-2">Productos más vendidos (top 10 por cantidad)</h2>
        <div class="table-responsive mb-4">
            <table class="table table-striped align-middle">
                <thead class="table-dark">
                    <tr><th>#</th><th>Código</th><th>Producto</th><th class="text-end">Unidades</th><th class="text-end">Importe</th></tr>
                </thead>
                <tbody>
                <?php if (!$top): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Sin ventas en el periodo seleccionado.</td></tr>
                <?php endif; ?>
                <?php foreach ($top as $i => $p): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e((string)$p['codigo']) ?></td>
                        <td><?= e((string)$p['nombre']) ?></td>
                        <td class="text-end"><?= e((string)(int)$p['unidades']) ?></td>
                        <td class="text-end"><?= e(reportes_dinero($p['importe'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row g-4">
            <!-- R06: por día -->
            <div class="col-12 col-lg-6">
                <h2 class="h5">Ventas por día</h2>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-dark">
                            <tr><th>Día</th><th class="text-end">Ventas</th><th class="text-end">Total</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!$porDia): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Sin datos.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($porDia as $r): ?>
                            <tr>
                                <td><?= e((string)$r['dia']) ?></td>
                                <td class="text-end"><?= e((string)(int)$r['ventas']) ?></td>
                                <td class="text-end"><?= e(reportes_dinero($r['total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">Suma</td>
                                <td class="text-end"><?= e(reportes_dinero($sumaDias / 100)) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- R06: por método -->
            <div class="col-12 col-lg-6">
                <h2 class="h5">Ventas por método de pago</h2>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead class="table-dark">
                            <tr><th>Método</th><th class="text-end">Ventas</th><th class="text-end">Total</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!$porMetodo): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Sin datos.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($porMetodo as $r): ?>
                            <tr>
                                <td><?= e(reportes_etiqueta_metodo((string)$r['metodo_pago'])) ?></td>
                                <td class="text-end"><?= e((string)(int)$r['ventas']) ?></td>
                                <td class="text-end"><?= e(reportes_dinero($r['total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">Suma</td>
                                <td class="text-end"><?= e(reportes_dinero($sumaMetodos / 100)) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <strong>Total general del periodo: <?= e(reportes_dinero($totalGeneral)) ?></strong>
            <?php if ($sumaDias === $totalCent && $sumaMetodos === $totalCent): ?>
                <span class="badge bg-success ms-2 no-imprimir">Los grupos suman el total general</span>
            <?php else: ?>
                <span class="badge bg-danger ms-2">Los grupos no coinciden con el total general</span>
            <?php endif; ?>
        </div>

        <!-- R07: gráfico opcional (si Chart.js no carga, el reporte sigue funcionando) -->
        <?php if ($porDia): ?>
            <h2 class="h5 mt-4">Ingresos por día</h2>
            <div style="max-width: 900px;">
                <canvas id="graficoDias" height="110"></canvas>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
                (function () {
                    if (typeof Chart === 'undefined') { return; }
                    new Chart(document.getElementById('graficoDias'), {
                        type: 'bar',
                        data: {
                            labels: <?= $chartLabels ?>,
                            datasets: [{ label: 'Total por día ($)', data: <?= $chartData ?> }]
                        },
                        options: { scales: { y: { beginAtZero: true } } }
                    });
                })();
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>