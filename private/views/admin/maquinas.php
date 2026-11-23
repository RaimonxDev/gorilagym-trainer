<?php partial('shared/head', ['t' => 'Máquinas y QR', 'sub' => 'Cada máquina tiene su QR/NFC. Al escanearlo se abren sus ejercicios (o el que el alumno tiene en su rutina).',
    'actions' => '<a href="' . e(url('admin/qr')) . '" target="_blank" class="btn-secondary btn-sm">' . icon('print', 'w-4 h-4') . 'Imprimir todos</a><a href="' . e(url('admin/maquina')) . '" class="btn-primary btn-sm">' . icon('add', 'w-4 h-4') . 'Nueva</a>']); ?>
<div class="card divide-y divide-dark/5">
  <?php if (!$rows): ?><p class="p-6 text-sm text-dark/55">Agrega las máquinas del gimnasio. Para ejercicios sin máquina crea "zonas" (ej: Mancuernas, Peso corporal).</p><?php endif; ?>
  <?php foreach ($rows as $m): ?>
    <div class="flex items-center gap-3 px-4 py-3">
      <div class="w-12 h-12 rounded-md bg-dark/5 overflow-hidden shrink-0"><?php if ($m['foto']): ?><img src="<?= e(media_url($m['foto'])) ?>" alt="" class="w-full h-full object-cover"><?php endif; ?></div>
      <a href="<?= e(url('admin/maquina', ['id' => $m['id']])) ?>" class="flex-1 min-w-0"><div class="font-medium truncate"><?= e($m['nombre']) ?><?= $m['es_zona'] ? ' <span class="chip chip-neutral ml-1">zona</span>' : '' ?></div><div class="text-sm text-dark/55"><?= e($m['zona'] ?? '') ?> <?= (int) $m['n_ej'] ?> ejercicios</div></a>
      <button type="button" class="chip <?= $m['estado'] === 'mantencion' ? 'chip-warn' : 'chip-ok' ?>" data-mantencion="<?= (int) $m['id'] ?>" data-estado="<?= $m['estado'] === 'mantencion' ? 'ok' : 'mantencion' ?>"><?= $m['estado'] === 'mantencion' ? 'En mantención' : 'Operativa' ?></button>
    </div>
  <?php endforeach; ?>
</div>
