# Colaboración con GitHub

Publicar la estructura inicial en main. A partir de esa base, cada integrante crea su rama:

```bash
git switch main
git pull --ff-only
git switch -c feature/productos
```

Usar la rama asignada en el README. Registrar tareas en Issues, hacer commits pequeños y abrir Pull Request hacia main. Cada PR requiere revisión de otro integrante, pruebas documentadas y capturas cuando corresponda. Después de la publicación inicial, no hacer push directo a main. Configurar protección de la rama en GitHub cuando el repositorio esté disponible.

Coordinar cambios de tablas y funciones compartidas antes de modificarlas. No cambiar módulos ajenos sin acuerdo. No subir `config/local.php`, contraseñas, datos privados, logs, vendor ni node_modules.

Ejemplo de commit: `feat(productos): agregar formulario de alta`.
