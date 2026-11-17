<?php
/* ============================================================
   MEDEL - PUNTO DE VENTA
   ventas/guardar.php
   V06: Guarda la venta validando TODO en el servidor
        - Recibe SOLO id + cantidad por POST (JSON); nunca precios
        - Los precios reales se leen de MySQL al momento de cobrar
        - Valida sesion, carrito, cantidades, stock y metodo de pago
        - En efectivo, rechaza si el recibido es menor que el total
   V07: Transaccion PDO + bloqueo de filas (SELECT ... FOR UPDATE)
        - Si dos cajas cobran a la vez, la segunda espera el
          bloqueo y ve el stock ya descontado: NO hay sobreventa
        - Si algo falla a medias, ROLLBACK: no queda venta sin
          detalle ni stock descontado sin venta
   V08: Descuenta stock y registra movimientos de salida
        - UPDATE productos SET stock = stock - cantidad
        - INSERT en movimientos (tipo salida, referencia = folio)
   Nota: detecta las columnas reales de tu base (mismo detector
   que nueva.php) y crea las tablas ventas/detalle_venta/
   movimientos si todavia no existen.
   ============================================================ */

session_start();
header('Content-Type: application/json; charset=utf-8');

/* V06: toda respuesta de este archivo es JSON */
function responder($ok, $datos, $mensaje) {
    echo json_encode(array_merge(['ok' => $ok, 'mensaje' => $mensaje], $datos));
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    responder(false, [], 'No hay sesion activa.');
}

/* Mismo detector de columnas que usa ventas/nueva.php.
   Pasada 1: coincidencia exacta; pasada 2: contiene la palabra.
   $excluir descarta falsos positivos (stock_minimo, costo, etc). */
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

/* Lista de columnas reales de una tabla */
function columnasDe($pdo, $tabla) {
    $lista = [];
    foreach ($pdo->query('SHOW COLUMNS FROM ' . $tabla, PDO::FETCH_ASSOC) as $c) {
        $lista[] = $c['Field'];
    }
    return $lista;
}

/* Agrega columnas que falten (por si la tabla ya existia con otra forma) */
function asegurarColumnas($pdo, $tabla, $mapa) {
    try {
        $actuales = columnasDe($pdo, $tabla);
    } catch (Throwable $e) {
        return;
    }
    foreach ($mapa as $nombre => $sqlColumna) {
        $existe = false;
        foreach ($actuales as $a) {
            if (strtolower($a) === strtolower($nombre)) { $existe = true; break; }
        }
        if (!$existe) {
            try {
                $pdo->exec('ALTER TABLE ' . $tabla . ' ADD COLUMN ' . $sqlColumna);
            } catch (Throwable $e) {
                /* si no se puede alterar, la deteccion decidira */
            }
        }
    }
}

/* ============================================================
   V06 - VALIDACION DE LA PETICION (antes de tocar la base)
   ============================================================ */
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, [], 'Solo se acepta POST.');
}

 $cuerpo = file_get_contents('php://input');
 $datos = json_decode((string)$cuerpo, true);
if (!is_array($datos)) {
    responder(false, [], 'Cuerpo de la peticion invalido: se esperaba JSON.');
}

 $carritoEntrada = (isset($datos['carrito']) && is_array($datos['carrito'])) ? $datos['carrito'] : [];
 $metodo = (isset($datos['metodo']) && is_string($datos['metodo'])) ? strtolower(trim($datos['metodo'])) : '';
 $recibido = (isset($datos['recibido']) && is_numeric($datos['recibido'])) ? (float)$datos['recibido'] : null;

if ($metodo !== 'efectivo' && $metodo !== 'transferencia') {
    responder(false, [], 'Metodo de pago invalido: usa efectivo o transferencia.');
}
if (count($carritoEntrada) === 0) {
    responder(false, [], 'El carrito esta vacio.');
}

/* Validar cada renglon y juntar cantidades del mismo producto */
 $renglones = [];
