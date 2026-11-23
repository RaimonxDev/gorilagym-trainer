<?php
declare(strict_types=1);

require_once PRIVATE_DIR . '/app/cron_tasks.php';
require_once __DIR__ . '/alumno.php';

function admin_inicio(): void
{
    require_staff();
    if (!can_admin()) redirect(url('coach'));
    $hoy = date('Y-m-d');
    $kpi = [
        'alumnos_vigentes' => (int) qv("SELECT COUNT(DISTINCT user_id) FROM membresias WHERE fecha_inicio <= ? AND fecha_fin >= ? AND pausada = 0", [$hoy, $hoy]),
        'checkins_hoy' => (int) qv("SELECT COUNT(DISTINCT user_id) FROM checkins WHERE date(created_at) = ?", [$hoy]),
        'sesiones_hoy' => (int) qv("SELECT COUNT(*) FROM sesiones WHERE date(created_at) = ? AND estado != 'cancelada'", [$hoy]),
        'vencen_7' => (int) qv("SELECT COUNT(*) FROM membresias m WHERE m.fecha_fin BETWEEN ? AND ? AND NOT EXISTS (SELECT 1 FROM membresias m2 WHERE m2.user_id = m.user_id AND m2.fecha_fin > m.fecha_fin)", [$hoy, date('Y-m-d', strtotime('+7 days'))]),
        'sin_correo' => (int) qv("SELECT COUNT(*) FROM users WHERE rol = 'alumno' AND activo = 1 AND (email IS NULL OR email = '')"),
        'pausas_pend' => (int) qv("SELECT COUNT(*) FROM pausas WHERE estado = 'pendiente'"),
        'reportes_muro' => (int) qv('SELECT COUNT(*) FROM muro_reportes WHERE resuelto = 0'),
        'solicitudes' => (int) qv("SELECT COUNT(*) FROM solicitudes_eliminacion WHERE estado = 'pendiente'"),
        'mantencion' => (int) qv("SELECT COUNT(*) FROM maquinas WHERE estado = 'mantencion'"),
        'ejercicios' => (int) qv('SELECT COUNT(*) FROM ejercicios WHERE activo = 1'),
        'sin_video' => (int) qv("SELECT COUNT(*) FROM ejercicios WHERE activo = 1 AND (video IS NULL OR video = '')"),
        'cron' => setting('cron_ultimo'),
    ];
    view('admin/inicio', ['title' => 'Gestión', 'nav' => 'admin', 'k' => $kpi]);
}

// ================================================================ Usuarios
function admin_usuarios(): void
{
    require_perm('sistema.usuarios_staff');
    $rows = qa("SELECT * FROM users WHERE rol != 'alumno' ORDER BY activo DESC, CASE rol WHEN 'superadmin' THEN 0 WHEN 'jefe' THEN 1 ELSE 2 END, nombre");
    view('admin/usuarios', ['title' => 'Usuarios del staff', 'nav' => 'usuarios', 'rows' => $rows]);
}

function admin_usuario(): void
{
    $me = require_staff();
    $id = input_int('id');
    $u = $id ? q1('SELECT * FROM users WHERE id = ?', [$id]) : null;
    if ($id && !$u) not_found();
    $nuevoRol = $u['rol'] ?? (input('rol') ?: 'coach');
    $esAlumno = $nuevoRol === 'alumno';
    if ($esAlumno && !can('alumnos.editar')) deny();
    if (!$esAlumno && !can('sistema.usuarios_staff')) deny();
    $rolesPermitidos = $esAlumno ? ['alumno'] : ($me['rol'] === 'superadmin' ? ['superadmin', 'jefe', 'coach'] : ['jefe', 'coach']);
    if ($u && $u['rol'] === 'superadmin' && $me['rol'] !== 'superadmin') deny('Solo un super admin puede editar a otro super admin.');

    if (is_post()) {
        if (input('accion') === 'quitar_clave' && $u) {
            update('users', ['password_hash' => null, 'session_token' => bin2hex(random_bytes(16))], 'id = ?', [$u['id']]);
            q('DELETE FROM remember_tokens WHERE user_id = ?', [$u['id']]);
            audit('clave_eliminada', 'users', (int) $u['id']);
            flash('ok', 'Contraseña eliminada y sesiones cerradas.');
            redirect(url('admin/usuario', ['id' => $u['id']]));
        }
        $tipo = in_array(input('doc_tipo'), ['rut', 'pasaporte', 'dni', 'otro'], true) ? input('doc_tipo') : 'rut';
        $docRaw = trim((string) input('doc', ''));
        $err = [];
        if ($tipo === 'rut' && !rut_valid($docRaw)) $err[] = 'RUT inválido.';
        if ($docRaw === '') $err[] = 'El documento es obligatorio.';
        $doc = $docRaw !== '' ? doc_normalize($tipo, $docRaw) : '';
        $email = mb_strtolower(trim((string) input('email', '')));
        if ($email !== '' && !valid_email($email)) $err[] = 'Correo inválido.';
        if (!$esAlumno && $email === '') $err[] = 'El staff necesita correo para entrar.';
        $nombre = trim((string) input('nombre', ''));
        if ($nombre === '') $err[] = 'El nombre es obligatorio.';
        $rol = in_array(input('rol'), $rolesPermitidos, true) ? input('rol') : $rolesPermitidos[count($rolesPermitidos) - 1];
        $dup = q1('SELECT id FROM users WHERE doc_tipo = ? AND doc_num = ? AND id != ?', [$tipo, $doc, $u['id'] ?? 0]);
        if ($dup) $err[] = 'Ya existe un usuario con ese documento.';
        if ($u && (int) $u['id'] === (int) $me['id'] && $rol !== $me['rol']) $err[] = 'No puedes cambiar tu propio rol.';
        if ($u && $u['rol'] === 'superadmin' && ($rol !== 'superadmin' || !input('activo')) && (int) qv("SELECT COUNT(*) FROM users WHERE rol = 'superadmin' AND activo = 1") <= 1) $err[] = 'Debe quedar al menos un super admin activo.';
        if ($err) {
            flash('error', implode(' ', $err));
            redirect(url('admin/usuario', $u ? ['id' => $u['id']] : ['rol' => $nuevoRol]));
        }
        $data = ['rol' => $rol, 'doc_tipo' => $tipo, 'doc_num' => $doc, 'nombre' => mb_substr($nombre, 0, 80), 'apellido' => mb_substr(trim((string) input('apellido', '')), 0, 80),
            'email' => $email ?: null, 'telefono' => preg_replace('/[^0-9+ ]/', '', (string) input('telefono', '')) ?: null, 'activo' => input('activo') ? 1 : 0];
        if ($u) {
            $cambios = array_filter($data, fn($v, $k) => (string) $u[$k] !== (string) $v, ARRAY_FILTER_USE_BOTH);
            update('users', $data, 'id = ?', [$u['id']]);
            if (!$data['activo']) q('DELETE FROM remember_tokens WHERE user_id = ?', [$u['id']]);
            audit('usuario_editado', 'users', (int) $u['id'], $cambios);
            $uid2 = (int) $u['id'];
        } else {
            $data['sede_id'] = sede_id();
            $uid2 = insert('users', $data);
            audit('usuario_creado', 'users', $uid2, ['rol' => $rol]);
        }
        flash('ok', 'Usuario guardado.');
        redirect($esAlumno ? url('coach/alumno', ['id' => $uid2]) : url('admin/usuarios'));
    }
    view('admin/usuario', ['title' => $u ? nombre_completo($u) : ($esAlumno ? 'Nuevo alumno' : 'Nuevo usuario'), 'nav' => $esAlumno ? 'alumnos' : 'usuarios', 'u' => $u, 'roles' => $rolesPermitidos, 'rolDef' => $nuevoRol]);
}

