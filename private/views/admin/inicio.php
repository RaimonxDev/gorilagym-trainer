<?php partial('shared/head', ['t' => 'Gestión', 'sub' => e(setting('brand_nombre'))]); ?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
  <div class="card p-4"><div class="text-sm text-dark/60">Alumnos con plan vigente</div><div class="mt-1 text-2xl font-semibold"><?= $k['alumnos_vigentes'] ?></div></div>
  <div class="card p-4"><div class="text-sm text-dark/60">Check-ins hoy</div><div class="mt-1 text-2xl font-semibold"><?= $k['checkins_hoy'] ?></div></div>
  <div class="card p-4"><div class="text-sm text-dark/60">Sesiones hoy</div><div class="mt-1 text-2xl font-semibold"><?= $k['sesiones_hoy'] ?></div></div>
  <div class="card p-4"><div class="text-sm text-dark/60">Vencen en 7 días</div><div class="mt-1 text-2xl font-semibold"><?= $k['vencen_7'] ?></div></div>
</div>

<h2 class="section-title mt-8">Pendientes</h2>
<div class="mt-2 card divide-y divide-dark/5">
  <?php
  $pend = [
      [can('alumnos.activar') && $k['sin_correo'], url('coach/alumnos', ['f' => 'sin_correo']), $k['sin_correo'] . ' alumnos sin correo (no pueden entrar hasta activarlos)'],
      [can('planes.pausas') && $k['pausas_pend'], url('admin/pausas'), $k['pausas_pend'] . ' pausas por lesión por aprobar'],
      [can('muro.moderar') && $k['reportes_muro'], url('admin/moderacion'), $k['reportes_muro'] . ' publicaciones reportadas en el muro'],
      [can('alumnos.eliminacion') && $k['solicitudes'], url('admin/solicitudes'), $k['solicitudes'] . ' solicitudes de eliminación de datos'],
      [can('catalogo.gestionar') && $k['mantencion'], url('admin/maquinas'), $k['mantencion'] . ' máquinas en mantención'],
      [can('catalogo.gestionar') && !$k['ejercicios'], url('admin/ejercicio'), 'El catálogo está vacío: carga ejercicios y máquinas para empezar'],
      [can('catalogo.gestionar') && $k['ejercicios'] && $k['sin_video'], url('admin/ejercicios'), $k['sin_video'] . ' ejercicios sin video'],
      [can('sistema.config') && (!$k['cron'] || strtotime($k['cron']) < time() - 600), url('admin/marca', ['tab' => 'sistema']), 'El cron no está corriendo: los avisos de descanso y respaldos automáticos no funcionarán'],
  ];
  $hay = false;
  foreach ($pend as [$show, $href, $txt]): if (!$show) continue; $hay = true; ?>
    <a href="<?= e($href) ?>" class="flex items-center justify-between px-4 py-3"><span><?= e($txt) ?></span><?= icon('right', 'w-4 h-4 text-dark/40') ?></a>
  <?php endforeach; ?>
  <?php if (!$hay): ?><p class="p-4 text-sm text-dark/55">Todo al día.</p><?php endif; ?>
</div>

<div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
  <?php
  $links = [
      ['planes.gestionar', 'admin/planes', 'ticket', 'Planes', 'Precios, horarios y membresías'],
      ['planes.importar', 'admin/importar', 'upload', 'Importar Excel', 'Alumnos y vigencias'],
      ['catalogo.gestionar', 'admin/ejercicios', 'dumbbell', 'Ejercicios', 'Instrucciones, fotos, videos, alternativas'],
      ['catalogo.gestionar', 'admin/maquinas', 'qr', 'Máquinas, QR y NFC', 'Estado, códigos e impresión'],
      ['plantillas.gestionar', 'admin/plantillas', 'list', 'Plantillas', 'Rutinas base del gimnasio'],
      ['reportes.gimnasio', 'admin/reportes', 'chart', 'Reportes', 'Asistencia, horas punta, retención'],
      ['avisos.publicar', 'admin/avisos', 'megaphone', 'Avisos', 'Banner y push'],
      ['sistema.config', 'admin/marca', 'settings', 'Marca y ajustes', 'Colores, textos, ubicación, cron'],
      ['sistema.respaldos', 'admin/respaldos', 'cloud', 'Respaldos', 'Copias de la base de datos'],
  ];
  foreach ($links as [$perm, $r, $ic, $t, $d]): if (!can($perm)) continue; ?>
    <a href="<?= e(url($r)) ?>" class="card p-4 flex gap-3 hover:border-primary/40"><span class="text-primary"><?= icon($ic, 'w-5 h-5') ?></span><span><span class="font-medium block"><?= e($t) ?></span><span class="text-sm text-dark/55"><?= e($d) ?></span></span></a>
  <?php endforeach; ?>
</div>
