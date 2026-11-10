## Módulo reportes (Isacc · feature/reportes)

Rutas: `reportes/ventas.php`, `reportes/detalle.php`, `reportes/resumen.php`.

> Completar "Resultado real" y adjuntar capturas solo después de ejecutar cada prueba.

| # | Prueba | Pasos | Resultado esperado | Resultado real | Captura |
| --- | --- | --- | --- | --- | --- |
| 1 | Rango vacío | Entrar como admin y filtrar un periodo sin ventas (ej. año 2000) | Tabla con "No hay ventas en el periodo", indicadores en 0 y ticket promedio $0.00, sin errores PHP | Pendiente | |
| 2 | Fecha inválida | Abrir `ventas.php?desde=2026-02-31` y `?desde=abc` | Alerta de fecha inválida y se usa el periodo por defecto, sin errores PHP | Pendiente | |
| 3 | Fecha inicial mayor a la final | `ventas.php?desde=2026-11-10&hasta=2026-11-01` | Alerta y periodo por defecto | Pendiente | |
| 4 | Método inválido | `ventas.php?metodo=bitcoin` | Alerta y filtro "Todos" | Pendiente | |
| 5 | Ventas múltiples y filtro de periodo | Con 3+ ventas en fechas distintas, filtrar un solo día | Solo aparecen ventas de ese día | Pendiente | |
| 6 | Filtro por método | Filtrar efectivo y luego transferencia | Cada listado muestra solo su método | Pendiente | |
| 7 | Indicadores | Comparar número de ventas, ingresos y ticket promedio contra suma manual de las ventas de prueba | Coinciden; canceladas excluidas | Pendiente | |
| 8 | Detalle de venta | Abrir una venta desde "Ver detalle" | Suma de subtotales = total y aparece el mensaje de coincidencia | Pendiente | |
| 9 | Detalle con folio inexistente o inválido | `detalle.php?id=99999` y `?id=abc` | 404 / 400 con mensaje, sin errores PHP | Pendiente | |
| 10 | Productos más vendidos | Revisar top 10 contra los detalles de venta | Orden descendente por unidades | Pendiente | |
| 11 | Por día y por método | Revisar filas "Suma" | Ambas igualan el total general (insignia verde) | Pendiente | |
| 12 | Gráfico | Comparar barras con la tabla "Ventas por día" | Mismas cifras | Pendiente | |
| 13 | Impresión | Botón "Imprimir reporte" → vista previa | Sin navbar, filtros ni botones; con línea de periodo | Pendiente | |
| 14 | Permisos: sin sesión | Abrir `reportes/ventas.php` sin iniciar sesión | Redirige a login | Pendiente | |
| 15 | Permisos: cajero | Iniciar sesión como cajero y abrir los 3 reportes | 403 "Acceso denegado" (con `REPORTES_CAJERO_VE_PROPIAS = false`) | Pendiente | |
