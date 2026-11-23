<?php
/**
 * Cron de GorilaGym. Configurar en el hosting para que corra CADA MINUTO:
 *   * * * * * php /ruta/a/gorilagym/private/cron/cron.php >/dev/null 2>&1
 * Cada ejecución dura ~55 s y revisa las notificaciones cada 5 s
 * (así el aviso de fin de descanso llega a tiempo).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require dirname(__DIR__) . '/app/core.php';
if (!is_installed()) exit("No instalado\n");
boot_errors();
auto_migrate();
require PRIVATE_DIR . '/app/domain.php';
require PRIVATE_DIR . '/app/push.php';
require PRIVATE_DIR . '/app/cron_tasks.php';

$lock = fopen(PRIVATE_DIR . '/storage/cache/cron.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) exit; // ya hay otra ejecución

$loop = !in_array('--once', $argv, true);
$res = cron_pasada($loop);
if (in_array('-v', $argv, true)) echo json_encode($res, JSON_UNESCAPED_UNICODE) . "\n";
flock($lock, LOCK_UN);
