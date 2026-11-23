<?php partial('shared/head', ['t' => 'Hola, ' . $u['nombre'], 'sub' => e(dia_nombre((int) date('N')) . ' ' . date('j') . ' · ' . rol_label($u['rol']))]); ?>

<?php if (can('alumnos.ver')): ?>
<form action="<?= e(url()) ?>" method="get" class="relative" data-buscador>
  <input type="hidden" name="r" value="coach/alumnos">
  <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-dark/40"><?= icon('search', 'w-5 h-5') ?></span>
  <input name="q" class="input pl-11 text-[16px]" placeholder="Buscar alumno por nombre o RUT" autocomplete="off" data-buscar-alumno>
  <div class="hidden absolute z-20 inset-x-0 mt-1 card shadow-lg max-h-80 overflow-y-auto" data-resultados></div>
</form>
<?php endif; ?>

<div class="mt-4 flex flex-wrap gap-2">
  <?php if (can('sala.ver')): ?><a href="<?= e(url('sala')) ?>" class="btn-secondary btn-sm"><?= icon('grid', 'w-4 h-4') ?>Sala en vivo</a><?php endif; ?>
  <?php if (can('planes.pase_diario')): ?><a href="<?= e(url('coach/pase')) ?>" class="btn-secondary btn-sm"><?= icon('ticket', 'w-4 h-4') ?>Pase Diario</a><?php endif; ?>
  <a href="<?= e(url('perfil')) ?>" class="btn-secondary btn-sm"><?= icon('user', 'w-4 h-4') ?>Mi perfil</a>
</div>

<div class="mt-8 grid lg:grid-cols-2 gap-6">
  <section>
    <h2 class="section-title">Mis sesiones de hoy</h2>
    <div class="mt-2 card divide-y divide-dark/5">
      <?php if (!$mias): ?><p class="p-4 text-sm text-dark/55">Busca un alumno y arma su rutina. Quedará aquí.</p><?php endif; ?>
      <?php foreach ($mias as $m): ?>
        <div class="flex items-center gap-3 px-4 py-3">
          <div class="flex-1 min-w-0">
            <div class="font-medium truncate"><?= e(trim($m['nombre'] . ' ' . $m['apellido'])) ?></div>
            <div class="text-sm text-dark/55"><?= $m['hechos'] ?>/<?= $m['n'] ?> ejercicios · ~<?= $m['est'] ?> min · <?= $m['estado'] === 'en_curso' ? 'en curso' : 'lista' ?></div>
          </div>
          <a href="<?= e(url('coach/rutina', ['sesion' => $m['id']])) ?>" class="btn-ghost btn-sm" aria-label="Editar"><?= icon('edit', 'w-4 h-4') ?></a>
          <a href="<?= e(url('sesion', ['id' => $m['id']])) ?>" class="btn-secondary btn-sm">Ver</a>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section>
    <h2 class="section-title">Llegaron recién</h2>
    <div class="mt-2 card divide-y divide-dark/5">
      <?php if (!$llegadas): ?><p class="p-4 text-sm text-dark/55">Sin check-ins en las últimas 2 horas.</p><?php endif; ?>
      <?php foreach ($llegadas as $l): ?>
        <div class="flex items-center gap-3 px-4 py-3">
          <div class="flex-1 min-w-0"><div class="font-medium truncate"><?= e(trim($l['nombre'] . ' ' . $l['apellido'])) ?></div><div class="text-sm text-dark/55"><?= date('H:i', strtotime($l['created_at'])) ?></div></div>
          <?php if ($l['sesion_id']): ?>
            <a class="btn-ghost btn-sm" href="<?= e(url('sesion', ['id' => $l['sesion_id']])) ?>">Con rutina</a>
          <?php elseif (can('rutinas.armar')): ?>
            <form method="post" action="<?= e(url('coach/nueva')) ?>"><?= csrf_field() ?><input type="hidden" name="alumno_id" value="<?= (int) $l['id'] ?>"><button class="btn-primary btn-sm">Armar rutina</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<?php if ($alertas): ?>
<div class="mt-8 grid lg:grid-cols-3 gap-6">
  <section>
    <h2 class="section-title">Sin venir hace <?= (int) setting('alerta_inactivo_dias', '10') ?>+ días</h2>
    <div class="mt-2 card divide-y divide-dark/5 text-sm">
      <?php if (!$alertas['inactivos']): ?><p class="p-4 text-dark/55">Nadie. 👌</p><?php endif; ?>
      <?php foreach ($alertas['inactivos'] as $r): ?><a href="<?= e(url('coach/alumno', ['id' => $r['id']])) ?>" class="flex justify-between px-4 py-2.5"><span><?= e(trim($r['nombre'] . ' ' . $r['apellido'])) ?></span><span class="text-dark/50"><?= $r['ultima'] ? e(hace($r['ultima'])) : 'nunca' ?></span></a><?php endforeach; ?>
    </div>
  </section>
  <section>
    <h2 class="section-title">Planes por vencer</h2>
    <div class="mt-2 card divide-y divide-dark/5 text-sm">
      <?php if (!$alertas['vencen']): ?><p class="p-4 text-dark/55">Ninguno esta semana.</p><?php endif; ?>
      <?php foreach ($alertas['vencen'] as $r): ?><a href="<?= e(url('coach/alumno', ['id' => $r['id']])) ?>" class="flex justify-between px-4 py-2.5"><span><?= e(trim($r['nombre'] . ' ' . $r['apellido'])) ?></span><span class="text-dark/50"><?= e($r['plan']) ?> · <?= e(fecha($r['fecha_fin'], 'd/m')) ?></span></a><?php endforeach; ?>
    </div>
  </section>
  <section>
    <h2 class="section-title">Estancados</h2>
    <div class="mt-2 card divide-y divide-dark/5 text-sm">
      <?php if (!$alertas['estancados']): ?><p class="p-4 text-dark/55">Todos progresando.</p><?php endif; ?>
      <?php foreach ($alertas['estancados'] as $r): ?><a href="<?= e(url('coach/alumno', ['id' => $r['alumno_id']])) ?>" class="block px-4 py-2.5"><span><?= e(trim($r['alumno']['nombre'] . ' ' . $r['alumno']['apellido'])) ?></span><span class="block text-dark/50"><?= e($r['ejercicio']) ?> · <?= kg($r['peso']) ?> hace <?= (int) $r['sesiones'] ?> sesiones</span></a><?php endforeach; ?>
    </div>
  </section>
</div>
<?php endif; ?>
