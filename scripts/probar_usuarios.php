<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Prueba aislada: no escribe en la base de trabajo ni requiere config/local.php.
$root = dirname(__DIR__);
$config = require $root . '/config/local.example.php';
$dbName = 'tienda_prueba_ivan_' . bin2hex(random_bytes(5));
$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $dbName;
$server = null;
$db = null;
$passed = 0;
function check(bool $condition, string $case): void {
    global $passed;
    if (!$condition) { throw new RuntimeException('FALLÓ: ' . $case); }
    echo 'OK: ' . $case . PHP_EOL;
    $passed++;
}
function request(string $path, string $client = 'admin', ?array $data = null): array {
    global $temp, $port;
    $curl = curl_init('http://127.0.0.1:' . $port . '/' . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $temp . '/' . $client . '.cookies', CURLOPT_COOKIEJAR => $temp . '/' . $client . '.cookies', CURLOPT_TIMEOUT => 10]);
    if ($data !== null) { curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($data)]); }
    $html = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return [$status, (string) $html];
}
function token(string $html): string {
    if (!preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $match)) { throw new RuntimeException('No se encontró CSRF.'); }
    return $match[1];
}
function removeTestDirectory(string $path): void {
    foreach (new DirectoryIterator($path) as $entry) {
        if ($entry->isDot()) { continue; }
        if ($entry->isDir()) { removeTestDirectory($entry->getPathname()); } else { unlink($entry->getPathname()); }
    }
    rmdir($path);
}
try {
    $db = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'], $config['port']), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (['schema.sql', 'seed.sql'] as $file) {
        $sql = str_replace('tienda_abarrotes', $dbName, file_get_contents($root . '/database/' . $file));
        $db->exec($sql);
    }
    check((int) $db->query('SELECT COUNT(*) FROM productos')->fetchColumn() === 10 && (int) $db->query('SELECT COUNT(*) FROM categorias')->fetchColumn() === 3, 'U03 importación de esquema y seed en base vacía');
    mkdir($temp);
    mkdir($temp . '/sessions');
    foreach (['includes', 'config', 'usuarios', 'assets'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        mkdir($temp . '/' . $dir);
        foreach ($iterator as $entry) {
            $destination = $temp . '/' . $dir . '/' . $iterator->getSubPathName();
            if ($entry->isDir()) { mkdir($destination); } else { copy($entry->getPathname(), $destination); }
        }
    }
    foreach (['index.php', 'login.php', 'logout.php'] as $file) { copy($root . '/' . $file, $temp . '/' . $file); }
    $config['database'] = $dbName;
    file_put_contents($temp . '/config/local.php', '<?php return ' . var_export($config, true) . ';');
    $password = bin2hex(random_bytes(12));
    $insert = $db->prepare('INSERT INTO usuarios(nombre,email,password_hash,rol) VALUES(?,?,?,?)');
    $insert->execute(['Administrador prueba', 'admin@example.test', password_hash($password, PASSWORD_DEFAULT), 'admin']);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) { throw new RuntimeException($error); }
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $server = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $temp . '/sessions', '-d', 'display_errors=0', '-S', '127.0.0.1:' . $port, '-t', $temp], [0 => ['pipe', 'r'], 1 => ['file', $temp . '/server.log', 'a'], 2 => ['file', $temp . '/server.log', 'a']], $pipes, $temp);
    if (!is_resource($server)) { throw new RuntimeException('No se pudo iniciar PHP.'); }
    for ($i = 0; $i < 40; $i++) {
        $ready = @fsockopen('127.0.0.1', $port);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }
    check(request('usuarios/index.php', 'anon')[0] === 302, 'U07 usuario anónimo redirigido a login');
    [$status, $html] = request('login.php');
    $csrf = token($html);
    check(request('login.php', 'admin', ['csrf' => $csrf, 'email' => 'admin@example.test', 'password' => 'incorrecta'])[0] === 200 && request('usuarios/index.php')[0] === 302, 'U05 contraseña incorrecta no autentica');
    check(request('login.php', 'admin', ['csrf' => $csrf, 'email' => 'admin@example.test', 'password' => $password])[0] === 302 && request('usuarios/index.php')[0] === 200, 'U05 login correcto permite administrar');
    $csrf = token(request('usuarios/crear.php')[1]);
    $user = ['csrf' => $csrf, 'nombre' => '<script>alert(1)</script>', 'email' => 'cajero@example.test', 'password' => $password, 'rol' => 'cajero', 'activo' => '1'];
    $invalidCsrf = $user;
    unset($invalidCsrf['csrf']);
    check(request('usuarios/crear.php', 'admin', $invalidCsrf)[0] === 403 && (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 1, 'U09 POST sin CSRF rechazado sin escrituras');
    check(request('usuarios/crear.php', 'admin', $user)[0] === 302, 'U08 alta de cajero persistida');
    $record = $db->query("SELECT * FROM usuarios WHERE email='cajero@example.test'")->fetch(PDO::FETCH_ASSOC);
    check(password_verify($password, $record['password_hash']) && $record['password_hash'] !== $password, 'U08 contraseña almacenada con hash');
    check(request('usuarios/crear.php', 'admin', $user)[0] === 422 && (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 2, 'U08 correo duplicado rechazado');
    $invalid = array_replace($user, ['email' => 'otro@example.test', 'rol' => 'superadmin']);
    check(request('usuarios/crear.php', 'admin', $invalid)[0] === 422, 'U08 rol manipulado rechazado');
    $list = request('usuarios/index.php')[1];
    check(str_contains($list, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($list, '<script>alert(1)</script>'), 'U09 nombre escapado en HTML');
    check(request('usuarios/editar.php?id=999999')[0] === 404 && request('usuarios/editar.php?id=abc')[0] === 400, 'U08 identificadores inexistentes o inválidos controlados');
    $edit = array_replace($user, ['nombre' => 'Cajero editado', 'password' => '']);
    check(request('usuarios/editar.php?id=' . $record['id'], 'admin', $edit)[0] === 302 && $db->query('SELECT nombre FROM usuarios WHERE id=' . (int) $record['id'])->fetchColumn() === 'Cajero editado', 'U08 edición persistida conservando contraseña vacía');
    $self = array_replace($user, ['email' => 'admin@example.test', 'rol' => 'cajero', 'password' => '']);
    check(request('usuarios/editar.php?id=1', 'admin', $self)[0] === 422, 'U08 administrador no puede quitarse acceso');
    $cashCsrf = token(request('login.php', 'cashier')[1]);
    check(request('login.php', 'cashier', ['csrf' => $cashCsrf, 'email' => $record['email'], 'password' => $password])[0] === 302, 'U05 cajero inicia sesión');
    check(request('usuarios/index.php', 'cashier')[0] === 403 && request('usuarios/crear.php', 'cashier')[0] === 403 && request('usuarios/editar.php?id=1', 'cashier')[0] === 403 && request('usuarios/crear.php', 'cashier', $user)[0] === 403, 'U07 cajero sin acceso a listado, alta, edición ni POST');
    $edit['activo'] = '0';
    check(request('usuarios/editar.php?id=' . $record['id'], 'admin', $edit)[0] === 302 && request('index.php', 'cashier')[0] === 302, 'U07 desactivación revoca sesión abierta');
    $cashCsrf = token(request('login.php', 'cashier')[1]);
    request('login.php', 'cashier', ['csrf' => $cashCsrf, 'email' => $record['email'], 'password' => $password]);
    check(request('index.php', 'cashier')[0] === 302, 'U05 usuario inactivo no inicia sesión');
    check(request('logout.php')[0] === 405 && request('logout.php', 'admin', [])[0] === 403, 'U06 logout exige POST y CSRF');
    check(request('logout.php', 'admin', ['csrf' => $csrf])[0] === 302 && request('usuarios/index.php')[0] === 302, 'U06 logout elimina acceso privado');
    check(!preg_match('/PHP (Warning|Fatal error|Parse error|Notice)/', file_get_contents($temp . '/server.log')), 'Sin errores ni avisos PHP durante las pruebas');
    echo $passed . ' verificaciones aprobadas.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    $failed = true;
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($db) { $db->exec('DROP DATABASE IF EXISTS `' . $dbName . '`'); }
    if (is_dir($temp)) { removeTestDirectory($temp); }
}
exit(empty($failed) ? 0 : 1);
