# Registro de pruebas

Esta es una estructura inicial. Las pruebas funcionales de los módulos están pendientes; no marcar casos como aprobados sin ejecutarlos.

## Verificación inicial realizada el 3 de noviembre de 2026

- Sintaxis de los 27 archivos PHP: correcta con PHP 8.0.30.
- Sintaxis de assets/js/app.js: correcta mediante node --check.
- Importación de schema.sql y seed.sql en una base temporal de MariaDB 10.4.32: correcta; seis tablas, tres categorías y diez productos. La base temporal se eliminó al terminar.
- Inicio y login por HTTP: respuesta 200 sin avisos PHP, cookie de sesión y token CSRF generado.
- Acceso a productos sin sesión: redirección a login.php.
- Logout sin CSRF: respuesta 403; con token válido: redirección a login.php.
- Para esta verificación se configuró session.save_path en el directorio temporal permitido, porque el entorno de pruebas no puede escribir en C:\xampp\tmp. En un entorno local, PHP necesita permisos sobre su directorio de sesiones.

No se ha probado todavía el login contra un usuario real ni los flujos de negocio pendientes.

| Caso | Pasos y resultado esperado | Estado |
| --- | --- | --- |
| Base de datos | Importar schema y seed en base vacía; comprobar seis tablas, tres categorías y diez productos | Pendiente |
| Restricciones SQL | Intentar código/email duplicado, stock negativo y subtotal incorrecto; deben rechazarse | Pendiente |
| Login | Crear administrador; probar contraseña correcta e incorrecta | Pendiente |
| Sesión | Acceder sin sesión a cada módulo; debe redirigir al login | Pendiente |
| Permisos | Acceder con cajero a usuarios y reportes; debe responder 403 | Pendiente |
| CSRF | Enviar POST sin token a logout o guardado; debe responder 403 | Pendiente |
| Método HTTP | Enviar GET a endpoints de guardado y logout; debe responder 405 | Pendiente |
| Venta integrada | Producto con 10, entrada de 5 y venta de 3; saldo final 12 y ticket correcto | Pendiente de implementación |
| Concurrencia | Dos ventas simultáneas sobre pocas unidades; no permitir stock negativo ni venta parcial | Pendiente de implementación |

Por cada módulo registrar al menos tres casos con fecha, responsable, datos usados, resultado real y evidencia. Añadir capturas al PR, sin contraseñas ni datos privados.

## Módulo de Ivan verificado el 3 de noviembre de 2026

La verificación posterior con `C:\xampp\php\php.exe scripts/probar_usuarios.php` completa las pruebas de usuarios que figuraban pendientes arriba. Resultado: 20 verificaciones aprobadas con PHP 8.0.30 y MariaDB 10.4.32, mediante HTTP y comprobación de persistencia SQL en una base temporal eliminada al finalizar.

Se probaron importación del esquema y seed, login correcto e incorrecto, usuario inactivo, alta, edición, hash de contraseña, correo duplicado, rol inválido, identificadores inválidos/inexistentes, escape HTML, POST sin CSRF, permisos de cajero, desactivación con sesión abierta, protección de la propia cuenta y logout con pérdida de acceso. No hubo avisos ni errores PHP.

Los pasos y resultados están en [usuarios/README.md](../usuarios/README.md). La lista de U01–U09 terminadas y U10 pendiente está en [actividad de Ivan](actividad_ivan.md). Los casos de negocio de otros integrantes y la integración completa permanecen pendientes.

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