// ================================================================ Permisos
function admin_permisos(): void
{
    $me = require_login();
    if ($me['rol'] !== 'superadmin') deny('Solo el super admin gestiona permisos.');
    $perms = qa('SELECT * FROM permissions ORDER BY orden');
    $roles = ['jefe', 'coach', 'alumno'];
    if (is_post()) {
        if (input('accion') === 'restaurar') {
            require_once PRIVATE_DIR . '/app/seed.php';
            seed_permissions(true);
            audit('permisos_restaurados');
            flash('ok', 'Permisos restaurados a los valores por defecto.');
            redirect(url('admin/permisos'));
        }
        $sel = (array) ($_POST['p'] ?? []);
        $antes = [];
        foreach (qa('SELECT rol, perm_key FROM role_permissions') as $r) $antes[$r['rol'] . '|' . $r['perm_key']] = true;
        $nuevos = [];
        $criticosNuevos = [];
        foreach ($perms as $p) {
            foreach ($roles as $rol) {
                $on = isset($sel[$rol][$p['key']]);
                if ($rol === 'alumno' && $p['ambito'] !== 'alumno') $on = false; // candado
                if ($rol !== 'alumno' && $p['ambito'] === 'alumno') $on = false;
                if ($on) {
                    $nuevos[$rol . '|' . $p['key']] = true;
                    if ($p['critico'] && !isset($antes[$rol . '|' . $p['key']])) $criticosNuevos[] = rol_label($rol) . ': ' . $p['nombre'];
                }
            }
        }
        if ($criticosNuevos && !input('confirmo_criticos')) {
            flash('error', 'Estás delegando permisos críticos (' . implode('; ', $criticosNuevos) . '). Marca la casilla de confirmación para guardar.');
            redirect(url('admin/permisos'));
        }
        tx(function () use ($nuevos) {
            q('DELETE FROM role_permissions');
            foreach (array_keys($nuevos) as $k) {
                [$rol, $key] = explode('|', $k, 2);
                insert('role_permissions', ['rol' => $rol, 'perm_key' => $key]);
            }
        });
        $agregados = array_keys(array_diff_key($nuevos, $antes));
        $quitados = array_keys(array_diff_key($antes, $nuevos));
        if ($agregados || $quitados) {
            audit('permisos_cambiados', 'permissions', null, ['activados' => $agregados, 'desactivados' => $quitados]);
            setting_set('permissions_version', (string) microtime(true));
        }
        flash('ok', 'Permisos guardados. Aplican de inmediato.');
        redirect(url('admin/permisos'));
    }
    $act = [];
    foreach (qa('SELECT rol, perm_key FROM role_permissions') as $r) $act[$r['rol']][$r['perm_key']] = true;
    view('admin/permisos', ['title' => 'Permisos', 'nav' => 'permisos', 'perms' => $perms, 'roles' => $roles, 'act' => $act, 'scripts' => ['assets/js/admin.js']]);
}

// ================================================================ Planes
function admin_planes(): void
{
    require_perm('planes.gestionar');
    $rows = qa('SELECT p.*, (SELECT COUNT(DISTINCT m.user_id) FROM membresias m WHERE m.plan_id = p.id AND m.fecha_fin >= ? AND m.fecha_inicio <= ?) AS vigentes FROM planes p ORDER BY p.activo DESC, p.orden', [date('Y-m-d'), date('Y-m-d')]);
    foreach ($rows as &$r) $r['horario'] = plan_horario_texto((int) $r['id']);
    view('admin/planes', ['title' => 'Planes', 'nav' => 'planes', 'rows' => $rows, 'pase' => setting('pase_diario_precio')]);
}

function admin_plan(): void
{
    require_perm('planes.gestionar');
    $id = input_int('id');
    $p = $id ? q1('SELECT * FROM planes WHERE id = ?', [$id]) : null;
    if ($id && !$p) not_found();
    if (is_post()) {
        if (input('accion') === 'pase') {
            setting_set('pase_diario_precio', preg_replace('/\D/', '', (string) input('pase_diario_precio', '')));
            flash('ok', 'Precio del Pase Diario guardado.');
            redirect(url('admin/planes'));
        }
        $money = fn($k) => ($v = preg_replace('/\D/', '', (string) input($k, ''))) === '' ? null : (int) $v;
        $nombre = trim((string) input('nombre', ''));
        if ($nombre === '') {
            flash('error', 'El nombre es obligatorio.');
            redirect(url('admin/plan', $id ? ['id' => $id] : []));
        }
        $mods = array_intersect((array) ($_POST['beneficio_modalidades'] ?? []), ['mensual', 'trimestral', 'semestral', 'anual']);
        $data = ['nombre' => mb_substr($nombre, 0, 60), 'tipo' => input('tipo') === 'libre' ? 'libre' : 'coach', 'descripcion' => mb_substr((string) input('descripcion', ''), 0, 300),
            'requiere_estudiante' => input('requiere_estudiante') ? 1 : 0, 'precio_mensual' => $money('precio_mensual'), 'precio_trimestral' => $money('precio_trimestral'),
            'precio_semestral' => $money('precio_semestral'), 'precio_anual' => $money('precio_anual'), 'beneficio' => mb_substr((string) input('beneficio', ''), 0, 100) ?: null,
            'beneficio_modalidades' => $mods ? implode(',', $mods) : null, 'activo' => input('activo') ? 1 : 0, 'orden' => (int) input('orden', 0)];
        $horas = [];
        for ($d = 1; $d <= 7; $d++) {
            $a = (string) ($_POST['h'][$d]['ini'] ?? '');
            $b = (string) ($_POST['h'][$d]['fin'] ?? '');
            if (preg_match('/^\d{2}:\d{2}$/', $a) && preg_match('/^\d{2}:\d{2}$/', $b) && $a < $b) $horas[] = [$d, $a, $b];
        }
        $pid = tx(function () use ($p, $data, $horas) {
            if ($p) {
                update('planes', $data, 'id = ?', [$p['id']]);
                $pid = (int) $p['id'];
            } else $pid = insert('planes', $data);
            q('DELETE FROM plan_horarios WHERE plan_id = ?', [$pid]);
            foreach ($horas as [$d, $a, $b]) insert('plan_horarios', ['plan_id' => $pid, 'dia' => $d, 'hora_inicio' => $a, 'hora_fin' => $b]);
            return $pid;
        });
        audit($p ? 'plan_editado' : 'plan_creado', 'planes', $pid, $data);
        flash('ok', 'Plan guardado.');
        redirect(url('admin/planes'));
    }
    $h = [];
    if ($p) foreach (plan_horarios((int) $p['id']) as $r) $h[(int) $r['dia']] = $r;
    view('admin/plan', ['title' => $p ? 'Plan ' . $p['nombre'] : 'Nuevo plan', 'nav' => 'planes', 'p' => $p, 'h' => $h]);
}

