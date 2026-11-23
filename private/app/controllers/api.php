<?php
declare(strict_types=1);

require_once __DIR__ . '/alumno.php';

// ================================================================ Acceso a sesiones
function api_sesion_cargar(int $sid, string $modo = 'ver'): array
{
    $u = require_login();
    $s = q1('SELECT * FROM sesiones WHERE id = ?', [$sid]);
    if (!$s) not_found();
    $owner = (int) $s['alumno_id'] === (int) $u['id'];
    $staff = $u['rol'] !== 'alumno';
    $ok = match ($modo) {
        'ver' => puede_ver_sesion($s),
        'registrar' => $owner ? can('alumno.rutina') : ($staff && can('rutinas.armar')),
        'editar' => ($staff && can('rutinas.armar')) || ($owner && $s['modo'] === 'libre' && !alumno_solo_plantillas($u)),
        'extra' => ($staff && can('rutinas.armar')) || ($owner && in_array($s['estado'], ['en_curso', 'planificada'], true)),
        default => false,
    };
    if (!$ok) deny();
    return $s;
}

function item_cargar(int $itemId, string $modo): array
{
    $it = q1('SELECT si.*, e.maquina_id, e.seg_por_serie, e.nombre FROM sesion_items si JOIN ejercicios e ON e.id = si.ejercicio_id WHERE si.id = ?', [$itemId]);
    if (!$it) not_found();
    $s = api_sesion_cargar((int) $it['sesion_id'], $modo);
    return [$it, $s];
}

function restricciones_alumno(int $uid): array
{
    return tags_list(qv('SELECT restricciones FROM fichas WHERE user_id = ?', [$uid]));
}

function sesion_payload(array $s): array
{
    $u = current_user();
    $items = sesion_items((int) $s['id']);
    $series = qa('SELECT id, item_id, n, reps, peso, auto, created_at FROM series_log WHERE sesion_id = ? ORDER BY item_id, n', [$s['id']]);
    $porItem = [];
    foreach ($series as $x) $porItem[$x['item_id']][] = ['id' => (int) $x['id'], 'n' => (int) $x['n'], 'reps' => (int) $x['reps'], 'peso' => (float) $x['peso'], 'auto' => (int) $x['auto'], 'ts' => strtotime($x['created_at'])];
    $restr = restricciones_alumno((int) $s['alumno_id']);
    $out = [];
    foreach ($items as $it) {
        $ult = ultimo_trabajo((int) $s['alumno_id'], (int) $it['ejercicio_id'], (int) $s['id']);
        $ocup = $it['maquina_id'] ? maquina_ocupacion((int) $it['maquina_id'], (int) $s['alumno_id']) : null;
        $ej = q1('SELECT instrucciones, errores, restricciones FROM ejercicios WHERE id = ?', [$it['ejercicio_id']]);
        $out[] = [
            'id' => (int) $it['id'], 'orden' => (int) $it['orden'], 'bloque' => (int) $it['bloque'], 'ejercicio_id' => (int) $it['ejercicio_id'],
            'nombre' => $it['nombre'], 'grupo' => $it['grupo_nombre'], 'maquina' => $it['maquina_nombre'], 'maquina_id' => $it['maquina_id'] ? (int) $it['maquina_id'] : null,
            'maquina_estado' => $it['maquina_estado'], 'series' => (int) $it['series'], 'reps_min' => (int) $it['reps_min'], 'reps_max' => (int) $it['reps_max'],
            'descanso' => (int) $it['descanso_seg'], 'peso_sugerido' => $it['peso_sugerido'] !== null ? (float) $it['peso_sugerido'] : null,
            'estado' => $it['estado'], 'extra' => (bool) $it['extra'], 'compartido' => (bool) $it['compartido'],
            'seg' => item_segundos($it), 'sps' => (int) ($it['seg_por_serie'] ?? 45), 'foto' => media_url($it['foto']), 'video' => media_url($it['video']),
            'instrucciones' => $ej['instrucciones'] ?? '', 'errores' => $ej['errores'] ?? '',
            'conflictos' => ejercicio_conflictos($ej ?? [], $restr),
            'hechas' => $porItem[$it['id']] ?? [],
            'ultimo' => $ult ? ['fecha' => fecha($ult['fecha']), 'series' => array_map(fn($x) => ['reps' => (int) $x['reps'], 'peso' => (float) $x['peso']], $ult['series'])] : null,
            'ocupada' => $ocup ? ['libre_en' => $ocup['libre_en_min'], 'alumno' => $ocup['alumno_nombre'], 'coach' => $ocup['coach_nombre']] : null,
        ];
    }
    $alumno = q1('SELECT id, nombre, apellido FROM users WHERE id = ?', [$s['alumno_id']]);
    $coach = $s['coach_id'] ? q1('SELECT id, nombre, apellido FROM users WHERE id = ?', [$s['coach_id']]) : null;
    $max = (int) setting('sesion_max_min', '90');
    $trans = (int) setting('transicion_seg', '90');
    $restante = 0;
    foreach ($items as $it) if (!in_array($it['estado'], ['hecho', 'omitido'], true)) $restante += item_segundos($it) + $trans;
    $restSt = q1("SELECT enviar_at FROM notificaciones WHERE user_id = ? AND ref = ? AND cancelada = 0 AND enviada_at IS NULL ORDER BY id DESC LIMIT 1", [$s['alumno_id'], 'rest:' . $s['alumno_id']]);
    return [
        'ok' => true,
        'sesion' => [
            'id' => (int) $s['id'], 'estado' => $s['estado'], 'modo' => $s['modo'], 'titulo' => $s['titulo'], 'inicio' => $s['inicio'] ? strtotime($s['inicio']) : null,
            'fin_coach' => $s['fin_coach'] ? strtotime($s['fin_coach']) : null, 'est_min' => estimacion_min(array_filter($items, fn($i) => !$i['extra'])), 'max_min' => $max,
            'restante_min' => (int) ceil($restante / 60), 'feedback' => $s['feedback'],
        ],
        'alumno' => $alumno ? ['id' => (int) $alumno['id'], 'nombre' => nombre_completo($alumno)] : null,
        'coach' => $coach ? ['id' => (int) $coach['id'], 'nombre' => nombre_completo($coach)] : null,
        'items' => $out,
        'rest_end' => $restSt ? (int) $restSt['enviar_at'] : null,
        'now' => time(),
        'yo' => ['id' => (int) $u['id'], 'rol' => $u['rol'], 'es_alumno' => (int) $u['id'] === (int) $s['alumno_id'],
            'puede_editar' => ($u['rol'] !== 'alumno' && can('rutinas.armar')) || ((int) $u['id'] === (int) $s['alumno_id'] && $s['modo'] === 'libre' && !alumno_solo_plantillas($u)),
            'solo_plantillas' => (int) $u['id'] === (int) $s['alumno_id'] && alumno_solo_plantillas($u),
            'puede_calificar' => (int) $u['id'] === (int) $s['alumno_id'] && $s['modo'] === 'coach' && $s['coach_id'] && can('alumno.calificar') && !qv('SELECT 1 FROM calificaciones WHERE sesion_id = ?', [$s['id']])],
        'urls' => ['editar' => url('coach/rutina', ['sesion' => $s['id']]), 'calificar' => url('calificar', ['sesion' => $s['id']]), 'inicio' => url()],
    ];
}

