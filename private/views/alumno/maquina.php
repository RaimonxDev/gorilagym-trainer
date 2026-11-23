<?php partial('shared/head', ['t' => $m['nombre'], 'sub' => e($m['zona'] ?? ''), 'back' => url()]); ?>
<?php if ($m['foto']): ?><img src="<?= e(media_url($m['foto'])) ?>" alt="" class="w-full rounded-lg aspect-video object-cover bg-dark/5 mb-4"><?php endif; ?>

<?php if ($m['estado'] === 'mantencion'): ?>
  <div class="mb-4 rounded-lg border border-warning/30 bg-warning/5 px-4 py-3 text-sm text-warning">En mantención.</div>
<?php elseif ($ocup): ?>
  <div class="mb-4 rounded-lg border border-dark/10 bg-white px-4 py-3 text-sm">Ocupada<?= is_staff() ? ' por ' . e($ocup['alumno_nombre']) : '' ?> · libre en ~<?= (int) $ocup['libre_en_min'] ?> min</div>
<?php endif; ?>

<?php if (is_staff() && can('catalogo.mantencion')): ?>
  <button type="button" class="mb-5 btn-secondary btn-sm" data-mantencion="<?= (int) $m['id'] ?>" data-estado="<?= $m['estado'] === 'mantencion' ? 'ok' : 'mantencion' ?>">
    <?= icon('wrench', 'w-4 h-4') ?><?= $m['estado'] === 'mantencion' ? 'Marcar como operativa' : 'Marcar en mantención' ?>
  </button>
<?php endif; ?>

<h2 class="section-title">Ejercicios en esta máquina</h2>
<div class="mt-2 card divide-y divide-dark/5">
  <?php if (!$ejs): ?><p class="p-4 text-sm text-dark/55">Aún no hay ejercicios cargados para esta máquina.</p><?php endif; ?>
  <?php foreach ($ejs as $e): ?>
    <a href="<?= e(url('ejercicio', ['id' => $e['id']])) ?>" class="flex items-center justify-between px-4 py-3">
      <div><div class="font-medium"><?= e($e['nombre']) ?></div><div class="text-sm text-dark/55"><?= e($e['grupo'] ?? '') ?></div></div>
      <div class="text-right text-sm"><?php if (!empty($e['ult'])): ?><div class="text-dark/50">Tu peso</div><div class="font-medium"><?= kg($e['ult']['peso']) ?></div><?php endif; ?></div>
    </a>
  <?php endforeach; ?>
</div>
