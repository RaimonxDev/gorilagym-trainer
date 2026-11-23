<?php
declare(strict_types=1);

function sesion_hoy(int $uid): ?array
{
    return q1("SELECT s.*, c.nombre AS coach_nombre FROM sesiones s LEFT JOIN users c ON c.id = s.coach_id
               WHERE s.alumno_id = ? AND s.estado IN ('planificada','en_curso') AND date(s.created_at) = date('now','localtime')
               ORDER BY s.id DESC LIMIT 1", [$uid]);
}

function puede_ver_sesion(array $s): bool
{
    $u = current_user();
    if (!$u) return false;
    if ((int) $s['alumno_id'] === (int) $u['id']) return true;
    return $u['rol'] !== 'alumno' && (can('rutinas.armar') || can('sala.ver') || can('alumnos.ver'));
}

function alumno_inicio(): void
{
    $u = require_login();
    if ($u['rol'] !== 'alumno') redirect(url('coach'));
    [$ok, $motivo, $m] = acceso_estado((int) $u['id']);
    $hoy = sesion_hoy((int) $u['id']);
    $items = $hoy ? sesion_items((int) $hoy['id']) : [];
    $pendCalif = q1("SELECT s.id, c.nombre AS coach FROM sesiones s JOIN users c ON c.id = s.coach_id
                     WHERE s.alumno_id = ? AND s.modo = 'coach' AND s.estado = 'finalizada' AND s.coach_id IS NOT NULL
                     AND s.created_at >= datetime('now','localtime','-3 days')
                     AND NOT EXISTS (SELECT 1 FROM calificaciones k WHERE k.sesion_id = s.id) ORDER BY s.id DESC LIMIT 1", [$u['id']]);
    view('alumno/inicio', [
        'title' => 'Inicio', 'nav' => 'inicio', 'u' => $u, 'acceso' => $ok, 'motivo' => $motivo, 'memb' => $m ?? membresia_actual((int) $u['id']),
        'hoy' => $hoy, 'items' => $items, 'checkin' => checkin_hoy((int) $u['id']), 'racha' => semanas_seguidas((int) $u['id']),
        'modo' => alumno_modo((int) $u['id']), 'pendCalif' => $pendCalif, 'bienvenida' => input('bienvenida') === '1',
        'ultimas' => qa("SELECT s.*, c.nombre AS coach_nombre FROM sesiones s LEFT JOIN users c ON c.id = s.coach_id WHERE s.alumno_id = ? AND s.estado = 'finalizada' ORDER BY s.id DESC LIMIT 3", [$u['id']]),
        'ficha' => q1('SELECT * FROM fichas WHERE user_id = ?', [$u['id']]),
        'scripts' => ['assets/js/inicio.js'],
    ]);
}

function alumno_sesion(): void
{
    $u = require_login();
    $id = input_int('id', 0);
    $s = q1('SELECT * FROM sesiones WHERE id = ?', [$id]);
    if (!$s) not_found();
    if (!puede_ver_sesion($s)) deny();
    $alumno = q1('SELECT id, nombre, apellido FROM users WHERE id = ?', [$s['alumno_id']]);
    view('alumno/sesion', ['title' => 'Rutina', 'nav' => 'inicio', 's' => $s, 'alumno' => $alumno, 'focus' => input_int('item'), 'autostart' => input('auto') === '1',
        'scripts' => ['assets/js/sesion.js']]);
}

function alumno_rutina_libre(): void
{
    $u = require_login();
    if ($u['rol'] !== 'alumno') redirect(url('coach'));
    if (is_post()) {
        [$ok, $motivo] = acceso_estado((int) $u['id']);
        if (!$ok) {
            flash('error', $motivo);
            redirect(url('inicio'));
        }
        $existing = sesion_hoy((int) $u['id']);
        if ($existing) redirect(url('sesion', ['id' => $existing['id']]));
        $pid = input_int('plantilla_id');
        $soloPlantillas = alumno_solo_plantillas($u);
        if ($soloPlantillas && !qv('SELECT 1 FROM plantillas WHERE id = ? AND activo = 1 AND oficial = 1', [$pid])) {
            flash('error', 'Elige uno de los entrenos del gimnasio.');
            redirect(url('rutina-libre'));
        }
        $sid = insert('sesiones', ['alumno_id' => $u['id'], 'coach_id' => null, 'sede_id' => sede_id(), 'modo' => 'libre', 'estado' => 'planificada', 'titulo' => $soloPlantillas ? (string) qv('SELECT nombre FROM plantillas WHERE id = ?', [$pid]) : 'Rutina libre']);
        if ($pid) {
            require_once __DIR__ . '/api.php';
            builder_aplicar_plantilla($sid, $pid, (int) $u['id']);
        }
        recalc_sesion($sid);
        redirect($soloPlantillas ? url('sesion', ['id' => $sid]) : url('coach/rutina', ['sesion' => $sid]));
    }
    view('alumno/rutina_libre', ['title' => 'Mi rutina', 'nav' => 'inicio', 'soloPlantillas' => alumno_solo_plantillas($u),
        'plantillas' => qa('SELECT p.*, (SELECT COUNT(*) FROM plantilla_items i WHERE i.plantilla_id = p.id) AS n FROM plantillas p WHERE p.activo = 1 AND p.oficial = 1 ORDER BY p.nombre')]);
}

function alumno_target(): array
{
    $u = require_login();
    $aid = input_int('alumno');
    if ($aid && $aid !== (int) $u['id']) {
        if (!can('alumnos.ver')) deny();
        $a = q1("SELECT * FROM users WHERE id = ? AND rol = 'alumno'", [$aid]);
        if (!$a) not_found();
        return $a;
    }
    if (!can('alumno.progreso') && $u['rol'] === 'alumno') deny();
    return $u;
}

function alumno_progreso(): void
{
    $a = alumno_target();
    view('alumno/progreso', ['title' => 'Progreso', 'nav' => current_user()['rol'] === 'alumno' ? 'progreso' : 'alumnos', 'a' => $a, 'rep' => reporte_alumno((int) $a['id']),
        'mediciones' => qa('SELECT * FROM mediciones WHERE user_id = ? ORDER BY fecha DESC LIMIT 6', [$a['id']]),
        'propio' => (int) $a['id'] === uid(), 'scripts' => ['assets/js/charts.js']]);
}

function alumno_progreso_ejercicio(): void
{
    $a = alumno_target();
    $ej = q1('SELECT * FROM ejercicios WHERE id = ?', [input_int('id', 0)]);
    if (!$ej) not_found();
    $datos = progreso_ejercicio((int) $a['id'], (int) $ej['id']);
    $hist = qa('SELECT sl.*, date(sl.created_at) AS fecha FROM series_log sl WHERE sl.alumno_id = ? AND sl.ejercicio_id = ? ORDER BY sl.id DESC LIMIT 60', [$a['id'], $ej['id']]);
    view('alumno/progreso_ejercicio', ['title' => $ej['nombre'], 'nav' => 'progreso', 'a' => $a, 'ej' => $ej, 'datos' => $datos, 'hist' => $hist, 'scripts' => ['assets/js/charts.js']]);
}

function alumno_reporte_mensual(): void
{
    $a = alumno_target();
    $periodo = preg_match('/^\d{4}-\d{2}$/', (string) input('periodo')) ? input('periodo') : date('Y-m', strtotime('first day of last month'));
    $desde = $periodo . '-01';
    $hasta = date('Y-m-t', strtotime($desde));
    $prevP = date('Y-m', strtotime($desde . ' -1 month'));
    $dias = fn($d1, $d2) => (int) qv("SELECT COUNT(DISTINCT date(d)) FROM (SELECT created_at AS d FROM checkins WHERE user_id = ? UNION ALL SELECT created_at FROM sesiones WHERE alumno_id = ? AND estado IN ('en_curso','finalizada')) WHERE date(d) BETWEEN ? AND ?", [$a['id'], $a['id'], $d1, $d2]);
    $data = [
        'periodo' => $periodo,
        'dias' => $dias($desde, $hasta),
        'dias_prev' => $dias($prevP . '-01', date('Y-m-t', strtotime($prevP . '-01'))),
        'sesiones' => (int) qv("SELECT COUNT(*) FROM sesiones WHERE alumno_id = ? AND estado = 'finalizada' AND date(created_at) BETWEEN ? AND ?", [$a['id'], $desde, $hasta]),
        'series' => (int) qv('SELECT COUNT(*) FROM series_log WHERE alumno_id = ? AND date(created_at) BETWEEN ? AND ?', [$a['id'], $desde, $hasta]),
        'volumen' => (float) qv('SELECT COALESCE(SUM(peso*reps),0) FROM series_log WHERE alumno_id = ? AND date(created_at) BETWEEN ? AND ?', [$a['id'], $desde, $hasta]),
        'records' => qa("SELECT l.*, e.nombre AS ejercicio FROM logros l LEFT JOIN ejercicios e ON e.id = l.ejercicio_id WHERE l.user_id = ? AND l.tipo = 'record' AND date(l.created_at) BETWEEN ? AND ? ORDER BY l.valor DESC", [$a['id'], $desde, $hasta]),
        'grupos' => qa('SELECT g.nombre, COUNT(*) AS series FROM series_log sl JOIN ejercicios e ON e.id = sl.ejercicio_id JOIN grupos_musculares g ON g.id = e.grupo_id WHERE sl.alumno_id = ? AND date(sl.created_at) BETWEEN ? AND ? GROUP BY g.nombre ORDER BY series DESC', [$a['id'], $desde, $hasta]),
        'racha' => semanas_seguidas((int) $a['id']),
    ];
    view('alumno/reporte_mensual', ['title' => 'Resumen ' . mes_nombre((int) substr($periodo, 5, 2)), 'nav' => 'progreso', 'a' => $a, 'd' => $data, 'scripts' => ['assets/js/sharecard.js']]);
}

function alumno_perfil(): void
{
    $u = require_login();
    if (is_post()) {
        $acc = input('accion');
        $tabDe = ['datos' => 'cuenta', 'foto' => 'cuenta', 'quitar_foto' => 'cuenta', 'preferencias' => 'privacidad', 'eliminar' => 'seguridad'];
        if ($acc === 'foto') {
            try {
                $f = guardar_avatar((int) $u['id']);
                if ($f) {
                    delete_media($u['foto'] ?? null);
                    update('users', ['foto' => $f], 'id = ?', [$u['id']]);
                    flash('ok', 'Foto de perfil actualizada.');
                } else flash('error', 'Elige una foto.');
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
            }
        } elseif ($acc === 'quitar_foto') {
            delete_media($u['foto'] ?? null);
            update('users', ['foto' => null], 'id = ?', [$u['id']]);
            flash('ok', 'Foto eliminada.');
        } elseif ($acc === 'datos') {
            $tel = preg_replace('/[^0-9+ ]/', '', (string) input('telefono', ''));
            update('users', ['telefono' => $tel ?: null], 'id = ?', [$u['id']]);
            flash('ok', 'Datos actualizados.');
        } elseif ($acc === 'preferencias') {
            update('users', [
                'muro_opt_in' => input('muro_opt_in') ? 1 : 0, 'muro_logros' => input('muro_logros') ? 1 : 0,
                'muro_mostrar_pesos' => input('muro_mostrar_pesos') ? 1 : 0, 'geo_opt_in' => input('geo_opt_in') ? 1 : 0,
            ], 'id = ?', [$u['id']]);
            if (!input('muro_opt_in')) q('UPDATE muro_posts SET oculto = 1 WHERE user_id = ?', [$u['id']]);
            flash('ok', 'Preferencias guardadas.');
        } elseif ($acc === 'cerrar_todo') {
            logout_user(true);
            redirect(url('login'));
        } elseif ($acc === 'eliminar') {
            if (!qv("SELECT 1 FROM solicitudes_eliminacion WHERE user_id = ? AND estado = 'pendiente'", [$u['id']])) {
                insert('solicitudes_eliminacion', ['user_id' => $u['id'], 'motivo' => mb_substr((string) input('motivo', ''), 0, 500)]);
                audit('solicitud_eliminacion', 'users', (int) $u['id']);
            }
            flash('ok', 'Recibimos tu solicitud. El gimnasio la revisará y te contactará.');
        }
        redirect(url('perfil', ['tab' => $tabDe[$acc] ?? 'cuenta']));
    }
    view('alumno/perfil', ['title' => 'Perfil', 'nav' => 'perfil', 'u' => q1('SELECT * FROM users WHERE id = ?', [$u['id']]),
        'memb' => membresia_actual((int) $u['id']), 'acred' => acreditacion_vigente((int) $u['id']),
        'solicitud' => q1("SELECT * FROM solicitudes_eliminacion WHERE user_id = ? AND estado = 'pendiente'", [$u['id']]),
        'tab' => in_array(input('tab'), ['cuenta', 'privacidad', 'notificaciones', 'seguridad'], true) ? input('tab') : 'cuenta',
        'scripts' => ['assets/js/perfil.js']]);
}

/** Foto de perfil: llega ya recortada/reducida desde el navegador; aquí se valida y se re-codifica (quita EXIF/GPS). */
function guardar_avatar(int $uid): ?string
{
    $rel = handle_upload('foto', 'avatars', IMG_TYPES, 6 * 1048576);
    if (!$rel) return null;
    $path = ROOT_DIR . '/media/' . $rel;
    $info = @getimagesize($path);
    if (!$info || $info[0] < 32 || $info[1] < 32) {
        @unlink($path);
        throw new RuntimeException('La imagen no es válida.');
    }
    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if ($src) {
            $w = imagesx($src);
            $h = imagesy($src);
            $lado = min($w, $h);
            $dst = imagecreatetruecolor(400, 400);
            imagecopyresampled($dst, $src, 0, 0, (int) (($w - $lado) / 2), (int) (($h - $lado) / 2), 400, 400, $lado, $lado);
            @unlink($path);
            $rel = 'avatars/' . $uid . '-' . token(6) . '.jpg';
            imagejpeg($dst, ROOT_DIR . '/media/' . $rel, 85);
            imagedestroy($src);
            imagedestroy($dst);
        }
    }
    return $rel;
}

function cuestionario_preguntas(): array
{
    $raw = setting('cuestionario_salud');
    $lines = $raw ? array_values(array_filter(array_map('trim', explode("\n", $raw)))) : [
        '¿Algún médico te ha dicho que tienes una condición cardíaca o presión alta?',
        '¿Sientes dolor en el pecho al hacer actividad física?',
        '¿Has perdido el equilibrio por mareos o perdido el conocimiento en el último año?',
        '¿Tienes alguna lesión o problema óseo/articular que pueda empeorar con el ejercicio?',
        '¿Tomas algún medicamento recetado para una condición crónica?',
        '¿Estás embarazada o tuviste un parto en los últimos 6 meses?',
    ];
    return $lines;
}

function alumno_ficha(): void
{
    $u = require_login();
    $aid = input_int('alumno') ?: (int) $u['id'];
    if ($aid !== (int) $u['id'] && !can('alumnos.salud')) deny();
    $f = q1('SELECT * FROM fichas WHERE user_id = ?', [$aid]) ?? [];
    $preg = cuestionario_preguntas();
    if (is_post()) {
        $propio = $aid === (int) $u['id'];
        if ($propio && !input('consentimiento')) {
            flash('error', 'Para guardar tu ficha debes aceptar el tratamiento de tus datos de salud.');
            redirect(url('ficha'));
        }
        $tags = array_values(array_intersect((array) ($_POST['restricciones'] ?? []), restricciones_catalogo()));
        $resp = [];
        foreach ($preg as $i => $p) $resp[] = ['p' => $p, 'r' => ($_POST['q'][$i] ?? 'no') === 'si' ? 'si' : 'no'];
        $data = [
            'objetivo' => mb_substr((string) input('objetivo', ''), 0, 120), 'nivel' => in_array(input('nivel'), ['principiante', 'intermedio', 'avanzado'], true) ? input('nivel') : 'principiante',
            'dias_semana' => max(1, min(6, (int) input('dias_semana', 3))), 'restricciones' => implode(',', $tags),
            'restricciones_detalle' => mb_substr((string) input('restricciones_detalle', ''), 0, 1000), 'cuestionario' => json_encode($resp, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($propio && empty($f['consentimiento_salud_at'])) $data['consentimiento_salud_at'] = date('Y-m-d H:i:s');
        if ($f) update('fichas', $data, 'user_id = ?', [$aid]);
        else insert('fichas', $data + ['user_id' => $aid]);
        audit('ficha_actualizada', 'users', $aid);
        $alertas = array_filter($resp, fn($r) => $r['r'] === 'si');
        flash($alertas ? 'warning' : 'ok', $alertas ? 'Ficha guardada. Respondiste "sí" en preguntas de salud: conversa con tu coach antes de entrenar y, si corresponde, consulta a tu médico.' : 'Ficha guardada.');
        redirect($propio ? url('inicio') : url('coach/alumno', ['id' => $aid]));
    }
    view('alumno/ficha', ['title' => 'Ficha de ingreso', 'nav' => 'perfil', 'f' => $f, 'preg' => $preg, 'aid' => $aid, 'propio' => $aid === (int) $u['id'], 'primera' => input('primera') === '1']);
}

function alumno_mediciones(): void
{
    $u = require_login();
    if (!can('alumno.mediciones') && $u['rol'] === 'alumno') deny();
    if (is_post()) {
        if (input('borrar')) {
            q('DELETE FROM mediciones WHERE id = ? AND user_id = ?', [input_int('borrar'), $u['id']]);
        } else {
            $num = fn($k) => ($v = str_replace(',', '.', (string) input($k, ''))) !== '' && is_numeric($v) && (float) $v > 0 && (float) $v < 400 ? (float) $v : null;
            $row = ['user_id' => $u['id'], 'fecha' => parse_date_input(input('fecha')) ?? date('Y-m-d'), 'peso' => $num('peso'), 'cintura' => $num('cintura'), 'cadera' => $num('cadera'),
                'pecho' => $num('pecho'), 'brazo' => $num('brazo'), 'muslo' => $num('muslo'), 'notas' => mb_substr((string) input('notas', ''), 0, 200)];
            if (count(array_filter(array_slice($row, 2, 6), fn($v) => $v !== null)) === 0) flash('error', 'Ingresa al menos una medida.');
            else {
                insert('mediciones', $row);
                flash('ok', 'Medición guardada.');
            }
        }
        redirect(url('mediciones'));
    }
    view('alumno/mediciones', ['title' => 'Mediciones', 'nav' => 'progreso', 'rows' => qa('SELECT * FROM mediciones WHERE user_id = ? ORDER BY fecha DESC', [$u['id']]), 'scripts' => ['assets/js/charts.js']]);
}

function parse_date_input($v): ?string
{
    return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

function alumno_calificar(): void
{
    $u = require_login();
    if (!can('alumno.calificar')) deny();
    $s = q1("SELECT s.*, c.nombre AS coach_nombre FROM sesiones s JOIN users c ON c.id = s.coach_id WHERE s.id = ? AND s.alumno_id = ? AND s.modo = 'coach'", [input_int('sesion', 0), $u['id']]);
    if (!$s) not_found();
    if (qv('SELECT 1 FROM calificaciones WHERE sesion_id = ?', [$s['id']])) {
        flash('ok', 'Ya calificaste esta sesión. ¡Gracias!');
        redirect(url('inicio'));
    }
    if (is_post()) {
        $v = fn($k) => max(1, min(5, (int) input($k, 0)));
        if (!input('tecnica') || !input('atencion') || !input('rutina')) {
            flash('error', 'Responde las tres preguntas.');
            redirect(url('calificar', ['sesion' => $s['id']]));
        }
        insert('calificaciones', ['sesion_id' => $s['id'], 'coach_id' => $s['coach_id'], 'alumno_id' => $u['id'], 'tecnica' => $v('tecnica'), 'atencion' => $v('atencion'), 'rutina' => $v('rutina'),
            'comentario' => mb_substr((string) input('comentario', ''), 0, 500) ?: null]);
        flash('ok', 'Gracias. Tu evaluación es anónima para el coach: solo ve un promedio mensual.');
        redirect(url('inicio'));
    }
    view('alumno/calificar', ['title' => 'Calificar sesión', 'nav' => 'inicio', 's' => $s]);
}

function alumno_notificaciones(): void
{
    $u = require_login();
    $rows = qa('SELECT * FROM notificaciones WHERE user_id = ? AND cancelada = 0 AND enviar_at <= ? ORDER BY enviar_at DESC LIMIT 40', [$u['id'], time()]);
    $avisos = qa('SELECT * FROM avisos ORDER BY id DESC LIMIT 10');
    view('alumno/notificaciones', ['title' => 'Avisos', 'nav' => 'avisos', 'rows' => $rows, 'avisos' => $avisos, 'scripts' => ['assets/js/perfil.js']]);
}

function alumno_instalar(): void
{
    require_login();
    view('alumno/instalar', ['title' => 'Instalar app', 'nav' => 'perfil']);
}

function ver_ejercicio(): void
{
    $u = require_login();
    $ej = q1('SELECT e.*, m.nombre AS maquina_nombre, m.foto AS maquina_foto, m.estado AS maquina_estado, g.nombre AS grupo FROM ejercicios e LEFT JOIN maquinas m ON m.id = e.maquina_id LEFT JOIN grupos_musculares g ON g.id = e.grupo_id WHERE e.id = ?', [input_int('id', 0)]);
    if (!$ej) not_found();
    $alts = qa('SELECT e.id, e.nombre, m.nombre AS maquina FROM ejercicio_alternativas a JOIN ejercicios e ON e.id = a.alternativa_id LEFT JOIN maquinas m ON m.id = e.maquina_id WHERE a.ejercicio_id = ? AND e.activo = 1', [$ej['id']]);
    $ult = $u['rol'] === 'alumno' ? ultimo_trabajo((int) $u['id'], (int) $ej['id']) : null;
    $sug = $u['rol'] === 'alumno' ? peso_sugerido((int) $u['id'], (int) $ej['id']) : null;
    view('alumno/ejercicio', ['title' => $ej['nombre'], 'nav' => $u['rol'] === 'alumno' ? 'inicio' : '', 'ej' => $ej, 'alts' => $alts, 'ult' => $ult, 'sug' => $sug,
        'ocup' => $ej['maquina_id'] ? maquina_ocupacion((int) $ej['maquina_id']) : null]);
}

function scan_maquina(): void
{
    $u = require_login();
    $m = q1('SELECT * FROM maquinas WHERE token = ?', [(string) ($_GET['m'] ?? '')]);
    if (!$m) not_found();
    $ejs = qa('SELECT e.*, g.nombre AS grupo FROM ejercicios e LEFT JOIN grupos_musculares g ON g.id = e.grupo_id WHERE e.maquina_id = ? AND e.activo = 1 ORDER BY e.nombre', [$m['id']]);
    if ($u['rol'] === 'alumno') {
        $s = q1("SELECT * FROM sesiones WHERE alumno_id = ? AND estado IN ('planificada','en_curso') AND date(created_at) = date('now','localtime') ORDER BY id DESC LIMIT 1", [$u['id']]);
        if ($s) {
            $it = q1("SELECT si.id FROM sesion_items si JOIN ejercicios e ON e.id = si.ejercicio_id WHERE si.sesion_id = ? AND e.maquina_id = ? AND si.estado IN ('pendiente','en_curso') ORDER BY si.orden LIMIT 1", [$s['id'], $m['id']]);
            if ($it) redirect(url('sesion', ['id' => $s['id'], 'item' => $it['id'], 'auto' => 1]));
        }
        if (count($ejs) === 1) redirect(url('ejercicio', ['id' => $ejs[0]['id']]));
    }
    foreach ($ejs as &$e) $e['ult'] = $u['rol'] === 'alumno' ? peso_sugerido((int) $u['id'], (int) $e['id']) : null;
    view('alumno/maquina', ['title' => $m['nombre'], 'nav' => '', 'm' => $m, 'ejs' => $ejs, 'ocup' => maquina_ocupacion((int) $m['id']), 'scripts' => ['assets/js/inicio.js']]);
}

function scan_ejercicio(): void
{
    require_login();
    $e = q1('SELECT id FROM ejercicios WHERE token = ?', [(string) ($_GET['e'] ?? '')]);
    if (!$e) not_found();
    redirect(url('ejercicio', ['id' => $e['id']]));
}

function scan_entrada(): void
{
    $u = require_login();
    $t = (string) ($_GET['entrada'] ?? '');
    if (!hash_equals((string) setting('entrada_token', ''), $t)) not_found();
    if ($u['rol'] !== 'alumno') {
        flash('ok', 'Tag de entrada funcionando correctamente.');
        redirect(url('coach'));
    }
    [$ok, $motivo] = acceso_estado((int) $u['id']);
    if ($ok) {
        registrar_checkin((int) $u['id'], 'tag');
        revisar_racha((int) $u['id']);
        redirect(url('inicio', ['bienvenida' => 1]));
    }
    flash('error', $motivo);
    redirect(url('inicio'));
}
