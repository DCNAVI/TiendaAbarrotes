<?php
// Plantilla para las pantallas pendientes; cada módulo reemplaza este contenido.
require __DIR__ . '/header.php';
?>
<h1 class="h3"><?= e($title) ?></h1>
<div class="alert alert-info" role="status">Estructura inicial. Esta funcionalidad está pendiente de implementación.</div>
<p><?= e($description ?? 'Consulta el README del módulo para comenzar.') ?></p>
<a href="<?= e(url()) ?>" class="btn btn-outline-success">Volver al inicio</a>
<?php require __DIR__ . '/footer.php'; ?>
