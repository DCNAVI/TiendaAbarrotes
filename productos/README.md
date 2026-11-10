# Módulo productos

Responsable: Giselle. Rama: `feature/productos`.

## Rutas iniciales

- crear.php
- editar.php
- index.php

Estado: plantillas pendientes de implementar. Los endpoints de guardado responden 501 hasta implementar la operación; no escriben datos.

Usar includes/bootstrap.php, requireLogin(), requireRole(), verifyCsrf(), e() y database(). Validar datos y permisos en PHP y usar consultas preparadas. Usuarios y reportes requieren administrador; definir permisos de escritura de los demás módulos antes de implementar.

## Pruebas por completar

- [ ] Flujo válido del módulo: registrar entradas y comprobar persistencia en MySQL.
- [ ] Datos inválidos o duplicados: mostrar error sin escrituras parciales.
- [ ] Acceso sin sesión, rol insuficiente y POST sin CSRF: rechazar la solicitud.

Documentar pasos, resultado esperado, resultado real y capturas en docs/pruebas.md. No marcar pruebas como realizadas sin ejecutarlas.