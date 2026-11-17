<?php
/* ============================================================
   MEDEL - PUNTO DE VENTA
   ventas/ticket.php
   V09: Ticket imprimible de la venta
        - Se abre con el folio: ticket.php?folio=V20251110-A3F2
        - Muestra folio, fecha, cajero, productos, total,
          metodo de pago, monto recibido y cambio
        - Boton Imprimir (window.print) con estilos de ticket
   Nota: usa el mismo detector de columnas que guardar.php,
   asi que funciona aunque tus tablas tengan otros nombres.
   ============================================================ */

/* ------------------------------------------------------------
   CONEXION (detector universal + fallback config/local.php)
   ------------------------------------------------------------ */
 $pdo = null;
try {
    $modulo = require_once __DIR__ . '/../config/database.php';
    if ($modulo instanceof PDO) {
        $pdo = $modulo;
    } elseif ($modulo instanceof Closure) {
        $pdo = $modulo();
    } elseif (function_exists('getConnection')) {
        $pdo = getConnection();
    } elseif (function_exists('conectar')) {
        $pdo = conectar();
    } elseif (function_exists('db')) {
        $pdo = db();
    } elseif (isset($connection) && $connection instanceof PDO) {
        $pdo = $connection;
    } elseif (isset($db) && $db instanceof PDO) {
        $pdo = $db;
    }
} catch (Throwable $e) {
    $pdo = null;
}
if (!$pdo instanceof PDO) {
    try {
        $cfg = require __DIR__ . '/../config/local.php';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $cfg['host'], $cfg['port'], $cfg['database']);
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        die('Error de conexion: ' . $e->getMessage());
    }
}
 $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* Mismo detector de columnas que guardar.php */
function elegirColumna($candidatas, $reales, $excluir = []) {
    foreach ($candidatas as $c) {
        foreach ($reales as $r) {
            if (strtolower($r) === strtolower($c)) { return $r; }
        }
    }
    foreach ($candidatas as $c) {
        foreach ($reales as $r) {
            $rl = strtolower($r);
            if (strpos($rl, strtolower($c)) !== false) {
                $saltar = false;
                foreach ($excluir as $x) {
                    if (strpos($rl, $x) !== false) { $saltar = true; break; }
                }
                if (!$saltar) { return $r; }
            }
        }
    }
    return '';
}

function columnasDe($pdo, $tabla) {
    $lista = [];
    foreach ($pdo->query('SHOW COLUMNS FROM ' . $tabla, PDO::FETCH_ASSOC) as $c) {
        $lista[] = $c['Field'];
    }
    return $lista;
}

/* Pagina de error amigable (mismo estilo que la tienda) */
function paginaError($titulo, $detalle) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">';
    echo '<title>Medel - Ticket</title>';
    echo '<style>body{font-family:Segoe UI,Arial,sans-serif;background:#f0f4f8;padding:40px;}';
    echo '.caja{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;}';
    echo 'h1{font-size:20px;color:#0f3460;} p{color:#475569;font-size:14px;line-height:1.5;}';
    echo 'a{color:#0f3460;font-weight:bold;}</style></head><body><div class="caja">';
    echo '<h1>' . htmlspecialchars($titulo, ENT_QUOTES) . '</h1>';
    echo '<p>' . htmlspecialchars($detalle, ENT_QUOTES) . '</p>';
    echo '<p><a href="nueva.php">&laquo; Volver a nueva venta</a></p>';
    echo '</div></body></html>';
    exit;
}

/* ------------------------------------------------------------
   DETECTAR COLUMNAS DE ventas Y detalle_venta
   ------------------------------------------------------------ */
 $folio = (isset($_GET['folio']) && is_string($_GET['folio'])) ? trim($_GET['folio']) : '';

try {
    $colsVentas = columnasDe($pdo, 'ventas');
} catch (Throwable $e) {
    paginaError('Todavia no hay ventas', 'La tabla ventas no existe en la base de datos. Haz primero una venta en la pantalla Nueva venta.');
}
 $colVFolio    = elegirColumna(['folio'], $colsVentas);
 $colVId       = elegirColumna(['id', 'id_venta'], $colsVentas);
 $colVFecha    = elegirColumna(['fecha', 'fecha_venta', 'created_at'], $colsVentas);
 $colVCajero   = elegirColumna(['cajero', 'usuario', 'vendedor', 'empleado'], $colsVentas);
 $colVMetodo   = elegirColumna(['metodo_pago', 'forma_pago', 'metodo', 'tipo_pago'], $colsVentas);
 $colVRecibido = elegirColumna(['monto_recibido', 'recibido'], $colsVentas);
 $colVCambio   = elegirColumna(['cambio'], $colsVentas);
 $colVTotal    = elegirColumna(['total', 'importe', 'monto'], $colsVentas);
