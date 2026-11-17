# Medel — Punto de venta: módulo de ventas

Documentación del módulo de ventas del sistema **Medel** (checklist V01–V10, rama `feature/ventas`).

## Cómo ejecutar

1. Abre el Panel de XAMPP e inicia **MySQL** (no hace falta Apache: se usa el servidor embebido de PHP).
2. Doble clic en `iniciar_servidor.bat` (o ejecuta `C:\xampp\php\php.exe -S localhost:8000` desde la raíz del proyecto).
3. Abre `http://localhost:8000/ventas/nueva.php` (usa Ctrl+F5 para ver siempre la versión más nueva).

## Archivos del módulo

| Archivo | Qué hace |
| --- | --- |
| `ventas/nueva.php` | Pantalla de venta: buscador, carrito, cobro y botón "Cobrar y guardar venta" |
| `ventas/guardar.php` | Valida y guarda la venta en MySQL (recibe JSON por POST) |
| `ventas/ticket.php` | Ticket imprimible de la venta, se abre con el folio |
| `iniciar_servidor.bat` | Levanta el servidor PHP y abre el navegador |

## Base de datos

- **`productos`** (ya existente): el código detecta automáticamente las columnas reales de código, nombre, precio de venta y stock (por ejemplo `precio_unitario` o `stock_actual`), así que funciona aunque los nombres cambien.
- **`ventas`**: folio, fecha, cajero, método de pago, monto recibido, cambio y total.
- **`detalle_venta`**: venta_id, producto_id, código, nombre, cantidad, precio unitario y subtotal. El código y el nombre se "congelan" al momento de la venta para que el ticket no cambie si después se edita el producto.
- **`movimientos`**: fecha, tipo (`salida`), producto_id, cantidad, referencia (= folio de la venta) y cajero.
- Las tablas `ventas`, `detalle_venta` y `movimientos` se crean automáticamente la primera vez que se cobra una venta.

## Cumplimiento del checklist

- **V01 — Buscador y productos activos:** búsqueda por código o nombre en `nueva.php`; la lista muestra únicamente productos activos (la condición se adapta al tipo de columna: enum o numérico).
- **V02 — Carrito en JavaScript:** agregar producto, cambiar cantidad y quitar renglón, todo sin recargar la página; el carrito sobrevive a las búsquedas (sessionStorage).
- **V03 — Validación de cantidades:** rechaza 0, negativos, decimales y cantidades sin stock, informando SIEMPRE el motivo ("Stock insuficiente: solo hay N piezas de X"). Se valida en el navegador Y otra vez en el servidor.
- **V04 — Formato monetario:** subtotales y total con `formatMoney` (Intl es-MX, MXN) en toda la pantalla.
- **V05 — Cobro:** método de pago efectivo o transferencia; en efectivo pide el monto recibido, calcula el cambio y no permite efectivo menor al total (validado también en el servidor).
- **V06 — Guardado validado en servidor:** el navegador solo envía `id` + `cantidad`; `guardar.php` recalcula los precios leyendo MySQL, valida sesión, carrito, cantidades enteras, stock y método de pago. Manipular el navegador NO altera el cobro.
- **V07 — Transacción y bloqueo:** transacción PDO con `SELECT ... FOR UPDATE`: si dos cajas cobran a la vez, la segunda espera y ve el stock ya descontado (no hay sobreventa); si algo falla a medias, ROLLBACK total (no quedan ventas a medias).
- **V08 — Inventario:** descuenta el stock de cada producto y registra un movimiento de `salida` en `movimientos` con referencia al folio de la venta.
- **V09 — Ticket imprimible:** `ventas/ticket.php?folio=...` muestra folio, fecha, cajero, productos (cantidad, precio unitario y subtotal), total, método de pago, monto recibido y cambio; el botón Imprimir usa estilos de impresión (solo se imprime el ticket, sin botones).
- **V10 — Pruebas y documentación:** guion de pruebas abajo y este documento.

## Guion de pruebas

1. **Venta normal:** agrega 2 o 3 productos, paga en efectivo con monto suficiente → folio generado, cambio correcto y enlace al ticket.
2. **Transferencia:** guarda la venta sin pedir monto ni cambio.
3. **Efectivo insuficiente:** intenta cobrar con menos dinero que el total → bloqueado en pantalla y, si se salta la pantalla, también por el servidor.
4. **Cantidad inválida:** 0, negativo, decimal o mayor al stock → bloqueado con el motivo exacto.
5. **Anti-manipulación (prueba de trampa):** en phpMyAdmin cambia el stock de un producto que ya está en el carrito, luego cobra SIN recargar la página → el servidor rechaza porque recalcula todo desde MySQL.
6. **Ventas simultáneas (V07):** abre dos pestañas con el mismo producto y stock justo para una sola venta; cobra en la pestaña 1 y luego intenta cobrar en la pestaña 2 → la pestaña 2 recibe "Stock insuficiente" al llegar al servidor.
7. **Ticket (V09):** abre "Ver ticket" después de cobrar → folio, fecha, cajero, productos, total, pago y cambio; "Imprimir ticket" muestra la vista previa de impresión solo con el ticket.

## Notas para el equipo (módulo de inventario de Cristo)

- Los movimientos de inventario se guardan en la tabla `movimientos` con: tipo `salida`, la `cantidad` vendida, `referencia` = folio de la venta, `producto_id` y `cajero`.
- Si el módulo de inventario usa otro formato (por ejemplo tipos `entrada`/`salida`/`ajuste`, u otros nombres de columna), coordinar el cambio: solo hay que ajustar el INSERT de movimientos dentro de `ventas/guardar.php`.
- El descuento de stock ocurre dentro de la misma transacción que la venta: si la venta falla, el stock no se descuenta y no se registra movimiento.