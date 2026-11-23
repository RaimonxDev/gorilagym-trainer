<?php partial('shared/head', ['t' => 'Mi calificación', 'sub' => 'Promedio mensual. Se muestra desde ' . $min . ' evaluaciones para que un día puntual no defina el mes. Nunca ves evaluaciones individuales.']); ?>
<div class="card divide-y divide-dark/5">
  <?php if (!$meses): ?><p class="p-4 text-sm text-dark/55">Aún no hay evaluaciones.</p><?php endif; ?>
  <?php foreach ($meses as $m): [$y, $mm] = explode('-', $m['mes']); ?>
    <div class="px-4 py-4">
      <div class="flex justify-between"><span class="font-medium"><?= e(mes_nombre((int) $mm) . ' ' . $y) ?></span><span class="text-sm text-dark/55"><?= (int) $m['n'] ?> evaluaciones</span></div>
      <?php if ((int) $m['n'] < $min): ?><p class="mt-1 text-sm text-dark/55">Faltan <?= $min - (int) $m['n'] ?> evaluaciones para mostrar el promedio.</p>
      <?php else: ?>
        <div class="mt-2 grid grid-cols-3 gap-3 text-sm">
          <div><div class="text-dark/55">Técnica</div><div class="text-xl font-semibold"><?= e($m['tecnica']) ?></div></div>
          <div><div class="text-dark/55">Atención</div><div class="text-xl font-semibold"><?= e($m['atencion']) ?></div></div>
          <div><div class="text-dark/55">Rutina</div><div class="text-xl font-semibold"><?= e($m['rutina']) ?></div></div>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