foreach ($carritoEntrada as $r) {
    if (!is_array($r) || !isset($r['id']) || !isset($r['cantidad'])) {
        responder(false, [], 'Renglon invalido en el carrito.');
    }
    $id = (int)$r['id'];
    $cant = (int)$r['cantidad'];
    if ($id <= 0) {
        responder(false, [], 'Id de producto invalido.');
    }
    if ($cant <= 0) {
        responder(false, [], 'La cantidad debe ser un entero mayor que 0.');
    }
    if ((string)$r['cantidad'] !== (string)$cant) {
        responder(false, [], 'La cantidad debe ser ENTERA (sin decimales).');
    }
    if (isset($renglones[$id])) {
        $renglones[$id]['cantidad'] = $renglones[$id]['cantidad'] + $cant;
    } else {
        $renglones[$id] = ['id' => $id, 'cantidad' => $cant];
    }
}
 $renglones = array_values($renglones);

/* V06: cajero desde la sesion (cuando exista login, aqui llega) */
 $cajero = 'Cajero';
if (isset($_SESSION['usuario_nombre']) && is_string($_SESSION['usuario_nombre']) && $_SESSION['usuario_nombre'] !== '') {
    $cajero = $_SESSION['usuario_nombre'];
} elseif (isset($_SESSION['usuario']) && is_string($_SESSION['usuario']) && $_SESSION['usuario'] !== '') {
    $cajero = $_SESSION['usuario'];
}

/* ============================================================
   CONEXION (detector universal + fallback config/local.php)
   ============================================================ */
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
        responder(false, [], 'No hay conexion a la base de datos: ' . $e->getMessage());
    }
}
 $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ============================================================
   DETECTAR LA TABLA Y COLUMNAS REALES DE PRODUCTOS
   ============================================================ */
 $tablaProductos = 'productos';
 $colCodigo = '';
 $colNombre = '';
 $colPrecio = '';
 $colStock = '';
 $listaColumnas = '';
try {
    $tablas = [];
    foreach ($pdo->query('SHOW TABLES', PDO::FETCH_ASSOC) as $t) {
        $tablas[] = strtolower((string)current($t));
    }
    $tablaProductos = elegirColumna(['productos', 'producto', 'articulos', 'products'], $tablas);
    if ($tablaProductos === '') { $tablaProductos = 'productos'; }

    $colsTabla = columnasDe($pdo, $tablaProductos);
    $listaColumnas = implode(', ', $colsTabla);

    $colCodigo = elegirColumna(['codigo', 'clave', 'sku'], $colsTabla);
    $colNombre = elegirColumna(['nombre', 'descripcion', 'producto'], $colsTabla);
    $colPrecio = elegirColumna(['precio_venta', 'precio_unitario', 'pvp', 'precio_publico', 'precio_menudeo', 'precio'], $colsTabla, ['compra', 'costo', 'proveedor']);
    $colStock  = elegirColumna(['stock', 'existencias', 'existencia', 'inventario', 'piezas', 'unidades', 'cantidad', 'disponible', 'cant', 'qty'], $colsTabla, ['minimo', 'maximo', 'reorden']);
} catch (Throwable $e) {
    responder(false, [], 'No pude leer la estructura de la base: ' . $e->getMessage());
}

 $faltan = [];
if ($colCodigo === '') { $faltan[] = 'codigo'; }
if ($colNombre === '') { $faltan[] = 'nombre'; }
if ($colPrecio === '') { $faltan[] = 'precio'; }
if ($colStock === '') { $faltan[] = 'stock'; }
if (count($faltan) > 0) {
    responder(false, [], 'Faltan columnas en ' . $tablaProductos . ': ' . implode(', ', $faltan) . '. Columnas reales: ' . $listaColumnas);
}

