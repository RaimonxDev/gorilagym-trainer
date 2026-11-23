<?php partial('shared/head', ['t' => 'Pase Diario', 'sub' => 'Acceso por un día para visitas, acompañantes o periodos cortos. No existe pase de prueba.', 'back' => url('coach')]); ?>
<form method="post" class="card p-5 grid sm:grid-cols-2 gap-4">
  <?= csrf_field() ?>
  <div><label class="label">Tipo de documento</label><select name="doc_tipo" class="input" data-doc-select><option value="rut">RUT</option><option value="pasaporte">Pasaporte / DNI</option></select></div>
  <div><label class="label">Documento</label><input name="doc" class="input" placeholder="Opcional si no tiene cuenta" data-rut></div>
  <div class="sm:col-span-2"><label class="label">Nombre</label><input name="nombre" class="input" placeholder="Si ya es alumno registrado, se completa solo"></div>
  <div><label class="label">Monto</label><input name="monto" class="input" inputmode="numeric" value="<?= e(setting('pase_diario_precio')) ?>" data-money></div>
  <div class="sm:col-span-2"><button class="btn-primary">Registrar Pase Diario</button></div>
</form>
<h2 class="section-title mt-8">Hoy</h2>
<div class="mt-2 card divide-y divide-dark/5">
  <?php if (!$hoy): ?><p class="p-4 text-sm text-dark/55">Sin pases hoy.</p><?php endif; ?>
  <?php foreach ($hoy as $p): ?><div class="flex justify-between px-4 py-3 text-sm"><span><?= e($p['nombre']) ?><?= $p['doc_num'] ? ' · ' . e($p['doc_num']) : '' ?></span><span class="text-dark/55"><?= money($p['monto']) ?> · <?= e($p['staff']) ?></span></div><?php endforeach; ?>
</div>
