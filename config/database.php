<?php
declare(strict_types=1);

function database(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }
    $path = __DIR__ . '/local.php';
    if (!is_file($path)) {
        throw new RuntimeException('Copia config/local.example.php como config/local.php y configura MySQL.');
    }
    $config = require $path;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'], $config['port'], $config['database']);
    $connection = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}