function admin_membresia(): void
{
    $me = require_staff();
    if (!can_any(['planes.gestionar', 'planes.pausas', 'planes.acreditar'])) deny();
    $a = q1("SELECT * FROM users WHERE id = ? AND rol = 'alumno'", [input_int('alumno', 0)]);
    if (!$a) not_found();
    if (is_post()) {
        $acc = input('accion');
        if (in_array($acc, ['crear', 'editar', 'borrar', 'regalo'], true)) require_perm('planes.gestionar');
        if ($acc === 'crear' || $acc === 'editar') {
            $plan = q1('SELECT * FROM planes WHERE id = ?', [input_int('plan_id', 0)]);
            $mod = in_array(input('modalidad'), ['mensual', 'trimestral', 'semestral', 'anual'], true) ? input('modalidad') : 'mensual';
            $fi = parse_date_input(input('fecha_inicio'));
            $ff = parse_date_input(input('fecha_fin'));
            if (!$plan || !$fi) {
                flash('error', 'Elige plan y fecha de inicio.');
                redirect(url('admin/membresia', ['alumno' => $a['id']]));
            }
            if (!$ff) $ff = date('Y-m-d', strtotime($fi . ' +' . ['mensual' => '1 month', 'trimestral' => '3 months', 'semestral' => '6 months', 'anual' => '12 months'][$mod] . ' -1 day'));
            if ($ff < $fi) {
                flash('error', 'La fecha de término no puede ser anterior al inicio.');
                redirect(url('admin/membresia', ['alumno' => $a['id']]));
            }
            $data = ['plan_id' => $plan['id'], 'modalidad' => $mod, 'fecha_inicio' => $fi, 'fecha_fin' => $ff, 'notas' => mb_substr((string) input('notas', ''), 0, 300) ?: null];
            if ($acc === 'editar') {
                $m = q1('SELECT * FROM membresias WHERE id = ? AND user_id = ?', [input_int('id', 0), $a['id']]);
                if (!$m) not_found();
                update('membresias', $data, 'id = ?', [$m['id']]);
                audit('membresia_editada', 'membresias', (int) $m['id'], ['antes' => $m, 'despues' => $data]);
            } else {
                $mid = insert('membresias', $data + ['user_id' => $a['id'], 'origen' => 'manual']);
                audit('membresia_creada', 'membresias', $mid, $data);
            }
            flash('ok', 'Membresía guardada.');
        } elseif ($acc === 'borrar') {
            $m = q1('SELECT * FROM membresias WHERE id = ? AND user_id = ?', [input_int('id', 0), $a['id']]);
            if ($m) {
                q('DELETE FROM pausas WHERE membresia_id = ?', [$m['id']]);
                q('DELETE FROM membresias WHERE id = ?', [$m['id']]);
                audit('membresia_borrada', 'membresias', (int) $m['id'], $m);
            }
            flash('ok', 'Membresía eliminada.');
        } elseif ($acc === 'regalo') {
            $m = q1('SELECT * FROM membresias WHERE id = ? AND user_id = ?', [input_int('id', 0), $a['id']]);
            if ($m) update('membresias', ['regalo_entregado_at' => $m['regalo_entregado_at'] ? null : date('Y-m-d H:i:s')], 'id = ?', [$m['id']]);
            audit('regalo_entregado', 'membresias', (int) ($m['id'] ?? 0));
        } elseif ($acc === 'pausa') {
            require_perm('planes.pausas');
            $m = membresia_actual((int) $a['id']);
            $fInc = parse_date_input(input('fecha_incidente'));
            $desc = trim((string) input('descripcion', ''));
            if (!$m || !$fInc || $desc === '' || !input('en_recinto')) {
                flash('error', 'Completa la fecha, la descripción y confirma que la lesión ocurrió dentro del recinto.');
            } else {
                $pid = insert('pausas', ['membresia_id' => $m['id'], 'fecha_incidente' => $fInc, 'descripcion' => mb_substr($desc, 0, 1000),
                    'desde' => parse_date_input(input('desde')) ?? date('Y-m-d'), 'hasta' => parse_date_input(input('hasta')), 'registrado_por' => $me['id']]);
                audit('pausa_registrada', 'pausas', $pid);
                flash('ok', 'Pausa registrada. Queda pendiente de aprobación.');
            }
        } elseif ($acc === 'acreditar') {
            require_perm('planes.acreditar');
            $doc = trim((string) input('documento', ''));
            $vence = parse_date_input(input('vence'));
            if ($doc === '' || !$vence) flash('error', 'Indica el documento revisado y hasta cuándo es válido.');
            else {
                insert('acreditaciones', ['user_id' => $a['id'], 'documento' => mb_substr($doc, 0, 150), 'validado_por' => $me['id'], 'fecha_validacion' => date('Y-m-d'), 'vence' => $vence]);
                audit('estudiante_acreditado', 'users', (int) $a['id'], ['vence' => $vence]);
                flash('ok', 'Condición de estudiante acreditada hasta el ' . fecha($vence) . '.');
            }
        }
        redirect(url('admin/membresia', ['alumno' => $a['id']]));
    }
    view('admin/membresia', ['title' => 'Plan de ' . $a['nombre'], 'nav' => 'alumnos', 'a' => $a,
        'rows' => qa('SELECT m.*, p.nombre AS plan, p.beneficio, p.beneficio_modalidades FROM membresias m JOIN planes p ON p.id = m.plan_id WHERE m.user_id = ? ORDER BY m.fecha_fin DESC', [$a['id']]),
        'planes' => qa('SELECT * FROM planes WHERE activo = 1 ORDER BY orden'), 'actual' => membresia_actual((int) $a['id']),
        'acred' => qa('SELECT ac.*, u.nombre AS validador FROM acreditaciones ac LEFT JOIN users u ON u.id = ac.validado_por WHERE ac.user_id = ? ORDER BY ac.vence DESC', [$a['id']]),
        'pausas' => qa('SELECT p.* FROM pausas p JOIN membresias m ON m.id = p.membresia_id WHERE m.user_id = ? ORDER BY p.id DESC', [$a['id']]),
        'editar' => input_int('editar') ? q1('SELECT * FROM membresias WHERE id = ? AND user_id = ?', [input_int('editar'), $a['id']]) : null]);
}