// ================================================================ Sesión (ejecución)
function api_sesion(): void
{
    $s = api_sesion_cargar(input_int('id', 0), 'ver');
    json_out(sesion_payload($s));
}

function sesion_iniciar(array $s): array
{
    if ($s['estado'] !== 'planificada') return $s;
    $now = date('Y-m-d H:i:s');
    $fin = $s['modo'] === 'coach' ? date('Y-m-d H:i:s', time() + (int) setting('sesion_max_min', '90') * 60) : null;
    update('sesiones', ['estado' => 'en_curso', 'inicio' => $now, 'fin_coach' => $fin], 'id = ?', [$s['id']]);
    registrar_checkin((int) $s['alumno_id'], 'sesion');
    estado_muro_desde_sesion((int) $s['id']);
    if ($s['coach_id'] && $fin) {
        $al = q1('SELECT nombre FROM users WHERE id = ?', [$s['alumno_id']]);
        notificar((int) $s['coach_id'], 'coach_tiempo', 'Quedan 10 min con ' . $al['nombre'], 'La sesión con coach termina a las ' . date('H:i', strtotime($fin)) . '.', url('sesion', ['id' => $s['id']]), strtotime($fin) - 600, 'fin:' . $s['id']);
    }
    return q1('SELECT * FROM sesiones WHERE id = ?', [$s['id']]);
}

function api_sesion_iniciar(): void
{
    $b = json_body();
    $s = api_sesion_cargar((int) ($b['id'] ?? 0), 'registrar');
    if ($s['estado'] === 'planificada' && (int) $s['alumno_id'] === uid()) {
        [$ok, $motivo] = acceso_estado(uid());
        if (!$ok) json_out(['ok' => false, 'error' => $motivo], 403);
    }
    $s = sesion_iniciar($s);
    json_out(sesion_payload($s));
}

function api_sesion_finalizar(): void
{
    $b = json_body();
    $s = api_sesion_cargar((int) ($b['id'] ?? 0), 'registrar');
    if ($s['estado'] === 'finalizada') json_out(sesion_payload($s));
    tx(function () use ($s) {
        update('sesiones', ['estado' => 'finalizada', 'fin' => date('Y-m-d H:i:s'), 'inicio' => $s['inicio'] ?? date('Y-m-d H:i:s')], 'id = ?', [$s['id']]);
        q("UPDATE sesion_items SET estado = 'omitido' WHERE sesion_id = ? AND estado = 'pendiente' AND id NOT IN (SELECT item_id FROM series_log WHERE sesion_id = ?)", [$s['id'], $s['id']]);
        q("UPDATE sesion_items SET estado = 'hecho', fin = COALESCE(fin, ?) WHERE sesion_id = ? AND estado IN ('en_curso','pendiente')", [date('Y-m-d H:i:s'), $s['id']]);
        q('UPDATE ocupacion SET liberada_at = ? WHERE alumno_id = ? AND liberada_at IS NULL', [time(), $s['alumno_id']]);
    });
    cancelar_notif((int) $s['alumno_id'], 'rest:' . $s['alumno_id']);
    if ($s['coach_id']) cancelar_notif((int) $s['coach_id'], 'fin:' . $s['id']);
    foreach (qa('SELECT DISTINCT ejercicio_id FROM sesion_items WHERE sesion_id = ?', [$s['id']]) as $r) recalibrar_ejercicio((int) $r['ejercicio_id']);
    revisar_racha((int) $s['alumno_id']);
    json_out(sesion_payload(q1('SELECT * FROM sesiones WHERE id = ?', [$s['id']])));
}

function api_feedback(): void
{
    $b = json_body();
    $s = api_sesion_cargar((int) ($b['id'] ?? 0), 'registrar');
    if ((int) $s['alumno_id'] !== uid()) deny();
    update('sesiones', ['feedback' => max(1, min(5, (int) ($b['valor'] ?? 3)))], 'id = ?', [$s['id']]);
    json_out(['ok' => true]);
}

function api_item_iniciar(): void
{
    $b = json_body();
    [$it, $s] = item_cargar((int) ($b['item_id'] ?? 0), 'registrar');
    $forzar = !empty($b['compartir']);
    if ($s['estado'] === 'planificada') {
        if ((int) $s['alumno_id'] === uid()) {
            [$ok, $motivo] = acceso_estado(uid());
            if (!$ok) json_out(['ok' => false, 'error' => $motivo], 403);
        }
        $s = sesion_iniciar($s);
    }
    if ($it['maquina_id']) {
        $ocup = maquina_ocupacion((int) $it['maquina_id'], (int) $s['alumno_id']);
        if ($ocup && !$forzar) {
            $alts = alternativas_con_estado((int) $it['ejercicio_id'], (int) $s['alumno_id']);
            json_out(['ok' => false, 'ocupada' => true, 'libre_en' => $ocup['libre_en_min'], 'alumno' => $ocup['alumno_nombre'], 'coach' => $ocup['coach_nombre'], 'alternativas' => $alts], 409);
        }
    }
    q("UPDATE sesion_items SET estado = 'pendiente' WHERE sesion_id = ? AND estado = 'en_curso' AND id != ? AND id NOT IN (SELECT item_id FROM series_log)", [$s['id'], $it['id']]);
    update('sesion_items', ['estado' => 'en_curso', 'inicio' => $it['inicio'] ?? date('Y-m-d H:i:s'), 'compartido' => $forzar ? 1 : (int) $it['compartido']], 'id = ?', [$it['id']]);
    $it['estado'] = 'en_curso';
    ocupar_maquina($it, (int) $s['alumno_id']);
    json_out(sesion_payload(q1('SELECT * FROM sesiones WHERE id = ?', [$s['id']])));
}

function api_item_estado(): void
{
    $b = json_body();
    [$it, $s] = item_cargar((int) ($b['item_id'] ?? 0), 'registrar');
    $estado = in_array($b['estado'] ?? '', ['hecho', 'omitido', 'pendiente'], true) ? $b['estado'] : 'hecho';
    update('sesion_items', ['estado' => $estado, 'fin' => $estado === 'pendiente' ? null : date('Y-m-d H:i:s')], 'id = ?', [$it['id']]);
    liberar_item((int) $it['id']);
    json_out(sesion_payload(q1('SELECT * FROM sesiones WHERE id = ?', [$s['id']])));
}

