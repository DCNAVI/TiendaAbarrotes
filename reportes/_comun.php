<?php
declare(strict_types=1);

/**
 * Funciones compartidas del módulo de reportes (Isacc · feature/reportes).
 * Solo lo usan reportes/ventas.php, reportes/detalle.php y reportes/resumen.php.
 */

// R09: si el equipo acuerda que el cajero vea SUS propias ventas, cambiar a true.
const REPORTES_CAJERO_VE_PROPIAS = false;

const REPORTES_METODOS = [
    'efectivo'      => 'Efectivo',
    'transferencia' => 'Transferencia',
];

// Las ventas con este estado se listan, pero no cuentan en ingresos ni rankings.
const REPORTES_ESTADO_CANCELADA = 'cancelada';

/** Devuelve id y rol del usuario en sesión (tolera varios nombres de clave). */
function reportes_usuario(): array
{
    if (function_exists('currentUser')) {
        $u = currentUser();
        if (is_array($u)) {
            return ['id' => (int)($u['id'] ?? 0), 'rol' => (string)($u['rol'] ?? '')];
        }
    }
    $s = $_SESSION ?? [];
    $u = $s['usuario'] ?? $s['user'] ?? $s;
    return [
        'id'  => (int)($u['id'] ?? $s['usuario_id'] ?? $s['user_id'] ?? 0),
        'rol' => (string)($u['rol'] ?? $s['rol'] ?? ''),
    ];
}

/** R09: exige sesión y rol permitido. Devuelve el contexto de autorización. */
function reportes_autorizar(): array
{
    requireLogin();
    $u = reportes_usuario();
    $esAdmin = $u['rol'] === 'admin';
    $cajeroPermitido = REPORTES_CAJERO_VE_PROPIAS && $u['rol'] === 'cajero';

    if (!$esAdmin && !$cajeroPermitido) {
        http_response_code(403);
        $pageTitle = $titulo = 'Acceso denegado';
        require __DIR__ . '/../includes/header.php';
        echo '<div class="container py-4"><div class="alert alert-danger">'
           . 'Acceso denegado: los reportes de ingresos son solo para administradores.</div></div>';
        require __DIR__ . '/../includes/footer.php';
        exit;
    }
    return ['id' => $u['id'], 'rol' => $u['rol'], 'solo_propias' => !$esAdmin];
}

