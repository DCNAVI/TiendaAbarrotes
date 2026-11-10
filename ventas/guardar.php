<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}
verifyCsrf();
http_response_code(501);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'Operación pendiente de implementación. No se guardaron cambios.'], JSON_UNESCAPED_UNICODE);