if ($colVFolio === '' || $colVTotal === '') {
    paginaError('Tabla ventas incompleta', 'La tabla ventas no tiene folio o total. Columnas reales: ' . implode(', ', $colsVentas));
}
if ($colVId === '') { $colVId = 'id'; }

try {
    $colsDetalle = columnasDe($pdo, 'detalle_venta');
} catch (Throwable $e) {
    paginaError('Detalle no disponible', 'La tabla detalle_venta no existe en la base de datos.');
}
 $colDVenta  = elegirColumna(['venta_id', 'id_venta', 'venta'], $colsDetalle);
 $colDCant   = elegirColumna(['cantidad'], $colsDetalle);
 $colDPrecio = elegirColumna(['precio_unitario', 'precio', 'precio_venta'], $colsDetalle);
 $colDSub    = elegirColumna(['subtotal', 'importe'], $colsDetalle);
 $colDCod    = elegirColumna(['codigo', 'codigo_producto'], $colsDetalle);
 $colDNom    = elegirColumna(['nombre', 'nombre_producto', 'descripcion'], $colsDetalle);
if ($colDVenta === '' || $colDCant === '' || $colDPrecio === '' || $colDSub === '') {
    paginaError('Tabla detalle incompleta', 'La tabla detalle_venta no tiene las columnas esenciales. Columnas reales: ' . implode(', ', $colsDetalle));
}

if ($folio === '') {
    paginaError('Falta el folio', 'Abre el ticket desde el enlace "Ver ticket" que aparece al cobrar una venta, o agrega ?folio=XXX a la direccion.');
}

/* Buscar la venta por folio */
 $stmt = $pdo->prepare("SELECT * FROM ventas WHERE $colVFolio = :f LIMIT 1");
 $stmt->execute([':f' => $folio]);
 $venta = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$venta) {
    paginaError('Venta no encontrada', 'No encontre ninguna venta con el folio "' . $folio . '". Verifica el folio o haz de nuevo la venta.');
}

/* Traer el detalle de la venta */
try {
    $stmtD = $pdo->prepare("SELECT * FROM detalle_venta WHERE $colDVenta = :v");
    $stmtD->execute([':v' => $venta[$colVId]]);
    $lineas = $stmtD->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    paginaError('Detalle no disponible', 'No pude leer el detalle de la venta: ' . $e->getMessage());
}

/* Datos de cabecera */
 $fechaTxt = '';
if ($colVFecha !== '' && isset($venta[$colVFecha]) && $venta[$colVFecha] !== null && (string)$venta[$colVFecha] !== '') {
    $ts = strtotime((string)$venta[$colVFecha]);
    $fechaTxt = ($ts ? date('d/m/Y H:i', $ts) : (string)$venta[$colVFecha]);
}
 $cajeroTxt = 'Cajero';
if ($colVCajero !== '' && isset($venta[$colVCajero]) && (string)$venta[$colVCajero] !== '') {
    $cajeroTxt = (string)$venta[$colVCajero];
}
 $metodoTxt = '';
if ($colVMetodo !== '' && isset($venta[$colVMetodo]) && (string)$venta[$colVMetodo] !== '') {
    $metodoTxt = (string)$venta[$colVMetodo];
}
 $metodoLower = strtolower($metodoTxt);
 $totalNum = (float)$venta[$colVTotal];
 $recibidoNum = null;
if ($colVRecibido !== '' && isset($venta[$colVRecibido]) && $venta[$colVRecibido] !== null) {
    $recibidoNum = (float)$venta[$colVRecibido];
}
 $cambioNum = null;
if ($colVCambio !== '' && isset($venta[$colVCambio]) && $venta[$colVCambio] !== null) {
    $cambioNum = (float)$venta[$colVCambio];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Medel - Ticket <?php echo htmlspecialchars($folio, ENT_QUOTES); ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f8; padding: 24px; }