function api_serie(): void
{
    $b = json_body();
    [$it, $s] = item_cargar((int) ($b['item_id'] ?? 0), 'registrar');
    $uuid = substr(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($b['uuid'] ?? '')), 0, 64) ?: null;
    if ($uuid && qv('SELECT 1 FROM series_log WHERE client_uuid = ?', [$uuid])) json_out(sesion_payload($s) + ['duplicado' => true]);
    $reps = max(0, min(200, (int) ($b['reps'] ?? 0)));
    $peso = max(0, min(1000, round((float) ($b['peso'] ?? 0) * 4) / 4));
    if ($reps <= 0) json_out(['ok' => false, 'error' => 'Ingresa las repeticiones.'], 422);
    // Serie automática: solo desde el celular del alumno, con la sesión en curso y una serie previa reciente
    $auto = !empty($b['auto']) ? 1 : 0;
    if ($auto) {
        $prev = q1('SELECT reps, peso, created_at FROM series_log WHERE item_id = ? ORDER BY n DESC LIMIT 1', [$it['id']]);
        $ok = (int) current_user()['id'] === (int) $s['alumno_id'] && $s['estado'] === 'en_curso' && $it['estado'] === 'en_curso' && $prev
            && time() - strtotime($prev['created_at']) >= (int) $it['descanso_seg'] + 20 && time() - strtotime($prev['created_at']) < 900
            && (int) qv('SELECT COUNT(*) FROM series_log WHERE item_id = ?', [$it['id']]) < (int) $it['series'];
        if (!$ok) json_out(sesion_payload($s) + ['auto_rechazada' => true]);
        $reps = (int) $prev['reps'];
        $peso = (float) $prev['peso'];
    }
    if ($s['estado'] === 'finalizada' && $s['fin'] && strtotime($s['fin']) < time() - 6 * 3600) json_out(['ok' => false, 'error' => 'La sesión ya está cerrada.'], 422);
    if ($s['estado'] === 'planificada') $s = sesion_iniciar($s);
    $n = (int) qv('SELECT COUNT(*) FROM series_log WHERE item_id = ?', [$it['id']]) + 1;
    insert('series_log', ['item_id' => $it['id'], 'sesion_id' => $s['id'], 'alumno_id' => $s['alumno_id'], 'ejercicio_id' => $it['ejercicio_id'], 'n' => $n, 'reps' => $reps, 'peso' => $peso, 'auto' => $auto, 'client_uuid' => $uuid, 'created_at' => date('Y-m-d H:i:s')]);
    if ($it['estado'] === 'pendiente') {
        update('sesion_items', ['estado' => 'en_curso', 'inicio' => date('Y-m-d H:i:s')], 'id = ?', [$it['id']]);
        ocupar_maquina($it, (int) $s['alumno_id']);
    }
    $record = $auto ? null : revisar_record((int) $s['alumno_id'], (int) $it['ejercicio_id'], (float) $peso, $reps, (int) $s['id']);
    $completo = $n >= (int) $it['series'];
    if ($completo) {
        update('sesion_items', ['estado' => 'hecho', 'fin' => date('Y-m-d H:i:s')], 'id = ?', [$it['id']]);
        liberar_item((int) $it['id']);
    }
    // Descanso + push programado
    $desc = (int) $it['descanso_seg'];
    $restEnd = null;
    $hayMas = (bool) qv("SELECT 1 FROM sesion_items WHERE sesion_id = ? AND estado IN ('pendiente','en_curso')", [$s['id']]);
    if ($desc > 0 && (!$completo || $hayMas)) {
        $restEnd = time() + $desc;
        $sig = $completo ? q1("SELECT e.nombre FROM sesion_items si JOIN ejercicios e ON e.id = si.ejercicio_id WHERE si.sesion_id = ? AND si.estado = 'pendiente' ORDER BY si.orden LIMIT 1", [$s['id']]) : null;
        notificar((int) $s['alumno_id'], 'descanso', 'Terminó el descanso', $completo ? ('Siguiente: ' . ($sig['nombre'] ?? 'próximo ejercicio')) : ('Serie ' . ($n + 1) . ' de ' . $it['nombre']), url('sesion', ['id' => $s['id']]), $restEnd, 'rest:' . $s['alumno_id']);
    }
    $p = sesion_payload(q1('SELECT * FROM sesiones WHERE id = ?', [$s['id']]));
    $p['rest_end'] = $restEnd;
    $p['record'] = $record;
    json_out($p);
}

function api_serie_borrar(): void
{
    $b = json_body();
    $x = q1('SELECT * FROM series_log WHERE id = ?', [(int) ($b['id'] ?? 0)]);
    if (!$x) not_found();
    [$it, $s] = item_cargar((int) $x['item_id'], 'registrar');
    q('DELETE FROM series_log WHERE id = ?', [$x['id']]);
    $i = 1;
    foreach (qa('SELECT id FROM series_log WHERE item_id = ? ORDER BY id', [$it['id']]) as $r) update('series_log', ['n' => $i++], 'id = ?', [$r['id']]);
    if ($it['estado'] === 'hecho') update('sesion_items', ['estado' => 'en_curso', 'fin' => null], 'id = ?', [$it['id']]);
    json_out(sesion_payload($s));
}

/** Corregir una serie (típicamente una marcada automáticamente). Al corregirla deja de ser "auto". */
function api_serie_editar(): void
{
    $b = json_body();
    $x = q1('SELECT * FROM series_log WHERE id = ?', [(int) ($b['id'] ?? 0)]);
    if (!$x) not_found();
    [$it, $s] = item_cargar((int) $x['item_id'], 'registrar');
    $reps = max(0, min(200, (int) ($b['reps'] ?? 0)));
    $peso = max(0, min(1000, round((float) ($b['peso'] ?? 0) * 4) / 4));
    if ($reps <= 0) json_out(['ok' => false, 'error' => 'Ingresa las repeticiones.'], 422);
    update('series_log', ['reps' => $reps, 'peso' => $peso, 'auto' => 0], 'id = ?', [$x['id']]);
    $p = sesion_payload($s);
    $p['record'] = revisar_record((int) $s['alumno_id'], (int) $it['ejercicio_id'], (float) $peso, $reps, (int) $s['id']);
    json_out($p);
}

function api_descanso_cancelar(): void
{
    $u = require_login();
    cancelar_notif((int) $u['id'], 'rest:' . $u['id']);
    json_out(['ok' => true]);
}

