<?php partial('shared/head', ['t' => 'Plantillas', 'sub' => 'Rutinas base del gimnasio. Cualquier coach parte desde aquí. Objetivo: ~' . (int) setting('plantilla_objetivo_min', '75') . ' min para dejar margen dentro de los ' . (int) setting('sesion_max_min', '90') . '.']); ?>
<form method="post" class="card p-4 flex gap-2 mb-5 max-w-lg">
  <?= csrf_field() ?><input type="hidden" name="accion" value="crear">
  <input name="nombre" class="input flex-1" placeholder="Nombre (ej: Pierna hipertrofia)" required>
  <button class="btn-primary">Crear</button>
</form>
<div class="card divide-y divide-dark/5">
  <?php if (!$rows): ?><p class="p-4 text-sm text-dark/55">Sin plantillas.</p><?php endif; ?>
  <?php foreach ($rows as $p): $obj = (int) setting('plantilla_objetivo_min', '75'); ?>
    <div class="flex items-center gap-3 px-4 py-3 <?= $p['activo'] ? '' : 'opacity-50' ?>">
      <a href="<?= e(url('admin/plantilla', ['id' => $p['id']])) ?>" class="flex-1 min-w-0"><div class="font-medium"><?= e($p['nombre']) ?></div><div class="text-sm text-dark/55"><?= (int) $p['n'] ?> ejercicios · <span class="<?= $p['min'] > $obj ? 'text-warning' : '' ?>">~<?= (int) $p['min'] ?> min</span><?= $p['nivel'] !== 'todos' ? ' · ' . e($p['nivel']) : '' ?></div></a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="duplicar"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn-ghost btn-sm">Duplicar</button></form>
    </div>
  <?php endforeach; ?>
</div>
