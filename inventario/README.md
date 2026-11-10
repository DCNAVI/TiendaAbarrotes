# Módulo de Inventario

**Responsable:** Cristo  
**Rama:** feature/inventario

## Descripción

Este módulo permite controlar las existencias de la tienda de abarrotes: consultar stock actual, registrar entradas de mercancía, realizar ajustes de inventario y consultar el historial de movimientos.

## Funcionalidades implementadas

| ID  | Funcionalidad                              | Estado |
|-----|--------------------------------------------|--------|
| I01 | Listado de inventario (código, stock, mínimo y estado) | ✅ |
| I02 | Filtro por producto y opción "solo stock bajo" | ✅ |
| I03 | Formulario de entrada de mercancía         | ✅ |
| I04 | Validación PHP + consultas preparadas      | ✅ |
| I05 | Registro de movimiento + actualización de stock en transacción | ✅ |
| I06 | Formulario de ajuste (suma/resta) con motivo obligatorio | ✅ |
| I07 | Registro de usuario y fecha en cada movimiento | ✅ |
| I08 | Historial de movimientos con filtros       | ✅ |
| I09 | Función compartida `descontarStockPorVenta()` | ✅ |
| I10 | Pruebas documentadas                       | ✅ |

## Archivos principales

- `index.php` → Listado de inventario + filtros
- `entrada.php` → Formulario de entrada de mercancía
- `ajuste.php` → Formulario de ajuste de stock
- `historial.php` → Historial de movimientos con filtros
- `guardar_movimiento.php` → Procesa entradas y ajustes (con transacción)
- `funciones.php` → Función compartida para descontar stock por venta

## Pruebas realizadas (I10)

### Prueba 1: Entrada de mercancía
- Se seleccionó el producto "Aceite 1L"
- Se ingresaron 5 unidades con motivo "Compra a proveedor"
- **Resultado:** El stock aumentó correctamente de 40 a 45
- El movimiento quedó registrado en el historial con usuario y fecha

### Prueba 2: Ajuste de inventario (evitar stock negativo)
- Se intentó restar 50 unidades de un producto que solo tenía 45
- **Resultado:** El sistema mostró mensaje de error y **no permitió** dejar el stock negativo
- Al restar una cantidad válida, el stock se actualizó correctamente

### Prueba 3: Consistencia de saldos
- Se realizaron varias entradas y ajustes
- Se verificó el historial
- **Resultado:** Los saldos del listado de inventario coinciden con la suma de todos los movimientos registrados

## Notas técnicas

- Todo movimiento de stock se realiza dentro de una **transacción PDO**
- Se usan consultas preparadas en todos los casos
- El stock nunca puede quedar negativo en ajustes
- La función `descontarStockPorVenta()` está lista para ser usada por el módulo de Punto de Venta