function admin_pausas(): void
{
    $me = require_perm('planes.pausas');
    if (is_post()) {
        $p = q1('SELECT * FROM pausas WHERE id = ?', [input_int('id', 0)]);
        if (!$p) not_found();
        $acc = input('accion');
        if ($acc === 'aprobar' && $p['estado'] === 'pendiente') {
            tx(function () use ($p, $me) {
                update('pausas', ['estado' => 'activa', 'aprobado_por' => $me['id']], 'id = ?', [$p['id']]);
                update('membresias', ['pausada' => 1], 'id = ?', [$p['membresia_id']]);
            });
            audit('pausa_aprobada', 'pausas', (int) $p['id']);
            flash('ok', 'Pausa aprobada. El plan queda congelado hasta que se cierre.');
        } elseif ($acc === 'rechazar' && $p['estado'] === 'pendiente') {
            update('pausas', ['estado' => 'rechazada', 'aprobado_por' => $me['id']], 'id = ?', [$p['id']]);
            audit('pausa_rechazada', 'pausas', (int) $p['id']);
            flash('ok', 'Pausa rechazada.');
        } elseif ($acc === 'cerrar' && $p['estado'] === 'activa') {
            pausa_cerrar((int) $p['id']);
            flash('ok', 'Pausa cerrada. El vencimiento se extendió por los días pausados.');
        }
        redirect(url('admin/pausas'));
    }
    $rows = qa("SELECT p.*, u.id AS alumno_id, u.nombre, u.apellido, pl.nombre AS plan, r.nombre AS registrado, a.nombre AS aprobado
                FROM pausas p JOIN membresias m ON m.id = p.membresia_id JOIN users u ON u.id = m.user_id JOIN planes pl ON pl.id = m.plan_id
                LEFT JOIN users r ON r.id = p.registrado_por LEFT JOIN users a ON a.id = p.aprobado_por
                ORDER BY CASE p.estado WHEN 'pendiente' THEN 0 WHEN 'activa' THEN 1 ELSE 2 END, p.id DESC LIMIT 100");
    view('admin/pausas', ['title' => 'Pausas por lesión', 'nav' => 'pausas', 'rows' => $rows]);
}

function admin_acreditar(): void
{
    redirect(url('admin/membresia', ['alumno' => input_int('alumno', 0)]));
}

// ================================================================ Importación
function admin_importar(): void
{
    $me = require_perm('planes.importar');
    require_once PRIVATE_DIR . '/app/import.php';
    $dir = PRIVATE_DIR . '/storage/imports';
    $paso = 'subir';
    $data = [];
    if (is_post()) {
        $acc = input('accion');
        try {
            if ($acc === 'subir') {
                if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Sube un archivo .xlsx o .csv.');
                $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['xlsx', 'csv', 'txt'], true)) throw new RuntimeException('Formato no soportado. Usa .xlsx o .csv (si es .xls antiguo, guárdalo como .xlsx).');
                if ($_FILES['archivo']['size'] > 10 * 1048576) throw new RuntimeException('Archivo muy grande (máx 10 MB).');
                $name = date('Ymd-His') . '-' . token(6) . '.' . ($ext === 'txt' ? 'csv' : $ext);
                move_uploaded_file($_FILES['archivo']['tmp_name'], $dir . '/' . $name);
                $_SESSION['import_file'] = $name;
                $_SESSION['import_orig'] = mb_substr(basename($_FILES['archivo']['name']), 0, 120);
                redirect(url('admin/importar', ['paso' => 'mapear']));
            }
            $file = $_SESSION['import_file'] ?? '';
            if (!$file || !is_file($dir . '/' . basename($file))) throw new RuntimeException('Vuelve a subir el archivo.');
            $rows = sheet_read($dir . '/' . basename($file), pathinfo($file, PATHINFO_EXTENSION));
            $map = array_filter((array) ($_POST['map'] ?? []), fn($v) => $v !== '');
            $_SESSION['import_map'] = $map;
            $hasHeader = (bool) input('encabezado');
            $_SESSION['import_header'] = $hasHeader;
            if (!isset($map['doc']) || (!isset($map['nombre']) && !isset($map['nombre_completo']))) throw new RuntimeException('Debes indicar al menos la columna del documento y la del nombre.');
            if ($acc === 'validar') {
                $res = import_process($rows, $map, $hasHeader, false, (int) $me['id']);
                $_SESSION['import_res'] = $res;
                redirect(url('admin/importar', ['paso' => 'revisar']));
            }
            if ($acc === 'aplicar') {
                $res = tx(fn() => import_process($rows, $map, $hasHeader, true, (int) $me['id']));
                insert('imports', ['archivo' => $_SESSION['import_orig'] ?? $file, 'filas' => $res['total'], 'creados' => $res['creados'], 'actualizados' => $res['actualizados'],
                    'errores' => json_encode(array_merge($res['errores'], $res['duplicados']), JSON_UNESCAPED_UNICODE), 'creado_por' => $me['id']]);
                audit('importacion', 'imports', null, ['creados' => $res['creados'], 'actualizados' => $res['actualizados']]);
                setting_set('import_map_ultimo', json_encode($map));
                unset($_SESSION['import_file'], $_SESSION['import_res']);
                flash('ok', "Importación aplicada: {$res['creados']} alumnos nuevos, {$res['actualizados']} actualizados." . ($res['errores'] ? ' Revisa las advertencias en el historial.' : ''));
                redirect(url('admin/importar'));
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect(url('admin/importar', ['paso' => input('accion') === 'subir' ? 'subir' : 'mapear']));
        }
    }
    $paso = in_array(input('paso'), ['mapear', 'revisar'], true) ? input('paso') : 'subir';
    if ($paso !== 'subir') {
        $file = $_SESSION['import_file'] ?? '';
        if (!$file || !is_file($dir . '/' . basename($file))) redirect(url('admin/importar'));
        try {
            $rows = sheet_read($dir . '/' . basename($file), pathinfo($file, PATHINFO_EXTENSION));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect(url('admin/importar'));
        }
        $headers = $rows[0] ?? [];
        $guess = $_SESSION['import_map'] ?? (json_decode((string) setting('import_map_ultimo', ''), true) ?: import_guess_map($headers));
        $data = ['headers' => $headers, 'muestra' => array_slice($rows, 1, 5), 'total' => max(0, count($rows) - 1), 'map' => $guess, 'campos' => import_fields(),
            'res' => $_SESSION['import_res'] ?? null, 'header' => $_SESSION['import_header'] ?? true, 'archivo' => $_SESSION['import_orig'] ?? ''];
    }
    view('admin/importar', ['title' => 'Importar Excel', 'nav' => 'importar', 'paso' => $paso, 'd' => $data,
        'hist' => qa('SELECT i.*, u.nombre AS por FROM imports i LEFT JOIN users u ON u.id = i.creado_por ORDER BY i.id DESC LIMIT 10')]);
}

