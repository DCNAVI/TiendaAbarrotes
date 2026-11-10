<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}
verifyCsrf();

$tipo = is_string($_POST['tipo'] ?? null) ? $_POST['tipo'] : '';
$direccion = is_string($_POST['direccion'] ?? null) ? $_POST['direccion'] : '';
$producto_id = filter_var($_POST['producto_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
$cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
$motivo = is_string($_POST['motivo'] ?? null) ? trim($_POST['motivo']) : '';
$redirect = $tipo === 'ajuste' ? 'ajuste.php' : 'entrada.php';
$pdo = null;
try {
    // Las salidas de venta se registran desde la transacción de ventas.
    if (!in_array($tipo, ['entrada', 'ajuste'], true) || !$producto_id || !$cantidad
        || $motivo === '' || mb_strlen($motivo, 'UTF-8') > 255
        || ($tipo === 'ajuste' && !in_array($direccion, ['suma', 'resta'], true))) {
        throw new InvalidArgumentException('Revisa el producto, tipo, cantidad entera positiva y motivo (máximo 255 caracteres).');
    }
    $pdo = database();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT stock_actual FROM productos WHERE id = ? AND activo = 1 FOR UPDATE');
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch();
    if (!$producto) {
        throw new InvalidArgumentException('El producto no existe o está inactivo.');
    }
    $diferencia = $tipo === 'ajuste' && $direccion === 'resta' ? -$cantidad : $cantidad;
    $saldo = (int) $producto['stock_actual'] + $diferencia;
    if ($saldo < 0 || $saldo > 2147483647) {
        throw new InvalidArgumentException('La cantidad excede el stock disponible o el límite de existencias.');
    }
    $stmt = $pdo->prepare('UPDATE productos SET stock_actual = ? WHERE id = ?');
    $stmt->execute([$saldo, $producto_id]);
    $stmt = $pdo->prepare('INSERT INTO inventario_movimientos (producto_id, tipo, cantidad, motivo, usuario_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$producto_id, $tipo, $diferencia, $motivo, $_SESSION['usuario']['id']]);
    $pdo->commit();
    $_SESSION['flash_success'] = 'Movimiento registrado correctamente.';
    header('Location: index.php');
    exit;
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($exception instanceof InvalidArgumentException) {
        $_SESSION['flash_error'] = $exception->getMessage();
    } else {
        error_log($exception->getMessage());
        $_SESSION['flash_error'] = 'No se pudo guardar el movimiento. Intenta nuevamente.';
    }
    header('Location: ' . $redirect);
    exit;
}
