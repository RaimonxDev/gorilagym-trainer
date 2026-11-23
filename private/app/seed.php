<?php
/**
 * Datos iniciales: permisos, roles por defecto, marca, planes de Gorila, grupos musculares.
 */
declare(strict_types=1);

function permissions_catalog(): array
{
    // key => [grupo, nombre, descripcion, ambito, critico, roles por defecto]
    return [
        // Sistema
        'sistema.config'         => ['Sistema', 'Configuración general y marca', 'Datos del gimnasio, colores, textos, ubicación y ajustes del sistema.', 'staff', 1, []],
        'sistema.usuarios_staff' => ['Sistema', 'Gestionar usuarios del staff', 'Crear, editar y desactivar coaches, jefes de turno y super admins.', 'staff', 1, []],
        'sistema.respaldos'      => ['Sistema', 'Respaldos de la base de datos', 'Crear y descargar respaldos completos.', 'staff', 1, []],
        'sistema.auditoria'      => ['Sistema', 'Log de auditoría', 'Ver quién hizo qué y cuándo.', 'staff', 1, []],
        // Planes
        'planes.gestionar'       => ['Planes', 'Gestionar planes y membresías', 'Crear o editar planes, horarios y membresías de alumnos.', 'staff', 0, ['jefe']],
        'planes.importar'        => ['Planes', 'Importar Excel de alumnos', 'Subir el Excel/CSV y aplicar la importación.', 'staff', 0, ['jefe']],
        'planes.pase_diario'     => ['Planes', 'Registrar Pase Diario', 'Registrar el acceso de un día para visitas o acompañantes.', 'staff', 0, ['jefe', 'coach']],
        'planes.pausas'          => ['Planes', 'Registrar y aprobar pausas por lesión', 'Solo lesiones ocurridas dentro del recinto.', 'staff', 0, ['jefe']],
        'planes.acreditar'       => ['Planes', 'Acreditar estudiantes', 'Validar documentación para el plan de estudiantes.', 'staff', 0, ['jefe']],
        // Alumnos
        'alumnos.ver'            => ['Alumnos', 'Ver fichas de alumnos', 'Plan, historial, progreso y notas.', 'staff', 0, ['jefe', 'coach']],
        'alumnos.activar'        => ['Alumnos', 'Activar alumno / registrar correo', 'Para alumnos sin correo en el Excel. Verificación presencial.', 'staff', 0, ['jefe', 'coach']],
        'alumnos.editar'         => ['Alumnos', 'Editar datos de alumnos', 'Nombre, documento, contacto, estado.', 'staff', 0, ['jefe']],
        'alumnos.salud'          => ['Alumnos', 'Ver datos de salud', 'Restricciones, lesiones y cuestionario de salud.', 'staff', 0, ['jefe', 'coach']],
        'alumnos.notas'          => ['Alumnos', 'Notas y traspaso', 'Escribir notas del coach y notas de traspaso.', 'staff', 0, ['jefe', 'coach']],
        'alumnos.reasignar'      => ['Alumnos', 'Reasignar alumnos entre coaches', 'Cambiar el coach de una sesión en curso o planificada.', 'staff', 0, ['jefe']],
        'alumnos.eliminacion'    => ['Alumnos', 'Gestionar solicitudes de eliminación', 'Revisar y ejecutar solicitudes de borrado de datos.', 'staff', 1, []],
        // Catálogo
        'catalogo.gestionar'     => ['Catálogo', 'Gestionar ejercicios y máquinas', 'Fichas, fotos, videos, alternativas, QR y NFC.', 'staff', 0, ['jefe']],
        'catalogo.mantencion'    => ['Catálogo', 'Marcar máquinas en mantención', 'Sacarlas o devolverlas a las sugerencias.', 'staff', 0, ['jefe', 'coach']],
        'plantillas.gestionar'   => ['Catálogo', 'Plantillas oficiales de rutina', 'Crear y editar las rutinas base del gimnasio.', 'staff', 0, ['jefe']],
        // Rutinas y sala
        'rutinas.armar'          => ['Sala', 'Armar y editar rutinas', 'Crear sesiones, reordenar, cambiar ejercicios.', 'staff', 0, ['jefe', 'coach']],
        'sala.ver'               => ['Sala', 'Vista de sala y ocupación', 'Quién está entrenando y qué máquinas están ocupadas.', 'staff', 0, ['jefe', 'coach']],
        // Reportes
        'reportes.gimnasio'      => ['Reportes', 'Reportes del gimnasio', 'Asistencia, horas punta, ocupación, retención, carga por coach.', 'staff', 0, ['jefe']],
        'reportes.adopcion'      => ['Reportes', 'Adopción del staff', 'Rutinas armadas en el sistema por coach.', 'staff', 0, ['jefe']],
        // Calificaciones
        'calif.detalle'          => ['Calificaciones', 'Ver calificaciones en detalle', 'Cada evaluación con su comentario.', 'staff', 1, []],
        'calif.promedios'        => ['Calificaciones', 'Ver promedios de todos los coaches', 'Promedios mensuales (mínimo de evaluaciones aplicado).', 'staff', 0, ['jefe']],
        'calif.propio'           => ['Calificaciones', 'Ver su propio promedio', 'Solo el promedio mensual, nunca evaluaciones individuales.', 'staff', 0, ['coach']],
        // Comunidad
        'muro.moderar'           => ['Comunidad', 'Moderar el muro', 'Ocultar publicaciones y revisar reportes.', 'staff', 0, ['jefe']],
        'avisos.publicar'        => ['Comunidad', 'Publicar avisos', 'Banner y push a todos los alumnos.', 'staff', 0, ['jefe']],
        // Alumno
        'alumno.rutina'          => ['Alumno', 'Ver su rutina y registrar series', 'Lo esencial del alumno en sala.', 'alumno', 0, ['alumno']],
        'alumno.progreso'        => ['Alumno', 'Ver su progreso y reportes', 'Gráficos, récords, asistencia, resumen mensual.', 'alumno', 0, ['alumno']],
        'alumno.mediciones'      => ['Alumno', 'Registrar mediciones corporales', 'Opcional para el alumno.', 'alumno', 0, ['alumno']],
        'alumno.calificar'       => ['Alumno', 'Calificar al coach', 'Al cerrar cada sesión con coach.', 'alumno', 0, ['alumno']],
        'alumno.muro'            => ['Alumno', 'Participar en el muro', 'Estado del día y reacciones.', 'alumno', 0, ['alumno']],
    ];
}

