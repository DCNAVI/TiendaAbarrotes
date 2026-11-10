<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

// Solo aceptar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

// ======================
// 1. Recibir datos
// ======================
$tipo        = $_POST['tipo'] ?? '';
$producto_id = (int)($_POST['producto_id'] ?? 0);
$cantidad    = (int)($_POST['cantidad'] ?? 0);
$motivo      = trim($_POST['motivo'] ?? '');
$direccion   = $_POST['direccion'] ?? 'suma'; // solo para ajustes

// ======================
// 2. Validaciones
// ======================
$errores = [];

if (!in_array($tipo, ['entrada', 'ajuste', 'salida'])) {
    $errores[] = 'Tipo de movimiento no válido.';
}

if ($producto_id <= 0) {
    $errores[] = 'Debes seleccionar un producto.';
}

if ($cantidad <= 0) {
    $errores[] = 'La cantidad debe ser un número positivo.';
}

if ($motivo === '') {
    $errores[] = 'El motivo es obligatorio.';
}

// Verificar que el producto exista
$stmt = database()->prepare("SELECT id, stock_actual, nombre FROM productos WHERE id = ? AND activo = 1");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch();

if (!$producto) {
    $errores[] = 'El producto seleccionado no existe o está inactivo.';
}

// Validación especial para ajustes: no dejar stock negativo
if ($tipo === 'ajuste' && $direccion === 'resta') {
    if ($producto && $cantidad > $producto['stock_actual']) {
        $errores[] = "No se puede restar $cantidad unidades. Solo hay {$producto['stock_actual']} en stock.";
    }
}

if (!empty($errores)) {
    $_SESSION['flash_error'] = implode('<br>', $errores);
    $redirect = ($tipo === 'ajuste') ? 'ajuste.php' : 'entrada.php';
    header("Location: $redirect");
    exit;
}

// ======================
// 3. Guardar con TRANSACCIÓN
// ======================
try {
    $pdo = database();
    $pdo->beginTransaction();

    // Determinar la cantidad real que se va a aplicar
    $cantidadFinal = $cantidad;
    if ($tipo === 'ajuste' && $direccion === 'resta') {
        $cantidadFinal = -$cantidad; // se guarda como negativo en el movimiento
    }

    // 3.1 Insertar el movimiento
    $stmt = $pdo->prepare("
        INSERT INTO inventario_movimientos 
            (producto_id, tipo, cantidad, motivo, usuario_id, fecha)
        VALUES 
            (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $producto_id,
        $tipo,
        $cantidadFinal,
        $motivo,
        $_SESSION['usuario_id'] ?? 1
    ]);

    // 3.2 Actualizar el stock
    if ($tipo === 'entrada' || ($tipo === 'ajuste' && $direccion === 'suma')) {
        // Sumar
        $stmt = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual + ? WHERE id = ?");
        $stmt->execute([$cantidad, $producto_id]);
    } 
    elseif ($tipo === 'ajuste' && $direccion === 'resta') {
        // Restar
        $stmt = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?");
        $stmt->execute([$cantidad, $producto_id]);
    }

    $pdo->commit();

    // Mensaje de éxito
    if ($tipo === 'entrada') {
        $_SESSION['flash_success'] = "Entrada registrada. Se agregaron $cantidad unidades.";
    } else {
        $accion = ($direccion === 'suma') ? 'sumaron' : 'restaron';
        $_SESSION['flash_success'] = "Ajuste registrado. Se $accion $cantidad unidades.";
    }

    header('Location: index.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = 'Error al guardar: ' . $e->getMessage();
    $redirect = ($tipo === 'ajuste') ? 'ajuste.php' : 'entrada.php';
    header("Location: $redirect");
    exit;
}