<?php
/**
 * Función compartida para descontar stock por venta (I09)
 * Registra el movimiento; el llamador debe impedir procesar la venta dos veces.
 * Debe usarse DENTRO de una transacción.
 */
function descontarStockPorVenta(PDO $pdo, int $producto_id, int $cantidad, int $usuario_id, string $motivo = 'Venta'): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('El descuento requiere una transacción activa.');
    }
    if ($cantidad <= 0) {
        throw new InvalidArgumentException('La cantidad a descontar debe ser positiva.');
    }

    // Verificar stock disponible
    $stmt = $pdo->prepare("SELECT stock_actual, nombre FROM productos WHERE id = ? AND activo = 1 FOR UPDATE");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch();

    if (!$producto) {
        throw new RuntimeException('Producto no encontrado o inactivo.');
    }

    if ($producto['stock_actual'] < $cantidad) {
        throw new RuntimeException("Stock insuficiente de \"{$producto['nombre']}\". Disponible: {$producto['stock_actual']}");
    }

    // Descontar stock
    $stmt = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?");
    $stmt->execute([$cantidad, $producto_id]);

    // Registrar movimiento de salida
    $stmt = $pdo->prepare("
        INSERT INTO inventario_movimientos
            (producto_id, tipo, cantidad, motivo, usuario_id, fecha)
        VALUES
            (?, 'salida', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $producto_id,
        -$cantidad,          // se guarda negativo
        $motivo,
        $usuario_id
    ]);
}
