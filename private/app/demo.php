<?php
/**
 * Datos de prueba: un alumno y un coach con contraseña, y (si el catálogo está vacío) máquinas, ejercicios y una plantilla.
 * Todo lo creado queda registrado en settings.demo para poder borrarlo con un clic.
 */
declare(strict_types=1);

const DEMO_ALUMNO_RUT = '11111111-1'; // 11.111.111-1 (RUT válido de prueba)
const DEMO_COACH_RUT = '22222222-2';  // 22.222.222-2 (RUT válido de prueba)

function demo_estado(): ?array
{
    $d = json_decode((string) setting('demo', ''), true);
    return is_array($d) ? $d : null;
}

function demo_clave(): string
{
    $abc = 'abcdefghjkmnpqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < 10; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
    return $s;
}

function demo_crear(): array
{
    if (demo_estado()) throw new RuntimeException('Los datos de prueba ya existen. Elimínalos primero si quieres regenerarlos.');
    foreach ([DEMO_ALUMNO_RUT, DEMO_COACH_RUT] as $rut) {
        if (qv("SELECT 1 FROM users WHERE doc_tipo = 'rut' AND doc_num = ?", [$rut])) {
            throw new RuntimeException('Ya existe un usuario con el RUT ' . rut_format($rut) . '. Bórralo o cámbiale el RUT antes de crear los datos de prueba.');
        }
    }
    $claveA = demo_clave();
    $claveC = demo_clave();
    $hoy = date('Y-m-d');

    return tx(function () use ($claveA, $claveC, $hoy) {
        $d = ['usuarios' => [], 'maquinas' => [], 'ejercicios' => [], 'plantillas' => []];
        $d['usuarios'][] = $coach = insert('users', ['rol' => 'coach', 'doc_tipo' => 'rut', 'doc_num' => DEMO_COACH_RUT, 'nombre' => 'Coach', 'apellido' => 'Prueba',
            'password_hash' => password_hash($claveC, PASSWORD_DEFAULT), 'activo' => 1, 'sede_id' => sede_id()]);
        $d['usuarios'][] = $alumno = insert('users', ['rol' => 'alumno', 'doc_tipo' => 'rut', 'doc_num' => DEMO_ALUMNO_RUT, 'nombre' => 'Alumno', 'apellido' => 'Prueba',
            'password_hash' => password_hash($claveA, PASSWORD_DEFAULT), 'activo' => 1, 'sede_id' => sede_id(), 'muro_opt_in' => 1, 'muro_logros' => 1]);
        $plan = (int) (qv("SELECT id FROM planes WHERE nombre = 'Gorila'") ?: qv("SELECT id FROM planes WHERE tipo = 'coach' AND activo = 1 ORDER BY orden LIMIT 1"));
        if ($plan) insert('membresias', ['user_id' => $alumno, 'plan_id' => $plan, 'modalidad' => 'mensual', 'fecha_inicio' => $hoy, 'fecha_fin' => date('Y-m-d', strtotime('+30 days')), 'origen' => 'manual', 'notas' => 'Datos de prueba']);

        // Catálogo de ejemplo solo si está vacío
        if (!(int) qv('SELECT COUNT(*) FROM ejercicios')) {
            $g = [];
            foreach (qa('SELECT id, nombre FROM grupos_musculares') as $r) $g[$r['nombre']] = (int) $r['id'];
            $maq = function (string $n, string $zona, int $esZona = 0) use (&$d) {
                $id = insert('maquinas', ['sede_id' => sede_id(), 'nombre' => $n, 'zona' => $zona, 'token' => token(8), 'es_zona' => $esZona]);
                $d['maquinas'][] = $id;
                return $id;
            };
            $mPrensa = $maq('Prensa 45°', 'Piernas');
            $mHack = $maq('Hack squat', 'Piernas');
            $mPolea = $maq('Polea doble', 'Multiuso');
            $mBanca = $maq('Banca plana', 'Pecho');
            $mManc = $maq('Mancuernas', 'Peso libre', 1);
            $ej = function (array $row) use (&$d) {
                $id = insert('ejercicios', $row + ['token' => token(8), 'activo' => 1]);
                $d['ejercicios'][] = $id;
                return $id;
            };
            $prensa = $ej(['nombre' => 'Prensa 45°', 'maquina_id' => $mPrensa, 'grupo_id' => $g['Cuádriceps'] ?? null, 'series_def' => 4, 'reps_min' => 8, 'reps_max' => 12, 'descanso_seg' => 90, 'incremento_kg' => 10,
                'instrucciones' => "Pies al ancho de hombros, al centro de la plataforma\nBaja hasta 90° sin despegar la espalda baja\nEmpuja con todo el pie, sin bloquear rodillas", 'errores' => "Despegar los glúteos del respaldo\nBloquear las rodillas arriba", 'restricciones' => 'rodilla']);
            $hack = $ej(['nombre' => 'Hack squat', 'maquina_id' => $mHack, 'grupo_id' => $g['Cuádriceps'] ?? null, 'series_def' => 3, 'reps_min' => 8, 'reps_max' => 12, 'descanso_seg' => 90, 'incremento_kg' => 5,
                'instrucciones' => "Espalda completa apoyada\nBaja controlado 2–3 segundos", 'restricciones' => 'rodilla']);
            $bulg = $ej(['nombre' => 'Sentadilla búlgara', 'maquina_id' => $mManc, 'grupo_id' => $g['Cuádriceps'] ?? null, 'series_def' => 3, 'reps_min' => 10, 'reps_max' => 12, 'descanso_seg' => 60, 'incremento_kg' => 2,
                'instrucciones' => "Pie de atrás apoyado en la banca\nTronco levemente inclinado adelante"]);
            $curl = $ej(['nombre' => 'Curl femoral en polea', 'maquina_id' => $mPolea, 'grupo_id' => $g['Isquiotibiales'] ?? null, 'series_def' => 3, 'reps_min' => 10, 'reps_max' => 15, 'descanso_seg' => 60, 'incremento_kg' => 2.5]);
            $press = $ej(['nombre' => 'Press banca', 'maquina_id' => $mBanca, 'grupo_id' => $g['Pecho'] ?? null, 'series_def' => 4, 'reps_min' => 6, 'reps_max' => 10, 'descanso_seg' => 120, 'incremento_kg' => 2.5,
                'instrucciones' => "Escápulas juntas y abajo\nBarra a la altura del esternón", 'restricciones' => 'hombro']);
            $aperturas = $ej(['nombre' => 'Aperturas en polea', 'maquina_id' => $mPolea, 'grupo_id' => $g['Pecho'] ?? null, 'series_def' => 3, 'reps_min' => 12, 'reps_max' => 15, 'descanso_seg' => 60, 'incremento_kg' => 2.5]);
            $pressManc = $ej(['nombre' => 'Press inclinado con mancuernas', 'maquina_id' => $mManc, 'grupo_id' => $g['Pecho'] ?? null, 'series_def' => 3, 'reps_min' => 8, 'reps_max' => 12, 'descanso_seg' => 90, 'incremento_kg' => 2]);
            foreach ([[$prensa, $hack], [$prensa, $bulg], [$hack, $bulg], [$press, $pressManc], [$aperturas, $pressManc]] as [$a, $b]) {
                q('INSERT OR IGNORE INTO ejercicio_alternativas VALUES(?,?)', [$a, $b]);
                q('INSERT OR IGNORE INTO ejercicio_alternativas VALUES(?,?)', [$b, $a]);
            }
            $pl = function (string $nombre, array $items) use (&$d) {
                $pid = insert('plantillas', ['nombre' => $nombre, 'nivel' => 'todos', 'descripcion' => 'Plantilla de prueba']);
                $d['plantillas'][] = $pid;
                $o = 1;
                foreach ($items as [$eid, $bloque, $series, $rmin, $rmax, $desc]) insert('plantilla_items', ['plantilla_id' => $pid, 'orden' => $o++, 'bloque' => $bloque, 'ejercicio_id' => $eid, 'series' => $series, 'reps_min' => $rmin, 'reps_max' => $rmax, 'descanso_seg' => $desc]);
            };
            $pl('Pierna (prueba)', [[$prensa, 1, 4, 8, 12, 90], [$hack, 2, 3, 8, 12, 90], [$bulg, 3, 3, 10, 12, 60], [$curl, 3, 3, 10, 15, 60]]);
            $pl('Pecho (prueba)', [[$press, 1, 4, 6, 10, 120], [$pressManc, 2, 3, 8, 12, 90], [$aperturas, 3, 3, 12, 15, 60]]);
        }
        setting_set('demo', json_encode($d));
        audit('demo_creado', null, null, $d);
        return ['alumno' => ['rut' => rut_format(DEMO_ALUMNO_RUT), 'clave' => $claveA], 'coach' => ['rut' => rut_format(DEMO_COACH_RUT), 'clave' => $claveC]];
    });
}

