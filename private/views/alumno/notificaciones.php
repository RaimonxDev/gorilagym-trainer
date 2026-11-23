<?php partial('shared/head', ['t' => 'Notificaciones']); ?>
<div class="card p-4 mb-6 flex items-center justify-between gap-3">
  <span class="text-sm text-dark/70" data-push-status>Notificaciones push</span>
  <button type="button" class="btn-secondary btn-sm" data-push-toggle>Activar</button>
</div>
<?php if ($avisos): ?>
<h2 class="section-title">Avisos del gimnasio</h2>
<div class="mt-2 card divide-y divide-dark/5 mb-6">
  <?php foreach ($avisos as $a): ?><div class="px-4 py-3"><div class="font-medium"><?= e($a['titulo']) ?></div><?php if ($a['cuerpo']): ?><div class="text-sm text-dark/65"><?= e($a['cuerpo']) ?></div><?php endif; ?><div class="text-xs text-dark/45 mt-1"><?= e(fecha($a['desde'], 'd/m H:i')) ?></div></div><?php endforeach; ?>
</div>
<?php endif; ?>
<h2 class="section-title">Recientes</h2>
<div class="mt-2 card divide-y divide-dark/5">
  <?php if (!$rows): ?><p class="p-4 text-sm text-dark/55">Nada por ahora.</p><?php endif; ?>
  <?php foreach ($rows as $n): ?>
    <a href="<?= e($n['url'] ?: '#') ?>" class="block px-4 py-3"><div class="font-medium"><?= e($n['titulo']) ?></div><?php if ($n['cuerpo']): ?><div class="text-sm text-dark/65"><?= e($n['cuerpo']) ?></div><?php endif; ?><div class="text-xs text-dark/45 mt-1"><?= date('d/m H:i', (int) $n['enviar_at']) ?></div></a>
  <?php endforeach; ?>
</div>
