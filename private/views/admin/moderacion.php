<?php partial('shared/head', ['t' => 'Moderación del muro']); ?>
<h2 class="section-title">Reportadas</h2>
<div class="mt-2 card divide-y divide-dark/5">
  <?php if (!$rep): ?><p class="p-4 text-sm text-dark/55">Sin reportes pendientes.</p><?php endif; ?>
  <?php foreach ($rep as $p): ?>
    <div class="px-4 py-3">
      <div class="flex justify-between text-sm"><b class="font-medium"><?= e(trim($p['nombre'] . ' ' . $p['apellido'])) ?></b><span class="text-danger"><?= (int) $p['n'] ?> reportes</span></div>
      <div class="mt-1"><?= e($p['texto']) ?><?= $p['spotify_id'] ? ' · 🎵 Spotify' : '' ?></div>
      <?php if ($p['motivos']): ?><div class="text-xs text-dark/55 mt-1">Motivos: <?= e($p['motivos']) ?></div><?php endif; ?>
      <form method="post" class="mt-2 flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button name="accion" value="ocultar" class="btn-secondary btn-sm">Ocultar</button><button name="accion" value="descartar" class="btn-ghost btn-sm">Descartar reporte</button></form>
    </div>
  <?php endforeach; ?>
</div>
<h2 class="section-title mt-8">Recientes</h2>
<div class="mt-2 card divide-y divide-dark/5 text-sm">
  <?php foreach ($rec as $p): ?>
    <div class="flex items-center gap-3 px-4 py-2.5 <?= $p['oculto'] ? 'opacity-50' : '' ?>">
      <div class="flex-1 min-w-0"><b class="font-medium"><?= e($p['nombre']) ?></b> · <?= e($p['texto']) ?><?= $p['spotify_id'] ? ' · 🎵' : '' ?> <span class="text-dark/45"><?= e(hace($p['created_at'])) ?></span></div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button name="accion" value="<?= $p['oculto'] ? 'mostrar' : 'ocultar' ?>" class="btn-ghost btn-sm"><?= $p['oculto'] ? 'Mostrar' : 'Ocultar' ?></button></form>
    </div>
  <?php endforeach; ?>
</div>
