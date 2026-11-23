<?php partial('shared/head', ['t' => $title, 'back' => url('admin/maquinas')]); ?>
<div class="grid lg:grid-cols-2 gap-6">
  <form method="post" enctype="multipart/form-data" class="card p-5 space-y-4">
    <?= csrf_field() ?>
    <div><label class="label">Nombre *</label><input name="nombre" class="input" required value="<?= e($m['nombre'] ?? '') ?>" placeholder="Ej: Polea alta"></div>
    <div><label class="label">Zona / sector</label><input name="zona" class="input" value="<?= e($m['zona'] ?? '') ?>" placeholder="Ej: Piso 1, sector peso libre"></div>
    <label class="toggle"><input type="checkbox" name="es_zona" value="1" <?= !empty($m['es_zona']) ? 'checked' : '' ?>><span>Es una zona, no una máquina (mancuernas, peso corporal). No se marca como ocupada.</span></label>
    <div><label class="label">Estado</label><select name="estado" class="input"><option value="ok" <?= ($m['estado'] ?? 'ok') === 'ok' ? 'selected' : '' ?>>Operativa</option><option value="mantencion" <?= ($m['estado'] ?? '') === 'mantencion' ? 'selected' : '' ?>>En mantención (sale de las sugerencias)</option></select></div>
    <div><label class="label">Foto</label><?php if (!empty($m['foto'])): ?><img src="<?= e(media_url($m['foto'])) ?>" alt="" class="mb-2 w-full aspect-video object-cover rounded-md bg-dark/5"><?php endif; ?><input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="file"></div>
    <button class="btn-primary">Guardar</button>
  </form>

  <?php if ($m): ?>
  <div class="space-y-6">
    <section class="card p-5">
      <h2 class="font-semibold">QR y NFC</h2>
      <?php partial('shared/qr_nfc', ['qrUrl' => abs_url('', ['m' => $m['token']]), 'nombre' => $m['nombre']]); ?>
      <div class="mt-4 flex flex-wrap gap-2">
        <a target="_blank" href="<?= e(url('admin/qr', ['ids' => $m['id']])) ?>" class="btn-ghost btn-sm"><?= icon('print', 'w-4 h-4') ?>Imprimir</a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="nuevo_token"><button class="btn-ghost btn-sm" data-confirm="Los QR y tags actuales dejarán de funcionar. ¿Continuar?">Regenerar código</button></form>
      </div>
    </section>
    <section class="card p-5">
      <h2 class="font-semibold">Ejercicios en esta máquina</h2>
      <div class="mt-2 text-sm divide-y divide-dark/5"><?php foreach ($ejs as $e): ?><a href="<?= e(url('admin/ejercicio', ['id' => $e['id']])) ?>" class="block py-2"><?= e($e['nombre']) ?></a><?php endforeach; ?><?php if (!$ejs): ?><p class="text-dark/55 py-2">Ninguno aún.</p><?php endif; ?></div>
      <a href="<?= e(url('admin/ejercicio')) ?>" class="mt-2 inline-block text-sm text-primary">+ Nuevo ejercicio</a>
    </section>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="borrar"><button class="btn-ghost btn-sm text-danger" data-confirm="¿Eliminar la máquina?">Eliminar máquina</button></form>
  </div>
  <?php endif; ?>
</div>
