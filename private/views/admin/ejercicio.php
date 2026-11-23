<?php
partial('shared/head', ['t' => $title, 'back' => url('admin/ejercicios')]);
$sec = array_map('intval', explode(',', (string) ($ej['grupos_secundarios'] ?? '')));
$restr = tags_list($ej['restricciones'] ?? '');
?>
<form method="post" enctype="multipart/form-data" class="grid lg:grid-cols-3 gap-6">
  <?= csrf_field() ?>
  <div class="lg:col-span-2 space-y-5">
    <section class="card p-5 grid sm:grid-cols-2 gap-4">
      <div class="sm:col-span-2"><label class="label">Nombre *</label><input name="nombre" class="input" required value="<?= e($ej['nombre'] ?? '') ?>" placeholder="Ej: Prensa 45°"></div>
      <div><label class="label">Máquina o zona</label><select name="maquina_id" class="input"><option value="">Sin máquina (peso corporal)</option><?php foreach ($maquinas as $m): ?><option value="<?= (int) $m['id'] ?>" <?= ($ej['maquina_id'] ?? '') == $m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?><?= $m['es_zona'] ? ' (zona)' : '' ?></option><?php endforeach; ?></select></div>
      <div><label class="label">Grupo principal</label><select name="grupo_id" class="input"><option value="">—</option><?php foreach ($grupos as $g): ?><option value="<?= (int) $g['id'] ?>" <?= ($ej['grupo_id'] ?? '') == $g['id'] ? 'selected' : '' ?>><?= e($g['nombre']) ?></option><?php endforeach; ?></select></div>
      <div class="sm:col-span-2"><label class="label">Grupos secundarios</label><div class="flex flex-wrap gap-2"><?php foreach ($grupos as $g): ?><label class="pill"><input type="checkbox" name="grupos_secundarios[]" value="<?= (int) $g['id'] ?>" <?= in_array((int) $g['id'], $sec, true) ? 'checked' : '' ?>><span><?= e($g['nombre']) ?></span></label><?php endforeach; ?></div></div>
    </section>

    <section class="card p-5 space-y-4">
      <h2 class="font-semibold">Instrucción</h2>
      <div><label class="label">Puntos de técnica</label><textarea name="instrucciones" rows="4" class="input" placeholder="Un punto por línea"><?= e($ej['instrucciones'] ?? '') ?></textarea></div>
      <div><label class="label">Errores comunes</label><textarea name="errores" rows="3" class="input" placeholder="Un error por línea"><?= e($ej['errores'] ?? '') ?></textarea></div>
    </section>

    <section class="card p-5 grid sm:grid-cols-2 gap-5">
      <div>
        <h2 class="font-semibold">Foto de la máquina</h2>
        <?php if (!empty($ej['foto'])): ?><img src="<?= e(media_url($ej['foto'])) ?>" class="mt-2 w-full aspect-video object-cover rounded-md bg-dark/5" alt=""><?php endif; ?>
        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="file mt-2">
        <p class="text-xs text-dark/50 mt-1">JPG, PNG o WebP · máx <?= (int) setting('foto_max_mb', '8') ?> MB</p>
      </div>
      <div>
        <h2 class="font-semibold">Video</h2>
        <?php if (!empty($ej['video'])): ?><video src="<?= e(media_url($ej['video'])) ?>" class="mt-2 w-full aspect-video rounded-md bg-dark/5" muted loop autoplay playsinline></video><?php endif; ?>
        <input type="file" name="video" accept="video/mp4,video/webm" class="file mt-2">
        <p class="text-xs text-dark/50 mt-1">MP4 en loop sin audio, ideal 5–10 s · máx <?= (int) setting('video_max_mb', '25') ?> MB. Comprímelo antes de subir.</p>
      </div>
    </section>

    <section class="card p-5">
      <h2 class="font-semibold">Alternativas</h2>
      <p class="text-sm text-dark/60">Se ofrecen cuando la máquina está ocupada o en mantención. La relación queda en ambos sentidos.</p>
      <input class="input mt-3" placeholder="Filtrar…" data-filtro-alt>
      <div class="mt-2 max-h-64 overflow-y-auto grid sm:grid-cols-2 gap-1" data-lista-alt>
        <?php foreach ($todos as $t): if ($ej && (int) $t['id'] === (int) $ej['id']) continue; ?>
          <label class="flex items-center gap-2 text-sm py-1"><input type="checkbox" name="alternativas[]" value="<?= (int) $t['id'] ?>" class="checkbox" <?= in_array((int) $t['id'], $alts, true) ? 'checked' : '' ?>><span><?= e($t['nombre']) ?> <span class="text-dark/45"><?= e($t['grupo'] ?? '') ?></span></span></label>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <aside class="space-y-5">
    <section class="card p-5 space-y-3">
      <h2 class="font-semibold">Valores por defecto</h2>
      <div class="grid grid-cols-3 gap-2">
        <div><label class="label">Series</label><input type="number" name="series_def" min="1" max="10" class="input" value="<?= (int) ($ej['series_def'] ?? 3) ?>"></div>
        <div><label class="label">Reps mín</label><input type="number" name="reps_min" min="1" max="100" class="input" value="<?= (int) ($ej['reps_min'] ?? 8) ?>"></div>
        <div><label class="label">Reps máx</label><input type="number" name="reps_max" min="1" max="100" class="input" value="<?= (int) ($ej['reps_max'] ?? 12) ?>"></div>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <div><label class="label">Descanso (s)</label><input type="number" name="descanso_seg" min="0" max="600" step="15" class="input" value="<?= (int) ($ej['descanso_seg'] ?? 90) ?>"></div>
        <div><label class="label">Seg. por serie</label><input type="number" name="seg_por_serie" min="10" max="300" class="input" value="<?= (int) ($ej['seg_por_serie'] ?? 45) ?>"></div>
      </div>
      <p class="text-xs text-dark/50">El tiempo por serie se recalibra solo con datos reales.</p>
      <div><label class="label">Incremento de progresión (kg)</label><input name="incremento_kg" class="input" inputmode="decimal" value="<?= e($ej['incremento_kg'] ?? '2.5') ?>"></div>
      <div><label class="label">Nivel</label><select name="nivel" class="input"><?php foreach (['todos', 'principiante', 'intermedio', 'avanzado'] as $n): ?><option <?= ($ej['nivel'] ?? 'todos') === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
    </section>
    <section class="card p-5">
      <h2 class="font-semibold">Evitar o advertir si el alumno tiene…</h2>
      <div class="mt-3 flex flex-wrap gap-2"><?php foreach (restricciones_catalogo() as $t): ?><label class="pill"><input type="checkbox" name="restricciones[]" value="<?= e($t) ?>" <?= in_array($t, $restr, true) ? 'checked' : '' ?>><span><?= e(ucfirst($t)) ?></span></label><?php endforeach; ?></div>
    </section>
    <section class="card p-5">
      <label class="toggle"><input type="checkbox" name="activo" value="1" <?= ($ej['activo'] ?? 1) ? 'checked' : '' ?>><span>Activo</span></label>
      <button class="mt-4 btn-primary w-full">Guardar ejercicio</button>
    </section>
  </aside>
