<?php
/**
 * Lógica de negocio: planes, acceso, sesiones, ocupación, progresión, logros, notificaciones.
 */
declare(strict_types=1);

// ================================================================ Planes y acceso
function membresia_actual(int $uid): ?array
{
    $hoy = date('Y-m-d');
    $m = q1("SELECT m.*, p.nombre AS plan_nombre, p.tipo AS plan_tipo, p.requiere_estudiante
             FROM membresias m JOIN planes p ON p.id = m.plan_id
             WHERE m.user_id = ? AND m.fecha_inicio <= ? ORDER BY (m.fecha_fin >= ?) DESC, m.fecha_fin DESC LIMIT 1", [$uid, $hoy, $hoy]);
    if (!$m) {
        $m = q1("SELECT m.*, p.nombre AS plan_nombre, p.tipo AS plan_tipo, p.requiere_estudiante
                 FROM membresias m JOIN planes p ON p.id = m.plan_id WHERE m.user_id = ? ORDER BY m.fecha_inicio DESC LIMIT 1", [$uid]);
        if (!$m) return null;
    }
    if ($m['pausada']) $m['estado'] = 'pausada';
    elseif ($m['fecha_inicio'] > $hoy) $m['estado'] = 'futura';
    elseif ($m['fecha_fin'] >= $hoy) $m['estado'] = 'vigente';
    else $m['estado'] = 'vencida';
    $m['dias_restantes'] = (int) floor((strtotime($m['fecha_fin']) - strtotime($hoy)) / 86400);
    return $m;
}

function plan_horarios(int $planId): array
{
    return qa('SELECT * FROM plan_horarios WHERE plan_id = ? ORDER BY dia, hora_inicio', [$planId]);
}

function plan_horario_ok(int $planId, ?int $ts = null): bool
{
    $ts = $ts ?? time();
    $dia = (int) date('N', $ts);
    $hora = date('H:i', $ts);
    return (bool) qv('SELECT 1 FROM plan_horarios WHERE plan_id = ? AND dia = ? AND hora_inicio <= ? AND hora_fin > ?', [$planId, $dia, $hora, $hora]);
}

function plan_horario_texto(int $planId): string
{
    $rows = plan_horarios($planId);
    $grupos = [];
    foreach ($rows as $r) $grupos[$r['hora_inicio'] . '–' . $r['hora_fin']][] = (int) $r['dia'];
    $abbr = ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    $out = [];
    foreach ($grupos as $h => $dias) {
        $txt = count($dias) > 2 && max($dias) - min($dias) === count($dias) - 1 ? $abbr[min($dias)] . '–' . $abbr[max($dias)] : implode(', ', array_map(fn($d) => $abbr[$d], $dias));
        $out[] = $txt . ' ' . $h;
    }
    return implode(' · ', $out);
}

function acreditacion_vigente(int $uid): ?array
{
    return q1('SELECT * FROM acreditaciones WHERE user_id = ? AND vence >= ? ORDER BY vence DESC LIMIT 1', [$uid, date('Y-m-d')]);
}

function pase_diario_hoy(int $uid): bool
{
    return (bool) qv('SELECT 1 FROM pases_diarios WHERE user_id = ? AND fecha = ?', [$uid, date('Y-m-d')]);
}

/** ¿Puede entrenar ahora? Devuelve [bool, motivo, membresia] */
function acceso_estado(int $uid): array
{
    if (pase_diario_hoy($uid)) return [true, 'Pase Diario de hoy', null];
    $m = membresia_actual($uid);
    if (!$m) return [false, 'No tienes un plan registrado. Acércate a recepción.', null];
    if ($m['estado'] === 'pausada') return [false, 'Tu plan está en pausa.', $m];
    if ($m['estado'] === 'futura') return [false, 'Tu plan comienza el ' . fecha($m['fecha_inicio']) . '.', $m];
    if ($m['estado'] === 'vencida') return [false, 'Tu plan venció el ' . fecha($m['fecha_fin']) . '. Renueva en recepción.', $m];
    if ($m['requiere_estudiante'] && !acreditacion_vigente($uid)) return [false, 'Debes acreditar tu condición de estudiante en recepción.', $m];
    if (!plan_horario_ok((int) $m['plan_id'])) return [false, 'Tu plan ' . $m['plan_nombre'] . ' no tiene acceso en este horario (' . plan_horario_texto((int) $m['plan_id']) . ').', $m];
    return [true, 'Plan ' . $m['plan_nombre'] . ' vigente', $m];
}

function alumno_modo(int $uid): string
{
    $m = membresia_actual($uid);
    return ($m && $m['plan_tipo'] === 'libre') ? 'libre' : 'coach';
}

function registrar_checkin(int $uid, string $metodo): bool
{
    $ult = qv("SELECT created_at FROM checkins WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$uid]);
    if ($ult && strtotime($ult) > time() - 3 * 3600) return false; // ya registrado
    insert('checkins', ['user_id' => $uid, 'sede_id' => sede_id(), 'metodo' => $metodo]);
    return true;
}

function checkin_hoy(int $uid): ?array
{
    return q1("SELECT * FROM checkins WHERE user_id = ? AND date(created_at) = date('now','localtime') ORDER BY id DESC LIMIT 1", [$uid]);
}

function sede_id(): int
{
    static $s = null;
    if ($s === null) $s = (int) (qv('SELECT id FROM sedes WHERE activa = 1 ORDER BY id LIMIT 1') ?: 1);
    return $s;
}
function sede(): array
{
    return q1('SELECT * FROM sedes WHERE id = ?', [sede_id()]) ?? [];
}

// ================================================================ Restricciones
function tags_list(?string $csv): array
{
    if (!$csv) return [];
    return array_values(array_filter(array_map(fn($t) => mb_strtolower(trim($t)), explode(',', $csv))));
}
function restricciones_catalogo(): array
{
    return ['hombro', 'codo', 'muñeca', 'cervical', 'lumbar', 'cadera', 'rodilla', 'tobillo', 'embarazo', 'hipertensión', 'cardíaco'];
}
function ejercicio_conflictos(array $ej, array $restriccionesAlumno): array
{
    return array_values(array_intersect(tags_list($ej['restricciones'] ?? ''), $restriccionesAlumno));
}

// ================================================================ Tiempos
function item_segundos(array $it): int
{
    $series = max(1, (int) $it['series']);
    $sps = (int) ($it['seg_por_serie'] ?? 45);
    $desc = (int) $it['descanso_seg'];
    return $series * $sps + ($series - 1) * $desc;
}
function sesion_items(int $sesionId): array
{
    return qa('SELECT si.*, e.nombre, e.maquina_id, e.seg_por_serie, e.grupo_id, e.foto, e.video, e.restricciones, e.incremento_kg,
                      m.nombre AS maquina_nombre, m.estado AS maquina_estado, g.nombre AS grupo_nombre
               FROM sesion_items si JOIN ejercicios e ON e.id = si.ejercicio_id
               LEFT JOIN maquinas m ON m.id = e.maquina_id
               LEFT JOIN grupos_musculares g ON g.id = e.grupo_id
               WHERE si.sesion_id = ? ORDER BY si.orden', [$sesionId]);
}
function estimacion_min(array $items): int
{
    if (!$items) return 0;
    $trans = (int) setting('transicion_seg', '90');
    $s = 0;
    foreach ($items as $it) $s += item_segundos($it) + $trans;
    return (int) ceil($s / 60);
}
function restante_min(array $items): int
{
    return estimacion_min(array_filter($items, fn($i) => !in_array($i['estado'], ['hecho', 'omitido'], true)));
}
function recalc_sesion(int $sesionId): int
{
    $min = estimacion_min(array_filter(sesion_items($sesionId), fn($i) => !$i['extra']));
    update('sesiones', ['est_min' => $min], 'id = ?', [$sesionId]);
    return $min;
}
function reordenar(int $sesionId): void
{
    $i = 1;
    foreach (qa('SELECT id FROM sesion_items WHERE sesion_id = ? ORDER BY orden, id', [$sesionId]) as $r) {
        update('sesion_items', ['orden' => $i++], 'id = ?', [$r['id']]);
    }
}

// ================================================================ Grupos musculares sugeridos
function grupos_recientes(int $uid, int $sesiones = 3): array
{
    $rows = qa("SELECT s.id, s.created_at, GROUP_CONCAT(DISTINCT g.nombre) AS grupos
                FROM sesiones s JOIN sesion_items si ON si.sesion_id = s.id JOIN ejercicios e ON e.id = si.ejercicio_id
                LEFT JOIN grupos_musculares g ON g.id = e.grupo_id
                WHERE s.alumno_id = ? AND s.estado IN ('en_curso','finalizada')
                GROUP BY s.id ORDER BY s.created_at DESC LIMIT ?", [$uid, $sesiones]);
    return $rows;
}
function grupo_sugerido(int $uid): ?string
{
    $todos = array_column(qa("SELECT nombre FROM grupos_musculares WHERE nombre NOT IN ('Cardio','Cuerpo completo','Antebrazo','Pantorrillas') ORDER BY orden"), 'nombre');
    $ult = [];
    foreach (qa("SELECT g.nombre, MAX(s.created_at) AS ultima FROM sesiones s JOIN sesion_items si ON si.sesion_id = s.id
                 JOIN ejercicios e ON e.id = si.ejercicio_id JOIN grupos_musculares g ON g.id = e.grupo_id
                 WHERE s.alumno_id = ? AND s.estado IN ('en_curso','finalizada') GROUP BY g.nombre", [$uid]) as $r) {
        $ult[$r['nombre']] = $r['ultima'];
    }
    if (!$ult) return null;
    usort($todos, fn($a, $b) => strcmp($ult[$a] ?? '0', $ult[$b] ?? '0'));
    return $todos[0] ?? null;
}

// ================================================================ Progresión
function ultimo_trabajo(int $uid, int $ejId, ?int $excluirSesion = null): ?array
{
    $sid = qv('SELECT sesion_id FROM series_log WHERE alumno_id = ? AND ejercicio_id = ? AND (? IS NULL OR sesion_id != ?) ORDER BY id DESC LIMIT 1', [$uid, $ejId, $excluirSesion, $excluirSesion]);
    if (!$sid) return null;
    $series = qa('SELECT sl.*, si.reps_max, si.reps_min, si.series AS series_obj FROM series_log sl JOIN sesion_items si ON si.id = sl.item_id
                  WHERE sl.sesion_id = ? AND sl.ejercicio_id = ? AND sl.alumno_id = ? ORDER BY sl.n', [$sid, $ejId, $uid]);
    if (!$series) return null;
    $fecha = qv('SELECT created_at FROM sesiones WHERE id = ?', [$sid]);
    return ['sesion_id' => (int) $sid, 'fecha' => $fecha, 'series' => $series];
}

/** Doble progresión: si completó el tope de reps en todas las series objetivo → +incremento. */
function peso_sugerido(int $uid, int $ejId, ?int $excluirSesion = null): ?array
{
    $u = ultimo_trabajo($uid, $ejId, $excluirSesion);
    if (!$u) return null;
    $ej = q1('SELECT incremento_kg FROM ejercicios WHERE id = ?', [$ejId]);
    $series = $u['series'];
    $pesoMax = max(array_map(fn($s) => (float) $s['peso'], $series));
    $topes = array_filter($series, fn($s) => (float) $s['peso'] >= $pesoMax);
    $objSeries = (int) $series[0]['series_obj'];
    $repsMax = (int) $series[0]['reps_max'];
    $completo = count($series) >= $objSeries && count(array_filter($series, fn($s) => (int) $s['reps'] >= $repsMax)) >= $objSeries;
    if ($completo && $pesoMax > 0) {
        return ['peso' => $pesoMax + (float) ($ej['incremento_kg'] ?? 2.5), 'motivo' => 'Completó ' . $repsMax . ' reps en todas las series: sube ' . kg($ej['incremento_kg'] ?? 2.5), 'subir' => true, 'anterior' => $pesoMax];
    }
    return ['peso' => $pesoMax, 'motivo' => 'Mantener peso hasta completar el tope de reps', 'subir' => false, 'anterior' => $pesoMax];
}

function epley(float $peso, int $reps): float
{
    if ($reps <= 0 || $peso <= 0) return 0.0;
    if ($reps === 1) return $peso;
    return round($peso * (1 + $reps / 30), 1);
}

// ================================================================ Ocupación de máquinas
function maquina_ocupacion(int $maquinaId, ?int $excluirAlumno = null): ?array
{
    $now = time();
    $r = q1('SELECT o.*, u.nombre AS alumno_nombre, u.apellido AS alumno_apellido, s.coach_id, c.nombre AS coach_nombre
             FROM ocupacion o LEFT JOIN users u ON u.id = o.alumno_id
             LEFT JOIN sesion_items si ON si.id = o.item_id LEFT JOIN sesiones s ON s.id = si.sesion_id
             LEFT JOIN users c ON c.id = s.coach_id
             WHERE o.maquina_id = ? AND o.liberada_at IS NULL AND o.hasta_est > ? AND (? IS NULL OR o.alumno_id != ?)
             ORDER BY o.hasta_est DESC LIMIT 1', [$maquinaId, $now - 120, $excluirAlumno, $excluirAlumno]);
    if (!$r) return null;
    $r['libre_en_min'] = max(1, (int) ceil(($r['hasta_est'] - $now) / 60));
    return $r;
}
function ocupar_maquina(array $item, int $alumnoId): void
{
    if (empty($item['maquina_id'])) return;
    if ((int) qv('SELECT es_zona FROM maquinas WHERE id = ?', [$item['maquina_id']])) return;
    q('UPDATE ocupacion SET liberada_at = ? WHERE alumno_id = ? AND liberada_at IS NULL', [time(), $alumnoId]);
    insert('ocupacion', [
        'maquina_id' => (int) $item['maquina_id'], 'item_id' => (int) $item['id'], 'alumno_id' => $alumnoId,
        'desde' => time(), 'hasta_est' => time() + item_segundos($item),
    ]);
}
function liberar_item(int $itemId): void
{
    q('UPDATE ocupacion SET liberada_at = ? WHERE item_id = ? AND liberada_at IS NULL', [time(), $itemId]);
}
function ocupacion_actual(): array
{
    return qa('SELECT o.*, m.nombre AS maquina, u.nombre AS alumno_nombre, u.apellido AS alumno_apellido
               FROM ocupacion o JOIN maquinas m ON m.id = o.maquina_id LEFT JOIN users u ON u.id = o.alumno_id
               WHERE o.liberada_at IS NULL AND o.hasta_est > ? ORDER BY m.nombre', [time() - 120]);
}

/** Recalibra seg_por_serie con datos reales (mediana de duración por serie de las últimas 30 ejecuciones). */
function recalibrar_ejercicio(int $ejId): void
{
    $rows = qa("SELECT si.inicio, si.fin, si.series, si.descanso_seg FROM sesion_items si
                WHERE si.ejercicio_id = ? AND si.estado = 'hecho' AND si.inicio IS NOT NULL AND si.fin IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM series_log sl WHERE sl.item_id = si.id AND sl.auto = 1)
                ORDER BY si.id DESC LIMIT 30", [$ejId]);
    if (count($rows) < 5) return;
    $vals = [];
    foreach ($rows as $r) {
        $dur = strtotime($r['fin']) - strtotime($r['inicio']);
        $series = max(1, (int) $r['series']);
        $sps = ($dur - ($series - 1) * (int) $r['descanso_seg']) / $series;
        if ($sps > 10 && $sps < 300) $vals[] = $sps;
    }
    if (count($vals) < 5) return;
    sort($vals);
    $med = (int) round($vals[intdiv(count($vals), 2)]);
    update('ejercicios', ['seg_por_serie' => $med], 'id = ?', [$ejId]);
}

// ================================================================ Logros
function revisar_record(int $uid, int $ejId, float $peso, int $reps, int $sesionId): ?array
{
    if ($peso <= 0) return null;
    $prev = (float) (qv('SELECT MAX(peso) FROM series_log WHERE alumno_id = ? AND ejercicio_id = ? AND sesion_id != ?', [$uid, $ejId, $sesionId]) ?? 0);
    $yaHoy = qv("SELECT 1 FROM logros WHERE user_id = ? AND ejercicio_id = ? AND tipo = 'record' AND valor >= ? AND date(created_at) = date('now','localtime')", [$uid, $ejId, $peso]);
    if ($prev <= 0 || $peso <= $prev || $yaHoy) return null;
    $ej = q1('SELECT nombre FROM ejercicios WHERE id = ?', [$ejId]);
    $id = insert('logros', ['user_id' => $uid, 'tipo' => 'record', 'ejercicio_id' => $ejId, 'valor' => $peso, 'texto' => 'Nuevo récord en ' . $ej['nombre']]);
    $u = q1('SELECT muro_opt_in, muro_logros, muro_mostrar_pesos FROM users WHERE id = ?', [$uid]);
    if ($u && $u['muro_opt_in'] && $u['muro_logros'] && setting('muro_activo', '1') === '1') {
        $txt = 'Nuevo récord personal en ' . $ej['nombre'] . ($u['muro_mostrar_pesos'] ? ' · ' . kg($peso) : '');
        insert('muro_posts', ['user_id' => $uid, 'tipo' => 'logro', 'texto' => $txt, 'logro_id' => $id]);
    }
    return ['id' => $id, 'texto' => 'Nuevo récord en ' . $ej['nombre'], 'anterior' => $prev];
}

function semanas_seguidas(int $uid): int
{
    $fechas = array_column(qa("SELECT DISTINCT date(created_at) AS d FROM checkins WHERE user_id = ?
                                UNION SELECT DISTINCT date(created_at) FROM sesiones WHERE alumno_id = ? AND estado IN ('en_curso','finalizada')", [$uid, $uid]), 'd');
    $set = [];
    foreach ($fechas as $f) $set[date('o-W', strtotime($f))] = true;
    $n = 0;
    $t = time();
    if (!isset($set[date('o-W', $t)])) $t -= 7 * 86400; // semana actual aún en curso
    while (isset($set[date('o-W', $t)])) {
        $n++;
        $t -= 7 * 86400;
    }
    return $n;
}

function revisar_racha(int $uid): void
{
    $n = semanas_seguidas($uid);
    if (!in_array($n, [4, 8, 12, 16, 24, 36, 52], true)) return;
    if (qv("SELECT 1 FROM logros WHERE user_id = ? AND tipo = 'racha' AND valor = ?", [$uid, $n])) return;
    $id = insert('logros', ['user_id' => $uid, 'tipo' => 'racha', 'valor' => $n, 'texto' => $n . ' semanas seguidas']);
    $u = q1('SELECT muro_opt_in, muro_logros FROM users WHERE id = ?', [$uid]);
    if ($u && $u['muro_opt_in'] && $u['muro_logros']) insert('muro_posts', ['user_id' => $uid, 'tipo' => 'logro', 'texto' => $n . ' semanas seguidas entrenando', 'logro_id' => $id]);
}

// ================================================================ Muro
function estado_muro_desde_sesion(int $sesionId): void
{
    if (setting('muro_activo', '1') !== '1') return;
    $s = q1('SELECT * FROM sesiones WHERE id = ?', [$sesionId]);
    if (!$s) return;
    $u = q1('SELECT muro_opt_in FROM users WHERE id = ?', [$s['alumno_id']]);
    if (!$u || !$u['muro_opt_in']) return;
    // Si el alumno ya publicó su estado hoy, no se pisa
    if (qv("SELECT 1 FROM muro_posts WHERE user_id = ? AND tipo = 'estado' AND auto = 0 AND date(created_at) = date('now','localtime')", [$s['alumno_id']])) return;
    $grupos = array_column(qa('SELECT DISTINCT g.nombre FROM sesion_items si JOIN ejercicios e ON e.id = si.ejercicio_id JOIN grupos_musculares g ON g.id = e.grupo_id WHERE si.sesion_id = ? ORDER BY si.orden', [$sesionId]), 'nombre');
    if (!$grupos) return;
    $txt = 'Hoy: ' . mb_strtolower(implode(', ', array_slice($grupos, 0, 3))) . ' 💪';
    $ex = q1("SELECT id FROM muro_posts WHERE user_id = ? AND tipo = 'estado' AND auto = 1 AND eliminado_at IS NULL AND date(created_at) = date('now','localtime')", [$s['alumno_id']]);
    if ($ex) update('muro_posts', ['texto' => $txt, 'sesion_id' => $sesionId], 'id = ?', [$ex['id']]);
    else insert('muro_posts', ['user_id' => $s['alumno_id'], 'tipo' => 'estado', 'texto' => $txt, 'sesion_id' => $sesionId, 'auto' => 1]);
}

function spotify_parse(string $link): ?array
{
    if (preg_match('~open\.spotify\.com/(?:intl-[a-z]{2}/)?(track|album|playlist|episode)/([A-Za-z0-9]{10,40})~', $link, $m)) {
        return ['tipo' => $m[1], 'id' => $m[2]];
    }
    if (preg_match('~^spotify:(track|album|playlist|episode):([A-Za-z0-9]{10,40})$~', trim($link), $m)) {
        return ['tipo' => $m[1], 'id' => $m[2]];
    }
    return null;
}
function spotify_embed(string $tipo, string $id, int $h = 80): string
{
    return '<iframe class="w-full rounded-lg" style="height:' . $h . 'px" src="https://open.spotify.com/embed/' . e($tipo) . '/' . e($id) . '?theme=0" loading="lazy" allow="encrypted-media" title="Spotify"></iframe>';
}

// ================================================================ Notificaciones
function notificar(int $uid, string $tipo, string $titulo, string $cuerpo = '', ?string $urlRel = null, ?int $at = null, ?string $ref = null): int
{
    if ($ref) q('UPDATE notificaciones SET cancelada = 1 WHERE user_id = ? AND ref = ? AND enviada_at IS NULL', [$uid, $ref]);
    return insert('notificaciones', [
        'user_id' => $uid, 'tipo' => $tipo, 'titulo' => $titulo, 'cuerpo' => $cuerpo, 'url' => $urlRel,
        'ref' => $ref, 'enviar_at' => $at ?? time(),
    ]);
}
function cancelar_notif(int $uid, string $ref): void
{
    q('UPDATE notificaciones SET cancelada = 1 WHERE user_id = ? AND ref = ? AND enviada_at IS NULL', [$uid, $ref]);
}

// ================================================================ Alertas para coach / jefe
function alertas_staff(int $limit = 30): array
{
    $out = ['inactivos' => [], 'vencen' => [], 'estancados' => []];
    $dias = (int) setting('alerta_inactivo_dias', '10');
    $hoy = date('Y-m-d');
    $out['inactivos'] = qa("SELECT u.id, u.nombre, u.apellido, MAX(c.created_at) AS ultima
        FROM users u JOIN membresias m ON m.user_id = u.id AND m.fecha_fin >= ? AND m.fecha_inicio <= ? AND m.pausada = 0
        LEFT JOIN checkins c ON c.user_id = u.id
        WHERE u.rol = 'alumno' AND u.activo = 1
        GROUP BY u.id HAVING ultima IS NULL OR ultima < datetime('now','localtime', ?) ORDER BY ultima LIMIT ?", [$hoy, $hoy, '-' . $dias . ' days', $limit]);
    $vd = (int) setting('alerta_vence_dias', '7');
    $out['vencen'] = qa("SELECT u.id, u.nombre, u.apellido, m.fecha_fin, p.nombre AS plan FROM membresias m JOIN users u ON u.id = m.user_id JOIN planes p ON p.id = m.plan_id
        WHERE u.activo = 1 AND m.fecha_fin BETWEEN ? AND ? AND NOT EXISTS (SELECT 1 FROM membresias m2 WHERE m2.user_id = m.user_id AND m2.fecha_fin > m.fecha_fin)
        ORDER BY m.fecha_fin LIMIT ?", [$hoy, date('Y-m-d', strtotime("+$vd days")), $limit]);
    $out['estancados'] = estancados(null, $limit);
    return $out;
}

/** Ejercicios sin progreso en las últimas N sesiones (peso máximo no sube). */
function estancados(?int $uid = null, int $limit = 30): array
{
    $n = (int) setting('alerta_estancado_sesiones', '4');
    $params = [];
    $where = '';
    if ($uid) {
        $where = 'AND sl.alumno_id = ?';
        $params[] = $uid;
    }
    $rows = qa("SELECT sl.alumno_id, sl.ejercicio_id, sl.sesion_id, MAX(sl.peso) AS pmax, MIN(sl.created_at) AS f
                FROM series_log sl WHERE sl.created_at >= datetime('now','localtime','-90 days') $where
                GROUP BY sl.alumno_id, sl.ejercicio_id, sl.sesion_id ORDER BY sl.alumno_id, sl.ejercicio_id, f", $params);
    $por = [];
    foreach ($rows as $r) $por[$r['alumno_id'] . ':' . $r['ejercicio_id']][] = (float) $r['pmax'];
    $out = [];
    foreach ($por as $k => $vals) {
        if (count($vals) < $n) continue;
        $ult = array_slice($vals, -$n);
        $antes = array_slice($vals, 0, -$n);
        $maxAntes = $antes ? max($antes) : $ult[0];
        if (max($ult) <= $maxAntes && max($ult) > 0) {
            [$a, $e] = explode(':', $k);
            $out[] = ['alumno_id' => (int) $a, 'ejercicio_id' => (int) $e, 'peso' => max($ult), 'sesiones' => $n];
        }
    }
    if (!$out) return [];
    $out = array_slice($out, 0, $limit);
    foreach ($out as &$o) {
        $o['alumno'] = q1('SELECT nombre, apellido FROM users WHERE id = ?', [$o['alumno_id']]);
        $o['ejercicio'] = qv('SELECT nombre FROM ejercicios WHERE id = ?', [$o['ejercicio_id']]);
    }
    return $out;
}

// ================================================================ Reportes del alumno
function reporte_alumno(int $uid, ?string $desde = null, ?string $hasta = null): array
{
    $desde = $desde ?? date('Y-m-d', strtotime('-90 days'));
    $hasta = $hasta ?? date('Y-m-d');
    $r = [];
    $r['records'] = qa("SELECT e.id, e.nombre, MAX(sl.peso) AS peso,
                         (SELECT reps FROM series_log x WHERE x.alumno_id = sl.alumno_id AND x.ejercicio_id = e.id ORDER BY x.peso DESC, x.reps DESC LIMIT 1) AS reps
                        FROM series_log sl JOIN ejercicios e ON e.id = sl.ejercicio_id WHERE sl.alumno_id = ? AND sl.peso > 0
                        GROUP BY e.id ORDER BY peso DESC LIMIT 12", [$uid]);
    $r['volumen_grupo'] = qa("SELECT strftime('%Y-%W', sl.created_at) AS semana, g.nombre AS grupo, SUM(sl.peso * sl.reps) AS volumen, COUNT(*) AS series
                              FROM series_log sl JOIN ejercicios e ON e.id = sl.ejercicio_id LEFT JOIN grupos_musculares g ON g.id = e.grupo_id
                              WHERE sl.alumno_id = ? AND date(sl.created_at) BETWEEN ? AND ? GROUP BY semana, grupo ORDER BY semana", [$uid, $desde, $hasta]);
    $r['asistencia_semanal'] = qa("SELECT strftime('%Y-%W', d) AS semana, COUNT(DISTINCT date(d)) AS dias FROM (
                                     SELECT created_at AS d FROM checkins WHERE user_id = ? UNION ALL
                                     SELECT created_at FROM sesiones WHERE alumno_id = ? AND estado IN ('en_curso','finalizada'))
                                   WHERE date(d) BETWEEN ? AND ? GROUP BY semana ORDER BY semana", [$uid, $uid, $desde, $hasta]);
    $r['racha'] = semanas_seguidas($uid);
    $mesAct = date('Y-m');
    $mesAnt = date('Y-m', strtotime('first day of last month'));
    $dias = fn($m) => (int) qv("SELECT COUNT(DISTINCT date(d)) FROM (SELECT created_at AS d FROM checkins WHERE user_id = ? UNION ALL SELECT created_at FROM sesiones WHERE alumno_id = ? AND estado IN ('en_curso','finalizada')) WHERE strftime('%Y-%m', d) = ?", [$uid, $uid, $m]);
    $r['mes_actual'] = $dias($mesAct);
    $r['mes_anterior'] = $dias($mesAnt);
    $r['sesiones_total'] = (int) qv("SELECT COUNT(*) FROM sesiones WHERE alumno_id = ? AND estado = 'finalizada'", [$uid]);
    $r['logros'] = qa('SELECT * FROM logros WHERE user_id = ? ORDER BY created_at DESC LIMIT 10', [$uid]);
    $r['ejercicios'] = qa('SELECT DISTINCT e.id, e.nombre FROM series_log sl JOIN ejercicios e ON e.id = sl.ejercicio_id WHERE sl.alumno_id = ? ORDER BY e.nombre', [$uid]);
    return $r;
}

function progreso_ejercicio(int $uid, int $ejId): array
{
    return qa("SELECT s.id AS sesion_id, date(s.created_at) AS fecha, MAX(sl.peso) AS peso, MAX(sl.reps) AS reps,
                      MAX(sl.peso * (1 + sl.reps / 30.0)) AS e1rm, SUM(sl.peso * sl.reps) AS volumen
               FROM series_log sl JOIN sesiones s ON s.id = sl.sesion_id
               WHERE sl.alumno_id = ? AND sl.ejercicio_id = ? GROUP BY s.id ORDER BY s.created_at", [$uid, $ejId]);
}

function adherencia(int $uid): array
{
    $asig = (int) qv("SELECT COUNT(*) FROM sesion_items si JOIN sesiones s ON s.id = si.sesion_id WHERE s.alumno_id = ? AND si.extra = 0 AND s.estado IN ('finalizada') AND s.created_at >= datetime('now','localtime','-60 days')", [$uid]);
    $hechos = (int) qv("SELECT COUNT(*) FROM sesion_items si JOIN sesiones s ON s.id = si.sesion_id WHERE s.alumno_id = ? AND si.extra = 0 AND si.estado = 'hecho' AND s.estado IN ('finalizada') AND s.created_at >= datetime('now','localtime','-60 days')", [$uid]);
    return ['asignados' => $asig, 'hechos' => $hechos, 'pct' => $asig ? round($hechos * 100 / $asig) : null];
}