function seed_permissions(bool $resetDefaults = false): void
{
    $i = 0;
    foreach (permissions_catalog() as $key => [$grupo, $nombre, $desc, $ambito, $critico, $roles]) {
        q('INSERT INTO permissions(key,grupo,nombre,descripcion,ambito,critico,orden) VALUES(?,?,?,?,?,?,?)
           ON CONFLICT(key) DO UPDATE SET grupo=excluded.grupo, nombre=excluded.nombre, descripcion=excluded.descripcion, ambito=excluded.ambito, critico=excluded.critico, orden=excluded.orden',
            [$key, $grupo, $nombre, $desc, $ambito, $critico, $i++]);
    }
    if ($resetDefaults) q('DELETE FROM role_permissions');
    $existing = (int) qv('SELECT COUNT(*) FROM role_permissions');
    if ($existing === 0 || $resetDefaults) {
        foreach (permissions_catalog() as $key => $p) {
            foreach ($p[5] as $rol) q('INSERT OR IGNORE INTO role_permissions(rol, perm_key) VALUES(?,?)', [$rol, $key]);
        }
    }
    setting_set('permissions_version', (string) time());
}

function seed_defaults(): void
{
    $defaults = [
        'brand_nombre' => 'Gorila Gym',
        'brand_primary' => '#283614',
        'brand_hover' => '#606C38',
        'brand_dark' => '#111D13',
        'brand_light' => '#F5F9E5',
        'brand_accent' => '#606C38',
        'brand_danger' => '#B3261E',
        'brand_warning' => '#A86B00',
        'brand_logo' => '',
        'brand_logo_claro' => '',
        'brand_isotipo' => '',
        'brand_bienvenida' => 'Ya llegaste a la jungla 🦍',
        'brand_tagline' => 'Entrenamiento semipersonalizado',
        'sesion_max_min' => '90',
        'plantilla_objetivo_min' => '75',
        'transicion_seg' => '90',
        'otp_ttl_seg' => '600',
        'remember_days' => '90',
        'calif_minimo' => '5',
        'alerta_inactivo_dias' => '10',
        'alerta_estancado_sesiones' => '4',
        'alerta_vence_dias' => '7',
        'video_max_mb' => '25',
        'foto_max_mb' => '8',
        'entrada_token' => token(10),
        'reporte_mensual_activo' => '1',
        'muro_activo' => '1',
        'muro_posts_dia' => '1',
        'pase_diario_precio' => '',
    ];
    foreach ($defaults as $k => $v) q('INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)', [$k, $v]);

    if (!(int) qv('SELECT COUNT(*) FROM sedes')) {
        insert('sedes', ['nombre' => 'Gorila Gym Osorno', 'direccion' => '18 de Septiembre 586, Osorno', 'radio_m' => 100]);
    }

    if (!(int) qv('SELECT COUNT(*) FROM grupos_musculares')) {
        foreach (['Pecho', 'Espalda', 'Hombros', 'Bíceps', 'Tríceps', 'Cuádriceps', 'Isquiotibiales', 'Glúteos', 'Pantorrillas', 'Core', 'Antebrazo', 'Cuerpo completo', 'Cardio'] as $i => $g) {
            insert('grupos_musculares', ['nombre' => $g, 'orden' => $i]);
        }
    }

    if (!(int) qv('SELECT COUNT(*) FROM planes')) {
        $lv = [[1, '07:00', '22:00'], [2, '07:00', '22:00'], [3, '07:00', '22:00'], [4, '07:00', '22:00'], [5, '07:00', '22:00'], [6, '10:00', '14:00']];
        $mandril = [[1, '07:00', '17:00'], [2, '07:00', '17:00'], [3, '07:00', '17:00'], [4, '07:00', '17:00'], [5, '07:00', '17:00'], [6, '10:00', '14:00']];
        $planes = [
            ['Jungla', 'libre', 'Plan libre (confirmar si incluye coach).', 0, 35000, 90000, 175000, 250000, 'Shaker + creatina', 'semestral,anual', $lv],
            ['Gorila', 'coach', 'Semipersonalizado.', 0, 60000, 170000, 235000, 350000, 'Shaker + creatina', 'semestral,anual', $lv],
            ['Mandril', 'coach', 'Semipersonalizado. Sin acceso después de las 17:00 de lunes a viernes.', 0, 45000, null, null, null, null, null, $mandril],
            ['Monkey', 'coach', 'Semipersonalizado para estudiantes. Requiere acreditar condición de estudiante.', 1, 40000, null, null, null, null, null, $lv],
        ];
        foreach ($planes as $o => $p) {
            $id = insert('planes', [
                'nombre' => $p[0], 'tipo' => $p[1], 'descripcion' => $p[2], 'requiere_estudiante' => $p[3],
                'precio_mensual' => $p[4], 'precio_trimestral' => $p[5], 'precio_semestral' => $p[6], 'precio_anual' => $p[7],
                'beneficio' => $p[8], 'beneficio_modalidades' => $p[9], 'orden' => $o,
            ]);
            foreach ($p[10] as [$d, $a, $b]) insert('plan_horarios', ['plan_id' => $id, 'dia' => $d, 'hora_inicio' => $a, 'hora_fin' => $b]);
        }
    }
}
