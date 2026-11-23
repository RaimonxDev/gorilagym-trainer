<?php
declare(strict_types=1);

function cat_ejercicios(): void
{
    require_perm('catalogo.gestionar');
    $grupo = input_int('grupo');
    $qtxt = trim((string) input('q', ''));
    $w = ['1=1'];
    $p = [];
    if ($grupo) {
        $w[] = 'e.grupo_id = ?';
        $p[] = $grupo;
    }
    if ($qtxt !== '') {
        $w[] = 'e.nombre LIKE ?';
        $p[] = '%' . $qtxt . '%';
    }
    $rows = qa('SELECT e.*, m.nombre AS maquina, g.nombre AS grupo, (SELECT COUNT(*) FROM ejercicio_alternativas a WHERE a.ejercicio_id = e.id) AS n_alt
                FROM ejercicios e LEFT JOIN maquinas m ON m.id = e.maquina_id LEFT JOIN grupos_musculares g ON g.id = e.grupo_id
                WHERE ' . implode(' AND ', $w) . ' ORDER BY e.activo DESC, g.orden, e.nombre', $p);
    view('admin/ejercicios', ['title' => 'Ejercicios', 'nav' => 'ejercicios', 'rows' => $rows, 'grupos' => qa('SELECT * FROM grupos_musculares ORDER BY orden'), 'grupo' => $grupo, 'q' => $qtxt]);
}

function cat_ejercicio(): void
{
    require_perm('catalogo.gestionar');
    $id = input_int('id');
    $ej = $id ? q1('SELECT * FROM ejercicios WHERE id = ?', [$id]) : null;
    if ($id && !$ej) not_found();
    if (is_post()) {
        $accion = input('accion', 'guardar');
        try {
            if ($accion === 'borrar_media' && $ej) {
                $campo = input('campo') === 'video' ? 'video' : 'foto';
                delete_media($ej[$campo]);
                update('ejercicios', [$campo => null], 'id = ?', [$ej['id']]);
                flash('ok', 'Archivo eliminado.');
                redirect(url('admin/ejercicio', ['id' => $ej['id']]));
            }
            $nombre = trim((string) input('nombre', ''));
            if ($nombre === '') throw new RuntimeException('El nombre es obligatorio.');
            $sec = array_map('intval', (array) ($_POST['grupos_secundarios'] ?? []));
            $data = [
                'nombre' => mb_substr($nombre, 0, 120),
                'maquina_id' => input_int('maquina_id') ?: null,
                'grupo_id' => input_int('grupo_id') ?: null,
                'grupos_secundarios' => $sec ? implode(',', $sec) : null,
                'nivel' => in_array(input('nivel'), ['todos', 'principiante', 'intermedio', 'avanzado'], true) ? input('nivel') : 'todos',
                'instrucciones' => mb_substr((string) input('instrucciones', ''), 0, 3000),
                'errores' => mb_substr((string) input('errores', ''), 0, 3000),
                'seg_por_serie' => max(10, min(300, (int) input('seg_por_serie', 45))),
                'descanso_seg' => max(0, min(600, (int) input('descanso_seg', 90))),
                'series_def' => max(1, min(10, (int) input('series_def', 3))),
                'reps_min' => max(1, min(100, (int) input('reps_min', 8))),
                'reps_max' => max(1, min(100, (int) input('reps_max', 12))),
                'incremento_kg' => max(0.25, min(20, (float) str_replace(',', '.', (string) input('incremento_kg', '2.5')))),
                'restricciones' => implode(',', array_intersect((array) ($_POST['restricciones'] ?? []), restricciones_catalogo())) ?: null,
                'activo' => input('activo') ? 1 : 0,
            ];
            if ($data['reps_max'] < $data['reps_min']) $data['reps_max'] = $data['reps_min'];
            $foto = handle_upload('foto', 'ejercicios', IMG_TYPES, (int) setting('foto_max_mb', '8') * 1048576);
            $video = handle_upload('video', 'ejercicios', VIDEO_TYPES, (int) setting('video_max_mb', '25') * 1048576);
            if ($foto) {
                if ($ej) delete_media($ej['foto']);
                $data['foto'] = $foto;
            }
            if ($video) {
                if ($ej) delete_media($ej['video']);
                $data['video'] = $video;
            }
            if ($ej) {
                update('ejercicios', $data, 'id = ?', [$ej['id']]);
                $eid = (int) $ej['id'];
            } else {
                $data['token'] = token(8);
                $eid = insert('ejercicios', $data);
            }
            // Alternativas (bidireccionales)
            $alts = array_unique(array_filter(array_map('intval', (array) ($_POST['alternativas'] ?? [])), fn($x) => $x && $x !== $eid));
            q('DELETE FROM ejercicio_alternativas WHERE ejercicio_id = ? OR alternativa_id = ?', [$eid, $eid]);
            foreach ($alts as $a) {
                q('INSERT OR IGNORE INTO ejercicio_alternativas(ejercicio_id, alternativa_id) VALUES(?,?)', [$eid, $a]);
                q('INSERT OR IGNORE INTO ejercicio_alternativas(ejercicio_id, alternativa_id) VALUES(?,?)', [$a, $eid]);
            }
            audit($ej ? 'ejercicio_editado' : 'ejercicio_creado', 'ejercicios', $eid, $data['nombre']);
            flash('ok', 'Ejercicio guardado.');
            redirect(url('admin/ejercicio', ['id' => $eid]));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect(url('admin/ejercicio', $id ? ['id' => $id] : []));
        }
    }
    $alts = $ej ? array_column(qa('SELECT alternativa_id FROM ejercicio_alternativas WHERE ejercicio_id = ?', [$ej['id']]), 'alternativa_id') : [];
    view('admin/ejercicio', ['title' => $ej ? $ej['nombre'] : 'Nuevo ejercicio', 'nav' => 'ejercicios', 'ej' => $ej, 'alts' => array_map('intval', $alts),
        'maquinas' => qa('SELECT id, nombre, es_zona FROM maquinas ORDER BY es_zona, nombre'), 'grupos' => qa('SELECT * FROM grupos_musculares ORDER BY orden'),
        'todos' => qa('SELECT e.id, e.nombre, g.nombre AS grupo FROM ejercicios e LEFT JOIN grupos_musculares g ON g.id = e.grupo_id WHERE e.activo = 1 ORDER BY g.orden, e.nombre'),
        'scripts' => ['assets/js/vendor/qrcode.js', 'assets/js/qr.js', 'assets/js/admin.js']]);
}

function cat_maquinas(): void
{
    require_perm('catalogo.gestionar');
    $rows = qa("SELECT m.*, (SELECT COUNT(*) FROM ejercicios e WHERE e.maquina_id = m.id AND e.activo = 1) AS n_ej FROM maquinas m ORDER BY m.es_zona, m.zona, m.nombre");
    view('admin/maquinas', ['title' => 'Máquinas y QR', 'nav' => 'maquinas', 'rows' => $rows, 'scripts' => ['assets/js/coach.js']]);
}

function cat_maquina(): void
{
    require_perm('catalogo.gestionar');
    $id = input_int('id');
    $m = $id ? q1('SELECT * FROM maquinas WHERE id = ?', [$id]) : null;
    if ($id && !$m) not_found();
    if (is_post()) {
        try {
            if (input('accion') === 'borrar' && $m) {
                if (qv('SELECT 1 FROM ejercicios WHERE maquina_id = ?', [$m['id']])) throw new RuntimeException('La máquina tiene ejercicios asociados. Cámbialos primero o déjala en mantención.');
                q('DELETE FROM ocupacion WHERE maquina_id = ?', [$m['id']]);
                q('DELETE FROM maquinas WHERE id = ?', [$m['id']]);
                delete_media($m['foto']);
                audit('maquina_borrada', 'maquinas', (int) $m['id'], $m['nombre']);
                flash('ok', 'Máquina eliminada.');
                redirect(url('admin/maquinas'));
            }
            if (input('accion') === 'nuevo_token' && $m) {
                update('maquinas', ['token' => token(8)], 'id = ?', [$m['id']]);
                audit('maquina_token_regenerado', 'maquinas', (int) $m['id']);
                flash('warning', 'Se generó un código nuevo. Los QR y tags anteriores dejaron de funcionar: reimprime y vuelve a grabar.');
                redirect(url('admin/maquina', ['id' => $m['id']]));
            }
            $nombre = trim((string) input('nombre', ''));
            if ($nombre === '') throw new RuntimeException('El nombre es obligatorio.');
            $data = ['nombre' => mb_substr($nombre, 0, 100), 'zona' => mb_substr((string) input('zona', ''), 0, 60) ?: null, 'estado' => input('estado') === 'mantencion' ? 'mantencion' : 'ok', 'es_zona' => input('es_zona') ? 1 : 0];
            $foto = handle_upload('foto', 'maquinas', IMG_TYPES, (int) setting('foto_max_mb', '8') * 1048576);
            if ($foto) {
                if ($m) delete_media($m['foto']);
                $data['foto'] = $foto;
            }
            if ($m) {
                update('maquinas', $data, 'id = ?', [$m['id']]);
                $mid = (int) $m['id'];
            } else {
                $data['token'] = token(8);
                $data['sede_id'] = sede_id();
                $mid = insert('maquinas', $data);
            }
            audit($m ? 'maquina_editada' : 'maquina_creada', 'maquinas', $mid, $data['nombre']);
            flash('ok', 'Guardado.');
            redirect(url('admin/maquina', ['id' => $mid]));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect(url('admin/maquina', $id ? ['id' => $id] : []));
        }
    }
    view('admin/maquina', ['title' => $m ? $m['nombre'] : 'Nueva máquina', 'nav' => 'maquinas', 'm' => $m,
        'ejs' => $m ? qa('SELECT id, nombre FROM ejercicios WHERE maquina_id = ? ORDER BY nombre', [$m['id']]) : [],
        'scripts' => ['assets/js/vendor/qrcode.js', 'assets/js/qr.js']]);
}

function cat_plantillas(): void
{
    $u = require_perm('plantillas.gestionar');
    if (is_post()) {
        if (input('accion') === 'crear') {
            $n = trim((string) input('nombre', ''));
            if ($n === '') {
                flash('error', 'Ponle un nombre.');
                redirect(url('admin/plantillas'));
            }
            $id = insert('plantillas', ['nombre' => mb_substr($n, 0, 100), 'nivel' => 'todos', 'creada_por' => $u['id']]);
            redirect(url('admin/plantilla', ['id' => $id]));
        }
        if (input('accion') === 'duplicar') {
            $src = q1('SELECT * FROM plantillas WHERE id = ?', [input_int('id', 0)]);
            if ($src) {
                $id = insert('plantillas', ['nombre' => $src['nombre'] . ' (copia)', 'descripcion' => $src['descripcion'], 'nivel' => $src['nivel'], 'creada_por' => $u['id']]);
                foreach (qa('SELECT * FROM plantilla_items WHERE plantilla_id = ?', [$src['id']]) as $it) {
                    unset($it['id']);
                    $it['plantilla_id'] = $id;
                    insert('plantilla_items', $it);
                }
                redirect(url('admin/plantilla', ['id' => $id]));
            }
        }
    }
    $rows = qa('SELECT p.*, (SELECT COUNT(*) FROM plantilla_items i WHERE i.plantilla_id = p.id) AS n FROM plantillas p ORDER BY p.activo DESC, p.nombre');
    foreach ($rows as &$r) $r['min'] = estimacion_min(qa('SELECT i.*, e.seg_por_serie FROM plantilla_items i JOIN ejercicios e ON e.id = i.ejercicio_id WHERE i.plantilla_id = ?', [$r['id']]));
    view('admin/plantillas', ['title' => 'Plantillas', 'nav' => 'plantillas', 'rows' => $rows]);
}

function cat_plantilla(): void
{
    require_perm('plantillas.gestionar');
    $p = q1('SELECT * FROM plantillas WHERE id = ?', [input_int('id', 0)]);
    if (!$p) not_found();
    if (is_post()) {
        if (input('accion') === 'borrar') {
            q('DELETE FROM plantillas WHERE id = ?', [$p['id']]);
            audit('plantilla_borrada', 'plantillas', (int) $p['id'], $p['nombre']);
            flash('ok', 'Plantilla eliminada.');
            redirect(url('admin/plantillas'));
        }
        update('plantillas', ['nombre' => mb_substr(trim((string) input('nombre', $p['nombre'])), 0, 100) ?: $p['nombre'], 'descripcion' => mb_substr((string) input('descripcion', ''), 0, 500),
            'nivel' => in_array(input('nivel'), ['todos', 'principiante', 'intermedio', 'avanzado'], true) ? input('nivel') : 'todos', 'activo' => input('activo') ? 1 : 0], 'id = ?', [$p['id']]);
        flash('ok', 'Plantilla guardada.');
        redirect(url('admin/plantilla', ['id' => $p['id']]));
    }
    view('admin/plantilla', ['title' => $p['nombre'], 'nav' => 'plantillas', 'p' => $p, 'grupos' => qa('SELECT * FROM grupos_musculares ORDER BY orden'),
        'scripts' => ['assets/js/vendor/Sortable.min.js', 'assets/js/builder.js']]);
}

/** Hoja imprimible de QR (entrada + máquinas). */
function cat_qr(): void
{
    require_perm('catalogo.gestionar');
    $ids = array_filter(array_map('intval', explode(',', (string) input('ids', ''))));
    $maq = $ids ? qa('SELECT * FROM maquinas WHERE id IN (' . implode(',', $ids) . ') ORDER BY nombre') : qa('SELECT * FROM maquinas ORDER BY es_zona, nombre');
    $ejs = input('ejercicios') ? qa('SELECT id, nombre, token FROM ejercicios WHERE activo = 1 AND maquina_id IS NULL AND token IS NOT NULL ORDER BY nombre') : [];
    view('admin/qr_hoja', ['title' => 'Imprimir QR', 'maq' => $maq, 'ejs' => $ejs, 'entrada' => input('entrada') === '1' || !$ids, 'bare' => true,
        'scripts' => ['assets/js/vendor/qrcode.js', 'assets/js/qr.js']], 'layout/print');
}
