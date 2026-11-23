<?php partial('shared/head', ['t' => $title, 'back' => url('admin/planes')]);
$mods = explode(',', (string) ($p['beneficio_modalidades'] ?? ''));
$fmt = fn($v) => $v !== null && $v !== '' ? number_format((int) $v, 0, '', '.') : '';
?>
<form method="post" class="space-y-5 max-w-3xl">
  <?= csrf_field() ?>
  <section class="card p-5 grid sm:grid-cols-2 gap-4">
    <div><label class="label">Nombre *</label><input name="nombre" class="input" required value="<?= e($p['nombre'] ?? '') ?>"></div>
    <div><label class="label">Tipo</label><select name="tipo" class="input"><option value="coach" <?= ($p['tipo'] ?? 'coach') === 'coach' ? 'selected' : '' ?>>Con coach (semipersonalizado)</option><option value="libre" <?= ($p['tipo'] ?? '') === 'libre' ? 'selected' : '' ?>>Libre (sin coach)</option></select><p class="text-xs text-dark/55 mt-1">Plan libre: sin coach, solo entrenos del gimnasio y muro (o sin app, según Marca y ajustes → General).</p></div>
    <div class="sm:col-span-2"><label class="label">Descripción</label><input name="descripcion" class="input" value="<?= e($p['descripcion'] ?? '') ?>"></div>
    <label class="toggle"><input type="checkbox" name="requiere_estudiante" value="1" <?= !empty($p['requiere_estudiante']) ? 'checked' : '' ?>><span>Requiere acreditar condición de estudiante</span></label>
    <label class="toggle"><input type="checkbox" name="activo" value="1" <?= ($p['activo'] ?? 1) ? 'checked' : '' ?>><span>Activo</span></label>
  </section>
  <section class="card p-5">
    <h2 class="font-semibold">Precios</h2>
    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
      <?php foreach (['mensual', 'trimestral', 'semestral', 'anual'] as $m): ?>
        <div><label class="label"><?= ucfirst($m) ?></label><input name="precio_<?= $m ?>" class="input" inputmode="numeric" data-money value="<?= e($fmt($p['precio_' . $m] ?? null)) ?>" placeholder="—"></div>
      <?php endforeach; ?>
    </div>
    <div class="mt-4 grid sm:grid-cols-2 gap-3">
      <div><label class="label">Beneficio de regalo</label><input name="beneficio" class="input" value="<?= e($p['beneficio'] ?? '') ?>" placeholder="Ej: Shaker + creatina"></div>
      <div><label class="label">Aplica en</label><div class="flex flex-wrap gap-2 pt-1"><?php foreach (['trimestral', 'semestral', 'anual'] as $m): ?><label class="pill"><input type="checkbox" name="beneficio_modalidades[]" value="<?= $m ?>" <?= in_array($m, $mods, true) ? 'checked' : '' ?>><span><?= ucfirst($m) ?></span></label><?php endforeach; ?></div></div>
    </div>
  </section>
  <section class="card p-5">
    <h2 class="font-semibold">Horario de acceso</h2>
    <p class="text-sm text-dark/60">Deja vacío el día que no tiene acceso. Fuera de este horario el check-in se rechaza.</p>
    <div class="mt-3 space-y-2">
      <?php for ($d = 1; $d <= 7; $d++): ?>
        <div class="grid grid-cols-[100px_1fr_1fr] gap-2 items-center">
          <span class="text-sm"><?= dia_nombre($d) ?></span>
          <input type="time" name="h[<?= $d ?>][ini]" class="input py-2" value="<?= e($h[$d]['hora_inicio'] ?? '') ?>">
          <input type="time" name="h[<?= $d ?>][fin]" class="input py-2" value="<?= e($h[$d]['hora_fin'] ?? '') ?>">
        </div>
      <?php endfor; ?>
    </div>
  </section>
  <input type="hidden" name="orden" value="<?= (int) ($p['orden'] ?? 0) ?>">
  <button class="btn-primary">Guardar plan</button>
</form>
