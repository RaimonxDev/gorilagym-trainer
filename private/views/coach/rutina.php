<?php
$c = $ctx;
partial('shared/head', ['t' => $esStaff ? 'Rutina de ' . $a['nombre'] : 'Mi rutina', 'sub' => $esStaff ? e(nombre_completo($a)) . ' · ' . e(fecha($s['created_at'])) : 'Arma tu rutina libre', 'back' => $esStaff ? url('coach/alumno', ['id' => $a['id']]) : url('inicio'),
    'actions' => '<a href="' . e(url('sesion', ['id' => $s['id']])) . '" class="btn-primary btn-sm">' . icon('play', 'w-4 h-4') . 'Ir a la sesión</a>']);
?>
<div id="builder" data-ctx="sesion" data-id="<?= (int) $s['id'] ?>" data-alumno="<?= (int) $a['id'] ?>" class="grid lg:grid-cols-5 gap-6">
  <div class="lg:col-span-3 space-y-4">
    <?php if ($esStaff): ?>
    <section class="card p-4 text-sm space-y-2">
      <div class="flex flex-wrap gap-x-5 gap-y-1">
        <?php if ($c['ficha']): ?><span><span class="text-dark/55">Objetivo:</span> <?= e($c['ficha']['objetivo'] ?: '—') ?></span><span><span class="text-dark/55">Nivel:</span> <?= e($c['ficha']['nivel']) ?></span><?php endif; ?>
        <?php if ($c['sugerido']): ?><span><span class="text-dark/55">Le toca:</span> <b class="font-medium text-primary"><?= e($c['sugerido']) ?></b></span><?php endif; ?>
      </div>
      <?php if ($c['restricciones']): ?><div><span class="text-dark/55">Restricciones:</span> <?php foreach ($c['restricciones'] as $t): ?><span class="chip chip-warn mr-1"><?= e($t) ?></span><?php endforeach; ?><?php if ($c['detalle']): ?><span class="text-dark/65"> · <?= e($c['detalle']) ?></span><?php endif; ?></div><?php endif; ?>
      <?php if ($c['ultimas']): ?><div class="text-dark/65"><span class="text-dark/55">Últimas:</span> <?= e(implode(' · ', array_map(fn($u) => fecha($u['created_at'], 'd/m') . ' ' . ($u['grupos'] ?: '—'), $c['ultimas']))) ?></div><?php endif; ?>
      <?php if ($c['traspaso']): ?><div class="rounded-md bg-accent/5 border border-accent/20 px-3 py-2"><span class="text-accent font-medium">Traspaso (<?= e($c['traspaso']['autor']) ?>):</span> <?= e($c['traspaso']['texto']) ?></div><?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="card px-4 py-3" data-tiempo>
      <div class="flex items-center justify-between text-sm"><span class="text-dark/60">Tiempo estimado</span><span class="font-medium tabular-nums" data-total>—</span></div>
      <div class="mt-2 h-1.5 rounded-full bg-dark/10 overflow-hidden"><div class="h-full bg-primary transition-all" data-bar style="width:0"></div></div>
      <div class="hidden mt-3" data-recortes></div>
    </section>

    <div class="flex flex-wrap items-center gap-2">
      <select class="input w-auto text-sm py-2" data-plantilla><option value="">Agregar plantilla…</option><?php foreach ($plantillas as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option><?php endforeach; ?></select>
      <button type="button" class="btn-ghost btn-sm lg:hidden" data-abrir-panel><?= icon('add', 'w-4 h-4') ?>Ejercicio</button>
      <button type="button" class="btn-ghost btn-sm ml-auto text-dark/50" data-vaciar>Vaciar</button>
    </div>

    <div data-lista class="space-y-3"><div class="card p-6 text-center text-dark/50">Cargando…</div></div>

    <?php if ($coaches && $s['modo'] === 'coach'): ?>
    <form method="post" action="<?= e(url('coach/reasignar')) ?>" class="card p-4 flex flex-wrap items-end gap-2 text-sm">
      <?= csrf_field() ?><input type="hidden" name="sesion_id" value="<?= (int) $s['id'] ?>">
      <div class="flex-1 min-w-[180px]"><label class="label">Coach a cargo</label>
        <select name="coach_id" class="input"><?php foreach ($coaches as $co): ?><option value="<?= (int) $co['id'] ?>" <?= (int) $co['id'] === (int) $s['coach_id'] ? 'selected' : '' ?>><?= e(trim($co['nombre'] . ' ' . $co['apellido'])) ?></option><?php endforeach; ?></select></div>
      <button class="btn-secondary">Reasignar</button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Panel de ejercicios -->
  <aside class="lg:col-span-2" data-panel>
    <div class="panel-sheet lg:sticky lg:top-6 card p-4">
      <div class="flex items-center justify-between mb-3"><h2 class="font-semibold">Agregar ejercicio</h2><button type="button" class="lg:hidden p-1 text-dark/50" data-cerrar-panel aria-label="Cerrar"><?= icon('close') ?></button></div>
      <input class="input" placeholder="Buscar ejercicio o máquina" data-buscar>
      <div class="mt-3 flex flex-wrap gap-1.5" data-grupos>
        <button type="button" class="pill-btn is-on" data-grupo="">Todos</button>
        <?php foreach ($grupos as $g): ?><button type="button" class="pill-btn" data-grupo="<?= (int) $g['id'] ?>"><?= e($g['nombre']) ?></button><?php endforeach; ?>
      </div>
      <div class="mt-3 max-h-[60vh] overflow-y-auto divide-y divide-dark/5 -mx-1" data-resultados></div>
    </div>
  </aside>
</div>
