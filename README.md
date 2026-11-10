# Tienda de abarrotes

Estructura inicial de un punto de venta colaborativo con PHP, MySQL/MariaDB y Bootstrap 5. Incluye conexión PDO, login base, permisos, CSRF y plantillas por módulo. El CRUD, carrito, movimientos y reportes están pendientes: esta versión no es un punto de venta terminado.

## Requisitos y ejecución

- PHP 8.0+ con PDO MySQL y sesiones; XAMPP es una opción.
- MySQL 8.0.16+ o MariaDB 10.2.1+ con InnoDB y restricciones CHECK activas.
- Internet para cargar Bootstrap desde CDN.

1. Clonar el repositorio dentro de `htdocs` o en una carpeta local.
2. Importar `database/schema.sql` y después `database/seed.sql` usando phpMyAdmin o el cliente MySQL. El esquema crea la base `tienda_abarrotes`; la cuenta usada para importarlo necesita ese permiso.
3. Copiar `config/local.example.php` a `config/local.php` y configurar las credenciales locales. Este último archivo está ignorado por Git.
4. Crear el primer administrador desde una terminal en la raíz: `php scripts/crear_admin.php`. En XAMPP para Windows se puede usar `C:\xampp\php\php.exe scripts/crear_admin.php`. La contraseña se solicita de forma interactiva y es visible en esa terminal; no hay credenciales predeterminadas.
5. Iniciar Apache y abrir `http://localhost/TiendaAbarrotes/`. Como alternativa: `php -S 127.0.0.1:8000 -t .` y abrir `http://127.0.0.1:8000`.
6. Iniciar sesión para acceder a las plantillas. Usuarios y reportes requieren rol administrador.

El servidor integrado es para desarrollo. Antes de un despliegue deben configurarse HTTPS, límites de intentos de login y restricciones del servidor para impedir servir `config`, `database`, `docs`, `storage` y archivos ocultos. La configuración y scripts PHP no imprimen credenciales.

## Estructura

```text
assets/              CSS, JavaScript e imágenes
config/              Conexión PDO y ejemplo de configuración local
includes/            Bootstrap PHP, sesiones, permisos, CSRF y layout
categorias/          Listado de categorías
productos/           Listado, alta y edición de productos
ventas/              Nueva venta, guardado y ticket
inventario/          Existencias, guardado de movimientos e historial
reportes/            Ventas y detalle
usuarios/            Listado, alta y edición de usuarios
database/            schema.sql, seed.sql y contrato de datos
docs/                Pruebas e instrucciones de colaboración
scripts/             Creación inicial del administrador por CLI
storage/logs/        Carpeta reservada; no se versionan logs
index.php            Inicio y accesos a módulos
login.php            Autenticación con password_verify
logout.php           Cierre de sesión mediante POST y CSRF
```

## Responsables

| Integrante | Módulo | Rama |
| --- | --- | --- |
| Giselle | Productos y categorías | feature/productos |
| Medel | Punto de venta | feature/ventas |
| Cristo | Inventario | feature/inventario |
| Isacc | Historial y reportes | feature/reportes |
| Ivan | Usuarios y estructura común | feature/usuarios |

Cada módulo tiene su README. Ver [contrato de datos](database/README.md), [colaboración](docs/colaboracion.md) y [pruebas](docs/pruebas.md).

## Acuerdos de implementación

- Consultas preparadas PDO y validación en PHP. Escapar salida HTML con `e()`.
- Toda escritura debe usar POST, sesión, autorización y token CSRF.
- Los endpoints `ventas/guardar.php` e `inventario/guardar_movimiento.php` devuelven 501 hasta implementar sus transacciones.
- Los precios se obtienen de MySQL al vender. No confiar en precios enviados por JavaScript ni usar FLOAT para persistir dinero.
- Venta, detalles, stock y movimientos se guardarán en una sola transacción, bloqueando productos con `SELECT ... FOR UPDATE`.
- Los productos y usuarios se desactivan; las ventas no se borran. Su cancelación requiere revertir stock exactamente una vez.
- No publicar credenciales, datos privados ni el documento de la actividad, que se conserva localmente.
