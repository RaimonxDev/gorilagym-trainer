<?php
$mes = mes_nombre((int) substr($d['periodo'], 5, 2));
$anio = substr($d['periodo'], 0, 4);
$q = (int) $a['id'] === uid() ? [] : ['alumno' => $a['id']];
$prev = date('Y-m', strtotime($d['periodo'] . '-01 -1 month'));
$next = date('Y-m', strtotime($d['periodo'] . '-01 +1 month'));
?>
<div class="no-print">
  <?php partial('shared/head', ['t' => 'Resumen de ' . $mes, 'sub' => e($anio) . ((int) $a['id'] === uid() ? '' : ' · ' . e(nombre_completo($a))), 'back' => url('progreso', $q),
      'actions' => '<button type="button" class="btn-secondary btn-sm" data-print>' . icon('print', 'w-4 h-4') . 'PDF</button>']); ?>
  <div class="flex justify-between text-sm mb-5">
    <a class="text-primary" href="<?= e(url('reporte', ['periodo' => $prev] + $q)) ?>">← <?= e(mes_nombre((int) substr($prev, 5, 2))) ?></a>
    <?php if ($next <= date('Y-m')): ?><a class="text-primary" href="<?= e(url('reporte', ['periodo' => $next] + $q)) ?>"><?= e(mes_nombre((int) substr($next, 5, 2))) ?> →</a><?php endif; ?>
  </div>
</div>

<div class="print-only mb-6">
  <img src="<?= asset('assets/img/brand/logo-horizontal.svg') ?>" class="h-10" alt="">
  <h1 class="mt-4 text-2xl font-semibold">Resumen de <?= e($mes . ' ' . $anio) ?></h1>
  <p><?= e(nombre_completo($a)) ?></p>
</div>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
  <div class="card p-4"><div class="text-sm text-dark/60">Días entrenados</div><div class="mt-1 text-2xl font-semibold"><?= $d['dias'] ?></div>
    <div class="text-xs text-dark/50"><?= $d['dias'] - $d['dias_prev'] >= 0 ? '+' : '' ?><?= $d['dias'] - $d['dias_prev'] ?> vs mes anterior</div></div>
  <div class="card p-4"><div class="text-sm text-dark/60">Sesiones</div><div class="mt-1 text-2xl font-semibold"><?= $d['sesiones'] ?></div></div>
  <div class="card p-4"><div class="text-sm text-dark/60">Series</div><div class="mt-1 text-2xl font-semibold"><?= $d['series'] ?></div></div>
  <div class="card p-4"><div class="text-sm text-dark/60">Racha actual</div><div class="mt-1 text-2xl font-semibold"><?= $d['racha'] ?></div><div class="text-xs text-dark/50">semanas</div></div>
</div>

<section class="mt-6">
  <h2 class="section-title">Récords del mes</h2>
  <div class="mt-2 card divide-y divide-dark/5">
    <?php if (!$d['records']): ?><p class="p-4 text-sm text-dark/55">Sin récords este mes. El próximo es tuyo.</p><?php endif; ?>
    <?php foreach ($d['records'] as $r): ?><div class="flex justify-between px-4 py-3"><span><?= e($r['ejercicio']) ?></span><span class="font-medium"><?= kg($r['valor']) ?></span></div><?php endforeach; ?>
  </div>
</section>

<section class="mt-6">
  <h2 class="section-title">Grupos trabajados</h2>
  <div class="mt-2 card p-4 flex flex-wrap gap-2">
    <?php if (!$d['grupos']): ?><span class="text-sm text-dark/55">Sin registros.</span><?php endif; ?>
    <?php foreach ($d['grupos'] as $g): ?><span class="chip chip-neutral"><?= e($g['nombre']) ?> · <?= (int) $g['series'] ?></span><?php endforeach; ?>
  </div>
</section>

<?php if ((int) $a['id'] === uid()): ?>
<section class="mt-8 no-print">
  <h2 class="section-title">Compártelo</h2>
  <p class="text-sm text-dark/60 mt-1">Genera una imagen para tus historias. Solo muestra días y récords, sin pesos.</p>
  <canvas id="sharecard" width="1080" height="1920" class="mt-3 w-40 rounded-lg border border-dark/10"
    data-brand="<?= e(setting('brand_nombre')) ?>" data-mes="<?= e($mes) ?>" data-dias="<?= $d['dias'] ?>" data-racha="<?= $d['racha'] ?>" data-records="<?= count($d['records']) ?>"
    data-primary="<?= e(setting('brand_primary')) ?>" data-light="<?= e(setting('brand_light')) ?>" data-logo="<?= asset('assets/img/brand/logo-horizontal-claro.svg') ?>"></canvas>
  <div class="mt-3 flex gap-2"><button type="button" class="btn-primary" data-share-card><?= icon('share', 'w-4 h-4') ?>Compartir</button><button type="button" class="btn-secondary" data-download-card><?= icon('download', 'w-4 h-4') ?>Descargar</button></div>
</section>
<?php endif; ?>
