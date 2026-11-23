<?php
/**
 * Tareas programadas. Se ejecutan desde private/cron/cron.php (cron cada minuto)
 * o, si el hosting no permite cron, desde ?r=api/cron&token=… (una pasada).
 */
declare(strict_types=1);

function cron_pasada(bool $loop): array
{
    $res = ['push' => 0, 'tareas' => []];
    $fin = time() + ($loop ? 55 : 0);
    do {
        $res['push'] += push_dispatch();
        if ($loop && time() < $fin) sleep(5);
    } while ($loop && time() < $fin);

    // Tareas de mantenimiento (a lo más una vez cada 10 min)
    $last = (int) setting('cron_mant_at', '0');
    if ($last < time() - 600) {
        setting_set('cron_mant_at', (string) time());
        $res['tareas'][] = cron_mantenimiento();
        if ((int) date('G') >= 3 && setting('cron_backup_dia') !== date('Y-m-d')) {
            $res['tareas'][] = backup_crear('auto') ? 'respaldo ok' : 'respaldo falló';
            setting_set('cron_backup_dia', date('Y-m-d'));
        }
        if (setting('reporte_mensual_activo', '1') === '1' && (int) date('j') <= 3 && (int) date('G') >= 9) {
            $res['tareas'][] = 'reportes: ' . cron_reportes_mensuales(40);
        }
    }
    setting_set('cron_ultimo', date('Y-m-d H:i:s'));
    return $res;
}

function cron_mantenimiento(): string
{
    $now = time();
    q('DELETE FROM otp_codes WHERE created_at < ?', [$now - 86400]);
    q('DELETE FROM rate_limits WHERE window_start < ?', [$now - 86400]);
    q('DELETE FROM remember_tokens WHERE expires_at < ?', [$now]);
    q('UPDATE ocupacion SET liberada_at = ? WHERE liberada_at IS NULL AND hasta_est < ?', [$now, $now - 1800]);
    // Sesiones olvidadas abiertas
    q("UPDATE sesiones SET estado = 'finalizada', fin = COALESCE(fin, datetime(inicio, '+120 minutes')) WHERE estado = 'en_curso' AND inicio < datetime('now','localtime','-6 hours')");
    q("UPDATE sesiones SET estado = 'cancelada' WHERE estado = 'planificada' AND created_at < datetime('now','localtime','-1 day')");
    // Pausas activas que terminaron: reactivar membresía y extender
    foreach (qa("SELECT * FROM pausas WHERE estado = 'activa' AND hasta IS NOT NULL AND hasta < date('now','localtime')") as $p) {
        pausa_cerrar((int) $p['id']);
    }
    return 'mantenimiento ok';
}

function pausa_cerrar(int $pausaId): void
{
    $p = q1('SELECT * FROM pausas WHERE id = ?', [$pausaId]);
    if (!$p || $p['estado'] !== 'activa') return;
    $hasta = $p['hasta'] && $p['hasta'] < date('Y-m-d') ? $p['hasta'] : date('Y-m-d');
    $dias = max(0, (int) round((strtotime($hasta) - strtotime($p['desde'])) / 86400));
    tx(function () use ($p, $hasta, $dias) {
        update('pausas', ['estado' => 'cerrada', 'hasta' => $hasta, 'dias' => $dias], 'id = ?', [$p['id']]);
        $m = q1('SELECT * FROM membresias WHERE id = ?', [$p['membresia_id']]);
        update('membresias', ['pausada' => 0, 'fecha_fin' => date('Y-m-d', strtotime($m['fecha_fin'] . " +$dias days"))], 'id = ?', [$m['id']]);
    });
    audit('pausa_cerrada', 'pausas', $pausaId, ['dias_extendidos' => $dias]);
}

function cron_reportes_mensuales(int $max): int
{
    $periodo = date('Y-m', strtotime('first day of last month'));
    $desde = $periodo . '-01';
    $hasta = date('Y-m-t', strtotime($desde));
    $rows = qa("SELECT DISTINCT u.id, u.nombre, u.email FROM users u JOIN sesiones s ON s.alumno_id = u.id
                WHERE u.rol = 'alumno' AND u.activo = 1 AND u.email IS NOT NULL AND s.estado = 'finalizada' AND date(s.created_at) BETWEEN ? AND ?
                AND NOT EXISTS (SELECT 1 FROM reportes_mensuales r WHERE r.user_id = u.id AND r.periodo = ?) LIMIT ?", [$desde, $hasta, $periodo, $max]);
    $n = 0;
    foreach ($rows as $u) {
        $dias = (int) qv("SELECT COUNT(DISTINCT date(created_at)) FROM sesiones WHERE alumno_id = ? AND estado = 'finalizada' AND date(created_at) BETWEEN ? AND ?", [$u['id'], $desde, $hasta]);
        $rec = qa("SELECT l.texto FROM logros l WHERE l.user_id = ? AND l.tipo = 'record' AND date(l.created_at) BETWEEN ? AND ? LIMIT 5", [$u['id'], $desde, $hasta]);
        $mes = mb_strtolower(mes_nombre((int) substr($periodo, 5, 2)));
        $html = '<p>Hola ' . e($u['nombre']) . ',</p><p>Así te fue en ' . $mes . ':</p>'
            . '<p style="font-size:22px;font-weight:bold;margin:12px 0">' . $dias . ' sesiones</p>'
            . ($rec ? '<p><b>Récords del mes</b></p><ul>' . implode('', array_map(fn($r) => '<li>' . e($r['texto']) . '</li>', $rec)) . '</ul>' : '')
            . '<p><a href="' . e(abs_url('reporte', ['periodo' => $periodo])) . '" style="display:inline-block;background:' . e(setting('brand_primary')) . ';color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none">Ver mi resumen completo</a></p>';
        send_mail($u['email'], 'Tu resumen de ' . $mes . ' en ' . setting('brand_nombre'), $html);
        insert('reportes_mensuales', ['user_id' => $u['id'], 'periodo' => $periodo, 'enviado_at' => date('Y-m-d H:i:s')]);
        $n++;
    }
    return $n;
}

function backup_dir(): string
{
    $d = PRIVATE_DIR . '/storage/backups';
    if (!is_dir($d)) mkdir($d, 0700, true);
    return $d;
}

function backup_crear(string $tipo = 'manual'): ?string
{
    $file = backup_dir() . '/gorila-' . date('Ymd-His') . '-' . $tipo . '.sqlite';
    try {
        db()->exec('VACUUM INTO ' . db()->quote($file));
    } catch (Throwable $e) {
        error_log('backup: ' . $e->getMessage());
        return null;
    }
    $files = glob(backup_dir() . '/gorila-*.sqlite');
    rsort($files);
    foreach (array_slice($files, 30) as $old) @unlink($old);
    return basename($file);
}
