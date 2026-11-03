# Actividad de Ivan

Responsable: Ivan. Módulo: usuarios, estructura común e integración.
Fuente: Actividad_Detallada_Tienda_Abarrotes_Bootstrap_GitHub.docx.
Actualizado el 3 de noviembre de 2026.

Se marca únicamente el trabajo implementado de Ivan. Los módulos de Giselle, Medel, Cristo e Isacc conservan sus pendientes.

- [x] U01 Crear estructura del repositorio, .gitignore, README raíz y carpetas compartidas. Las instrucciones de ejecución están en README.md.
- [x] U02 Centralizar PDO en config/database.php y proporcionar config/local.example.php. config/local.php está ignorado y no se publican credenciales.
- [x] U03 Crear schema.sql y seed.sql con las seis tablas, claves foráneas e índices. Importación verificada en una base temporal de MariaDB: tres categorías y diez productos.
- [x] U04 Crear header.php, navbar.php y footer.php con Bootstrap 5 y navegación compartida.
- [x] U05 Implementar login por correo, password_verify y rechazo de contraseñas incorrectas o usuarios inactivos.
- [x] U06 Regenerar identificador de sesión al autenticar y cerrar sesión mediante POST y CSRF. Verificado que después del logout se pierde acceso privado.
- [x] U07 Implementar requireLogin y requireRole. Los cajeros no acceden a usuarios. Las rutas privadas consultan el estado y rol actuales; desactivar una cuenta revoca su acceso en la siguiente solicitud.
- [x] U08 Implementar listado, alta y edición de usuarios para administradores, roles admin/cajero, rechazo de correo duplicado y desactivación lógica. Contraseñas con hash; edición permite conservar la anterior dejando el campo vacío. Se impide desactivar la propia cuenta o eliminar al último administrador activo.
- [x] U09 Implementar CSRF en formularios operativos de login, logout y usuarios, y escapar la salida HTML. Probados rechazo de POST sin token y escape de nombres con etiquetas HTML. Los módulos pendientes deberán usar estos mismos helpers al implementar sus formularios.
- [ ] U10 Revisar PR de compañeros, resolver conflictos, probar el flujo completo y etiquetar v1.0. Pendiente: productos, inventario, ventas y reportes todavía son plantillas. No se ha publicado una etiqueta ni se han revisado o integrado PR.

## Verificación

Ejecutar desde la raíz:

```powershell
C:\xampp\php\php.exe scripts/probar_usuarios.php
```

Requiere PHP con PDO MySQL, cURL y mbstring, MariaDB activo y una cuenta de desarrollo con permiso de crear y eliminar bases temporales. El script toma la conexión de config/local.example.php, crea una base con nombre aleatorio, levanta una copia temporal del módulo y elimina esa base y copia al terminar. No modifica la base tienda_abarrotes ni genera usuarios reales.

Resultado del 3 de noviembre de 2026: 20 verificaciones aprobadas. También se verificó la sintaxis PHP del proyecto. Detalle en usuarios/README.md.

La publicación del repositorio, invitaciones a colaboradores, Issues, revisiones, PR y release requieren el flujo de GitHub del equipo y no se marcan como realizados por esta implementación local.
