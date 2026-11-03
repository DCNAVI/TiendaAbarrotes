-- MySQL 8.0.16+ o MariaDB 10.2.1+. Ejecutar en una base vacía.
CREATE DATABASE IF NOT EXISTS tienda_abarrotes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tienda_abarrotes;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'cajero') NOT NULL DEFAULT 'cajero',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT chk_usuario_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB;

CREATE TABLE categorias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT chk_categoria_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB;

CREATE TABLE productos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    categoria_id INT UNSIGNED NOT NULL,
    precio_compra DECIMAL(10,2) NOT NULL,
    precio_venta DECIMAL(10,2) NOT NULL,
    stock_actual INT NOT NULL DEFAULT 0,
    stock_minimo INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_productos_nombre (nombre),
    CONSTRAINT fk_producto_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT,
    CONSTRAINT chk_producto_precios CHECK (precio_compra >= 0 AND precio_venta > 0),
    CONSTRAINT chk_producto_stock CHECK (stock_actual >= 0 AND stock_minimo >= 0),
    CONSTRAINT chk_producto_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB;

CREATE TABLE ventas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('efectivo', 'transferencia') NOT NULL,
    efectivo_recibido DECIMAL(10,2) NULL,
    cambio DECIMAL(10,2) NOT NULL DEFAULT 0,
    estado ENUM('confirmada', 'cancelada') NOT NULL DEFAULT 'confirmada',
    INDEX idx_ventas_fecha (fecha),
    INDEX idx_ventas_usuario_fecha (usuario_id, fecha),
    CONSTRAINT fk_venta_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT chk_venta_total CHECK (total > 0),
    CONSTRAINT chk_venta_pago CHECK (
        (metodo_pago = 'efectivo' AND efectivo_recibido IS NOT NULL AND efectivo_recibido >= total AND cambio = efectivo_recibido - total)
        OR (metodo_pago = 'transferencia' AND efectivo_recibido IS NULL AND cambio = 0)
    )
) ENGINE=InnoDB;

CREATE TABLE venta_detalles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venta_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    UNIQUE KEY uq_detalle_venta_producto (venta_id, producto_id),
    CONSTRAINT fk_detalle_venta FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE RESTRICT,
    CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT,
    CONSTRAINT chk_detalle_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_detalle_precio CHECK (precio_unitario > 0 AND subtotal = cantidad * precio_unitario)
) ENGINE=InnoDB;

CREATE TABLE inventario_movimientos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    producto_id INT UNSIGNED NOT NULL,
    tipo ENUM('entrada', 'ajuste', 'salida') NOT NULL,
    cantidad INT NOT NULL COMMENT 'Diferencia firmada: entrada positiva, salida negativa, ajuste positivo o negativo',
    motivo VARCHAR(255) NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    venta_id INT UNSIGNED NULL COMMENT 'Referencia opcional para salida por venta',
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_movimientos_producto_fecha (producto_id, fecha),
    INDEX idx_movimientos_fecha (fecha),
    UNIQUE KEY uq_movimiento_venta_producto_tipo (venta_id, producto_id, tipo),
    CONSTRAINT fk_movimiento_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_movimiento_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_movimiento_venta FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE RESTRICT,
    CONSTRAINT chk_movimiento_cantidad CHECK (
        (tipo = 'entrada' AND cantidad > 0)
        OR (tipo = 'salida' AND cantidad < 0)
        OR (tipo = 'ajuste' AND cantidad <> 0)
    ),
    CONSTRAINT chk_movimiento_motivo CHECK (CHAR_LENGTH(TRIM(motivo)) > 0)
) ENGINE=InnoDB;
