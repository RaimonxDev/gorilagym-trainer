<?php partial('shared/head', ['t' => 'Importar Excel', 'sub' => 'Crea o actualiza alumnos y sus vigencias desde el Excel del gimnasio. Nunca borra datos.']); ?>

<?php if ($paso === 'subir'): ?>
  <form method="post" enctype="multipart/form-data" class="card p-5 max-w-xl space-y-4">
    <?= csrf_field() ?><input type="hidden" name="accion" value="subir">
    <div><label class="label">Archivo (.xlsx o .csv)</label><input type="file" name="archivo" accept=".xlsx,.csv" class="file" required></div>
    <p class="text-sm text-dark/60">Columnas útiles: RUT, nombre, apellido, correo, teléfono, plan, modalidad, fecha de inicio y vencimiento. En el siguiente paso eliges qué columna es cada cosa.</p>
    <button class="btn-primary"><?= icon('upload', 'w-4 h-4') ?>Subir y revisar</button>
  </form>
<?php else: ?>
  <div class="mb-4 text-sm text-dark/60">Archivo: <b class="font-medium text-dark"><?= e($d['archivo']) ?></b> · <?= (int) $d['total'] ?> filas · <a class="text-primary" href="<?= e(url('admin/importar')) ?>">subir otro</a></div>
  <form method="post" class="space-y-5">
    <?= csrf_field() ?>
    <section class="card p-5">
      <h2 class="font-semibold">1. ¿Qué columna es cada dato?</h2>
      <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="encabezado" value="1" class="checkbox" <?= $d['header'] ? 'checked' : '' ?>> La primera fila son títulos</label>
      <div class="mt-4 grid sm:grid-cols-2 gap-3">
        <?php foreach ($d['campos'] as $k => $l): ?>
          <div><label class="label"><?= e($l) ?></label>
            <select name="map[<?= $k ?>]" class="input"><option value="">— No viene —</option>
              <?php foreach ($d['headers'] as $i => $hname): ?><option value="<?= $i ?>" <?= isset($d['map'][$k]) && (string) $d['map'][$k] === (string) $i ? 'selected' : '' ?>><?= e(chr(65 + ($i % 26))) ?> · <?= e(mb_substr((string) $hname, 0, 40)) ?></option><?php endforeach; ?>
            </select></div>
        <?php endforeach; ?>
      </div>
      <div class="mt-4 overflow-x-auto"><table class="table text-sm"><thead><tr><?php foreach ($d['headers'] as $i => $hname): ?><th><?= e(chr(65 + ($i % 26))) ?> · <?= e($hname) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($d['muestra'] as $r): ?><tr><?php foreach ($d['headers'] as $i => $_): ?><td><?= e($r[$i] ?? '') ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
      <button name="accion" value="validar" class="mt-4 btn-secondary">2. Validar sin guardar</button>
    </section>

    <?php if ($paso === 'revisar' && $d['res']): $r = $d['res']; ?>
    <section class="card p-5">
      <h2 class="font-semibold">Resultado de la validación</h2>
      <div class="mt-2 text-[15px]"><b class="font-medium"><?= (int) $r['ok'] ?></b> de <?= (int) $r['total'] ?> filas listas para importar.</div>
      <?php if ($r['duplicados'] || $r['errores']): ?>
        <details class="mt-3" open><summary class="cursor-pointer text-sm text-warning"><?= count($r['errores']) + count($r['duplicados']) ?> advertencias</summary>
          <ul class="mt-2 text-sm text-dark/70 space-y-1 max-h-60 overflow-y-auto"><?php foreach (array_merge($r['duplicados'], $r['errores']) as $er): ?><li>· <?= e($er) ?></li><?php endforeach; ?></ul></details>
      <?php endif; ?>
      <div class="mt-4 overflow-x-auto"><table class="table text-sm"><thead><tr><th>Documento</th><th>Nombre</th><th>Correo</th><th>Plan</th><th>Vence</th></tr></thead>
        <tbody><?php foreach ($r['preview'] as $p): ?><tr><td><?= e($p['tipo'] === 'rut' ? rut_format($p['doc']) : $p['doc']) ?></td><td><?= e($p['nombre'] . ' ' . $p['apellido']) ?></td><td><?= e($p['email'] ?: '—') ?></td><td><?= e($p['planTxt']) ?> <?= e($p['modalidad']) ?></td><td><?= e($p['ff'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div>
      <button name="accion" value="aplicar" class="mt-4 btn-primary" data-confirm="¿Aplicar la importación? Se crearán y actualizarán alumnos.">3. Aplicar importación</button>
    </section>
    <?php endif; ?>
  </form>
<?php endif; ?>

<?php if ($hist): ?>
<h2 class="section-title mt-8">Historial</h2>
<div class="mt-2 card divide-y divide-dark/5 text-sm">
  <?php foreach ($hist as $h): $errs = json_decode((string) $h['errores'], true) ?: []; ?>
    <div class="px-4 py-3"><div class="flex justify-between"><span class="font-medium"><?= e($h['archivo']) ?></span><span class="text-dark/55"><?= e(fecha($h['created_at'], 'd/m/Y H:i')) ?> · <?= e($h['por']) ?></span></div>
      <div class="text-dark/60"><?= (int) $h['creados'] ?> nuevos · <?= (int) $h['actualizados'] ?> actualizados · <?= count($errs) ?> advertencias</div>
      <?php if ($errs): ?><details><summary class="cursor-pointer text-dark/50 text-xs mt-1">Ver advertencias</summary><ul class="mt-1 text-xs text-dark/60"><?php foreach (array_slice($errs, 0, 100) as $er): ?><li>· <?= e($er) ?></li><?php endforeach; ?></ul></details><?php endif; ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
