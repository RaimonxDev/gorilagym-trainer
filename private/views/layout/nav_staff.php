<?php
$link = function (string $route, string $key, string $ic, string $label) use ($nav) {
    $on = $nav === $key;
    echo '<a href="' . e(url($route)) . '" class="flex items-center gap-3 rounded-md px-3 py-2 ' . ($on ? 'bg-primary/10 text-primary font-medium' : 'text-dark/80 hover:bg-dark/5') . '">' . icon($ic, 'w-5 h-5 opacity-80') . '<span>' . e($label) . '</span></a>';
};
$sec = fn(string $t) => print('<div class="px-3 pt-5 pb-1.5 text-[11px] uppercase tracking-wide text-dark/45">' . e($t) . '</div>');
?>
<div class="space-y-0.5">
  <?php $link('coach', 'coach', 'home', 'Inicio'); ?>
  <?php if (can('alumnos.ver')) $link('coach/alumnos', 'alumnos', 'users', 'Alumnos'); ?>
  <?php if (can('sala.ver')) $link('sala', 'sala', 'grid', 'Sala en vivo'); ?>
  <?php if (setting('muro_activo', '1') === '1') $link('muro', 'muro', 'fire', 'Muro'); ?>
  <?php if (can('calif.propio') && !can('calif.promedios')) $link('mi-calificacion', 'micalif', 'star', 'Mi calificación'); ?>
  <?php $link('perfil', 'perfil', 'user', 'Mi perfil'); ?>

  <?php if (can_any(['planes.gestionar', 'planes.importar', 'planes.pausas', 'planes.acreditar', 'alumnos.editar'])): $sec('Planes'); ?>
    <?php if (can('planes.gestionar')) $link('admin/planes', 'planes', 'ticket', 'Planes'); ?>
    <?php if (can('planes.importar')) $link('admin/importar', 'importar', 'upload', 'Importar Excel'); ?>
    <?php if (can('planes.pausas')) $link('admin/pausas', 'pausas', 'pause', 'Pausas por lesión'); ?>
  <?php endif; ?>

  <?php if (can_any(['catalogo.gestionar', 'plantillas.gestionar'])): $sec('Catálogo'); ?>
    <?php if (can('catalogo.gestionar')) { $link('admin/ejercicios', 'ejercicios', 'dumbbell', 'Ejercicios'); $link('admin/maquinas', 'maquinas', 'wrench', 'Máquinas y QR'); } ?>
    <?php if (can('plantillas.gestionar')) $link('admin/plantillas', 'plantillas', 'list', 'Plantillas'); ?>
  <?php endif; ?>

  <?php if (can_any(['reportes.gimnasio', 'reportes.adopcion', 'calif.promedios', 'calif.detalle'])): $sec('Reportes'); ?>
    <?php if (can_any(['reportes.gimnasio', 'reportes.adopcion'])) $link('admin/reportes', 'reportes', 'chart', 'Reportes'); ?>
    <?php if (can_any(['calif.promedios', 'calif.detalle'])) $link('admin/calificaciones', 'calificaciones', 'star', 'Calificaciones'); ?>
  <?php endif; ?>

  <?php if (can_any(['avisos.publicar', 'muro.moderar', 'alumnos.eliminacion'])): $sec('Comunidad'); ?>
    <?php if (can('avisos.publicar')) $link('admin/avisos', 'avisos', 'megaphone', 'Avisos'); ?>
    <?php if (can('muro.moderar')) $link('admin/moderacion', 'moderacion', 'flag', 'Moderación'); ?>
    <?php if (can('alumnos.eliminacion')) $link('admin/solicitudes', 'solicitudes', 'shield', 'Solicitudes de datos'); ?>
  <?php endif; ?>

  <?php if (can_any(['sistema.config', 'sistema.usuarios_staff', 'sistema.respaldos', 'sistema.auditoria'])): $sec('Sistema'); ?>
    <?php if (can('sistema.usuarios_staff')) { $link('admin/usuarios', 'usuarios', 'user', 'Usuarios'); $link('admin/turnos', 'turnos', 'clock', 'Turnos'); } ?>
    <?php if (current_user()['rol'] === 'superadmin') $link('admin/permisos', 'permisos', 'key', 'Permisos'); ?>
    <?php if (can('sistema.config')) $link('admin/marca', 'marca', 'settings', 'Marca y ajustes'); ?>
    <?php if (can('sistema.respaldos')) $link('admin/respaldos', 'respaldos', 'cloud', 'Respaldos'); ?>
    <?php if (can('sistema.auditoria')) $link('admin/auditoria', 'auditoria', 'file', 'Auditoría'); ?>
  <?php endif; ?>
</div>
