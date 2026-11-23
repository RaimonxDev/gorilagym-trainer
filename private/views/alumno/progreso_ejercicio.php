<?php
$q = (int) $a['id'] === uid() ? [] : ['alumno' => $a['id']];
partial('shared/head', ['t' => $ej['nombre'], 'sub' => (int) $a['id'] === uid() ? '' : e(nombre_completo($a)), 'back' => url('progreso', $q)]);
$best = $datos ? max(array_map(fn($d) => (float) $d['e1rm'], $datos)) : 0;
?>
<div class="grid grid-cols-2 gap-3">
  <div class="card p-4"><div class="text-sm text-dark/60">Peso máximo</div><div class="mt-1 text-2xl font-semibold"><?= $datos ? kg(max(array_column($datos, 'peso'))) : '—' ?></div></div>
  <div class="card p-4"><div class="text-sm text-dark/60">1RM estimado</div><div class="mt-1 text-2xl font-semibold"><?= $best ? kg(round($best, 1)) : '—' ?></div><div class="text-xs text-dark/50">Fórmula de Epley, es una estimación</div></div>
</div>

<section class="mt-6">
  <h2 class="section-title">Peso máximo por sesión</h2>
  <div class="mt-2 card p-4"><div class="h-48" data-chart="line" data-json='<?= e(json_encode(array_map(fn($d) => ['x' => fecha($d['fecha'], 'd/m'), 'y' => (float) $d['peso']], $datos))) ?>' data-unit="kg"></div></div>
</section>
<section class="mt-6">
  <h2 class="section-title">1RM estimado</h2>
  <div class="mt-2 card p-4"><div class="h-40" data-chart="line" data-json='<?= e(json_encode(array_map(fn($d) => ['x' => fecha($d['fecha'], 'd/m'), 'y' => round((float) $d['e1rm'], 1)], $datos))) ?>' data-unit="kg"></div></div>
</section>

<section class="mt-6">
  <h2 class="section-title">Historial</h2>
  <div class="mt-2 card overflow-hidden">
    <table class="table">
      <thead><tr><th>Fecha</th><th>Serie</th><th class="text-right">Reps</th><th class="text-right">Peso</th></tr></thead>
      <tbody>
        <?php foreach ($hist as $h): ?>
          <tr><td><?= e(fecha($h['fecha'])) ?></td><td><?= (int) $h['n'] ?></td><td class="text-right tabular-nums"><?= (int) $h['reps'] ?></td><td class="text-right tabular-nums"><?= kg($h['peso']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