// ================================================================ Avisos
function admin_avisos(): void
{
    $me = require_perm('avisos.publicar');
    if (is_post()) {
        if (input('accion') === 'borrar') {
            q('DELETE FROM avisos WHERE id = ?', [input_int('id', 0)]);
            flash('ok', 'Aviso eliminado.');
            redirect(url('admin/avisos'));
        }
        $t = trim((string) input('titulo', ''));
        if ($t === '') {
            flash('error', 'El título es obligatorio.');
            redirect(url('admin/avisos'));
        }
        $desde = input('desde') ? date('Y-m-d H:i:s', strtotime((string) input('desde'))) : date('Y-m-d H:i:s');
        $hasta = input('hasta') ? date('Y-m-d H:i:s', strtotime((string) input('hasta'))) : null;
        $id = insert('avisos', ['titulo' => mb_substr($t, 0, 120), 'cuerpo' => mb_substr((string) input('cuerpo', ''), 0, 500), 'desde' => $desde, 'hasta' => $hasta, 'push' => input('push') ? 1 : 0, 'creado_por' => $me['id']]);
        if (input('push')) {
            $at = max(time(), strtotime($desde));
            foreach (qa("SELECT DISTINCT user_id FROM push_subs ps JOIN users u ON u.id = ps.user_id WHERE u.activo = 1") as $r) {
                notificar((int) $r['user_id'], 'aviso', $t, (string) input('cuerpo', ''), url('notificaciones'), $at, 'aviso:' . $id);
            }
        }
        audit('aviso_publicado', 'avisos', $id, $t);
        flash('ok', 'Aviso publicado.');
        redirect(url('admin/avisos'));
    }
    view('admin/avisos', ['title' => 'Avisos', 'nav' => 'avisos', 'rows' => qa('SELECT a.*, u.nombre AS autor FROM avisos a LEFT JOIN users u ON u.id = a.creado_por ORDER BY a.id DESC LIMIT 50'),
        'subs' => (int) qv('SELECT COUNT(DISTINCT user_id) FROM push_subs')]);
}

// ================================================================ Marca y ajustes
function admin_marca(): void
{
    require_perm('sistema.config');
    if (is_post()) {
        $acc = input('accion');
        try {
            if ($acc === 'marca') {
                foreach (['brand_primary', 'brand_hover', 'brand_dark', 'brand_light', 'brand_accent', 'brand_danger', 'brand_warning'] as $k) {
                    $v = (string) input($k, '');
                    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $v)) setting_set($k, strtoupper($v));
                }
                foreach (['brand_nombre' => 60, 'brand_bienvenida' => 80, 'brand_tagline' => 120] as $k => $len) setting_set($k, mb_substr(trim((string) input($k, '')), 0, $len));
                $ig = ltrim(trim((string) input('brand_instagram', '')), '@');
                if ($ig !== '' && !preg_match('/^[A-Za-z0-9._]{1,30}$/', $ig)) throw new RuntimeException('Usuario de Instagram inválido.');
                setting_set('brand_instagram', $ig);
                foreach (['brand_logo', 'brand_logo_claro'] as $k) {
                    $f = handle_upload($k, 'branding', IMG_TYPES, 4 * 1048576);
                    if ($f) {
                        delete_media(setting($k));
                        setting_set($k, $f);
                    }
                    if (input('quitar_' . $k)) {
                        delete_media(setting($k));
                        setting_set($k, '');
                    }
                }
            } elseif ($acc === 'sede') {
                $lat = input('lat') !== '' ? (float) str_replace(',', '.', (string) input('lat')) : null;
                $lng = input('lng') !== '' ? (float) str_replace(',', '.', (string) input('lng')) : null;
                if (($lat !== null && ($lat < -90 || $lat > 90)) || ($lng !== null && ($lng < -180 || $lng > 180))) throw new RuntimeException('Coordenadas inválidas.');
                update('sedes', ['nombre' => mb_substr((string) input('nombre', ''), 0, 80), 'direccion' => mb_substr((string) input('direccion', ''), 0, 150), 'lat' => $lat, 'lng' => $lng,
                    'radio_m' => max(30, min(1000, (int) input('radio_m', 100)))], 'id = ?', [sede_id()]);
            } elseif ($acc === 'ajustes') {
                $ints = ['sesion_max_min' => [30, 240], 'plantilla_objetivo_min' => [20, 180], 'transicion_seg' => [0, 600], 'calif_minimo' => [1, 50], 'alerta_inactivo_dias' => [3, 90],
                    'alerta_estancado_sesiones' => [2, 12], 'alerta_vence_dias' => [1, 60], 'video_max_mb' => [2, 200], 'foto_max_mb' => [1, 30], 'otp_ttl_seg' => [120, 1800], 'remember_days' => [1, 365]];
                foreach ($ints as $k => [$mn, $mx]) setting_set($k, (string) max($mn, min($mx, (int) input($k, setting($k)))));
                setting_set('muro_activo', input('muro_activo') ? '1' : '0');
                setting_set('app_plan_libre', input('app_plan_libre') === 'bloqueado' ? 'bloqueado' : 'limitado');
                setting_set('sesion_unica_alumnos', input('sesion_unica_alumnos') ? '1' : '0');
                setting_set('sesion_unica_staff', input('sesion_unica_staff') ? '1' : '0');
                setting_set('reporte_mensual_activo', input('reporte_mensual_activo') ? '1' : '0');
            } elseif ($acc === 'spotify') {
                require_once PRIVATE_DIR . '/app/musica.php';
                if (input('quitar')) {
                    foreach (['spotify_client_id', 'spotify_client_secret', 'spotify_token', 'spotify_token_exp', 'spotify_error_at'] as $k) setting_set($k, '');
                    audit('spotify_quitado');
                    flash('ok', 'Credenciales eliminadas. El buscador usa el catálogo de respaldo.');
                    redirect(url('admin/marca', ['tab' => 'musica']));
                }
                $id = trim((string) input('spotify_client_id', ''));
                $sec = trim((string) input('spotify_client_secret', ''));
                if (!preg_match('/^[A-Za-z0-9]{20,64}$/', $id)) throw new RuntimeException('El Client ID no tiene el formato esperado.');
                if ($sec !== '' && !preg_match('/^[A-Za-z0-9]{20,64}$/', $sec)) throw new RuntimeException('El Client secret no tiene el formato esperado.');
                setting_set('spotify_client_id', $id);
                if ($sec !== '') setting_set('spotify_client_secret', $sec);
                if (!setting('spotify_client_secret') && $sec === '') throw new RuntimeException('Falta el Client secret.');
                foreach (['spotify_token', 'spotify_token_exp', 'spotify_error_at'] as $k) setting_set($k, '');
                q('DELETE FROM musica_cache');
                $prueba = buscar_spotify('gorillaz');
                audit('spotify_configurado');
                flash($prueba ? 'ok' : 'warning', $prueba ? 'Spotify conectado: la búsqueda de prueba devolvió ' . count($prueba) . ' canciones.' : 'Guardado, pero Spotify rechazó la prueba (revisa las credenciales o que la cuenta sea Premium). Mientras tanto se usa el catálogo de respaldo.');
                redirect(url('admin/marca', ['tab' => 'musica']));
            } elseif ($acc === 'demo_crear' || $acc === 'demo_eliminar') {
                if (current_user()['rol'] !== 'superadmin') deny('Solo el super admin gestiona los datos de prueba.');
                require_once PRIVATE_DIR . '/app/demo.php';
                if ($acc === 'demo_crear') {
                    $c = demo_crear();
                    $_SESSION['demo_claves'] = $c;
                    flash('ok', 'Datos de prueba creados. Anota las contraseñas: se muestran una sola vez.');
                } else {
                    demo_eliminar();
                    flash('ok', 'Datos de prueba eliminados (usuarios, su historial y el catálogo de ejemplo que no se esté usando).');
                }
                redirect(url('admin/marca', ['tab' => 'pruebas']));
            } elseif ($acc === 'muro') {
                $lineas = array_values(array_unique(array_filter(array_map(fn($l) => mb_substr(trim($l), 0, 60), explode("\n", (string) input('muro_estados', ''))), fn($l) => $l !== '')));
                if (count($lineas) < 2) throw new RuntimeException('Deja al menos 2 estados.');
                setting_set('muro_estados', implode("\n", array_slice($lineas, 0, 40)));
                setting_set('muro_posts_dia', (string) max(1, min(10, (int) input('muro_posts_dia', 1))));
            } elseif ($acc === 'cuestionario') {
                setting_set('cuestionario_salud', mb_substr((string) input('cuestionario_salud', ''), 0, 4000));
            } elseif ($acc === 'entrada_token') {
                setting_set('entrada_token', token(10));
                flash('warning', 'Nuevo código de entrada generado: reimprime el QR y vuelve a grabar el tag NFC.');
            } elseif ($acc === 'probar_correo') {
                $ok = send_mail((string) current_user()['email'], 'Prueba de correo · ' . setting('brand_nombre'), '<p>Si lees esto, el envío de correos funciona. Revisa que no haya llegado a spam.</p>');
                flash($ok ? 'ok' : 'error', $ok ? 'Correo de prueba enviado a ' . current_user()['email'] . '.' : 'El servidor no pudo enviar el correo. Revisa la configuración de mail() del hosting.');
                redirect(url('admin/marca', ['tab' => 'sistema']));
            }
            audit('ajustes_' . $acc);
            if ($acc !== 'entrada_token') flash('ok', 'Guardado.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        $tabDe = ['marca' => 'marca', 'sede' => 'sede', 'ajustes' => 'general', 'muro' => 'muro', 'cuestionario' => 'salud', 'entrada_token' => 'entrada'];
        redirect(url('admin/marca', ['tab' => $tabDe[$acc] ?? 'marca']));
    }
    view('admin/marca', ['title' => 'Marca y ajustes', 'nav' => 'marca', 'sede' => sede(), 'cron' => setting('cron_ultimo'), 'tab' => (string) input('tab', 'marca'),
        'demo' => (function () {
            require_once PRIVATE_DIR . '/app/demo.php';
            return demo_estado();
        })(),
        'demoClaves' => (function () {
            if (input('tab') !== 'pruebas') return null;
            $c = $_SESSION['demo_claves'] ?? null;
            unset($_SESSION['demo_claves']);
            return $c;
        })(),
        'spotifyOk' => setting('spotify_client_id', '') !== '' && setting('spotify_client_secret', '') !== '' && (int) setting('spotify_error_at', '0') < time() - 600,
        'preg' => (function () {
            require_once PRIVATE_DIR . '/app/controllers/alumno.php';
            return implode("\n", cuestionario_preguntas());
        })(),
        'scripts' => ['assets/js/vendor/qrcode.js', 'assets/js/qr.js', 'assets/js/admin.js']]);
}

