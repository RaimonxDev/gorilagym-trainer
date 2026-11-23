<?php
$q = $propio ? [] : ['alumno' => $a['id']];
partial('shared/head', ['t' => $propio ? 'Tu progreso' : 'Progreso de ' . $a['nombre'], 'back' => $propio ? null : url('coach/alumno', ['id' => $a['id']]),
    'actions' => '<a href="' . e(url('reporte', $q)) . '" class="btn-secondary btn-sm">' . icon('calendar', 'w-4 h-4') . 'Resumen mensual</a>']);
$diff = $rep['mes_actual'] - $rep['mes_anterior'];
?>
<div class="grid grid-cols-3 gap-3">
  <div class="card p-4"><div class="text-sm text-dark/60">Racha</div><div class="mt-1 text-2xl font-semibold"><?= $rep['racha'] ?></div><div class="text-xs text-dark/50">semanas seguidas</div></div>
  <div class="card p-4"><div class="text-sm text-dark/60">Este mes</div><div class="mt-1 text-2xl font-semibold"><?= $rep['mes_actual'] ?></div>
    <div class="text-xs <?= $diff >= 0 ? 'text-primary' : 'text-dark/50' ?>"><?= $diff >= 0 ? '+' : '' ?><?= $diff ?> vs mes anterior</div></div>
  <div class="card p-4"><div class="text-sm text-dark/60">Sesiones</div><div class="mt-1 text-2xl font-semibold"><?= $rep['sesiones_total'] ?></div><div class="text-xs text-dark/50">en total</div></div>
</div>

<section class="mt-8">
  <h2 class="section-title">Asistencia semanal</h2>
  <div class="mt-2 card p-4"><div class="h-40" data-chart="bars" data-json='<?= e(json_encode(array_map(fn($r) => ['x' => $r['semana'], 'y' => (int) $r['dias']], $rep['asistencia_semanal']))) ?>' data-unit="días"></div></div>
</section>

<section class="mt-8">
  <h2 class="section-title">Volumen semanal por grupo muscular</h2>
  <p class="text-sm text-dark/55 mt-1">Series realizadas. Sirve para ver si estás dejando algún grupo de lado.</p>
  <?php
  $grupos = [];
  foreach ($rep['volumen_grupo'] as $r) $grupos[$r['grupo'] ?? 'Otros'] = ($grupos[$r['grupo'] ?? 'Otros'] ?? 0) + (int) $r['series'];
  arsort($grupos);
  $maxG = $grupos ? max($grupos) : 1;
  ?>
  <div class="mt-2 card p-4 space-y-2.5">
    <?php if (!$grupos): ?><p class="text-dark/50 text-sm">Aún no hay series registradas.</p><?php endif; ?>
    <?php foreach ($grupos as $g => $n): ?>
      <div class="flex items-center gap-3 text-sm">
        <span class="w-28 shrink-0 text-dark/75"><?= e($g) ?></span>
        <div class="flex-1 h-2 rounded-full bg-dark/5"><div class="h-2 rounded-full bg-primary" style="width:<?= round($n * 100 / $maxG) ?>%"></div></div>
        <span class="w-10 text-right tabular-nums text-dark/60"><?= $n ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mt-8">
  <h2 class="section-title">Récords personales</h2>
  <div class="mt-2 card divide-y divide-dark/5">
    <?php if (!$rep['records']): ?><p class="p-4 text-dark/50 text-sm">Tus récords aparecerán aquí a medida que registres series.</p><?php endif; ?>
    <?php foreach ($rep['records'] as $r): ?>
      <a href="<?= e(url('progreso/ejercicio', ['id' => $r['id']] + $q)) ?>" class="flex items-center justify-between px-4 py-3">
        <span class="font-medium"><?= e($r['nombre']) ?></span>
        <span class="text-sm text-dark/65 tabular-nums"><?= kg($r['peso']) ?> × <?= (int) $r['reps'] ?> · 1RM est. <?= kg(epley((float) $r['peso'], (int) $r['reps'])) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($rep['ejercicios']): ?>
<section class="mt-8">
  <h2 class="section-title">Progreso por ejercicio</h2>
  <div class="mt-2 flex flex-wrap gap-2">
    <?php foreach ($rep['ejercicios'] as $ej): ?>
      <a href="<?= e(url('progreso/ejercicio', ['id' => $ej['id']] + $q)) ?>" class="rounded-md border border-dark/15 bg-white px-3 py-1.5 text-sm hover:border-primary/40"><?= e($ej['nombre']) ?></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($rep['logros']): ?>
<section class="mt-8">
  <h2 class="section-title">Logros</h2>
  <div class="mt-2 card divide-y divide-dark/5">
    <?php foreach ($rep['logros'] as $l): ?>
      <div class="flex items-center gap-3 px-4 py-3"><?= icon($l['tipo'] === 'racha' ? 'fire' : 'trophy', 'w-5 h-5 text-accent') ?><span class="flex-1"><?= e($l['texto']) ?></span><span class="text-sm text-dark/50"><?= e(fecha($l['created_at'])) ?></span></div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="mt-8">
  <div class="flex items-center justify-between"><h2 class="section-title">Mediciones</h2><?php if ($propio): ?><a href="<?= e(url('mediciones')) ?>" class="text-sm text-primary">Registrar</a><?php endif; ?></div>
  <div class="mt-2 card p-4 text-sm">
    <?php if (!$mediciones): ?><p class="text-dark/55">Opcional. Si quieres, registra tu peso y medidas para ver la evolución.</p>
    <?php else: $m = $mediciones[0]; ?>
      <div class="text-dark/60">Última: <?= e(fecha($m['fecha'])) ?></div>
      <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1">
        <?php foreach (['peso' => 'Peso', 'cintura' => 'Cintura', 'cadera' => 'Cadera', 'pecho' => 'Pecho', 'brazo' => 'Brazo', 'muslo' => 'Muslo'] as $k => $l): if ($m[$k] === null) continue; ?>
          <span><span class="text-dark/55"><?= $l ?></span> <b class="font-medium"><?= e(rtrim(rtrim(number_format((float) $m[$k], 1, ',', ''), '0'), ',')) ?><?= $k === 'peso' ? ' kg' : ' cm' ?></b></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
