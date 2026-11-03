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