/* ============================================================
   PREPARAR LAS TABLAS DE VENTA (se crean si no existen)
   ============================================================ */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ventas (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        folio VARCHAR(30) NOT NULL UNIQUE,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        cajero VARCHAR(80) NOT NULL DEFAULT 'Cajero',
        metodo_pago VARCHAR(20) NOT NULL,
        monto_recibido DECIMAL(10,2) NULL,
        cambio DECIMAL(10,2) NULL,
        total DECIMAL(10,2) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS detalle_venta (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        venta_id INT UNSIGNED NOT NULL,
        producto_id INT UNSIGNED NOT NULL,
        codigo VARCHAR(30) NULL,
        nombre VARCHAR(120) NULL,
        cantidad INT UNSIGNED NOT NULL,
        precio_unitario DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS movimientos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        tipo VARCHAR(20) NOT NULL,
        producto_id INT UNSIGNED NOT NULL,
        cantidad INT UNSIGNED NOT NULL,
        referencia VARCHAR(60) NULL,
        cajero VARCHAR(80) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    responder(false, [], 'No se pudieron preparar las tablas de venta: ' . $e->getMessage());
}

/* Si las tablas ya existian con otra forma, agregar lo que falte */
asegurarColumnas($pdo, 'ventas', [
    'folio' => 'folio VARCHAR(30) NULL',
    'total' => 'total DECIMAL(10,2) NULL',
    'fecha' => 'fecha DATETIME NULL',
    'cajero' => 'cajero VARCHAR(80) NULL',
    'metodo_pago' => 'metodo_pago VARCHAR(20) NULL',
    'monto_recibido' => 'monto_recibido DECIMAL(10,2) NULL',
    'cambio' => 'cambio DECIMAL(10,2) NULL',
]);
asegurarColumnas($pdo, 'detalle_venta', [
    'venta_id' => 'venta_id INT NULL',
    'producto_id' => 'producto_id INT NULL',
    'cantidad' => 'cantidad INT NULL',
    'precio_unitario' => 'precio_unitario DECIMAL(10,2) NULL',
    'subtotal' => 'subtotal DECIMAL(10,2) NULL',
    'codigo' => 'codigo VARCHAR(30) NULL',
    'nombre' => 'nombre VARCHAR(120) NULL',
]);
asegurarColumnas($pdo, 'movimientos', [
    'tipo' => 'tipo VARCHAR(20) NULL',
    'producto_id' => 'producto_id INT NULL',
    'cantidad' => 'cantidad INT NULL',
    'referencia' => 'referencia VARCHAR(60) NULL',
    'cajero' => 'cajero VARCHAR(80) NULL',
]);

/* Detectar columnas de las tablas de venta */
 $colsVentas = columnasDe($pdo, 'ventas');
 $colVFolio    = elegirColumna(['folio'], $colsVentas);
 $colVFecha    = elegirColumna(['fecha', 'fecha_venta', 'created_at'], $colsVentas);
 $colVCajero   = elegirColumna(['cajero', 'usuario', 'vendedor', 'empleado'], $colsVentas);
 $colVMetodo   = elegirColumna(['metodo_pago', 'forma_pago', 'metodo', 'tipo_pago'], $colsVentas);
 $colVRecibido = elegirColumna(['monto_recibido', 'recibido'], $colsVentas);
 $colVCambio   = elegirColumna(['cambio'], $colsVentas);
 $colVTotal    = elegirColumna(['total', 'importe', 'monto'], $colsVentas);
if ($colVFolio === '' || $colVTotal === '') {
    responder(false, [], 'La tabla ventas no tiene las columnas esenciales. Columnas reales: ' . implode(', ', $colsVentas));
}

 $colsDetalle = columnasDe($pdo, 'detalle_venta');
 $colDVenta  = elegirColumna(['venta_id', 'id_venta', 'venta'], $colsDetalle);
 $colDProd   = elegirColumna(['producto_id', 'id_producto', 'producto'], $colsDetalle);
 $colDCant   = elegirColumna(['cantidad'], $colsDetalle);
 $colDPrecio = elegirColumna(['precio_unitario', 'precio', 'precio_venta'], $colsDetalle);
 $colDSub    = elegirColumna(['subtotal', 'importe'], $colsDetalle);
 $colDCod    = elegirColumna(['codigo', 'codigo_producto'], $colsDetalle);
 $colDNom    = elegirColumna(['nombre', 'nombre_producto', 'descripcion'], $colsDetalle);
if ($colDVenta === '' || $colDProd === '' || $colDCant === '' || $colDPrecio === '' || $colDSub === '') {
    responder(false, [], 'La tabla detalle_venta no tiene las columnas esenciales. Columnas reales: ' . implode(', ', $colsDetalle));
}

 $colsMovs = columnasDe($pdo, 'movimientos');
 $colMTipo   = elegirColumna(['tipo', 'movimiento', 'tipo_movimiento'], $colsMovs);
 $colMProd   = elegirColumna(['producto_id', 'id_producto', 'producto'], $colsMovs);
 $colMCant   = elegirColumna(['cantidad'], $colsMovs);
 $colMFecha  = elegirColumna(['fecha', 'fecha_movimiento', 'created_at'], $colsMovs);
 $colMRef    = elegirColumna(['referencia', 'documento', 'folio'], $colsMovs);
 $colMCajero = elegirColumna(['cajero', 'usuario', 'vendedor'], $colsMovs);
 $hayMovimientos = ($colMTipo !== '' && $colMProd !== '' && $colMCant !== '');

/* V07: FOR UPDATE exige InnoDB; convertir la tabla si es necesario */
try {
    $info = $pdo->query("SHOW TABLE STATUS LIKE '" . $tablaProductos . "'", PDO::FETCH_ASSOC)->fetch();
    if ($info && isset($info['Engine']) && strtolower((string)$info['Engine']) !== 'innodb') {
        $pdo->exec('ALTER TABLE ' . $tablaProductos . ' ENGINE = InnoDB');
    }
} catch (Throwable $e) {
    /* si no se pudo convertir, el FOR UPDATE se intenta igual */
}

/* ============================================================
   V06+V07+V08 - TRANSACCION: validar, cobrar, descontar, registrar
   ============================================================ */
 $folio = 'V' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
 $total = 0.0;
 $cambio = null;
 $idVenta = 0;

try {
    $pdo->beginTransaction();

    /* V07: bloquear cada renglon mientras se valida y cobra */
    $sqlP = "SELECT id, $colCodigo AS codigo, $colNombre AS nombre, $colPrecio AS precio, $colStock AS stock FROM $tablaProductos WHERE id = :id FOR UPDATE";
    $stmtP = $pdo->prepare($sqlP);

    $lineas = [];
    foreach ($renglones as $r) {
        $stmtP->execute([':id' => $r['id']]);
        $prod = $stmtP->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            throw new Exception('El producto con id ' . $r['id'] . ' no existe.');
        }
        $stockActual = (int)$prod['stock'];
        if ($r['cantidad'] > $stockActual) {
            throw new Exception('Stock insuficiente de "' . $prod['nombre'] . '": quedan ' . $stockActual . ' y se piden ' . $r['cantidad'] . '.');
        }
        /* V06: el precio SIEMPRE viene de MySQL, no del navegador */
        $precio = (float)$prod['precio'];
        if ($precio <= 0) {
            throw new Exception('Precio invalido en la base para "' . $prod['nombre'] . '".');
        }
        $sub = round($precio * $r['cantidad'], 2);
        $total = round($total + $sub, 2);
        $lineas[] = [
            'id' => (int)$prod['id'],
            'codigo' => (string)$prod['codigo'],
            'nombre' => (string)$prod['nombre'],
            'cantidad' => $r['cantidad'],
            'precio' => $precio,
            'sub' => $sub,
        ];
    }

    /* V05/V06: en efectivo, el recibido debe cubrir el total */
    if ($metodo === 'efectivo') {
        if ($recibido === null) {
            throw new Exception('Falta el monto recibido en efectivo.');
        }
        if ($recibido < $total) {
            throw new Exception('El efectivo (' . number_format($recibido, 2) . ') es MENOR que el total (' . number_format($total, 2) . ').');
        }
        $cambio = round($recibido - $total, 2);
    }

    /* Cabecera de la venta (columnas adaptadas a la tabla real) */
    $camposV = [];
    $camposV[$colVFolio] = $folio;
    if ($colVFecha !== '') { $camposV[$colVFecha] = date('Y-m-d H:i:s'); }
    if ($colVCajero !== '') { $camposV[$colVCajero] = $cajero; }
    if ($colVMetodo !== '') { $camposV[$colVMetodo] = $metodo; }
    if ($colVRecibido !== '' && $metodo === 'efectivo') { $camposV[$colVRecibido] = $recibido; }
    if ($colVCambio !== '') { $camposV[$colVCambio] = $cambio; }
    $camposV[$colVTotal] = $total;
    $colsIns = array_keys($camposV);
    $sqlV = 'INSERT INTO ventas (' . implode(', ', $colsIns) . ') VALUES (' . rtrim(str_repeat('?, ', count($colsIns)), ', ') . ')';
    $pdo->prepare($sqlV)->execute(array_values($camposV));
    $idVenta = (int)$pdo->lastInsertId();

    /* Detalle por producto (con precios reales de MySQL) */
    $camposD = [$colDVenta, $colDProd, $colDCant, $colDPrecio, $colDSub];
    if ($colDCod !== '') { $camposD[] = $colDCod; }
    if ($colDNom !== '') { $camposD[] = $colDNom; }
    $sqlD = 'INSERT INTO detalle_venta (' . implode(', ', $camposD) . ') VALUES (' . rtrim(str_repeat('?, ', count($camposD)), ', ') . ')';
    $stmtD = $pdo->prepare($sqlD);
    foreach ($lineas as $l) {
        $valoresD = [$idVenta, $l['id'], $l['cantidad'], $l['precio'], $l['sub']];
        if ($colDCod !== '') { $valoresD[] = $l['codigo']; }
        if ($colDNom !== '') { $valoresD[] = $l['nombre']; }
        $stmtD->execute($valoresD);
    }

    /* V08: descontar el stock de cada producto */
    $sqlU = "UPDATE $tablaProductos SET $colStock = $colStock - :c WHERE id = :id";
    $stmtU = $pdo->prepare($sqlU);
    foreach ($lineas as $l) {
        $stmtU->execute([':c' => $l['cantidad'], ':id' => $l['id']]);
        if ($stmtU->rowCount() !== 1) {
            throw new Exception('No se pudo descontar el stock de "' . $l['nombre'] . '".');
        }
    }

    /* V08: movimiento de salida por cada producto */
    if ($hayMovimientos) {
        $camposM = [$colMTipo, $colMProd, $colMCant];
        if ($colMFecha !== '') { $camposM[] = $colMFecha; }
        if ($colMRef !== '') { $camposM[] = $colMRef; }
        if ($colMCajero !== '') { $camposM[] = $colMCajero; }
        $sqlM = 'INSERT INTO movimientos (' . implode(', ', $camposM) . ') VALUES (' . rtrim(str_repeat('?, ', count($camposM)), ', ') . ')';
        $stmtM = $pdo->prepare($sqlM);
        foreach ($lineas as $l) {
            $valoresM = ['salida', $l['id'], $l['cantidad']];
            if ($colMFecha !== '') { $valoresM[] = date('Y-m-d H:i:s'); }
            if ($colMRef !== '') { $valoresM[] = $folio; }
            if ($colMCajero !== '') { $valoresM[] = $cajero; }
            $stmtM->execute($valoresM);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    /* V07: si algo fallo, TODO se cancela (rollback) */
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responder(false, [], 'No se guardo la venta: ' . $e->getMessage());
}

 $respuesta = [
    'folio' => $folio,
    'total' => $total,
    'cambio' => $cambio,
    'cajero' => $cajero,
    'ticket' => 'ticket.php?folio=' . urlencode($folio),
];
if (!$hayMovimientos) {
    $respuesta['aviso'] = 'Venta guardada, pero no se registro el movimiento de salida.';
}
responder(true, $respuesta, 'Venta guardada correctamente.');
?>