.barra { max-width: 380px; margin: 0 auto 14px; display: flex; gap: 8px; }
.barra button, .barra a { flex: 1; padding: 10px 14px; border: 0; border-radius: 6px; cursor: pointer; font-size: 15px; font-weight: bold; }
.btn-imprimir { background: #0f3460; color: #fff; }
.btn-imprimir:hover { background: #16294d; }
.btn-volver { background: #e2e8f0; color: #0f3460; text-decoration: none; text-align: center; }
.btn-volver:hover { background: #cbd5e1; }
.ticket { max-width: 380px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 22px 20px; }
.t-center { text-align: center; }
.marca { font-size: 22px; font-weight: bold; color: #0f3460; letter-spacing: 2px; }
.sub { font-size: 12px; color: #64748b; }
.sep { border: 0; border-top: 1px dashed #94a3b8; margin: 12px 0; }
.dato { font-size: 13px; color: #475569; margin: 2px 0; }
.dato b { color: #1f2937; }
.renglon { margin: 7px 0; font-size: 13px; overflow: hidden; }
.renglon .nombre { font-weight: bold; color: #1f2937; }
.renglon .cant { color: #0f3460; }
.renglon .monto { float: right; color: #1f2937; }
.renglon .detalle { color: #64748b; font-size: 12px; }
.total-line { display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; color: #0f3460; margin-top: 6px; }
.pago-line { display: flex; justify-content: space-between; font-size: 13px; color: #475569; margin-top: 4px; }
.gracias { text-align: center; font-size: 12px; color: #64748b; margin-top: 10px; }
@media print {
    body { background: #fff; padding: 0; }
    .barra { display: none; }
    .ticket { border: 0; max-width: 100%; padding: 0; }
}
</style>
</head>
<body>

<div class="barra">
    <button type="button" class="btn-imprimir" onclick="window.print()">Imprimir ticket</button>
    <a class="btn-volver" href="nueva.php">Nueva venta</a>
</div>

<div class="ticket">
    <p class="marca t-center">MEDEL</p>
    <p class="sub t-center">Punto de venta &mdash; Abarrotes</p>
    <hr class="sep">
    <p class="dato">Folio: <b><?php echo htmlspecialchars($folio, ENT_QUOTES); ?></b></p>
    <?php if ($fechaTxt !== ''): ?>
    <p class="dato">Fecha: <b><?php echo htmlspecialchars($fechaTxt, ENT_QUOTES); ?></b></p>
    <?php endif; ?>
    <p class="dato">Cajero: <b><?php echo htmlspecialchars($cajeroTxt, ENT_QUOTES); ?></b></p>
    <hr class="sep">

    <?php foreach ($lineas as $l): ?>
    <div class="renglon">
        <span class="cant"><?php echo (int)$l[$colDCant]; ?> x</span>
        <span class="nombre"><?php echo htmlspecialchars(($colDNom !== '' && isset($l[$colDNom]) ? (string)$l[$colDNom] : 'Producto'), ENT_QUOTES); ?></span>
        <span class="monto">$<?php echo number_format((float)$l[$colDSub], 2); ?></span>
        <?php if ($colDCod !== '' && isset($l[$colDCod]) && (string)$l[$colDCod] !== ''): ?>
        <div class="detalle">C&oacute;digo: <?php echo htmlspecialchars((string)$l[$colDCod], ENT_QUOTES); ?> &middot; $<?php echo number_format((float)$l[$colDPrecio], 2); ?> c/u</div>
        <?php else: ?>
        <div class="detalle">$<?php echo number_format((float)$l[$colDPrecio], 2); ?> c/u</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <hr class="sep">
    <div class="total-line"><span>TOTAL</span><span>$<?php echo number_format($totalNum, 2); ?></span></div>
    <?php if ($metodoTxt !== ''): ?>
    <div class="pago-line"><span>M&eacute;todo de pago:</span><span><?php echo htmlspecialchars(ucfirst($metodoTxt), ENT_QUOTES); ?></span></div>
    <?php endif; ?>
    <?php if ($metodoLower === 'efectivo' && $recibidoNum !== null): ?>
    <div class="pago-line"><span>Recibido:</span><span>$<?php echo number_format($recibidoNum, 2); ?></span></div>
    <?php endif; ?>
    <?php if ($metodoLower === 'efectivo' && $cambioNum !== null): ?>
    <div class="pago-line"><span>Cambio:</span><span>$<?php echo number_format($cambioNum, 2); ?></span></div>
    <?php endif; ?>
    <hr class="sep">
    <p class="gracias">&iexcl;Gracias por su compra!</p>
</div>

</body>
</html>