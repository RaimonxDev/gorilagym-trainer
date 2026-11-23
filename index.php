<?php
/**
 * GorilaGym · front controller.
 * Todo pasa por aquí: ?r=ruta
 */
declare(strict_types=1);

require __DIR__ . '/private/app/core.php';

if (!is_installed()) {
    header('Location: install/');
    exit;
}

boot_errors();
auto_migrate();
require PRIVATE_DIR . '/app/domain.php';
require PRIVATE_DIR . '/app/push.php';

security_headers();
start_session();

// Rutas cortas para QR / NFC
if (isset($_GET['m'])) $_GET['r'] = 'm';
if (isset($_GET['e'])) $_GET['r'] = 'e';
if (isset($_GET['entrada'])) $_GET['r'] = 'entrada';

$routes = require PRIVATE_DIR . '/app/routes.php';
$r = (string) ($_GET['r'] ?? '');
if ($r === '') {
    $u = current_user();
    if (!$u) redirect(url('login'));
    redirect($u['rol'] === 'alumno' ? url('inicio') : url('coach'));
}

if (!isset($routes[$r])) not_found();
[$file, $fn] = $routes[$r];

if (is_post()) csrf_check();

// Contraseña obligatoria: quien entró por código y aún no la crea, no avanza
if (!str_starts_with($r, 'api/') && !in_array($r, ['cuenta/contrasena', 'logout', 'manifest', 'login', 'login/codigo', 'login/recuperar'], true)) {
    $__u = current_user();
    if ($__u && empty($__u['password_hash'])) redirect(url('cuenta/contrasena'));
}

try {
    require_once PRIVATE_DIR . '/app/controllers/' . $file . '.php';
    $fn();
} catch (Throwable $e) {
    error_log('[' . $r . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (is_api()) json_out(['ok' => false, 'error' => config('debug') ? $e->getMessage() : 'Ocurrió un error. Intenta de nuevo.'], 500);
    http_response_code(500);
    view('shared/error', ['title' => 'Error', 'msg' => config('debug') ? $e->getMessage() : 'Ocurrió un error inesperado. Si se repite, avisa en recepción.']);
}
