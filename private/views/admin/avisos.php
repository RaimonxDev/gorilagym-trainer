<?php partial('shared/head', ['t' => 'Avisos', 'sub' => 'Banner en la app y, si quieres, push a quienes activaron notificaciones (' . $subs . ' personas).']); ?>
<form method="post" class="card p-5 grid sm:grid-cols-2 gap-4 max-w-2xl">
  <?= csrf_field() ?>
  <div class="sm:col-span-2"><label class="label">Título *</label><input name="titulo" class="input" maxlength="120" required placeholder="Ej: Lunes 18 cerramos a las 14:00"></div>
  <div class="sm:col-span-2"><label class="label">Detalle</label><textarea name="cuerpo" class="input" rows="2" maxlength="500"></textarea></div>
  <div><label class="label">Mostrar desde</label><input type="datetime-local" name="desde" class="input" value="<?= date('Y-m-d\TH:i') ?>"></div>
  <div><label class="label">Hasta (opcional)</label><input type="datetime-local" name="hasta" class="input"></div>
  <label class="toggle sm:col-span-2"><input type="checkbox" name="push" value="1"><span>Enviar también como notificación push</span></label>
  <div class="sm:col-span-2"><button class="btn-primary">Publicar</button></div>
</form>
<div class="mt-6 card divide-y divide-dark/5">
  <?php if (!$rows): ?><p class="p-4 text-sm text-dark/55">Sin avisos.</p><?php endif; ?>
  <?php foreach ($rows as $a): $vig = $a['desde'] <= date('Y-m-d H:i:s') && (!$a['hasta'] || $a['hasta'] >= date('Y-m-d H:i:s')); ?>
    <div class="flex items-start gap-3 px-4 py-3">
      <div class="flex-1"><div class="font-medium"><?= e($a['titulo']) ?> <?= $vig ? '<span class="chip chip-ok ml-1">visible</span>' : '' ?><?= $a['push'] ? '<span class="chip chip-neutral ml-1">push</span>' : '' ?></div>
        <?php if ($a['cuerpo']): ?><div class="text-sm text-dark/65"><?= e($a['cuerpo']) ?></div><?php endif; ?>
        <div class="text-xs text-dark/50 mt-1"><?= e(fecha($a['desde'], 'd/m H:i')) ?><?= $a['hasta'] ? ' → ' . e(fecha($a['hasta'], 'd/m H:i')) : '' ?> · <?= e($a['autor']) ?></div></div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="borrar"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="text-dark/40 hover:text-danger" data-confirm="¿Eliminar aviso?" aria-label="Eliminar"><?= icon('delete', 'w-4 h-4') ?></button></form>
    </div>
  <?php endforeach; ?>
</div>