// ================================================================ Builder (sesiones y plantillas)
function alternativas_con_estado(int $ejId, ?int $alumnoId = null): array
{
    $restr = $alumnoId ? restricciones_alumno($alumnoId) : [];
    $rows = qa('SELECT e.id, e.nombre, e.maquina_id, e.restricciones, m.nombre AS maquina, m.estado AS maquina_estado FROM ejercicio_alternativas a JOIN ejercicios e ON e.id = a.alternativa_id
                LEFT JOIN maquinas m ON m.id = e.maquina_id WHERE a.ejercicio_id = ? AND e.activo = 1 ORDER BY e.nombre', [$ejId]);
    $out = [];
    foreach ($rows as $r) {
        if ($r['maquina_estado'] === 'mantencion') continue;
        $o = $r['maquina_id'] ? maquina_ocupacion((int) $r['maquina_id'], $alumnoId) : null;
        $out[] = ['id' => (int) $r['id'], 'nombre' => $r['nombre'], 'maquina' => $r['maquina'], 'libre' => !$o, 'libre_en' => $o['libre_en_min'] ?? 0, 'conflictos' => ejercicio_conflictos($r, $restr)];
    }
    usort($out, fn($a, $b) => [$b['libre'], count($a['conflictos'])] <=> [$a['libre'], count($b['conflictos'])]);
    return $out;
}

function builder_tabla(string $ctx): array
{
    return $ctx === 'plantilla' ? ['plantilla_items', 'plantilla_id'] : ['sesion_items', 'sesion_id'];
}

function builder_check(string $ctx, int $id, bool $extra = false): ?array
{
    if ($ctx === 'plantilla') {
        require_perm('plantillas.gestionar');
        if (!qv('SELECT 1 FROM plantillas WHERE id = ?', [$id])) not_found();
        return null;
    }
    return api_sesion_cargar($id, $extra ? 'extra' : 'editar');
}

function builder_items(string $ctx, int $id, ?array $s): array
{
    [$tbl, $fk] = builder_tabla($ctx);
    $rows = qa("SELECT i.*, e.nombre, e.maquina_id, e.seg_por_serie, e.restricciones, e.foto, m.nombre AS maquina, m.estado AS maquina_estado, g.nombre AS grupo
                FROM $tbl i JOIN ejercicios e ON e.id = i.ejercicio_id LEFT JOIN maquinas m ON m.id = e.maquina_id LEFT JOIN grupos_musculares g ON g.id = e.grupo_id
                WHERE i.$fk = ? ORDER BY i.orden, i.id", [$id]);
    $alumnoId = $s ? (int) $s['alumno_id'] : null;
    $restr = $alumnoId ? restricciones_alumno($alumnoId) : [];
    $trans = (int) setting('transicion_seg', '90');
    $out = [];
    $total = 0;
    foreach ($rows as $r) {
        $seg = item_segundos($r);
        if (empty($r['extra'])) $total += $seg + $trans;
        $o = ($alumnoId && $r['maquina_id']) ? maquina_ocupacion((int) $r['maquina_id'], $alumnoId) : null;
        $sug = $alumnoId ? peso_sugerido($alumnoId, (int) $r['ejercicio_id'], $s ? (int) $s['id'] : null) : null;
        $out[] = [
            'id' => (int) $r['id'], 'bloque' => (int) $r['bloque'], 'ejercicio_id' => (int) $r['ejercicio_id'], 'nombre' => $r['nombre'], 'grupo' => $r['grupo'],
            'maquina' => $r['maquina'], 'maquina_estado' => $r['maquina_estado'], 'series' => (int) $r['series'], 'reps_min' => (int) $r['reps_min'], 'reps_max' => (int) $r['reps_max'],
            'descanso' => (int) $r['descanso_seg'], 'peso_sugerido' => isset($r['peso_sugerido']) && $r['peso_sugerido'] !== null ? (float) $r['peso_sugerido'] : null,
            'estado' => $r['estado'] ?? 'pendiente', 'extra' => !empty($r['extra']), 'min' => round($seg / 60, 1),
            'conflictos' => ejercicio_conflictos($r, $restr), 'ocupada' => $o ? ['libre_en' => $o['libre_en_min'], 'alumno' => $o['alumno_nombre'], 'coach' => $o['coach_nombre']] : null,
            'sugerencia' => $sug, 'foto' => media_url($r['foto']),
        ];
    }
    $max = (int) setting('sesion_max_min', '90');
    $obj = (int) setting('plantilla_objetivo_min', '75');
    $min = (int) ceil($total / 60);
    $recortes = [];
    if ($min > $max) {
        foreach ($out as $it) {
            if ($it['series'] > 2) $recortes[] = ['tipo' => 'serie', 'item_id' => $it['id'], 'texto' => 'Bajar una serie en ' . $it['nombre'], 'ahorro' => round((($it['descanso'] + 45) / 60), 1)];
            if ($it['descanso'] > 60) $recortes[] = ['tipo' => 'descanso', 'item_id' => $it['id'], 'texto' => 'Descanso de ' . $it['nombre'] . ' a 60 s', 'ahorro' => round(($it['descanso'] - 60) * max(0, $it['series'] - 1) / 60, 1)];
        }
        usort($recortes, fn($a, $b) => $b['ahorro'] <=> $a['ahorro']);
        if ($out) $recortes[] = ['tipo' => 'quitar', 'item_id' => end($out)['id'], 'texto' => 'Quitar el último ejercicio (' . end($out)['nombre'] . ')', 'ahorro' => end($out)['min']];
        $recortes = array_slice($recortes, 0, 4);
    }
    return ['ok' => true, 'items' => $out, 'total_min' => $min, 'max_min' => $max, 'objetivo_min' => $obj, 'recortes' => $recortes];
}

function builder_siguiente_orden(string $ctx, int $id): array
{
    [$tbl, $fk] = builder_tabla($ctx);
    $r = q1("SELECT COALESCE(MAX(orden),0) AS o, COALESCE(MAX(bloque),0) AS b FROM $tbl WHERE $fk = ?", [$id]);
    return [(int) $r['o'] + 1, (int) $r['b'] + 1];
}

function builder_aplicar_plantilla(int $sesionId, int $plantillaId, int $alumnoId): int
{
    [$orden, $bloque] = builder_siguiente_orden('sesion', $sesionId);
    $mapB = [];
    $n = 0;
    foreach (qa('SELECT i.*, e.activo FROM plantilla_items i JOIN ejercicios e ON e.id = i.ejercicio_id WHERE i.plantilla_id = ? ORDER BY i.orden', [$plantillaId]) as $pi) {
        if (!$pi['activo']) continue;
        if (!isset($mapB[$pi['bloque']])) $mapB[$pi['bloque']] = $bloque++;
        $sug = peso_sugerido($alumnoId, (int) $pi['ejercicio_id'], $sesionId);
        insert('sesion_items', ['sesion_id' => $sesionId, 'orden' => $orden++, 'bloque' => $mapB[$pi['bloque']], 'ejercicio_id' => $pi['ejercicio_id'], 'series' => $pi['series'],
            'reps_min' => $pi['reps_min'], 'reps_max' => $pi['reps_max'], 'descanso_seg' => $pi['descanso_seg'], 'peso_sugerido' => $sug['peso'] ?? null]);
        $n++;
    }
    $p = q1('SELECT nombre FROM plantillas WHERE id = ?', [$plantillaId]);
    if ($p) q('UPDATE sesiones SET titulo = COALESCE(NULLIF(titulo, \'Rutina libre\'), ?) WHERE id = ?', [$p['nombre'], $sesionId]);
    return $n;
}

function api_builder(): void
{
    $b = json_body();
    $ctx = ($b['ctx'] ?? 'sesion') === 'plantilla' ? 'plantilla' : 'sesion';
    $id = (int) ($b['id'] ?? 0);
    $accion = (string) ($b['accion'] ?? 'get');
    $extra = !empty($b['extra']) && $ctx === 'sesion';
    // El alumno (sin permiso de edición) puede: agregar extras, mover al final y cambiar por una alternativa cargada.
    $modoAlumno = $ctx === 'sesion' && (($extra && $accion === 'agregar') || in_array($accion, ['al_final', 'reemplazar'], true));
    if ($modoAlumno) {
        $s = api_sesion_cargar($id, 'extra');
        $u0 = current_user();
        $esEditor = ($u0['rol'] !== 'alumno' && can('rutinas.armar')) || ((int) $s['alumno_id'] === (int) $u0['id'] && $s['modo'] === 'libre' && !alumno_solo_plantillas($u0));
        if (!$esEditor && $accion === 'agregar' && alumno_solo_plantillas($u0)) json_out(['ok' => false, 'error' => 'Tu plan usa los entrenos del gimnasio tal como vienen.'], 403);
        if (!$esEditor && $accion === 'reemplazar') {
            $it0 = q1('SELECT ejercicio_id FROM sesion_items WHERE id = ? AND sesion_id = ?', [(int) ($b['item_id'] ?? 0), $id]);
            if (!$it0 || !qv('SELECT 1 FROM ejercicio_alternativas WHERE ejercicio_id = ? AND alternativa_id = ?', [$it0['ejercicio_id'], (int) ($b['ejercicio_id'] ?? 0)])) {
                json_out(['ok' => false, 'error' => 'Solo puedes cambiar por una alternativa del ejercicio.'], 403);
            }
        }
        if (!$esEditor && $accion === 'agregar') $extra = true;
    } else {
        $s = builder_check($ctx, $id, false);
    }
    [$tbl, $fk] = builder_tabla($ctx);

    $itemDe = function (int $itemId) use ($tbl, $fk, $id) {
        $r = q1("SELECT * FROM $tbl WHERE id = ? AND $fk = ?", [$itemId, $id]);
        if (!$r) json_out(['ok' => false, 'error' => 'Ítem no encontrado'], 404);
        return $r;
    };
    $noTocarHecho = function (array $r) use ($ctx) {
        if ($ctx === 'sesion' && ($r['estado'] ?? '') === 'hecho') json_out(['ok' => false, 'error' => 'Ese ejercicio ya está hecho.'], 422);
    };

    switch ($accion) {
        case 'get':
            break;
        case 'agregar':
            $ej = q1('SELECT * FROM ejercicios WHERE id = ? AND activo = 1', [(int) ($b['ejercicio_id'] ?? 0)]);
            if (!$ej) json_out(['ok' => false, 'error' => 'Ejercicio no encontrado'], 404);
            [$orden, $bloque] = builder_siguiente_orden($ctx, $id);
            $row = [$fk => $id, 'orden' => $orden, 'bloque' => $bloque, 'ejercicio_id' => $ej['id'], 'series' => $ej['series_def'], 'reps_min' => $ej['reps_min'], 'reps_max' => $ej['reps_max'], 'descanso_seg' => $ej['descanso_seg']];
            if ($ctx === 'sesion') {
                $sug = peso_sugerido((int) $s['alumno_id'], (int) $ej['id'], $id);
                $row['peso_sugerido'] = $sug['peso'] ?? null;
                $row['extra'] = $extra ? 1 : 0;
            }
            insert($tbl, $row);
            break;
        case 'actualizar':
            $r = $itemDe((int) ($b['item_id'] ?? 0));
            $upd = [
                'series' => max(1, min(10, (int) ($b['series'] ?? $r['series']))),
                'reps_min' => max(1, min(100, (int) ($b['reps_min'] ?? $r['reps_min']))),
                'reps_max' => max(1, min(100, (int) ($b['reps_max'] ?? $r['reps_max']))),
                'descanso_seg' => max(0, min(600, (int) ($b['descanso'] ?? $r['descanso_seg']))),
            ];
            if ($upd['reps_max'] < $upd['reps_min']) $upd['reps_max'] = $upd['reps_min'];
            if ($ctx === 'sesion' && array_key_exists('peso_sugerido', $b)) $upd['peso_sugerido'] = $b['peso_sugerido'] === null || $b['peso_sugerido'] === '' ? null : max(0, min(1000, (float) $b['peso_sugerido']));
            update($tbl, $upd, 'id = ?', [$r['id']]);
            break;
        case 'quitar':
            $r = $itemDe((int) ($b['item_id'] ?? 0));
            if ($ctx === 'sesion' && qv('SELECT 1 FROM series_log WHERE item_id = ?', [$r['id']])) json_out(['ok' => false, 'error' => 'Ese ejercicio ya tiene series registradas.'], 422);
            q("DELETE FROM $tbl WHERE id = ?", [$r['id']]);
            break;
        case 'reemplazar':
            $r = $itemDe((int) ($b['item_id'] ?? 0));
            $noTocarHecho($r);
            $ej = q1('SELECT * FROM ejercicios WHERE id = ? AND activo = 1', [(int) ($b['ejercicio_id'] ?? 0)]);
            if (!$ej) json_out(['ok' => false, 'error' => 'Ejercicio no encontrado'], 404);
            $upd = ['ejercicio_id' => $ej['id']];
            if ($ctx === 'sesion') {
                if (qv('SELECT 1 FROM series_log WHERE item_id = ?', [$r['id']])) json_out(['ok' => false, 'error' => 'Ya hay series registradas en ese ejercicio.'], 422);
                $sug = peso_sugerido((int) $s['alumno_id'], (int) $ej['id'], $id);
                $upd['peso_sugerido'] = $sug['peso'] ?? null;
                liberar_item((int) $r['id']);
                $upd['estado'] = 'pendiente';
            }
            update($tbl, $upd, 'id = ?', [$r['id']]);
            break;
        case 'al_final':
            $r = $itemDe((int) ($b['item_id'] ?? 0));
            $noTocarHecho($r);
            [$orden, $bloque] = builder_siguiente_orden($ctx, $id);
            $grupo = qa("SELECT id FROM $tbl WHERE $fk = ? AND bloque = ? ORDER BY orden", [$id, $r['bloque']]);
            foreach ($grupo as $g) update($tbl, ['orden' => $orden++, 'bloque' => $bloque], 'id = ?', [$g['id']]);
            if ($ctx === 'sesion') {
                foreach ($grupo as $g) liberar_item((int) $g['id']);
                q("UPDATE sesion_items SET estado = 'pendiente' WHERE sesion_id = ? AND bloque = ? AND estado = 'en_curso' AND id NOT IN (SELECT item_id FROM series_log)", [$id, $bloque]);
            }
            break;
        case 'ordenar':
            // bloques: [[item_id, item_id], [item_id], ...]
            $bloques = is_array($b['bloques'] ?? null) ? $b['bloques'] : [];
            $orden = 1;
            $bn = 1;
            tx(function () use ($bloques, $tbl, $fk, $id, &$orden, &$bn) {
                foreach ($bloques as $blk) {
                    if (!is_array($blk) || !$blk) continue;
                    foreach ($blk as $iid) q("UPDATE $tbl SET orden = ?, bloque = ? WHERE id = ? AND $fk = ?", [$orden++, $bn, (int) $iid, $id]);
                    $bn++;
                }
            });
            break;
        case 'unir':
            $r = $itemDe((int) ($b['item_id'] ?? 0));
            $prev = q1("SELECT * FROM $tbl WHERE $fk = ? AND orden < ? ORDER BY orden DESC LIMIT 1", [$id, $r['orden']]);
            if ($prev) q("UPDATE $tbl SET bloque = ? WHERE $fk = ? AND bloque = ?", [$prev['bloque'], $id, $r['bloque']]);
            break;
        case 'separar':
            $r = $itemDe((int) ($b['item_id'] ?? 0));
            [, $bloque] = builder_siguiente_orden($ctx, $id);
            q("UPDATE $tbl SET bloque = ? WHERE $fk = ? AND bloque = ? AND orden >= ?", [$bloque, $id, $r['bloque'], $r['orden']]);
            break;
        case 'plantilla':
            if ($ctx !== 'sesion') break;
            builder_aplicar_plantilla($id, (int) ($b['plantilla_id'] ?? 0), (int) $s['alumno_id']);
            break;
        case 'recorte':
            $r = $itemDe((int) ($b['item_id'] ?? 0));
            $tipo = $b['tipo'] ?? '';
            if ($tipo === 'serie') update($tbl, ['series' => max(1, (int) $r['series'] - 1)], 'id = ?', [$r['id']]);
            elseif ($tipo === 'descanso') update($tbl, ['descanso_seg' => min(60, (int) $r['descanso_seg'])], 'id = ?', [$r['id']]);
            elseif ($tipo === 'quitar' && !($ctx === 'sesion' && qv('SELECT 1 FROM series_log WHERE item_id = ?', [$r['id']]))) q("DELETE FROM $tbl WHERE id = ?", [$r['id']]);
            break;
        case 'vaciar':
            if ($ctx === 'sesion') q('DELETE FROM sesion_items WHERE sesion_id = ? AND id NOT IN (SELECT item_id FROM series_log WHERE sesion_id = ?)', [$id, $id]);
            else q('DELETE FROM plantilla_items WHERE plantilla_id = ?', [$id]);
            break;
        default:
            json_out(['ok' => false, 'error' => 'Acción desconocida'], 400);
    }
    if ($accion !== 'get') {
        // normalizar orden
        $i = 1;
        foreach (qa("SELECT id FROM $tbl WHERE $fk = ? ORDER BY orden, id", [$id]) as $r) q("UPDATE $tbl SET orden = ? WHERE id = ?", [$i++, $r['id']]);
        if ($ctx === 'sesion') recalc_sesion($id);
    }
    json_out(builder_items($ctx, $id, $s));
}

function api_ejercicios(): void
{
    require_login();
    $qtxt = trim((string) input('q', ''));
    $grupo = input_int('grupo');
    $alumno = input_int('alumno');
    $where = ['e.activo = 1'];
    $p = [];
    if ($qtxt !== '') {
        $where[] = '(e.nombre LIKE ? OR m.nombre LIKE ?)';
        $p[] = '%' . $qtxt . '%';
        $p[] = '%' . $qtxt . '%';
    }
    if ($grupo) {
        $where[] = '(e.grupo_id = ? OR (\',\' || COALESCE(e.grupos_secundarios,\'\') || \',\') LIKE ?)';
        $p[] = $grupo;
        $p[] = '%,' . $grupo . ',%';
    }
    $rows = qa('SELECT e.id, e.nombre, e.maquina_id, e.restricciones, e.foto, m.nombre AS maquina, m.estado AS maquina_estado, g.nombre AS grupo,
                (SELECT COUNT(*) FROM ejercicio_alternativas a WHERE a.ejercicio_id = e.id) AS n_alt
                FROM ejercicios e LEFT JOIN maquinas m ON m.id = e.maquina_id LEFT JOIN grupos_musculares g ON g.id = e.grupo_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY g.orden, e.nombre LIMIT 80', $p);
    $restr = $alumno && is_staff() ? restricciones_alumno($alumno) : ($alumno === uid() ? restricciones_alumno(uid()) : []);
    $out = [];
    foreach ($rows as $r) {
        $o = $r['maquina_id'] ? maquina_ocupacion((int) $r['maquina_id'], $alumno) : null;
        $out[] = ['id' => (int) $r['id'], 'nombre' => $r['nombre'], 'grupo' => $r['grupo'], 'maquina' => $r['maquina'], 'mantencion' => $r['maquina_estado'] === 'mantencion',
            'ocupada' => $o ? $o['libre_en_min'] : null, 'conflictos' => ejercicio_conflictos($r, $restr), 'n_alt' => (int) $r['n_alt'], 'foto' => media_url($r['foto'])];
    }
    json_out(['ok' => true, 'items' => $out]);
}

function api_alumnos(): void
{
    require_perm('alumnos.ver');
    $qtxt = trim((string) input('q', ''));
    if (mb_strlen($qtxt) < 2) json_out(['ok' => true, 'items' => []]);
    $clean = rut_clean($qtxt);
    $rows = qa("SELECT id, nombre, apellido, doc_tipo, doc_num, email FROM users WHERE rol = 'alumno' AND activo = 1
                AND (nombre || ' ' || apellido LIKE ? OR doc_num LIKE ?) ORDER BY nombre LIMIT 8",
        ['%' . $qtxt . '%', '%' . (strlen($clean) > 3 ? substr($clean, 0, -1) : $qtxt) . '%']);
    $out = [];
    foreach ($rows as $r) {
        [$ok, $motivo, $m] = acceso_estado((int) $r['id']);
        $hoy = qv("SELECT id FROM sesiones WHERE alumno_id = ? AND estado IN ('planificada','en_curso') AND date(created_at) = date('now','localtime') ORDER BY id DESC LIMIT 1", [$r['id']]);
        $out[] = ['id' => (int) $r['id'], 'nombre' => nombre_completo($r), 'doc' => doc_display($r), 'acceso' => $ok, 'motivo' => $motivo, 'plan' => isset($m['plan_nombre']) ? $m['plan_nombre'] . (alumno_solo_plantillas($r + ['rol' => 'alumno']) ? ' · sin coach' : '') : null,
            'sin_coach' => alumno_solo_plantillas($r + ['rol' => 'alumno']),
            'sin_correo' => !$r['email'], 'sesion' => $hoy ? (int) $hoy : null, 'url' => url('coach/alumno', ['id' => $r['id']]),
            'url_sesion' => $hoy ? url('coach/rutina', ['sesion' => $hoy]) : null];
    }
    json_out(['ok' => true, 'items' => $out, 'puede_armar' => can('rutinas.armar')]);
}

function api_ocupacion(): void
{
    require_login();
    $ej = input_int('ejercicio');
    $alumno = input_int('alumno');
    $e = q1('SELECT * FROM ejercicios WHERE id = ?', [$ej]);
    if (!$e) not_found();
    $o = $e['maquina_id'] ? maquina_ocupacion((int) $e['maquina_id'], $alumno) : null;
    json_out(['ok' => true, 'ocupada' => $o ? ['libre_en' => $o['libre_en_min'], 'alumno' => $o['alumno_nombre'], 'coach' => $o['coach_nombre']] : null, 'alternativas' => alternativas_con_estado($ej, $alumno)]);
}

function api_sala(): void
{
    require_perm('sala.ver');
    json_out(['ok' => true] + sala_datos());
}

function sala_datos(): array
{
    $ses = qa("SELECT s.*, u.nombre, u.apellido, c.nombre AS coach_nombre FROM sesiones s JOIN users u ON u.id = s.alumno_id LEFT JOIN users c ON c.id = s.coach_id
               WHERE s.estado = 'en_curso' AND date(s.inicio) = date('now','localtime') ORDER BY s.inicio");
    $out = [];
    foreach ($ses as $s) {
        $items = sesion_items((int) $s['id']);
        $hechos = count(array_filter($items, fn($i) => in_array($i['estado'], ['hecho', 'omitido'], true)));
        $actual = null;
        foreach ($items as $i) if ($i['estado'] === 'en_curso') $actual = $i;
        if (!$actual) foreach ($items as $i) if ($i['estado'] === 'pendiente') {
            $actual = $i;
            break;
        }
        $ultSerie = $actual ? q1('SELECT reps, peso FROM series_log WHERE item_id = ? ORDER BY id DESC LIMIT 1', [$actual['id']]) : null;
        $min = (int) floor((time() - strtotime($s['inicio'])) / 60);
        $out[] = ['id' => (int) $s['id'], 'alumno' => trim($s['nombre'] . ' ' . $s['apellido']), 'alumno_id' => (int) $s['alumno_id'], 'coach' => $s['coach_nombre'], 'modo' => $s['modo'],
            'minutos' => $min, 'max' => (int) setting('sesion_max_min', '90'), 'hechos' => $hechos, 'total' => count($items), 'restante' => restante_min($items),
            'actual' => $actual ? ['nombre' => $actual['nombre'], 'maquina' => $actual['maquina_nombre'], 'estado' => $actual['estado'], 'ultima' => $ultSerie] : null,
            'url' => url('sesion', ['id' => $s['id']])];
    }
    $grupos = qa("SELECT g.nombre, COUNT(DISTINCT s.id) AS n FROM sesiones s JOIN sesion_items si ON si.sesion_id = s.id JOIN ejercicios e ON e.id = si.ejercicio_id JOIN grupos_musculares g ON g.id = e.grupo_id
                  WHERE s.estado = 'en_curso' AND date(s.inicio) = date('now','localtime') AND si.orden <= 2 GROUP BY g.nombre ORDER BY n DESC");
    $presentes = (int) qv("SELECT COUNT(DISTINCT user_id) FROM checkins WHERE created_at >= datetime('now','localtime','-90 minutes')");
    $ocup = array_map(fn($o) => ['maquina' => $o['maquina'], 'alumno' => trim($o['alumno_nombre'] . ' ' . mb_substr((string) $o['alumno_apellido'], 0, 1)), 'libre_en' => max(0, (int) ceil(($o['hasta_est'] - time()) / 60))], ocupacion_actual());
    $mant = qa("SELECT id, nombre FROM maquinas WHERE estado = 'mantencion' ORDER BY nombre");
    return ['sesiones' => $out, 'grupos' => $grupos, 'presentes' => max($presentes, count($out)), 'ocupacion' => $ocup, 'mantencion' => $mant, 'hora' => date('H:i')];
}

// ================================================================ Check-in geolocalizado
function distancia_m(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 2 * $r * asin(min(1, sqrt($a)));
}

function api_checkin(): void
{
    $u = require_login();
    if ($u['rol'] !== 'alumno') json_out(['ok' => false]);
    $b = json_body();
    $sede = sede();
    if (empty($sede['lat']) || empty($sede['lng'])) json_out(['ok' => false, 'error' => 'Ubicación del gimnasio no configurada.']);
    $lat = (float) ($b['lat'] ?? 0);
    $lng = (float) ($b['lng'] ?? 0);
    $acc = (float) ($b['acc'] ?? 9999);
    $d = distancia_m($lat, $lng, (float) $sede['lat'], (float) $sede['lng']);
    $radio = (int) ($sede['radio_m'] ?: 100);
    if ($d > $radio + min($acc, 150)) json_out(['ok' => true, 'cerca' => false, 'distancia' => round($d)]);
    $ya = checkin_hoy((int) $u['id']);
    [$ok, $motivo] = acceso_estado((int) $u['id']);
    $hecho = false;
    if ($ok && !empty($b['confirmar'])) {
        $hecho = registrar_checkin((int) $u['id'], 'geo');
        revisar_racha((int) $u['id']);
    }
    json_out(['ok' => true, 'cerca' => true, 'ya' => (bool) $ya, 'hecho' => $hecho, 'acceso' => $ok, 'motivo' => $motivo, 'mensaje' => setting('brand_bienvenida')]);
}

// ================================================================ Push
function api_push_clave(): void
{
    require_login();
    json_out(['ok' => true, 'key' => config('vapid_public')]);
}

function api_push_suscribir(): void
{
    $u = require_login();
    $b = json_body();
    $ep = (string) ($b['endpoint'] ?? '');
    if (!empty($b['baja'])) {
        q('DELETE FROM push_subs WHERE endpoint = ? AND user_id = ?', [$ep, $u['id']]);
        json_out(['ok' => true]);
    }
    if (!preg_match('~^https://~', $ep) || strlen($ep) > 1000) json_out(['ok' => false, 'error' => 'Suscripción inválida'], 422);
    q('INSERT INTO push_subs(user_id, endpoint, p256dh, auth, ua, created_at) VALUES(?,?,?,?,?,?)
       ON CONFLICT(endpoint) DO UPDATE SET user_id = excluded.user_id, p256dh = excluded.p256dh, auth = excluded.auth',
        [$u['id'], $ep, (string) ($b['keys']['p256dh'] ?? ''), (string) ($b['keys']['auth'] ?? ''), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200), time()]);
    json_out(['ok' => true]);
}

/** Lo consulta el service worker al recibir un push vacío. */
function api_push_pendientes(): void
{
    $u = current_user();
    if (!$u) json_out(['ok' => true, 'items' => []]);
    $rows = qa('SELECT id, tipo, titulo, cuerpo, url FROM notificaciones WHERE user_id = ? AND enviada_at IS NOT NULL AND entregada_at IS NULL AND cancelada = 0 AND enviada_at > ? ORDER BY id DESC LIMIT 3', [$u['id'], time() - 900]);
    if ($rows) q('UPDATE notificaciones SET entregada_at = ? WHERE id IN (' . implode(',', array_map(fn($r) => (int) $r['id'], $rows)) . ')', [time()]);
    json_out(['ok' => true, 'items' => $rows]);
}

function api_pwa_visto(): void
{
    $u = require_login();
    update('users', ['pwa_hint_visto' => 1], 'id = ?', [$u['id']]);
    json_out(['ok' => true]);
}

// ================================================================ Muro
function api_muro_reaccion(): void
{
    $u = require_login();
    if ($u['rol'] === 'alumno' && !can('alumno.muro')) deny();
    $b = json_body();
    $emoji = in_array($b['emoji'] ?? '', ['🔥', '💪', '🦍'], true) ? $b['emoji'] : null;
    $post = q1('SELECT id, user_id FROM muro_posts WHERE id = ? AND oculto = 0', [(int) ($b['post_id'] ?? 0)]);
    if (!$emoji || !$post) json_out(['ok' => false], 422);
    if ((int) $post['user_id'] === (int) $u['id']) json_out(['ok' => false, 'error' => 'No puedes reaccionar a tu propia publicación 😄'], 422);
    if (qv('SELECT 1 FROM muro_reacciones WHERE post_id = ? AND user_id = ? AND emoji = ?', [$post['id'], $u['id'], $emoji])) {
        q('DELETE FROM muro_reacciones WHERE post_id = ? AND user_id = ? AND emoji = ?', [$post['id'], $u['id'], $emoji]);
        $on = false;
    } else {
        insert('muro_reacciones', ['post_id' => $post['id'], 'user_id' => $u['id'], 'emoji' => $emoji]);
        $on = true;
    }
    $n = (int) qv('SELECT COUNT(*) FROM muro_reacciones WHERE post_id = ? AND emoji = ?', [$post['id'], $emoji]);
    json_out(['ok' => true, 'on' => $on, 'n' => $n]);
}

function api_muro_borrar(): void
{
    $u = require_login();
    $b = json_body();
    $post = q1('SELECT * FROM muro_posts WHERE id = ? AND eliminado_at IS NULL', [(int) ($b['post_id'] ?? 0)]);
    if (!$post) not_found();
    $propio = (int) $post['user_id'] === (int) $u['id'];
    if (!$propio && !can('muro.moderar')) deny();
    update('muro_posts', ['eliminado_at' => date('Y-m-d H:i:s')], 'id = ?', [$post['id']]);
    q('DELETE FROM muro_reacciones WHERE post_id = ?', [$post['id']]);
    if (!$propio) audit('muro_borrado_moderador', 'muro_posts', (int) $post['id']);
    json_out(['ok' => true]);
}

function api_muro_reportar(): void
{
    $u = require_login();
    $b = json_body();
    $post = q1('SELECT id FROM muro_posts WHERE id = ?', [(int) ($b['post_id'] ?? 0)]);
    if (!$post) not_found();
    if (!qv('SELECT 1 FROM muro_reportes WHERE post_id = ? AND user_id = ?', [$post['id'], $u['id']])) {
        insert('muro_reportes', ['post_id' => $post['id'], 'user_id' => $u['id'], 'motivo' => mb_substr((string) ($b['motivo'] ?? ''), 0, 200)]);
    }
    json_out(['ok' => true]);
}

// ================================================================ Otros
function api_mantencion(): void
{
    require_perm('catalogo.mantencion');
    $b = json_body();
    $m = q1('SELECT * FROM maquinas WHERE id = ?', [(int) ($b['maquina_id'] ?? 0)]);
    if (!$m) not_found();
    $estado = ($b['estado'] ?? '') === 'mantencion' ? 'mantencion' : 'ok';
    update('maquinas', ['estado' => $estado], 'id = ?', [$m['id']]);
    audit('maquina_' . $estado, 'maquinas', (int) $m['id'], $m['nombre']);
    json_out(['ok' => true, 'estado' => $estado]);
}

function api_progreso(): void
{
    $u = require_login();
    $aid = input_int('alumno') ?: (int) $u['id'];
    if ($aid !== (int) $u['id'] && !can('alumnos.ver')) deny();
    $ej = input_int('ejercicio');
    if ($ej) json_out(['ok' => true, 'datos' => progreso_ejercicio($aid, $ej)]);
    $tipo = input('tipo');
    if ($tipo === 'mediciones') json_out(['ok' => true, 'datos' => qa('SELECT fecha, peso, cintura FROM mediciones WHERE user_id = ? ORDER BY fecha', [$aid])]);
    $r = reporte_alumno($aid);
    json_out(['ok' => true, 'volumen' => $r['volumen_grupo'], 'asistencia' => $r['asistencia_semanal']]);
}

function api_cron(): void
{
    $t = (string) input('token', '');
    if (!config('cron_token') || !hash_equals((string) config('cron_token'), $t)) json_out(['ok' => false], 403);
    require_once PRIVATE_DIR . '/app/cron_tasks.php';
    json_out(['ok' => true] + cron_pasada(false));
}
