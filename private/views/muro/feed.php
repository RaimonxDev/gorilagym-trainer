<?php partial('shared/head', ['t' => 'La jungla', 'sub' => 'Solo lo ven alumnos y staff del gimnasio.']); ?>

<section class="card p-4">
  <div class="flex items-center gap-2 text-sm text-dark/60"><span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full rounded-full bg-primary/40 animate-ping"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-primary"></span></span>En la jungla ahora</div>
  <div class="mt-1 text-2xl font-semibold"><?= (int) $sala['presentes'] ?> <span class="text-base font-normal text-dark/60">personas entrenando</span></div>
  <?php if ($sala['grupos']): ?><div class="mt-2 flex flex-wrap gap-1.5"><?php foreach ($sala['grupos'] as $g): ?><span class="chip chip-neutral"><?= e($g['nombre']) ?> · <?= (int) $g['n'] ?></span><?php endforeach; ?></div><?php endif; ?>
</section>

<button type="button" class="mt-4 w-full btn-secondary" data-historia data-texto="<?= e(($mio['texto'] ?? '') ?: 'Entrenando en la jungla') ?>" data-ig="<?= e(setting('brand_instagram') ? '@' . ltrim(setting('brand_instagram'), '@') : '') ?>"><?= icon('image', 'w-5 h-5') ?>Foto para tu historia de Instagram</button>

<?php if ($u['rol'] === 'alumno' && !$u['muro_opt_in']): ?>
  <form method="post" class="mt-4 card p-4 flex flex-col sm:flex-row sm:items-center gap-3">
    <?= csrf_field() ?>
    <p class="flex-1 text-sm text-dark/70">Hoy no apareces en el muro. Si te sumas, puedes publicar tu estado del día y reaccionar a los demás.</p>
    <button class="btn-secondary" name="activar" value="1">Sumarme</button>
  </form>
<?php elseif ($puedePublicar): ?>
  <form method="post" class="mt-4 card p-4 space-y-3">
    <?= csrf_field() ?>
    <div class="flex items-center justify-between gap-2"><span class="text-sm text-dark/60">¿Cómo viene tu día?</span><?php if ($limite > 1): ?><span class="text-xs text-dark/45">Te quedan <?= (int) $restantes ?> hoy</span><?php endif; ?></div>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($estados as $es): ?>
        <label class="pill"><input type="radio" name="estado" value="<?= e($es) ?>"><span><?= e($es) ?></span></label>
      <?php endforeach; ?>
    </div>
    <?php partial('shared/musica_picker', ['placeholder' => 'Agrega una canción (opcional)']); ?>
    <button class="btn-primary btn-sm">Publicar</button>
  </form>
<?php else: ?>
  <div class="mt-4 rounded-lg border border-dark/10 bg-white px-4 py-3 text-sm text-dark/65"><?= $limite === 1 ? 'Ya publicaste tu estado de hoy.' : 'Ya hiciste tus publicaciones de hoy.' ?> Mañana puedes publicar de nuevo 🦍</div>
<?php endif; ?>

<div class="mt-6 space-y-3" data-muro>
  <?php if (!$posts): ?><p class="text-sm text-dark/55 text-center py-8">Todavía nadie publica hoy. Sé el primero.</p><?php endif; ?>
  <?php foreach ($posts as $p): $propio = (int) $p['user_id'] === (int) $u['id']; ?>
    <article class="card p-4" data-post="<?= (int) $p['id'] ?>">
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <?= avatar($p, 'w-10 h-10') ?>
          <div class="min-w-0"><div class="font-medium leading-tight truncate"><?= e(nombre_corto($p)) ?></div><div class="text-xs text-dark/50"><?= e(fecha_post($p['created_at'])) ?></div></div>
        </div>
        <?php if ($propio || can('muro.moderar')): ?>
          <button type="button" class="text-dark/35 hover:text-danger p-1 -m-1" data-borrar-post="<?= (int) $p['id'] ?>" aria-label="Borrar publicación"><?= icon('delete', 'w-[18px] h-[18px]') ?></button>
        <?php else: ?>
          <button type="button" class="text-dark/30 hover:text-dark/60 p-1 -m-1" data-reportar="<?= (int) $p['id'] ?>" aria-label="Reportar"><?= icon('flag', 'w-4 h-4') ?></button>
        <?php endif; ?>
      </div>
      <?php if ($p['texto']): ?><p class="mt-3 text-[15px] <?= $p['tipo'] === 'logro' ? 'font-medium text-primary flex items-center gap-1.5' : '' ?>"><?= $p['tipo'] === 'logro' ? icon('trophy', 'w-4 h-4') : '' ?><?= e($p['texto']) ?></p><?php endif; ?>
      <?php if ($p['spotify_id']): ?><div class="mt-3"><?= musica_card($p) ?></div><?php endif; ?>
      <div class="mt-3 flex items-center gap-5">
        <?php foreach (['🔥', '💪', '🦍'] as $em): $n = $reac[$p['id']][$em] ?? 0; $on = isset($mias[$p['id']][$em]); ?>
          <?php if ($propio): ?>
            <span class="react is-mine"><span class="react-emoji"><?= $em ?></span><span class="react-n"><?= $n ?: '' ?></span></span>
          <?php else: ?>
            <button type="button" class="react <?= $on ? 'is-on' : '' ?>" data-react="<?= (int) $p['id'] ?>" data-emoji="<?= $em ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>"><span class="react-emoji"><?= $em ?></span><span class="react-n" data-n><?= $n ?: '' ?></span></button>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