// ================================================================ Reportes
function admin_reportes(): void
{
    require_staff();
    if (!can_any(['reportes.gimnasio', 'reportes.adopcion'])) deny();
    $dias = in_array(input_int('dias', 30), [7, 30, 90, 365], true) ? input_int('dias', 30) : 30;
    $desde = date('Y-m-d', strtotime("-$dias days"));
    $r = [];
    if (can('reportes.gimnasio')) {
        $r['asistencia_dia'] = qa("SELECT date(created_at) AS d, COUNT(DISTINCT user_id) AS n FROM checkins WHERE date(created_at) >= ? GROUP BY d ORDER BY d", [$desde]);
        $heat = qa("SELECT CAST(strftime('%w', created_at) AS INTEGER) AS dow, CAST(strftime('%H', created_at) AS INTEGER) AS h, COUNT(*) AS n FROM checkins WHERE date(created_at) >= ? GROUP BY dow, h", [$desde]);
        $r['heat'] = $heat;
        $r['maquinas'] = qa("SELECT m.nombre, COUNT(*) AS usos, ROUND(SUM(MIN(COALESCE(o.liberada_at, o.hasta_est), o.hasta_est + 1800) - o.desde) / 60.0) AS minutos
                             FROM ocupacion o JOIN maquinas m ON m.id = o.maquina_id WHERE o.desde >= ? GROUP BY m.id ORDER BY minutos DESC LIMIT 15", [strtotime($desde)]);
        $hoy = date('Y-m-d');
        $r['retencion'] = [
            'vigentes' => (int) qv("SELECT COUNT(DISTINCT user_id) FROM membresias WHERE fecha_inicio <= ? AND fecha_fin >= ?", [$hoy, $hoy]),
            'vencidos_30' => (int) qv("SELECT COUNT(DISTINCT m.user_id) FROM membresias m WHERE m.fecha_fin BETWEEN ? AND ? AND NOT EXISTS (SELECT 1 FROM membresias m2 WHERE m2.user_id = m.user_id AND m2.fecha_fin >= ?)", [date('Y-m-d', strtotime('-30 days')), $hoy, $hoy]),
            'renovaron_30' => (int) qv("SELECT COUNT(DISTINCT m.user_id) FROM membresias m WHERE m.fecha_inicio BETWEEN ? AND ? AND EXISTS (SELECT 1 FROM membresias m2 WHERE m2.user_id = m.user_id AND m2.id != m.id AND m2.fecha_fin < m.fecha_inicio)", [date('Y-m-d', strtotime('-30 days')), $hoy]),
            'en_riesgo' => count(alertas_staff(500)['inactivos']),
        ];
        $r['coaches'] = qa("SELECT u.id, u.nombre, u.apellido, COUNT(s.id) AS sesiones, COUNT(DISTINCT s.alumno_id) AS alumnos,
                            ROUND(AVG(CASE WHEN s.fin IS NOT NULL AND s.inicio IS NOT NULL THEN (julianday(s.fin) - julianday(s.inicio)) * 1440 END)) AS dur_real, ROUND(AVG(s.est_min)) AS dur_est
                            FROM users u LEFT JOIN sesiones s ON s.coach_id = u.id AND date(s.created_at) >= ? AND s.estado = 'finalizada'
                            WHERE u.rol IN ('coach','jefe') AND u.activo = 1 GROUP BY u.id ORDER BY sesiones DESC", [$desde]);
        $r['duracion'] = q1("SELECT ROUND(AVG((julianday(fin) - julianday(inicio)) * 1440)) AS real, ROUND(AVG(est_min)) AS est, SUM(CASE WHEN (julianday(fin) - julianday(inicio)) * 1440 > ? THEN 1 ELSE 0 END) AS pasadas, COUNT(*) AS n
                            FROM sesiones WHERE estado = 'finalizada' AND modo = 'coach' AND inicio IS NOT NULL AND fin IS NOT NULL AND date(created_at) >= ?", [(int) setting('sesion_max_min', '90'), $desde]);
        $r['planes'] = qa("SELECT p.nombre, COUNT(DISTINCT m.user_id) AS n FROM planes p LEFT JOIN membresias m ON m.plan_id = p.id AND m.fecha_inicio <= ? AND m.fecha_fin >= ? GROUP BY p.id ORDER BY p.orden", [$hoy, $hoy]);
    }
    if (can('reportes.adopcion')) {
        $r['adopcion'] = qa("SELECT u.id, u.nombre, u.apellido,
            (SELECT COUNT(*) FROM sesiones s WHERE s.coach_id = u.id AND date(s.created_at) >= ? AND (SELECT COUNT(*) FROM sesion_items si WHERE si.sesion_id = s.id) > 0) AS rutinas,
            (SELECT COUNT(*) FROM turnos t WHERE t.user_id = u.id) AS tiene_turno
            FROM users u WHERE u.rol IN ('coach','jefe') AND u.activo = 1 ORDER BY rutinas DESC", [$desde]);
        $r['visitas_coach'] = (int) qv("SELECT COUNT(DISTINCT c.user_id || date(c.created_at)) FROM checkins c JOIN membresias m ON m.user_id = c.user_id AND date(c.created_at) BETWEEN m.fecha_inicio AND m.fecha_fin JOIN planes p ON p.id = m.plan_id AND p.tipo = 'coach' WHERE date(c.created_at) >= ?", [$desde]);
        $r['visitas_con_rutina'] = (int) qv("SELECT COUNT(*) FROM sesiones WHERE modo = 'coach' AND estado IN ('en_curso','finalizada') AND date(created_at) >= ?", [$desde]);
    }
    view('admin/reportes', ['title' => 'Reportes', 'nav' => 'reportes', 'r' => $r, 'dias' => $dias, 'scripts' => ['assets/js/charts.js']]);
}

function admin_calificaciones(): void
{
    require_staff();
    if (!can_any(['calif.promedios', 'calif.detalle'])) deny();
    $min = (int) setting('calif_minimo', '5');
    $prom = qa("SELECT c.coach_id, u.nombre, u.apellido, strftime('%Y-%m', c.created_at) AS mes, COUNT(*) AS n, ROUND(AVG(c.tecnica),1) AS tecnica, ROUND(AVG(c.atencion),1) AS atencion, ROUND(AVG(c.rutina),1) AS rutina
                FROM calificaciones c JOIN users u ON u.id = c.coach_id WHERE c.created_at >= datetime('now','localtime','-6 months') GROUP BY c.coach_id, mes ORDER BY mes DESC, u.nombre");
    $det = can('calif.detalle') ? qa('SELECT c.*, u.nombre AS coach, a.nombre AS alumno, a.apellido AS alumno_ap FROM calificaciones c JOIN users u ON u.id = c.coach_id JOIN users a ON a.id = c.alumno_id ORDER BY c.id DESC LIMIT 100') : [];
    view('admin/calificaciones', ['title' => 'Calificaciones', 'nav' => 'calificaciones', 'prom' => $prom, 'det' => $det, 'min' => $min]);
}

function admin_auditoria(): void
{
    require_perm('sistema.auditoria');
    $pag = max(1, input_int('p', 1));
    $f = trim((string) input('q', ''));
    $w = '1=1';
    $p = [];
    if ($f !== '') {
        $w = '(a.accion LIKE ? OR a.detalle LIKE ? OR u.nombre LIKE ?)';
        $p = ['%' . $f . '%', '%' . $f . '%', '%' . $f . '%'];
    }
    $rows = qa("SELECT a.*, u.nombre, u.apellido, u.rol FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE $w ORDER BY a.id DESC LIMIT 50 OFFSET " . (($pag - 1) * 50), $p);
    view('admin/auditoria', ['title' => 'Auditoría', 'nav' => 'auditoria', 'rows' => $rows, 'pag' => $pag, 'q' => $f]);
}

function admin_respaldos(): void
{
    require_perm('sistema.respaldos');
    if (is_post()) {
        if (input('accion') === 'crear') {
            $f = backup_crear('manual');
            audit('respaldo_creado', null, null, $f);
            flash($f ? 'ok' : 'error', $f ? 'Respaldo creado.' : 'No se pudo crear el respaldo.');
        } elseif (input('accion') === 'borrar') {
            $f = basename((string) input('f', ''));
            if (preg_match('/^gorila-[\w\-]+\.sqlite$/', $f)) @unlink(backup_dir() . '/' . $f);
            audit('respaldo_borrado', null, null, $f);
        }
        redirect(url('admin/respaldos'));
    }
    if ($dl = input('descargar')) {
        $f = basename((string) $dl);
        $path = backup_dir() . '/' . $f;
        if (!preg_match('/^gorila-[\w\-]+\.sqlite$/', $f) || !is_file($path)) not_found();
        audit('respaldo_descargado', null, null, $f);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $f . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    $files = glob(backup_dir() . '/gorila-*.sqlite') ?: [];
    rsort($files);
    $rows = array_map(fn($f) => ['f' => basename($f), 'size' => filesize($f), 'fecha' => date('Y-m-d H:i', filemtime($f))], $files);
    view('admin/respaldos', ['title' => 'Respaldos', 'nav' => 'respaldos', 'rows' => $rows, 'db' => filesize((string) config('db_path'))]);
}

function admin_turnos(): void
{
    require_perm('sistema.usuarios_staff');
    if (is_post()) {
        if (input('accion') === 'borrar') q('DELETE FROM turnos WHERE id = ?', [input_int('id', 0)]);
        else {
            $uid2 = input_int('user_id', 0);
            $dias = array_filter(array_map('intval', (array) ($_POST['dias'] ?? [])), fn($d) => $d >= 1 && $d <= 7);
            $a = (string) input('desde');
            $b = (string) input('hasta');
            if (!q1("SELECT 1 FROM users WHERE id = ? AND rol != 'alumno'", [$uid2]) || !$dias || !preg_match('/^\d{2}:\d{2}$/', $a) || !preg_match('/^\d{2}:\d{2}$/', $b) || $a >= $b) {
                flash('error', 'Completa persona, días y horario válido.');
            } else {
                foreach ($dias as $d) insert('turnos', ['user_id' => $uid2, 'dia' => $d, 'desde' => $a, 'hasta' => $b]);
                flash('ok', 'Turno agregado.');
            }
        }
        redirect(url('admin/turnos'));
    }
    view('admin/turnos', ['title' => 'Turnos', 'nav' => 'turnos', 'staff' => qa("SELECT id, nombre, apellido, rol FROM users WHERE rol != 'alumno' AND activo = 1 ORDER BY nombre"),
        'rows' => qa('SELECT t.*, u.nombre, u.apellido FROM turnos t JOIN users u ON u.id = t.user_id ORDER BY t.dia, t.desde, u.nombre')]);
}

function admin_moderacion(): void
{
    require_perm('muro.moderar');
    if (is_post()) {
        $id = input_int('id', 0);
        $acc = input('accion');
        if ($acc === 'ocultar' || $acc === 'mostrar') {
            update('muro_posts', ['oculto' => $acc === 'ocultar' ? 1 : 0], 'id = ?', [$id]);
            q('UPDATE muro_reportes SET resuelto = 1 WHERE post_id = ?', [$id]);
            audit('muro_' . $acc, 'muro_posts', $id);
        } elseif ($acc === 'descartar') {
            q('UPDATE muro_reportes SET resuelto = 1 WHERE post_id = ?', [$id]);
        }
        redirect(url('admin/moderacion'));
    }
    $rep = qa('SELECT p.*, u.nombre, u.apellido, COUNT(r.id) AS n, GROUP_CONCAT(r.motivo, " · ") AS motivos FROM muro_reportes r JOIN muro_posts p ON p.id = r.post_id JOIN users u ON u.id = p.user_id WHERE r.resuelto = 0 GROUP BY p.id ORDER BY n DESC');
    $rec = qa('SELECT p.*, u.nombre, u.apellido FROM muro_posts p JOIN users u ON u.id = p.user_id ORDER BY p.id DESC LIMIT 40');
    view('admin/moderacion', ['title' => 'Moderación', 'nav' => 'moderacion', 'rep' => $rep, 'rec' => $rec]);
}

function admin_solicitudes(): void
{
    $me = require_perm('alumnos.eliminacion');
    if (is_post()) {
        $s = q1('SELECT * FROM solicitudes_eliminacion WHERE id = ?', [input_int('id', 0)]);
        if (!$s) not_found();
        if (input('accion') === 'ejecutar') {
            if (input('confirmar') !== 'ELIMINAR') {
                flash('error', 'Escribe ELIMINAR para confirmar.');
                redirect(url('admin/solicitudes'));
            }
            $uidX = (int) $s['user_id'];
            delete_media((string) qv('SELECT foto FROM users WHERE id = ?', [$uidX]));
            tx(function () use ($uidX, $s, $me) {
                q('DELETE FROM fichas WHERE user_id = ?', [$uidX]);
                q('DELETE FROM mediciones WHERE user_id = ?', [$uidX]);
                q('DELETE FROM muro_reacciones WHERE user_id = ?', [$uidX]);
                q('DELETE FROM muro_posts WHERE user_id = ?', [$uidX]);
                q('DELETE FROM canciones WHERE user_id = ?', [$uidX]);
                q('DELETE FROM push_subs WHERE user_id = ?', [$uidX]);
                q('DELETE FROM remember_tokens WHERE user_id = ?', [$uidX]);
                q('DELETE FROM notas_alumno WHERE alumno_id = ?', [$uidX]);
                q('DELETE FROM notificaciones WHERE user_id = ?', [$uidX]);
                q('UPDATE calificaciones SET comentario = NULL WHERE alumno_id = ?', [$uidX]);
                update('users', ['nombre' => 'Alumno eliminado', 'apellido' => '', 'doc_tipo' => 'otro', 'doc_num' => 'DEL-' . $uidX . '-' . token(4), 'email' => null, 'telefono' => null,
                    'password_hash' => null, 'activo' => 0, 'muro_opt_in' => 0, 'foto' => null, 'session_token' => null], 'id = ?', [$uidX]);
                update('solicitudes_eliminacion', ['estado' => 'ejecutada', 'resuelto_por' => $me['id'], 'resuelto_at' => date('Y-m-d H:i:s')], 'id = ?', [$s['id']]);
            });
            audit('datos_eliminados', 'users', $uidX);
            flash('ok', 'Datos personales eliminados. Se conservan registros anónimos de asistencia y entrenamiento para estadísticas.');
        } elseif (input('accion') === 'rechazar') {
            update('solicitudes_eliminacion', ['estado' => 'rechazada', 'resuelto_por' => $me['id'], 'resuelto_at' => date('Y-m-d H:i:s')], 'id = ?', [$s['id']]);
            flash('ok', 'Solicitud marcada como rechazada.');
        }
        redirect(url('admin/solicitudes'));
    }
    view('admin/solicitudes', ['title' => 'Solicitudes de datos', 'nav' => 'solicitudes',
        'rows' => qa('SELECT s.*, u.nombre, u.apellido, u.email FROM solicitudes_eliminacion s JOIN users u ON u.id = s.user_id ORDER BY s.id DESC LIMIT 50')]);
}

function admin_exportar(): void
{
    require_staff();
    if (!can_any(['planes.gestionar', 'reportes.gimnasio'])) deny();
    require_once PRIVATE_DIR . '/app/import.php';
    audit('exportacion_alumnos');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="alumnos-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Documento', 'Nombre', 'Apellido', 'Correo', 'Teléfono', 'Plan', 'Modalidad', 'Inicio', 'Vencimiento', 'Estado', 'Último check-in'], ';');
    foreach (qa("SELECT * FROM users WHERE rol = 'alumno' ORDER BY nombre") as $u) {
        $m = membresia_actual((int) $u['id']);
        $uc = qv('SELECT MAX(created_at) FROM checkins WHERE user_id = ?', [$u['id']]);
        fputcsv($out, array_map('csv_safe', [doc_display($u), $u['nombre'], $u['apellido'], $u['email'], $u['telefono'], $m['plan_nombre'] ?? '', $m['modalidad'] ?? '', $m['fecha_inicio'] ?? '', $m['fecha_fin'] ?? '', $m['estado'] ?? 'sin plan', $uc]), ';');
    }
    fclose($out);
    exit;
}