/** R02: lee y valida filtros por GET. Si algo es inválido usa el valor por defecto y avisa. */
function reportes_filtros(): array
{
    $hoy = new DateTimeImmutable('today');
    $defDesde = $hoy->modify('first day of this month')->format('Y-m-d');
    $defHasta = $hoy->format('Y-m-d');
    $errores = [];

    $leerFecha = function (string $clave, string $etiqueta, string $def) use (&$errores): string {
        $raw = $_GET[$clave] ?? '';
        if (!is_string($raw) || $raw === '') {
            return $def;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if (!$d || $d->format('Y-m-d') !== $raw) {
            $errores[] = "La fecha $etiqueta no es válida (use AAAA-MM-DD).";
            return $def;
        }
        return $raw;
    };

    $desde = $leerFecha('desde', 'inicial', $defDesde);
    $hasta = $leerFecha('hasta', 'final', $defHasta);

    if ($desde > $hasta) {
        $errores[] = 'La fecha inicial no puede ser posterior a la fecha final.';
        $desde = $defDesde;
        $hasta = $defHasta;
    }

    $metodo = $_GET['metodo'] ?? '';
    if (!is_string($metodo) || ($metodo !== '' && !array_key_exists($metodo, REPORTES_METODOS))) {
        $errores[] = 'El método de pago seleccionado no es válido.';
        $metodo = '';
    }

    return ['desde' => $desde, 'hasta' => $hasta, 'metodo' => $metodo, 'errores' => $errores];
}

/**
 * Arma el WHERE (alias "v" para ventas) con parámetros nombrados.
 * $soloValidas = true excluye ventas canceladas (ingresos, rankings).
 */
function reportes_condiciones(array $f, array $auth, bool $soloValidas): array
{
    $hastaSig = (new DateTimeImmutable($f['hasta']))->modify('+1 day')->format('Y-m-d');
    $where = ['v.fecha >= :desde', 'v.fecha < :hasta_sig'];
    $params = [
        ':desde'     => $f['desde'] . ' 00:00:00',
        ':hasta_sig' => $hastaSig . ' 00:00:00',
    ];

    if ($f['metodo'] !== '') {
        $where[] = 'v.metodo_pago = :metodo';
        $params[':metodo'] = $f['metodo'];
    }
    if ($auth['solo_propias']) {
        $where[] = 'v.usuario_id = :uid';
        $params[':uid'] = $auth['id'];
    }
    if ($soloValidas) {
        $where[] = 'v.estado <> :cancelada';
        $params[':cancelada'] = REPORTES_ESTADO_CANCELADA;
    }
    return [implode(' AND ', $where), $params];
}

function reportes_dinero($n): string
{
    return '$' . number_format((float)$n, 2);
}

function reportes_folio($id): string
{
    return str_pad((string)(int)$id, 6, '0', STR_PAD_LEFT);
}

function reportes_etiqueta_metodo(string $m): string
{
    return REPORTES_METODOS[$m] ?? $m;
}

/** Texto del periodo para pantalla e impresión. */
function reportes_texto_periodo(array $f): string
{
    $m = $f['metodo'] === '' ? 'Todos los métodos' : reportes_etiqueta_metodo($f['metodo']);
    return "Periodo: {$f['desde']} al {$f['hasta']} · $m";
}

/** Alertas Bootstrap con los errores de validación de filtros. */
function reportes_render_errores(array $f): void
{
    foreach ($f['errores'] as $msg) {
        echo '<div class="alert alert-warning no-imprimir">' . e($msg) . '</div>';
    }
}

/** Formulario de filtros (GET). $accion es el archivo destino. */
function reportes_render_filtros(array $f, string $accion): void
{
    ?>
    <form method="get" action="<?= e($accion) ?>" class="row g-3 align-items-end mb-4 no-imprimir">
        <div class="col-6 col-md-3">
            <label for="desde" class="form-label">Fecha inicial</label>
            <input type="date" id="desde" name="desde" class="form-control" value="<?= e($f['desde']) ?>">
        </div>
        <div class="col-6 col-md-3">
            <label for="hasta" class="form-label">Fecha final</label>
            <input type="date" id="hasta" name="hasta" class="form-control" value="<?= e($f['hasta']) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label for="metodo" class="form-label">Método de pago</label>
            <select id="metodo" name="metodo" class="form-select">
                <option value="">Todos</option>
                <?php foreach (REPORTES_METODOS as $valor => $texto): ?>
                    <option value="<?= e($valor) ?>" <?= $f['metodo'] === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="<?= e($accion) ?>" class="btn btn-outline-secondary">Limpiar</a>
        </div>
    </form>
    <?php
}

/** R08: oculta menú, filtros, botones y pie al imprimir. */
function reportes_render_estilos_impresion(): void
{
    ?>
    <style>
        .solo-imprimir { display: none; }
        @media print {
            .navbar, nav, footer, .no-imprimir, .btn, form { display: none !important; }
            .solo-imprimir { display: block !important; }
            body { background: #fff !important; }
            .container { max-width: 100% !important; }
            .card { border: 1px solid #999 !important; box-shadow: none !important; }
            table { font-size: 12px; }
            a { color: inherit !important; text-decoration: none !important; }
        }
    </style>
    <?php
}

/** Pestañas de navegación interna del módulo. */
function reportes_render_nav(string $activa, array $f): void
{
    $qs = http_build_query(['desde' => $f['desde'], 'hasta' => $f['hasta'], 'metodo' => $f['metodo']]);
    ?>
    <ul class="nav nav-tabs mb-3 no-imprimir">
        <li class="nav-item">
            <a class="nav-link <?= $activa === 'ventas' ? 'active' : '' ?>" href="ventas.php?<?= e($qs) ?>">Historial de ventas</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activa === 'resumen' ? 'active' : '' ?>" href="resumen.php?<?= e($qs) ?>">Resumen y rankings</a>
        </li>
    </ul>
    <?php
}