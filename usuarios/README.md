# Módulo de usuarios

Responsable: Ivan. Rama asignada: feature/usuarios.
Estado: implementación local terminada para U01–U09. U10 permanece pendiente de integración del equipo. Ver [lista de actividades](../docs/actividad_ivan.md).

## Rutas

- `index.php`: listado de usuarios con nombre, correo, rol, estado y enlace para editar.
- `crear.php`: alta de administrador o cajero.
- `editar.php?id=...`: edición de datos, rol, estado y contraseña opcional.
- `formulario.php` y `funciones.php`: código interno compartido por alta y edición.
- `../login.php` y `../logout.php`: autenticación y cierre de sesión.

Solo un administrador activo puede gestionar usuarios. Toda escritura requiere POST y CSRF. Los correos duplicados se rechazan también mediante el índice UNIQUE de MySQL. Las contraseñas se guardan con password_hash, admiten de 12 a 72 bytes y nunca se precargan en formularios. En edición, el campo vacío conserva el hash existente.

Los usuarios se desactivan con el estado Inactivo; no se eliminan. Los permisos y el estado se consultan en la base en cada acceso privado. Un administrador no puede desactivar su propia cuenta ni quitarse permisos; se conserva al menos un administrador activo.

## Ejecución

Seguir el README raíz: importar esquema y seed, configurar config/local.php y crear el primer administrador mediante scripts/crear_admin.php. Después abrir usuarios/index.php desde una sesión de administrador. No existen credenciales predeterminadas.

## Pruebas realizadas

El 3 de noviembre de 2026 se ejecutó `C:\xampp\php\php.exe scripts/probar_usuarios.php` con PHP 8.0.30 y MariaDB 10.4.32. Resultado: 20 verificaciones aprobadas mediante HTTP y consultas SQL, sin avisos ni errores PHP.

| Caso | Pasos | Resultado real |
| --- | --- | --- |
| Base vacía | Importar schema y seed en base temporal | Importación correcta, tres categorías y diez productos |
| Login | Probar clave incorrecta, correcta y usuario inactivo | Incorrecta e inactivo sin acceso; correcta permite acceso |
| Alta | Crear cajero y consultar su registro | Registro persistido y contraseña verificada mediante hash |
| Duplicados | Repetir alta con mismo correo | HTTP 422 y solo un registro para el correo |
| Validación | Enviar rol inventado e identificadores inválidos o inexistentes | HTTP 422, 400 y 404 respectivamente |
| Edición | Cambiar nombre dejando contraseña vacía | Cambio persistido y contraseña anterior todavía válida |
| Permisos | Como cajero abrir listado, alta, edición y enviar POST | HTTP 403 en las cuatro operaciones |
| Desactivación | Desactivar cajero con sesión abierta e intentar acceder | Redirección al login en la siguiente solicitud |
| Autoprotección | Administrador intenta cambiar su propia cuenta a cajero | HTTP 422 sin quitar acceso |
| CSRF | Enviar alta y logout sin token | HTTP 403 sin escrituras |
| Escape HTML | Guardar nombre con etiquetas script y consultar listado | Etiquetas escapadas, no interpretadas como HTML |
| Logout | Probar GET, POST sin token y POST con token | HTTP 405, 403 y redirección; después no hay acceso privado |

La prueba usa datos temporales y los elimina al finalizar. Las capturas y el PR del equipo quedan pendientes; no se han publicado cambios ni etiquetado v1.0.
