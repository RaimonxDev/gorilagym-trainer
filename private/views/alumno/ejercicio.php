<?php partial('shared/head', ['t' => $ej['nombre'], 'sub' => e(implode(' · ', array_filter([$ej['grupo'], $ej['maquina_nombre']]))), 'back' => url()]); ?>
<?php if ($ej['video']): ?>
  <video class="w-full rounded-lg bg-dark/5 aspect-video object-cover" src="<?= e(media_url($ej['video'])) ?>" autoplay muted loop playsinline preload="metadata"></video>
<?php elseif ($ej['foto'] || $ej['maquina_foto']): ?>
  <img class="w-full rounded-lg bg-dark/5 aspect-video object-cover" src="<?= e(media_url($ej['foto'] ?: $ej['maquina_foto'])) ?>" alt="">
<?php endif; ?>

<?php if ($ej['maquina_estado'] === 'mantencion'): ?>
  <div class="mt-4 rounded-lg border border-warning/30 bg-warning/5 px-4 py-3 text-sm text-warning">La máquina está en mantención.</div>
<?php elseif ($ocup): ?>
  <div class="mt-4 rounded-lg border border-dark/10 bg-white px-4 py-3 text-sm">Ocupada ahora · libre en ~<?= (int) $ocup['libre_en_min'] ?> min</div>
<?php endif; ?>

<?php if ($sug): ?>
<div class="mt-4 card p-4">
  <div class="text-sm text-dark/60">Tu próximo peso sugerido</div>
  <div class="mt-1 text-2xl font-semibold"><?= kg($sug['peso']) ?></div>
  <div class="text-sm text-dark/60"><?= e($sug['motivo']) ?></div>
  <?php if ($ult): ?><div class="mt-2 text-sm text-dark/55">Última vez (<?= e(fecha($ult['fecha'])) ?>): <?= e(implode(' · ', array_map(fn($x) => kg($x['peso']) . '×' . $x['reps'], $ult['series']))) ?></div><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($ej['instrucciones']): ?>
<section class="mt-6"><h2 class="section-title">Técnica</h2><div class="mt-2 card p-4 prose-list"><?= nl2br(e($ej['instrucciones'])) ?></div></section>
<?php endif; ?>
<?php if ($ej['errores']): ?>
<section class="mt-6"><h2 class="section-title">Errores comunes</h2><div class="mt-2 card p-4 prose-list"><?= nl2br(e($ej['errores'])) ?></div></section>
<?php endif; ?>
<?php if ($alts): ?>
<section class="mt-6"><h2 class="section-title">Alternativas</h2>
  <div class="mt-2 card divide-y divide-dark/5"><?php foreach ($alts as $a): ?><a href="<?= e(url('ejercicio', ['id' => $a['id']])) ?>" class="flex justify-between px-4 py-3"><span><?= e($a['nombre']) ?></span><span class="text-sm text-dark/50"><?= e($a['maquina'] ?? '') ?></span></a><?php endforeach; ?></div>
</section>
<?php endif; ?>
