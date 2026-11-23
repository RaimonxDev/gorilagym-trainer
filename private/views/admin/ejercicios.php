<?php partial('shared/head', ['t' => 'Ejercicios', 'sub' => count($rows) . ' en el catálogo', 'actions' => '<a href="' . e(url('admin/ejercicio')) . '" class="btn-primary btn-sm">' . icon('add', 'w-4 h-4') . 'Nuevo</a>']); ?>
<form class="flex flex-wrap gap-2 mb-4">
  <input type="hidden" name="r" value="admin/ejercicios">
  <input name="q" value="<?= e($q) ?>" class="input flex-1 min-w-[180px]" placeholder="Buscar">
  <select name="grupo" class="input w-auto"><option value="">Todos los grupos</option><?php foreach ($grupos as $g): ?><option value="<?= (int) $g['id'] ?>" <?= $grupo == $g['id'] ? 'selected' : '' ?>><?= e($g['nombre']) ?></option><?php endforeach; ?></select>
  <button class="btn-secondary">Filtrar</button>
</form>
<div class="card divide-y divide-dark/5">
  <?php if (!$rows): ?><p class="p-6 text-sm text-dark/55">Aún no hay ejercicios. Crea primero las máquinas (o zonas como "Mancuernas") y después los ejercicios.</p><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <a href="<?= e(url('admin/ejercicio', ['id' => $r['id']])) ?>" class="flex items-center gap-3 px-4 py-3 <?= $r['activo'] ? '' : 'opacity-50' ?>">
      <div class="w-12 h-12 rounded-md bg-dark/5 overflow-hidden shrink-0"><?php if ($r['foto']): ?><img src="<?= e(media_url($r['foto'])) ?>" alt="" class="w-full h-full object-cover"><?php endif; ?></div>
      <div class="flex-1 min-w-0"><div class="font-medium truncate"><?= e($r['nombre']) ?></div><div class="text-sm text-dark/55 truncate"><?= e(implode(' · ', array_filter([$r['grupo'], $r['maquina']]))) ?></div></div>
      <div class="flex gap-1.5 text-dark/40">
        <span title="Instrucción" class="<?= $r['instrucciones'] ? 'text-primary' : '' ?>"><?= icon('book', 'w-4 h-4') ?></span>
        <span title="Foto" class="<?= $r['foto'] ? 'text-primary' : '' ?>"><?= icon('image', 'w-4 h-4') ?></span>
        <span title="Video" class="<?= $r['video'] ? 'text-primary' : '' ?>"><?= icon('video', 'w-4 h-4') ?></span>
        <span title="Alternativas" class="<?= $r['n_alt'] ? 'text-primary' : '' ?>"><?= icon('swap', 'w-4 h-4') ?></span>
      </div>
    </a>
  <?php endforeach; ?>
</div>
