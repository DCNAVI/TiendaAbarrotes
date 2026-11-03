# Registro de pruebas

Esta es una estructura inicial. Las pruebas funcionales de los módulos están pendientes; no marcar casos como aprobados sin ejecutarlos.

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
