-- Datos de demostración. Importar una sola vez después de schema.sql en una base vacía.
-- Sin usuarios ni contraseñas predeterminadas: crear el administrador mediante scripts/crear_admin.php.
USE tienda_abarrotes;
START TRANSACTION;
INSERT INTO categorias (id, nombre) VALUES (1, 'Abarrotes'), (2, 'Bebidas'), (3, 'Limpieza');
-- Saldo inicial cero: al crear el administrador, registrar entradas desde el módulo de inventario.
INSERT INTO productos (codigo, nombre, categoria_id, precio_compra, precio_venta, stock_actual, stock_minimo) VALUES
('AB001', 'Arroz 1 kg', 1, 22.00, 30.00, 0, 5),
('AB002', 'Frijol 1 kg', 1, 28.00, 38.00, 0, 5),
('AB003', 'Azúcar 1 kg', 1, 20.00, 28.00, 0, 5),
('AB004', 'Aceite 1 L', 1, 32.00, 45.00, 0, 4),
('BE001', 'Agua 1 L', 2, 8.00, 14.00, 0, 10),
('BE002', 'Refresco 600 ml', 2, 13.00, 20.00, 0, 8),
('BE003', 'Jugo 1 L', 2, 19.00, 28.00, 0, 5),
('LI001', 'Detergente 1 kg', 3, 25.00, 36.00, 0, 4),
('LI002', 'Jabón de barra', 3, 9.00, 15.00, 0, 6),
('LI003', 'Cloro 1 L', 3, 12.00, 20.00, 0, 4);
COMMIT;