</form>

<?php if ($ej): ?>
<div class="mt-6 grid lg:grid-cols-3 gap-6">
  <section class="lg:col-span-2 card p-5">
    <h2 class="font-semibold">QR y NFC del ejercicio</h2>
    <p class="text-sm text-dark/60 mt-1"><?= $ej['maquina_id'] ? 'Este ejercicio usa una máquina: lo normal es pegar el QR/NFC en la máquina (abre la lista de sus ejercicios). Este código sirve si quieres uno específico.' : 'Para ejercicios sin máquina (zona de mancuernas, peso corporal).' ?></p>
    <?php partial('shared/qr_nfc', ['qrUrl' => abs_url('', ['e' => $ej['token']]), 'nombre' => $ej['nombre']]); ?>
  </section>
  <section class="card p-5 text-sm space-y-2">
    <h2 class="font-semibold text-[15px]">Archivos</h2>
    <?php foreach (['foto' => 'Foto', 'video' => 'Video'] as $c => $l): if (empty($ej[$c])) continue; ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="borrar_media"><input type="hidden" name="campo" value="<?= $c ?>"><button class="btn-ghost btn-sm text-danger" data-confirm="¿Eliminar <?= mb_strtolower($l) ?>?">Eliminar <?= mb_strtolower($l) ?></button></form>
    <?php endforeach; ?>
    <a class="text-primary" href="<?= e(url('ejercicio', ['id' => $ej['id']])) ?>">Ver como alumno →</a>
  </section>
</div>
<?php endif; ?>
