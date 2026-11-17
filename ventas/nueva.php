<?php
/* ============================================================
   MEDEL - PUNTO DE VENTA
   ventas/nueva.php
   V01: Buscador por codigo o nombre + SOLO productos activos
   V02: Carrito con JavaScript
        - Agregar producto
        - Modificar cantidad
        - Quitar renglon
        - La tabla y el total se actualizan SIN recargar
   V03: Validacion de cantidades
        - Rechaza 0, negativos, decimales y cantidades sin stock
        - Informa POR QUE es invalida la cantidad
   V04: Subtotal por linea y total con formato monetario
        - formatMoney en JavaScript
        - El carrito solo enviara id y cantidad; el cobro real
          lo recalcula PHP desde MySQL al guardar (V06)
   V05: Cobro
        - Metodo de pago: efectivo o transferencia
        - En efectivo pide monto recibido y calcula el cambio
        - No permite efectivo menor al total
   FIX: adaptacion automatica a las columnas reales de la base
        (detecta tabla y columnas aunque se llamen distinto,
         p. ej. precio_unitario o stock_actual; si no encuentra
         alguna, muestra un aviso con las columnas reales)
   V06: Boton "Cobrar y guardar venta"
        - Envia SOLO id + cantidad a ventas/guardar.php (JSON)
        - Muestra el folio guardado y el enlace al ticket (V09)
   ============================================================ */

/* ------------------------------------------------------------
   PASO 1: CONEXION (detector universal + fallback config/local.php)
   ------------------------------------------------------------ */
try {
    $modulo = require_once __DIR__ . '/../config/database.php';
} catch (Throwable $e) {
    die('Error cargando config/database.php: ' . $e->getMessage());
}

 $pdo = null;
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

/* ------------------------------------------------------------
   PASO 2: BUSCADOR - consulta SOLO productos activos
   FIX: la base puede usar otros nombres de columna (p. ej.
   precio_unitario en vez de precio), asi que primero se
   detectan las columnas reales y se usan con alias.
   ------------------------------------------------------------ */
 $busqueda = (isset($_GET['busqueda']) && is_string($_GET['busqueda'])) ? trim($_GET['busqueda']) : '';

/* Busca una columna entre las reales de la tabla.
   Pasada 1: coincidencia exacta (sin importar mayusculas).
   Pasada 2: columna real que CONTENGA la palabra clave
   (asi detecta stock_actual, precio_unitario, etc.).
   $excluir descarta falsos positivos como stock_minimo o
   precio_compra. Devuelve '' si no encuentra nada. */
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

try {
    /* Detectar el nombre de la tabla de productos */
    $tablas = [];
    foreach ($pdo->query('SHOW TABLES', PDO::FETCH_ASSOC) as $t) {
        $tablas[] = strtolower((string)current($t));
    }
    $tablaProductos = elegirColumna(['productos', 'producto', 'articulos', 'products'], $tablas);
    if ($tablaProductos === '') { $tablaProductos = 'productos'; }

    /* Detectar las columnas reales de esa tabla */
    $colsInfo = [];
    foreach ($pdo->query('SHOW COLUMNS FROM ' . $tablaProductos, PDO::FETCH_ASSOC) as $c) {
        $colsInfo[$c['Field']] = strtolower((string)$c['Type']);
    }
    $colsTabla = array_keys($colsInfo);
    $listaColumnas = implode(', ', $colsTabla);

    $colCodigo = elegirColumna(['codigo', 'clave', 'sku'], $colsTabla);
    $colNombre = elegirColumna(['nombre', 'descripcion', 'producto'], $colsTabla);
    $colPrecio = elegirColumna(['precio_venta', 'precio_unitario', 'pvp', 'precio_publico', 'precio_menudeo', 'precio'], $colsTabla, ['compra', 'costo', 'proveedor']);
    $colStock  = elegirColumna(['stock', 'existencias', 'existencia', 'inventario', 'piezas', 'unidades', 'cantidad', 'disponible', 'cant', 'qty'], $colsTabla, ['minimo', 'maximo', 'reorden']);
    $colEstado = elegirColumna(['estado', 'activo', 'estatus', 'status', 'disponible', 'vigente'], $colsTabla, ['texto']);

    /* Si falta alguna columna esencial, avisar con la lista real */
    $faltantes = [];
    if ($colCodigo === '') { $faltantes[] = 'codigo'; }
    if ($colNombre === '') { $faltantes[] = 'nombre'; }
    if ($colPrecio === '') { $faltantes[] = 'precio'; }
    if ($colStock === '') { $faltantes[] = 'stock'; }
    if ($colEstado === '') { $faltantes[] = 'estado/activo'; }
    if (count($faltantes) > 0) {
        die('No encontre estas columnas en la tabla ' . $tablaProductos . ': ' . implode(', ', $faltantes) . '. Columnas reales: ' . $listaColumnas . '. Manda captura de este mensaje.');
    }

    /* Condicion de "activo" segun el tipo de dato de la columna */
    $tipoEstado = isset($colsInfo[$colEstado]) ? $colsInfo[$colEstado] : '';
    if (strpos($tipoEstado, 'enum') !== false || strpos($tipoEstado, 'char') !== false || strpos($tipoEstado, 'text') !== false) {
        $condicionActivo = "$colEstado IN ('activo', 'si', '1')";
    } else {
        $condicionActivo = "$colEstado = 1";
    }
} catch (Throwable $e) {
    die('No pude leer la estructura de la tabla de productos. Detalle: ' . $e->getMessage());
}

 $sql = "SELECT id, $colCodigo AS codigo, $colNombre AS nombre, $colPrecio AS precio, $colStock AS stock FROM $tablaProductos WHERE $condicionActivo";
 $params = [];
