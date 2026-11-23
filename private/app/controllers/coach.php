<?php
declare(strict_types=1);

function coach_inicio(): void
{
    $u = require_staff();
    $mias = qa("SELECT s.*, a.nombre, a.apellido FROM sesiones s JOIN users a ON a.id = s.alumno_id
                WHERE s.coach_id = ? AND s.estado IN ('planificada','en_curso') AND date(s.created_at) = date('now','localtime') ORDER BY s.id DESC", [$u['id']]);
    foreach ($mias as &$m) {
        $it = sesion_items((int) $m['id']);
        $m['n'] = count($it);
        $m['hechos'] = count(array_filter($it, fn($i) => in_array($i['estado'], ['hecho', 'omitido'], true)));
        $m['est'] = estimacion_min($it);
    }
    unset($m);
    $hoyCheckins = qa("SELECT c.created_at, u.id, u.nombre, u.apellido,
                       (SELECT s.id FROM sesiones s WHERE s.alumno_id = u.id AND date(s.created_at) = date('now','localtime') AND s.estado != 'cancelada' ORDER BY s.id DESC LIMIT 1) AS sesion_id
                       FROM checkins c JOIN users u ON u.id = c.user_id
                       WHERE c.created_at >= datetime('now','localtime','-120 minutes') ORDER BY c.created_at DESC LIMIT 20");
    view('coach/inicio', ['title' => 'Inicio', 'nav' => 'coach', 'u' => $u, 'mias' => $mias, 'llegadas' => $hoyCheckins,
        'alertas' => can('alumnos.ver') ? alertas_staff(8) : null, 'scripts' => ['assets/js/coach.js']]);
}

function coach_alumnos(): void
{
    require_perm('alumnos.ver');
    $qtxt = trim((string) input('q', ''));
    $filtro = (string) input('f', '');
    $where = ["u.rol = 'alumno'"];
    $p = [];
    if ($qtxt !== '') {
        $clean = rut_clean($qtxt);
        $where[] = "(u.nombre || ' ' || u.apellido LIKE ? OR u.doc_num LIKE ? OR u.email LIKE ?)";
        $p[] = '%' . $qtxt . '%';
        $p[] = '%' . ($clean !== '' && strlen($clean) > 3 ? substr($clean, 0, -1) : $qtxt) . '%';
        $p[] = '%' . $qtxt . '%';
    }
    $hoy = date('Y-m-d');
    if ($filtro === 'sin_correo') $where[] = "(u.email IS NULL OR u.email = '')";
    if ($filtro === 'vencidos') $where[] = "NOT EXISTS (SELECT 1 FROM membresias m WHERE m.user_id = u.id AND m.fecha_fin >= '$hoy')";
    if ($filtro === 'vigentes') $where[] = "EXISTS (SELECT 1 FROM membresias m WHERE m.user_id = u.id AND m.fecha_fin >= '$hoy' AND m.fecha_inicio <= '$hoy')";
    if ($filtro === 'inactivos') $where[] = 'u.activo = 0';
    $pag = max(1, input_int('p', 1));
    $total = (int) qv('SELECT COUNT(*) FROM users u WHERE ' . implode(' AND ', $where), $p);
    $rows = qa('SELECT u.* FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.nombre, u.apellido LIMIT 40 OFFSET ' . (($pag - 1) * 40), $p);
    foreach ($rows as &$r) $r['memb'] = membresia_actual((int) $r['id']);
    view('coach/alumnos', ['title' => 'Alumnos', 'nav' => 'alumnos', 'rows' => $rows, 'q' => $qtxt, 'f' => $filtro, 'total' => $total, 'pag' => $pag]);
}

function coach_alumno(): void
{
    require_perm('alumnos.ver');
    $a = q1("SELECT * FROM users WHERE id = ? AND rol = 'alumno'", [input_int('id', 0)]);
    if (!$a) not_found();
    $aid = (int) $a['id'];
    [$ok, $motivo, $m] = acceso_estado($aid);
    view('coach/alumno', [
        'title' => nombre_completo($a), 'nav' => 'alumnos', 'a' => $a, 'acceso' => $ok, 'motivo' => $motivo, 'memb' => $m ?? membresia_actual($aid),
        'membresias' => qa('SELECT m.*, p.nombre AS plan FROM membresias m JOIN planes p ON p.id = m.plan_id WHERE m.user_id = ? ORDER BY m.fecha_fin DESC LIMIT 6', [$aid]),
        'acred' => acreditacion_vigente($aid), 'ficha' => can('alumnos.salud') ? q1('SELECT * FROM fichas WHERE user_id = ?', [$aid]) : null,
        'notas' => qa('SELECT n.*, u.nombre AS autor FROM notas_alumno n JOIN users u ON u.id = n.autor_id WHERE n.alumno_id = ? ORDER BY n.id DESC LIMIT 15', [$aid]),
        'sesiones' => qa("SELECT s.*, c.nombre AS coach_nombre, (SELECT GROUP_CONCAT(DISTINCT g.nombre) FROM sesion_items si JOIN ejercicios e ON e.id = si.ejercicio_id JOIN grupos_musculares g ON g.id = e.grupo_id WHERE si.sesion_id = s.id) AS grupos
                          FROM sesiones s LEFT JOIN users c ON c.id = s.coach_id WHERE s.alumno_id = ? AND s.estado != 'cancelada' ORDER BY s.id DESC LIMIT 8", [$aid]),
        'estancados' => estancados($aid, 10), 'adherencia' => adherencia($aid),
        'feedback' => qv("SELECT ROUND(AVG(feedback),1) FROM sesiones WHERE alumno_id = ? AND feedback IS NOT NULL AND created_at >= datetime('now','localtime','-60 days')", [$aid]),
        'hoy' => q1("SELECT * FROM sesiones WHERE alumno_id = ? AND estado IN ('planificada','en_curso') AND date(created_at) = date('now','localtime') ORDER BY id DESC LIMIT 1", [$aid]),
        'sugerido' => grupo_sugerido($aid), 'plantillas' => qa('SELECT id, nombre FROM plantillas WHERE activo = 1 AND oficial = 1 ORDER BY nombre'),
        'ultCheckin' => qv('SELECT MAX(created_at) FROM checkins WHERE user_id = ?', [$aid]),
        'pausas' => qa('SELECT p.* FROM pausas p JOIN membresias m ON m.id = p.membresia_id WHERE m.user_id = ? ORDER BY p.id DESC LIMIT 3', [$aid]),
        'planes' => qa('SELECT * FROM planes WHERE activo = 1 ORDER BY orden'),
    ]);
}

function coach_sesion_nueva(): void
{
    $u = require_perm('rutinas.armar');
    if (!is_post()) redirect(url('coach'));
    $a = q1("SELECT * FROM users WHERE id = ? AND rol = 'alumno' AND activo = 1", [input_int('alumno_id', 0)]);
    if (!$a) not_found();
    if (alumno_solo_plantillas($a)) {
        flash('error', 'Su plan es sin coach: entrena con los entrenos predeterminados del gimnasio.');
        redirect(url('coach/alumno', ['id' => $a['id']]));
    }
    $ex = q1("SELECT * FROM sesiones WHERE alumno_id = ? AND estado IN ('planificada','en_curso') AND date(created_at) = date('now','localtime') ORDER BY id DESC LIMIT 1", [$a['id']]);
    if ($ex) {
        if (!$ex['coach_id']) update('sesiones', ['coach_id' => $u['id'], 'modo' => 'coach'], 'id = ?', [$ex['id']]);
        redirect(url('coach/rutina', ['sesion' => $ex['id']]));
    }
    [$ok, $motivo] = acceso_estado((int) $a['id']);
    if (!$ok) {
        flash('error', 'No se puede armar la rutina: ' . $motivo);
        redirect(url('coach/alumno', ['id' => $a['id']]));
    }
    $sid = insert('sesiones', ['alumno_id' => $a['id'], 'coach_id' => $u['id'], 'sede_id' => sede_id(), 'modo' => 'coach', 'estado' => 'planificada']);
    if ($pid = input_int('plantilla_id')) {
        require_once __DIR__ . '/api.php';
        builder_aplicar_plantilla($sid, $pid, (int) $a['id']);
    }
    recalc_sesion($sid);
    audit('sesion_creada', 'sesiones', $sid, ['alumno' => $a['id']]);
    redirect(url('coach/rutina', ['sesion' => $sid]));
}

function coach_rutina(): void
{
    $u = require_login();
    require_once __DIR__ . '/api.php';
    $s = api_sesion_cargar(input_int('sesion', 0), 'editar');
    $a = q1('SELECT * FROM users WHERE id = ?', [$s['alumno_id']]);
    $aid = (int) $a['id'];
    $esStaff = $u['rol'] !== 'alumno';
    view('coach/rutina', [
        'title' => 'Rutina de ' . $a['nombre'], 'nav' => $esStaff ? 'coach' : 'inicio', 's' => $s, 'a' => $a, 'esStaff' => $esStaff,
        'ctx' => [
            'ultimas' => grupos_recientes($aid, 3),
            'sugerido' => grupo_sugerido($aid),
            'restricciones' => restricciones_alumno($aid),
            'detalle' => $esStaff && can('alumnos.salud') ? qv('SELECT restricciones_detalle FROM fichas WHERE user_id = ?', [$aid]) : null,
            'traspaso' => $esStaff ? q1("SELECT n.*, u.nombre AS autor FROM notas_alumno n JOIN users u ON u.id = n.autor_id WHERE n.alumno_id = ? AND n.tipo = 'traspaso' ORDER BY n.id DESC LIMIT 1", [$aid]) : null,
            'ficha' => q1('SELECT objetivo, nivel FROM fichas WHERE user_id = ?', [$aid]),
        ],
        'plantillas' => qa('SELECT id, nombre, nivel FROM plantillas WHERE activo = 1 AND oficial = 1 ORDER BY nombre'),
        'grupos' => qa('SELECT * FROM grupos_musculares ORDER BY orden'),
        'coaches' => $esStaff && can('alumnos.reasignar') ? qa("SELECT id, nombre, apellido FROM users WHERE rol IN ('coach','jefe','superadmin') AND activo = 1 ORDER BY nombre") : [],
        'scripts' => ['assets/js/vendor/Sortable.min.js', 'assets/js/builder.js'],
    ]);
}

function coach_activar(): void
{
    require_perm('alumnos.activar');
    if (!is_post()) redirect(url('coach'));
    $a = q1("SELECT * FROM users WHERE id = ? AND rol = 'alumno'", [input_int('alumno_id', 0)]);
    if (!$a) not_found();
    $email = mb_strtolower(trim((string) input('email', '')));
    if (!valid_email($email)) {
        flash('error', 'El correo no es válido.');
        redirect(url('coach/alumno', ['id' => $a['id']]));
    }
    $old = $a['email'];
    update('users', ['email' => $email, 'activo' => 1], 'id = ?', [$a['id']]);
    audit('alumno_activado', 'users', (int) $a['id'], ['correo_anterior' => $old, 'correo_nuevo' => $email, 'verificacion' => 'presencial']);
    flash('ok', 'Listo. ' . $a['nombre'] . ' ya puede entrar con su documento y el código que le llegará a ' . $email . '.');
    redirect(url('coach/alumno', ['id' => $a['id']]));
}

function coach_nota(): void
{
    $u = require_perm('alumnos.notas');
    $aid = input_int('alumno_id', 0);
    if (!q1("SELECT 1 FROM users WHERE id = ? AND rol = 'alumno'", [$aid])) not_found();
    if (input('borrar')) {
        q('DELETE FROM notas_alumno WHERE id = ? AND autor_id = ?', [input_int('borrar'), $u['id']]);
    } else {
        $txt = trim((string) input('texto', ''));
        if ($txt !== '') insert('notas_alumno', ['alumno_id' => $aid, 'autor_id' => $u['id'], 'tipo' => input('tipo') === 'traspaso' ? 'traspaso' : 'nota', 'texto' => mb_substr($txt, 0, 1000)]);
    }
    back(url('coach/alumno', ['id' => $aid]));
}

function coach_reasignar(): void
{
    require_perm('alumnos.reasignar');
    $s = q1('SELECT * FROM sesiones WHERE id = ?', [input_int('sesion_id', 0)]);
    $c = q1("SELECT * FROM users WHERE id = ? AND rol != 'alumno' AND activo = 1", [input_int('coach_id', 0)]);
    if (!$s || !$c) not_found();
    update('sesiones', ['coach_id' => $c['id'], 'modo' => 'coach'], 'id = ?', [$s['id']]);
    audit('sesion_reasignada', 'sesiones', (int) $s['id'], ['de' => $s['coach_id'], 'a' => $c['id']]);
    notificar((int) $c['id'], 'reasignada', 'Te asignaron una sesión', 'Revisa la rutina en curso.', url('sesion', ['id' => $s['id']]));
    flash('ok', 'Sesión reasignada a ' . $c['nombre'] . '.');
    back(url('coach'));
}

function coach_pase_diario(): void
{
    $u = require_perm('planes.pase_diario');
    if (is_post()) {
        $tipo = input('doc_tipo') === 'rut' ? 'rut' : 'pasaporte';
        $doc = trim((string) input('doc', ''));
        $nombre = trim((string) input('nombre', ''));
        if ($tipo === 'rut' && $doc !== '' && !rut_valid($doc)) {
            flash('error', 'RUT inválido.');
            redirect(url('coach/pase'));
        }
        $docN = $doc !== '' ? doc_normalize($tipo, $doc) : null;
        $user = $docN ? q1('SELECT * FROM users WHERE doc_tipo = ? AND doc_num = ?', [$tipo, $docN]) : null;
        if (!$nombre && $user) $nombre = nombre_completo($user);
        if ($nombre === '') {
            flash('error', 'Ingresa el nombre.');
            redirect(url('coach/pase'));
        }
        $monto = (int) preg_replace('/\D/', '', (string) input('monto', '')) ?: null;
        insert('pases_diarios', ['user_id' => $user['id'] ?? null, 'nombre' => $nombre, 'doc_num' => $docN, 'fecha' => date('Y-m-d'), 'monto' => $monto, 'registrado_por' => $u['id']]);
        audit('pase_diario', 'pases_diarios', null, ['nombre' => $nombre, 'doc' => $docN]);
        if ($user) registrar_checkin((int) $user['id'], 'pase');
        flash('ok', 'Pase Diario registrado para ' . $nombre . '.');
        redirect(url('coach/pase'));
    }
    view('coach/pase', ['title' => 'Pase Diario', 'nav' => 'coach', 'hoy' => qa('SELECT p.*, u.nombre AS staff FROM pases_diarios p LEFT JOIN users u ON u.id = p.registrado_por WHERE p.fecha = ? ORDER BY p.id DESC', [date('Y-m-d')])]);
}

function coach_sala(): void
{
    require_perm('sala.ver');
    view('coach/sala', ['title' => 'Sala en vivo', 'nav' => 'sala', 'scripts' => ['assets/js/sala.js']]);
}

function coach_mi_calificacion(): void
{
    $u = require_perm('calif.propio');
    $min = (int) setting('calif_minimo', '5');
    $meses = qa("SELECT strftime('%Y-%m', created_at) AS mes, COUNT(*) AS n, ROUND(AVG(tecnica),1) AS tecnica, ROUND(AVG(atencion),1) AS atencion, ROUND(AVG(rutina),1) AS rutina
                 FROM calificaciones WHERE coach_id = ? GROUP BY mes ORDER BY mes DESC LIMIT 6", [$u['id']]);
    view('coach/mi_calificacion', ['title' => 'Mi calificación', 'nav' => 'micalif', 'meses' => $meses, 'min' => $min]);
}
