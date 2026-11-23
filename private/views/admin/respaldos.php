<?php partial('shared/head', ['t' => 'Respaldos', 'sub' => 'Copia completa de la base de datos. El cron crea uno diario y guarda los últimos 30. Base actual: ' . round($db / 1048576, 2) . ' MB.',
    'actions' => '<form method="post">' . csrf_field() . '<button name="accion" value="crear" class="btn-primary btn-sm">' . icon('cloud', 'w-4 h-4') . 'Crear respaldo</button></form>']); ?>
<div class="card divide-y divide-dark/5">
  <?php if (!$rows): ?><p class="p-4 text-sm text-dark/55">Aún no hay respaldos.</p><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <div class="flex items-center gap-3 px-4 py-3 text-sm">
      <span class="flex-1 font-mono text-xs"><?= e($r['f']) ?></span><span class="text-dark/55"><?= e($r['fecha']) ?> · <?= round($r['size'] / 1048576, 2) ?> MB</span>
      <a class="btn-ghost btn-sm" href="<?= e(url('admin/respaldos', ['descargar' => $r['f']])) ?>"><?= icon('download', 'w-4 h-4') ?></a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="borrar"><input type="hidden" name="f" value="<?= e($r['f']) ?>"><button class="btn-ghost btn-sm text-dark/40 hover:text-danger" data-confirm="¿Borrar respaldo?"><?= icon('delete', 'w-4 h-4') ?></button></form>
    </div>
  <?php endforeach; ?>
</div>
<p class="mt-4 text-sm text-dark/60">Para restaurar: reemplaza <code class="text-xs">private/storage/db/gorila.sqlite</code> por el respaldo (renombrándolo) y elimina los archivos <code class="text-xs">-wal</code> y <code class="text-xs">-shm</code>. Descarga copias periódicamente fuera del servidor.</p>