if ($busqueda !== '') {
    $sql .= " AND ($colCodigo LIKE :b1 OR $colNombre LIKE :b2)";
    $params[':b1'] = '%' . $busqueda . '%';
    $params[':b2'] = '%' . $busqueda . '%';
}
 $sql .= " ORDER BY $colNombre ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die('Error en la consulta: ' . $e->getMessage() . ' | Columnas reales de ' . $tablaProductos . ': ' . $listaColumnas);
}

 $totalProductos = count($productos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Medel - Nueva venta</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f8; color: #1f2937; padding: 24px; }
.wrap { max-width: 980px; margin: 0 auto; }
header h1 { font-size: 26px; color: #0f3460; }
header .sub { color: #64748b; margin-top: 4px; }
.buscador { margin: 18px 0; display: flex; gap: 8px; }
.buscador input { flex: 1; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 15px; }
.buscador button { padding: 10px 18px; background: #0f3460; color: #fff; border: 0; border-radius: 6px; cursor: pointer; font-size: 15px; }
.limpiar { align-self: center; color: #0f3460; font-size: 14px; }
section { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
section h2 { font-size: 18px; margin-bottom: 12px; color: #0f3460; }
.badge { background: #0f3460; color: #fff; border-radius: 10px; padding: 2px 9px; font-size: 13px; vertical-align: middle; }
.tabla { width: 100%; border-collapse: collapse; }
.tabla th { background: #f1f5f9; text-align: left; padding: 9px 10px; font-size: 13px; color: #475569; border-bottom: 2px solid #e2e8f0; }
.tabla td { padding: 9px 10px; border-bottom: 1px solid #eef2f7; font-size: 14px; }
.c { text-align: center; }
.vacio { color: #64748b; font-style: italic; padding: 10px; }
.nota { margin-top: 10px; font-size: 12px; color: #94a3b8; }
.mensaje { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 10px 12px; border-radius: 6px; margin-bottom: 10px; font-size: 14px; }
.btn-agregar { background: #16a34a; color: #fff; border: 0; border-radius: 6px; padding: 7px 12px; cursor: pointer; font-size: 13px; }
.btn-agregar:hover { background: #15803d; }
.btn-cant { width: 28px; height: 28px; border: 1px solid #cbd5e1; background: #f8fafc; cursor: pointer; border-radius: 4px; font-size: 15px; line-height: 1; }
.btn-cant:hover { background: #e2e8f0; }
.input-cant { width: 56px; height: 28px; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; margin: 0 5px; }
.btn-quitar { background: #fee2e2; color: #b91c1c; border: 0; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 13px; }
.btn-quitar:hover { background: #fecaca; }
.total-celda { text-align: right; font-weight: bold; font-size: 16px; color: #0f3460; background: #f8fafc; }
.num { text-align: right; }
.cobro { margin-top: 14px; padding-top: 12px; border-top: 2px solid #e2e8f0; }
.cobro h3 { font-size: 15px; color: #0f3460; margin-bottom: 8px; }
.cobro select { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
.cobro input { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 15px; width: 130px; }
.cambio-monto { font-size: 20px; font-weight: bold; color: #0f3460; }
.estado-ok { color: #15803d; font-weight: bold; }
.estado-error { color: #b91c1c; font-weight: bold; }
.estado-info { color: #475569; }
.btn-cobrar { background: #0f3460; color: #fff; border: 0; border-radius: 6px; padding: 10px 16px; font-size: 15px; cursor: pointer; }
.btn-cobrar:hover { background: #123a6d; }
.btn-cobrar:disabled { opacity: 0.6; cursor: wait; }
.venta-ok { color: #15803d; font-weight: bold; margin-left: 10px; font-size: 14px; }
</style>
</head>
<body>
<div class="wrap">

<header>
    <h1>Medel &mdash; Punto de venta</h1>
    <p class="sub">Nueva venta</p>
</header>

<!-- V01: buscador por codigo o nombre -->
<form method="get" action="nueva.php" class="buscador">
    <input type="text" name="busqueda" placeholder="Buscar por c&oacute;digo o nombre..."
           value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES); ?>">
    <button type="submit">Buscar</button>
    <?php if ($busqueda !== ''): ?>
    <a class="limpiar" href="nueva.php">Limpiar</a>
    <?php endif; ?>
</form>

<!-- V01: lista de productos ACTIVOS -->
<section>
    <h2>Productos activos (<?php echo (int)$totalProductos; ?>)</h2>
    <table class="tabla">
        <thead>
        <tr>
            <th>C&oacute;digo</th>
            <th>Producto</th>
            <th>Precio</th>
            <th>Stock</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($totalProductos === 0): ?>
        <tr>
            <td colspan="5" class="vacio">
                Sin resultados<?php if ($busqueda !== ''): ?> para &quot;<?php echo htmlspecialchars($busqueda, ENT_QUOTES); ?>&quot;<?php endif; ?>.
            </td>
        </tr>
        <?php else: ?>
        <?php foreach ($productos as $p): ?>
        <tr>
            <td><?php echo htmlspecialchars((string)$p['codigo'], ENT_QUOTES); ?></td>
            <td><?php echo htmlspecialchars((string)$p['nombre'], ENT_QUOTES); ?></td>
            <td>$<?php echo number_format((float)$p['precio'], 2); ?></td>
            <td><?php echo (int)$p['stock']; ?></td>
            <td class="c">
                <button type="button" class="btn-agregar"
                    data-id="<?php echo (int)$p['id']; ?>"
                    data-codigo="<?php echo htmlspecialchars((string)$p['codigo'], ENT_QUOTES); ?>"
                    data-nombre="<?php echo htmlspecialchars((string)$p['nombre'], ENT_QUOTES); ?>"
                    data-precio="<?php echo number_format((float)$p['precio'], 2, '.', ''); ?>"
                    data-stock="<?php echo (int)$p['stock']; ?>"
                    onclick="agregarAlCarrito(this)">+ Agregar</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    <p class="nota">V01: esta lista muestra &uacute;nicamente los productos activos (los inactivos nunca aparecen).</p>
</section>

<!-- V02-V06: carrito con validaciones, formato monetario y cobro -->
<section>
    <h2>Carrito <span id="contador" class="badge">0</span></h2>
    <p id="mensaje" class="mensaje" style="display:none;"></p>
    <table class="tabla" id="tablaCarrito">
        <thead>
        <tr>
            <th>C&oacute;digo</th>
            <th>Producto</th>
            <th>Precio</th>
            <th>Cantidad</th>
            <th>Subtotal</th>
            <th></th>
        </tr>
        </thead>
        <tbody id="cuerpoCarrito"></tbody>
        <tfoot id="pieCarrito" style="display:none;">
        <tr>
            <td colspan="5" class="total-celda">TOTAL</td>
            <td class="total-celda" id="totalTxt">$0.00</td>
        </tr>
        </tfoot>
    </table>
    <p id="carritoVacio" class="vacio">El carrito est&aacute; vac&iacute;o. Agrega productos con el bot&oacute;n &quot;+ Agregar&quot;.</p>
    <p class="nota">V04: los subtotales y el total se muestran con formato monetario; al guardar la venta (V06) PHP recalcula los precios desde MySQL, as&iacute; que manipular el navegador NO altera el cobro.</p>

    <!-- V05: cobro con metodo de pago y cambio -->
    <div class="cobro" id="panelCobro">
        <h3>Cobro</h3>
        <label for="metodoPago">M&eacute;todo de pago:</label>
        <select id="metodoPago">
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
        </select>
        <div id="zonaEfectivo" style="display:none; margin-top:10px;">
            <label for="montoRecibido">Monto recibido: $</label>
            <input type="number" id="montoRecibido" min="0" step="0.01" placeholder="0.00">
            <p>Cambio: <span id="cambioTxt" class="cambio-monto">$0.00</span></p>
        </div>
        <p id="cobroEstado" class="estado-info">El carrito est&aacute; vac&iacute;o.</p>
        <div style="margin-top:12px;">
            <button type="button" id="btnCobrar" class="btn-cobrar">Cobrar y guardar venta</button>
            <span id="ventaOk" class="venta-ok" style="display:none;"></span>
        </div>
    </div>
</section>

</div>

<script>
/* V02: CARRITO EN MEMORIA (la pagina NUNCA se recarga al vender) */
let carrito = [];

/* Restaurar carrito despues de una busqueda o recarga (mismo tab) */
try {
    let guardado = sessionStorage.getItem('medelCarrito');
    if (guardado) {
        let datos = JSON.parse(guardado);
        if (Array.isArray(datos)) {
            carrito = datos.filter(function(it) {
                return it && typeof it.precio === 'number' && typeof it.cantidad === 'number' && it.cantidad > 0;
            });
        }
    }
} catch (e) {
    carrito = [];
}

/* V04: formato monetario en toda la pantalla */
const formatoDinero = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
function formatMoney(n) {
    return formatoDinero.format(Number(n));
}

/* V04: lo unico que se enviara al servidor es id + cantidad.
   Los precios reales los recalcula PHP desde MySQL al guardar. */
function carritoParaServidor() {
    return carrito.map(function(it) {
        return { id: it.id, cantidad: it.cantidad };
    });
}

/* ---------- V05: COBRO (efectivo / transferencia + cambio) ---------- */

function totalCarrito() {
    let t = 0;
    carrito.forEach(function(it) { t = t + it.precio * it.cantidad; });
    return t;
}

function actualizarCobro() {
    let metodo = document.getElementById('metodoPago').value;
    let zona = document.getElementById('zonaEfectivo');
    let estado = document.getElementById('cobroEstado');
    let cambioTxt = document.getElementById('cambioTxt');
    let total = totalCarrito();

    if (carrito.length === 0) {
        zona.style.display = 'none';
        estado.className = 'estado-info';
        estado.textContent = 'El carrito est\u00e1 vac\u00edo: agrega productos para cobrar.';
        cambioTxt.textContent = formatMoney(0);
        return;
    }

    if (metodo === 'transferencia') {
        zona.style.display = 'none';
        estado.className = 'estado-ok';
        estado.textContent = 'Transferencia seleccionada. El pago se registrar\u00e1 sin cambio al guardar (V06).';
        cambioTxt.textContent = formatMoney(0);
        return;
    }

    zona.style.display = 'block';
    let monto = parseFloat(document.getElementById('montoRecibido').value);
    cambioTxt.textContent = formatMoney(0);

    if (isNaN(monto) || monto <= 0) {
        estado.className = 'estado-info';
        estado.textContent = 'Escribe el monto con el que paga el cliente (total: ' + formatMoney(total) + ').';
        return;
    }

    if (monto < total) {
        estado.className = 'estado-error';
        estado.textContent = 'No se puede cobrar: el efectivo (' + formatMoney(monto) + ') es MENOR que el total (' + formatMoney(total) + '). Faltan ' + formatMoney(total - monto) + '.';
        return;
    }

    let cambio = monto - total;
    cambioTxt.textContent = formatMoney(cambio);
    estado.className = 'estado-ok';
    estado.textContent = 'Cobro v\u00e1lido. Cambio a entregar: ' + formatMoney(cambio) + '.';
}

/* ---------- V03: VALIDACION DE CANTIDADES ---------- */

/* Devuelve el motivo del error, o cadena vacia si la cantidad es valida */
function validarCantidad(it, valor) {
    let n = Number(valor);
    if (String(valor).trim() === '' || isNaN(n)) {
        return 'Escribe una cantidad v\u00e1lida: n\u00famero ENTERO mayor que 0.';
    }
    if (!Number.isInteger(n)) {
        return 'La cantidad debe ser un n\u00famero ENTERO (sin decimales). Ejemplo: 3';
    }
    if (n <= 0) {
        return 'La cantidad no puede ser 0 ni negativa.';
    }
    if (n > it.stock) {
        return 'Stock insuficiente: solo hay ' + it.stock + ' piezas de "' + it.nombre + '".';
    }
    return '';
}

let temporizadorMensaje = null;
function mostrarMensaje(texto) {
    let m = document.getElementById('mensaje');
    m.textContent = texto;
    m.style.display = 'block';
    if (temporizadorMensaje) { clearTimeout(temporizadorMensaje); }
    temporizadorMensaje = setTimeout(ocultarMensaje, 6000);
}
function ocultarMensaje() {
    let m = document.getElementById('mensaje');
    if (m) { m.style.display = 'none'; }
}

/* Agregar producto (si ya esta en el carrito, aumenta su cantidad) */
function agregarAlCarrito(btn) {
    let id = parseInt(btn.dataset.id, 10);
    let stock = parseInt(btn.dataset.stock, 10);
    let existente = null;
    for (let i = 0; i < carrito.length; i++) {
        if (carrito[i].id === id) { existente = carrito[i]; break; }
    }
    if (existente) {
        if (existente.cantidad + 1 > existente.stock) {
            mostrarMensaje('Stock insuficiente: solo hay ' + existente.stock + ' piezas de "' + existente.nombre + '".');
            return;
        }
        existente.cantidad = existente.cantidad + 1;
    } else {
        if (stock < 1) {
            mostrarMensaje('Este producto no tiene stock disponible.');
            return;
        }
        carrito.push({
            id: id,
            codigo: btn.dataset.codigo,
            nombre: btn.dataset.nombre,
            precio: parseFloat(btn.dataset.precio),
            stock: stock,
            cantidad: 1
        });
    }
    ocultarMensaje();
    renderCarrito();
}

/* Botones - y + de cada renglon (tambien validan) */
function ajustarCantidad(idx, delta) {
    let it = carrito[idx];
    if (!it) return;
    let nueva = it.cantidad + delta;
    if (delta > 0 && nueva > it.stock) {
        mostrarMensaje('Stock insuficiente: solo hay ' + it.stock + ' piezas de "' + it.nombre + '".');
        return;
    }
    if (nueva < 1) {
        mostrarMensaje('La cantidad m\u00ednima es 1. Usa el bot\u00f3n "Quitar" para eliminar el rengl\u00f3n.');
        return;
    }
    it.cantidad = nueva;
    ocultarMensaje();
    renderCarrito();
}

/* Input de cantidad escrito a mano: valida y, si es invalida,
   informa el motivo y deja la cantidad anterior */
function cambiarCantidad(idx, valor) {
    let it = carrito[idx];
    if (!it) return;
    let error = validarCantidad(it, valor);
    if (error !== '') {
        mostrarMensaje(error);
        renderCarrito();
        return;
    }
    it.cantidad = Number(valor);
    ocultarMensaje();
    renderCarrito();
}

/* Quitar renglon completo */
function quitarRenglon(idx) {
    carrito.splice(idx, 1);
    renderCarrito();
}

/* Dibuja la tabla del carrito y el total */
function renderCarrito() {
    let tbody = document.getElementById('cuerpoCarrito');
    let pie = document.getElementById('pieCarrito');
    let vacia = document.getElementById('carritoVacio');
    let totalTxt = document.getElementById('totalTxt');
    let contador = document.getElementById('contador');
    tbody.innerHTML = '';
    contador.textContent = String(carrito.length);
    if (carrito.length === 0) {
        vacia.style.display = 'block';
        pie.style.display = 'none';
    } else {
        vacia.style.display = 'none';
        pie.style.display = '';
        let total = 0;
        carrito.forEach(function(it, idx) {
            let sub = it.precio * it.cantidad;
            total = total + sub;
            let tr = document.createElement('tr');

            let tdCod = document.createElement('td');
            tdCod.textContent = it.codigo;
            tr.appendChild(tdCod);

            let tdNom = document.createElement('td');
            tdNom.textContent = it.nombre;
            tr.appendChild(tdNom);

            let tdPre = document.createElement('td');
            tdPre.className = 'num';
            tdPre.textContent = formatMoney(it.precio);
            tr.appendChild(tdPre);

            let tdCan = document.createElement('td');
            tdCan.className = 'c';
            let bMenos = document.createElement('button');
            bMenos.type = 'button';
            bMenos.className = 'btn-cant';
            bMenos.textContent = '-';
            bMenos.addEventListener('click', function() { ajustarCantidad(idx, -1); });
            let inp = document.createElement('input');
            inp.type = 'number';
            inp.className = 'input-cant';
            inp.min = '1';
            inp.value = it.cantidad;
            inp.addEventListener('change', function() { cambiarCantidad(idx, this.value); });
            let bMas = document.createElement('button');
            bMas.type = 'button';
            bMas.className = 'btn-cant';
            bMas.textContent = '+';
            bMas.addEventListener('click', function() { ajustarCantidad(idx, 1); });
            tdCan.appendChild(bMenos);
            tdCan.appendChild(inp);
            tdCan.appendChild(bMas);
            tr.appendChild(tdCan);

            let tdSub = document.createElement('td');
            tdSub.className = 'num';
            tdSub.textContent = formatMoney(sub);
            tr.appendChild(tdSub);

            let tdAcc = document.createElement('td');
            tdAcc.className = 'c';
            let bQuitar = document.createElement('button');
            bQuitar.type = 'button';
            bQuitar.className = 'btn-quitar';
            bQuitar.textContent = 'Quitar';
            bQuitar.addEventListener('click', function() { quitarRenglon(idx); });
            tdAcc.appendChild(bQuitar);
            tr.appendChild(tdAcc);

            tbody.appendChild(tr);
        });
        totalTxt.textContent = formatMoney(total);
    }
    actualizarCobro();
    try { sessionStorage.setItem('medelCarrito', JSON.stringify(carrito)); } catch (e) {}
}

renderCarrito();

/* V05: reconectar el panel de cobro */
document.getElementById('metodoPago').addEventListener('change', actualizarCobro);
document.getElementById('montoRecibido').addEventListener('input', actualizarCobro);

/* ---------- V06: ENVIAR LA VENTA AL SERVIDOR ---------- */
function enviarVenta() {
    let btn = document.getElementById('btnCobrar');
    let ok = document.getElementById('ventaOk');
    let estado = document.getElementById('cobroEstado');

    ok.style.display = 'none';

    if (carrito.length === 0) {
        mostrarMensaje('El carrito est\u00e1 vac\u00edo: agrega productos antes de cobrar.');
        return;
    }

    let metodo = document.getElementById('metodoPago').value;
    let recibido = null;
    if (metodo === 'efectivo') {
        recibido = parseFloat(document.getElementById('montoRecibido').value);
        if (isNaN(recibido) || recibido <= 0) {
            mostrarMensaje('Escribe el monto recibido en efectivo.');
            return;
        }
        if (recibido < totalCarrito()) {
            mostrarMensaje('El efectivo es MENOR que el total. Faltan ' + formatMoney(totalCarrito() - recibido) + '.');
            return;
        }
    }

    /* V04/V06: solo id + cantidad viajan al servidor.
       Los precios reales los recalcula PHP desde MySQL. */
    let payload = { carrito: carritoParaServidor(), metodo: metodo, recibido: recibido };

    btn.disabled = true;
    estado.className = 'estado-info';
    estado.textContent = 'Guardando venta...';

    fetch('guardar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
        btn.disabled = false;
        if (resp && resp.ok) {
            carrito = [];
            try { sessionStorage.setItem('medelCarrito', '[]'); } catch (e) {}
            renderCarrito();
            ok.textContent = 'Venta ' + resp.folio + ' guardada (' + formatMoney(resp.total) + ')';
            if (resp.cambio !== null && resp.cambio !== undefined) {
                ok.textContent = ok.textContent + ' | Cambio: ' + formatMoney(resp.cambio);
            }
            ok.textContent = ok.textContent + '. ';
            ok.style.display = 'inline';
            let link = document.createElement('a');
            link.href = resp.ticket;
            link.textContent = 'Ver ticket';
            ok.appendChild(link);
        } else {
            mostrarMensaje((resp && resp.mensaje) ? resp.mensaje : 'No se pudo guardar la venta.');
            actualizarCobro();
        }
    })
    .catch(function() {
        btn.disabled = false;
        mostrarMensaje('Error de red: no se pudo contactar al servidor.');
        actualizarCobro();
    });
}
document.getElementById('btnCobrar').addEventListener('click', enviarVenta);
</script>
</body>
</html>