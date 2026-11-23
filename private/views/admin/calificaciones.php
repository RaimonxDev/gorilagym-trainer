<?php partial('shared/head', ['t' => 'Calificaciones', 'sub' => 'Promedios mensuales por coach. Se muestran desde ' . $min . ' evaluaciones.']); ?>
<div class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>Mes</th><th>Coach</th><th class="text-right">Evaluaciones</th><th class="text-right">Técnica</th><th class="text-right">Atención</th><th class="text-right">Rutina</th></tr></thead>
    <tbody>
      <?php if (!$prom): ?><tr><td colspan="6" class="text-dark/55">Sin evaluaciones aún.</td></tr><?php endif; ?>
      <?php foreach ($prom as $p): [$y, $m] = explode('-', $p['mes']); $ok = (int) $p['n'] >= $min; ?>
        <tr><td><?= e(mes_nombre((int) $m) . ' ' . $y) ?></td><td><?= e(trim($p['nombre'] . ' ' . $p['apellido'])) ?></td><td class="text-right"><?= (int) $p['n'] ?></td>
          <?php foreach (['tecnica', 'atencion', 'rutina'] as $k): ?><td class="text-right tabular-nums"><?= $ok ? e($p[$k]) : '<span class="text-dark/40">—</span>' ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($det): ?>
<h2 class="section-title mt-8">Detalle</h2>
<div class="mt-2 card divide-y divide-dark/5 text-sm">
  <?php foreach ($det as $d): ?>
    <div class="px-4 py-3"><div class="flex justify-between"><span><b class="font-medium"><?= e($d['coach']) ?></b> · por <?= e(trim($d['alumno'] . ' ' . mb_substr((string) $d['alumno_ap'], 0, 1))) ?></span><span class="text-dark/55"><?= e(fecha($d['created_at'])) ?></span></div>
      <div class="text-dark/65">Técnica <?= (int) $d['tecnica'] ?> · Atención <?= (int) $d['atencion'] ?> · Rutina <?= (int) $d['rutina'] ?></div>
      <?php if ($d['comentario']): ?><div class="mt-1">"<?= e($d['comentario']) ?>"</div><?php endif; ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
