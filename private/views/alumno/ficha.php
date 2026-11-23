<?php
$resp = [];
foreach (json_decode((string) ($f['cuestionario'] ?? '[]'), true) ?: [] as $r) $resp[$r['p']] = $r['r'];
$tags = tags_list($f['restricciones'] ?? '');
partial('shared/head', ['t' => 'Ficha de ingreso', 'sub' => $primera ? 'Antes de partir: 1 minuto para que tu coach sepa tu objetivo y cualquier restricción.' : 'Objetivo, nivel y restricciones.', 'back' => $propio ? ($primera ? null : url('perfil')) : url('coach/alumno', ['id' => $aid])]);
?>
<form method="post" class="space-y-6">
  <?= csrf_field() ?>
  <?php if (!$propio): ?><input type="hidden" name="alumno" value="<?= (int) $aid ?>"><?php endif; ?>
  <section class="card p-5 space-y-4">
    <div>
      <label class="label">¿Cuál es tu objetivo principal?</label>
      <div class="grid grid-cols-2 gap-2 text-sm">
        <?php foreach (['Ganar fuerza', 'Ganar masa muscular', 'Bajar grasa', 'Salud y movilidad', 'Rendimiento deportivo', 'Volver a entrenar'] as $o): ?>
          <label class="option"><input type="radio" name="objetivo" value="<?= e($o) ?>" <?= ($f['objetivo'] ?? '') === $o ? 'checked' : '' ?>><span><?= e($o) ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="label">Experiencia</label>
        <select name="nivel" class="input"><?php foreach (['principiante' => 'Principiante', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'] as $k => $l): ?><option value="<?= $k ?>" <?= ($f['nivel'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div><label class="label">Días por semana</label><input type="number" name="dias_semana" min="1" max="6" class="input" value="<?= (int) ($f['dias_semana'] ?? 3) ?>"></div>
    </div>
  </section>

  <section class="card p-5 space-y-4">
    <div>
      <h2 class="font-semibold">Lesiones o restricciones</h2>
      <p class="text-sm text-dark/60 mt-0.5">Marca lo que aplique. El sistema evita o advierte ejercicios que puedan afectarte.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <?php foreach (restricciones_catalogo() as $t): ?>
        <label class="pill"><input type="checkbox" name="restricciones[]" value="<?= e($t) ?>" <?= in_array($t, $tags, true) ? 'checked' : '' ?>><span><?= e(ucfirst($t)) ?></span></label>
      <?php endforeach; ?>
    </div>
    <div><label class="label">Detalle (opcional)</label><textarea name="restricciones_detalle" rows="2" class="input" placeholder="Ej: operado de rodilla izquierda en 2023"><?= e($f['restricciones_detalle'] ?? '') ?></textarea></div>
  </section>

  <section class="card p-5 space-y-3">
    <h2 class="font-semibold">Preguntas de salud</h2>
    <?php foreach ($preg as $i => $p): $r = $resp[$p] ?? 'no'; ?>
      <div class="flex items-start justify-between gap-4 py-1">
        <span class="text-[15px]"><?= e($p) ?></span>
        <div class="seg shrink-0">
          <label><input type="radio" name="q[<?= $i ?>]" value="no" <?= $r === 'no' ? 'checked' : '' ?>><span>No</span></label>
          <label><input type="radio" name="q[<?= $i ?>]" value="si" <?= $r === 'si' ? 'checked' : '' ?>><span>Sí</span></label>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <?php if ($propio): ?>
  <label class="toggle card p-4">
    <input type="checkbox" name="consentimiento" value="1" <?= !empty($f['consentimiento_salud_at']) ? 'checked' : '' ?> required>
    <span class="text-sm">Autorizo a <?= e(setting('brand_nombre')) ?> a guardar estos datos de salud para adaptar mi entrenamiento. Solo los ve el staff autorizado y puedo pedir su eliminación cuando quiera desde mi perfil.</span>
  </label>
  <?php endif; ?>

  <button class="btn-primary w-full">Guardar ficha</button>
</form>