function demo_eliminar(): void
{
    $d = demo_estado();
    if (!$d) return;
    tx(function () use ($d) {
        $u = array_map('intval', $d['usuarios'] ?? []);
        if ($u) {
            $in = implode(',', $u);
            foreach (qa("SELECT foto FROM users WHERE id IN ($in) AND foto IS NOT NULL") as $f) delete_media($f['foto']);
            $ses = "SELECT id FROM sesiones WHERE alumno_id IN ($in) OR coach_id IN ($in)";
            q("DELETE FROM calificaciones WHERE sesion_id IN ($ses) OR coach_id IN ($in) OR alumno_id IN ($in)");
            q("DELETE FROM ocupacion WHERE alumno_id IN ($in) OR item_id IN (SELECT id FROM sesion_items WHERE sesion_id IN ($ses))");
            q("DELETE FROM series_log WHERE alumno_id IN ($in) OR sesion_id IN ($ses)");
            q("UPDATE muro_posts SET sesion_id = NULL WHERE sesion_id IN ($ses)");
            q("DELETE FROM sesion_items WHERE sesion_id IN ($ses)");
            q("DELETE FROM sesiones WHERE alumno_id IN ($in) OR coach_id IN ($in)");
            q("DELETE FROM muro_reacciones WHERE user_id IN ($in) OR post_id IN (SELECT id FROM muro_posts WHERE user_id IN ($in))");
            q("DELETE FROM muro_reportes WHERE user_id IN ($in) OR post_id IN (SELECT id FROM muro_posts WHERE user_id IN ($in))");
            q("DELETE FROM muro_posts WHERE user_id IN ($in)");
            q("DELETE FROM logros WHERE user_id IN ($in)");
            q("DELETE FROM pausas WHERE membresia_id IN (SELECT id FROM membresias WHERE user_id IN ($in)) OR registrado_por IN ($in) OR aprobado_por IN ($in)");
            foreach (['canciones', 'checkins', 'mediciones', 'fichas', 'membresias', 'push_subs', 'notificaciones', 'remember_tokens', 'otp_codes', 'solicitudes_eliminacion', 'turnos'] as $t) q("DELETE FROM $t WHERE user_id IN ($in)");
            q("DELETE FROM acreditaciones WHERE user_id IN ($in) OR validado_por IN ($in)");
            q("UPDATE pases_diarios SET user_id = NULL WHERE user_id IN ($in)");
            q("UPDATE pases_diarios SET registrado_por = NULL WHERE registrado_por IN ($in)");
            q("DELETE FROM notas_alumno WHERE alumno_id IN ($in) OR autor_id IN ($in)");
            q("UPDATE plantillas SET creada_por = NULL WHERE creada_por IN ($in)");
            q("UPDATE avisos SET creado_por = NULL WHERE creado_por IN ($in)");
            q("UPDATE imports SET creado_por = NULL WHERE creado_por IN ($in)");
            q("DELETE FROM users WHERE id IN ($in)");
        }
        foreach (array_map('intval', $d['plantillas'] ?? []) as $p) q('DELETE FROM plantillas WHERE id = ?', [$p]);
        foreach (array_map('intval', $d['ejercicios'] ?? []) as $e) {
            if (qv('SELECT 1 FROM sesion_items WHERE ejercicio_id = ?', [$e]) || qv('SELECT 1 FROM plantilla_items WHERE ejercicio_id = ?', [$e])) {
                update('ejercicios', ['activo' => 0], 'id = ?', [$e]); // lo usan rutinas reales: solo se desactiva
                continue;
            }
            q('DELETE FROM ejercicio_alternativas WHERE ejercicio_id = ? OR alternativa_id = ?', [$e, $e]);
            q('DELETE FROM ejercicios WHERE id = ?', [$e]);
        }
        foreach (array_map('intval', $d['maquinas'] ?? []) as $m) {
            if (qv('SELECT 1 FROM ejercicios WHERE maquina_id = ?', [$m])) continue;
            q('DELETE FROM ocupacion WHERE maquina_id = ?', [$m]);
            q('DELETE FROM maquinas WHERE id = ?', [$m]);
        }
        setting_set('demo', '');
    });
    audit('demo_eliminado');
}
