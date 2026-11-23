<?php partial('shared/head', ['t' => 'Reportes', 'actions' => '<form class="flex gap-1">' . '<input type="hidden" name="r" value="admin/reportes">' . implode('', array_map(fn($d) => '<button name="dias" value="' . $d . '" class="pill-btn ' . ($dias == $d ? 'is-on' : '') . '">' . ($d === 365 ? '1 año' : $d . ' días') . '</button>', [7, 30, 90, 365])) . '</form>']); ?>

<?php if (isset($r['asistencia_dia'])): ?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
  <div class="card p-4"><div class="text-sm text-dark/60">Planes vigentes</div><div class="mt-1 text-2xl font-semibold"><?= $r['retencion']['vigentes'] ?></div></div>
  <div class="card p-4"><div class="text-sm text-dark/60">Vencieron sin renovar (30 d)</div><div class="mt-1 text-2xl font-semibold"><?= $r['retencion']['vencidos_30'] ?></div></div>
  <div class="card p-4"><div class="text-sm text-dark/60">Renovaron (30 d)</div><div class="mt-1 text-2xl font-semibold"><?= $r['retencion']['renovaron_30'] ?></div></div>
  <div class="card p-4"><div class="text-sm text-dark/60">En riesgo (sin venir)</div><div class="mt-1 text-2xl font-semibold"><?= $r['retencion']['en_riesgo'] ?></div></div>
</div>

<section class="mt-6"><h2 class="section-title">Asistencia diaria (personas distintas)</h2>
  <div class="mt-2 card p-4"><div class="h-48" data-chart="bars" data-unit="personas" data-json='<?= e(json_encode(array_map(fn($x) => ['x' => fecha($x['d'], 'd/m'), 'y' => (int) $x['n']], $r['asistencia_dia']))) ?>'></div></div>
</section>

<section class="mt-6"><h2 class="section-title">Horas punta (check-ins)</h2>
  <?php $mat = [];
  $maxH = 1;
  foreach ($r['heat'] as $h) {
      $mat[$h['dow']][$h['h']] = (int) $h['n'];
      $maxH = max($maxH, (int) $h['n']);
  } ?>
  <div class="mt-2 card p-4 overflow-x-auto">
    <table class="text-xs border-separate border-spacing-0.5">
      <tr><td></td><?php for ($h = 6; $h <= 22; $h++): ?><td class="text-center text-dark/50 w-7"><?= $h ?></td><?php endfor; ?></tr>
      <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $d): ?>
        <tr><td class="pr-2 text-dark/60"><?= ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][$d] ?></td>
          <?php for ($h = 6; $h <= 22; $h++): $n = $mat[$d][$h] ?? 0; ?><td class="w-7 h-6 rounded-sm text-center align-middle <?= $n / $maxH > 0.55 ? 'text-light' : 'text-dark/60' ?>" style="background:rgb(var(--c-primary) / <?= $n ? round(0.08 + 0.92 * $n / $maxH, 2) : 0.04 ?>)" title="<?= $n ?>"><?= $n ?: '' ?></td><?php endfor; ?></tr>
      <?php endforeach; ?>
    </table>
  </div>
</section>

<div class="mt-6 grid lg:grid-cols-2 gap-6">
  <section><h2 class="section-title">Uso de máquinas</h2>
    <div class="mt-2 card divide-y divide-dark/5 text-sm"><?php if (!$r['maquinas']): ?><p class="p-4 text-dark/55">Sin datos de ocupación aún.</p><?php endif; ?>
      <?php foreach ($r['maquinas'] as $m): ?><div class="flex justify-between px-4 py-2.5"><span><?= e($m['nombre']) ?></span><span class="text-dark/60"><?= (int) $m['usos'] ?> usos · <?= (int) $m['minutos'] ?> min</span></div><?php endforeach; ?></div>
  </section>
  <section><h2 class="section-title">Alumnos por plan</h2>
    <div class="mt-2 card divide-y divide-dark/5 text-sm"><?php foreach ($r['planes'] as $p): ?><div class="flex justify-between px-4 py-2.5"><span><?= e($p['nombre']) ?></span><span class="font-medium"><?= (int) $p['n'] ?></span></div><?php endforeach; ?></div>
  </section>
</div>

<section class="mt-6"><h2 class="section-title">Carga por coach</h2>
  <div class="mt-2 card overflow-x-auto"><table class="table">
    <thead><tr><th>Coach</th><th class="text-right">Sesiones</th><th class="text-right">Alumnos distintos</th><th class="text-right">Duración real</th><th class="text-right">Estimada</th></tr></thead>
    <tbody><?php foreach ($r['coaches'] as $c): ?><tr><td><?= e(trim($c['nombre'] . ' ' . $c['apellido'])) ?></td><td class="text-right"><?= (int) $c['sesiones'] ?></td><td class="text-right"><?= (int) $c['alumnos'] ?></td><td class="text-right"><?= $c['dur_real'] ? (int) $c['dur_real'] . ' min' : '—' ?></td><td class="text-right"><?= $c['dur_est'] ? (int) $c['dur_est'] . ' min' : '—' ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
  <?php if (!empty($r['duracion']['n'])): ?><p class="mt-2 text-sm text-dark/60">Promedio real <?= (int) $r['duracion']['real'] ?> min vs estimado <?= (int) $r['duracion']['est'] ?> min · <?= (int) $r['duracion']['pasadas'] ?> de <?= (int) $r['duracion']['n'] ?> sesiones superaron los <?= (int) setting('sesion_max_min', '90') ?> min.</p><?php endif; ?>
</section>
<?php endif; ?>

<?php if (isset($r['adopcion'])): ?>
<section class="mt-6"><h2 class="section-title">Adopción del sistema</h2>
  <div class="mt-2 card p-4 text-[15px]">
    <?php $pct = $r['visitas_coach'] ? round($r['visitas_con_rutina'] * 100 / $r['visitas_coach']) : null; ?>
    De <b class="font-medium"><?= (int) $r['visitas_coach'] ?></b> visitas de alumnos con plan con coach, <b class="font-medium"><?= (int) $r['visitas_con_rutina'] ?></b> tuvieron rutina armada en el sistema<?= $pct !== null ? ' (' . $pct . '%)' : '' ?>.
  </div>
  <div class="mt-3 card divide-y divide-dark/5 text-sm"><?php foreach ($r['adopcion'] as $c): ?><div class="flex justify-between px-4 py-2.5"><span><?= e(trim($c['nombre'] . ' ' . $c['apellido'])) ?></span><span class="text-dark/60"><?= (int) $c['rutinas'] ?> rutinas armadas</span></div><?php endforeach; ?></div>
</section>
<?php endif; ?>
