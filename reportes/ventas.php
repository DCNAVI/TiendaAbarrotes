<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_comun.php';

$auth = reportes_autorizar();          // R09
$f = reportes_filtros();               // R02

$ventas = [];
$kpi = ['num' => 0, 'ingresos' => 0.0, 'ticket' => 0.0];
$errorBD = null;

try {
    $pdo = database();

    // R01: listado (incluye canceladas, marcadas con su estado)
    [$where, $params] = reportes_condiciones($f, $auth, false);
    $st = $pdo->prepare(
        "SELECT v.id, v.fecha, v.metodo_pago, v.total, v.estado, u.nombre AS cajero
           FROM ventas v
           LEFT JOIN usuarios u ON u.id = v.usuario_id
          WHERE $where
          ORDER BY v.fecha DESC, v.id DESC"
    );
    $st->execute($params);
    $ventas = $st->fetchAll(PDO::FETCH_ASSOC);

    // R04: indicadores (solo ventas no canceladas)
    [$whereV, $paramsV] = reportes_condiciones($f, $auth, true);
    $st = $pdo->prepare(
        "SELECT COUNT(*) AS num, COALESCE(SUM(v.total), 0) AS ingresos
           FROM ventas v
          WHERE $whereV"
    );
    $st->execute($paramsV);
    $fila = $st->fetch(PDO::FETCH_ASSOC);
    $kpi['num'] = (int)$fila['num'];
    $kpi['ingresos'] = (float)$fila['ingresos'];
    $kpi['ticket'] = $kpi['num'] > 0 ? $kpi['ingresos'] / $kpi['num'] : 0.0;
} catch (Throwable $ex) {
    error_log('[reportes/ventas] ' . $ex->getMessage());
    $errorBD = 'No fue posible consultar las ventas. Intente de nuevo más tarde.';
}

$pageTitle = $titulo = 'Historial de ventas';
require __DIR__ . '/../includes/header.php';
reportes_render_estilos_impresion();
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Historial de ventas</h1>
        <button type="button" class="btn btn-outline-dark no-imprimir" onclick="window.print()">Imprimir reporte</button>
    </div>

    <?php reportes_render_nav('ventas', $f); ?>
    <?php reportes_render_errores($f); ?>
    <?php reportes_render_filtros($f, 'ventas.php'); ?>

    <p class="solo-imprimir fw-semibold"><?= e(reportes_texto_periodo($f)) ?></p>

    <?php if ($errorBD): ?>
        <div class="alert alert-danger"><?= e($errorBD) ?></div>
    <?php else: ?>

        <!-- R04: indicadores -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="text-muted small">Número de ventas</div>
                        <div class="display-6 fw-bold"><?= e((string)$kpi['num']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="text-muted small">Ingresos</div>
                        <div class="display-6 fw-bold"><?= e(reportes_dinero($kpi['ingresos'])) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="text-muted small">Ticket promedio</div>
                        <div class="display-6 fw-bold"><?= e(reportes_dinero($kpi['ticket'])) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <p class="text-muted small no-imprimir">Los indicadores no incluyen ventas canceladas.</p>

        <!-- R01: tabla -->
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Cajero</th>
                        <th>Método</th>
                        <th class="text-end">Total</th>
                        <th>Estado</th>
                        <th class="no-imprimir"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$ventas): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay ventas en el periodo seleccionado.</td></tr>
                <?php endif; ?>
                <?php foreach ($ventas as $v): ?>
                    <?php $cancelada = $v['estado'] === REPORTES_ESTADO_CANCELADA; ?>
                    <tr class="<?= $cancelada ? 'text-decoration-line-through text-muted' : '' ?>">
                        <td><?= e(reportes_folio($v['id'])) ?></td>
                        <td><?= e((string)$v['fecha']) ?></td>
                        <td><?= e((string)($v['cajero'] ?? '—')) ?></td>
                        <td><?= e(reportes_etiqueta_metodo((string)$v['metodo_pago'])) ?></td>
                        <td class="text-end"><?= e(reportes_dinero($v['total'])) ?></td>
                        <td><span class="badge <?= $cancelada ? 'bg-danger' : 'bg-success' ?>"><?= e((string)$v['estado']) ?></span></td>
                        <td class="no-imprimir text-end">
                            <a class="btn btn-sm btn-outline-primary" href="detalle.php?id=<?= (int)$v['id'] ?>">Ver detalle</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>