<div class="min-h-[80vh] flex flex-col justify-center max-w-sm mx-auto pt-[env(safe-area-inset-top)]">
  <?php if ($state === 'sin_correo'): ?>
    <div class="text-center">
      <div class="mx-auto w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center"><?= icon('mail', 'w-6 h-6') ?></div>
      <h1 class="mt-4 text-xl font-semibold">No tenemos tu correo</h1>
      <p class="mt-2 text-dark/70">Para enviarte el código necesitamos un correo registrado. Pídele a tu coach o en recepción que lo registren.</p>
      <a href="<?= e(url('login')) ?>" class="mt-6 inline-block btn-secondary">Volver</a>
    </div>
  <?php elseif ($state === 'sin_plan'): ?>
    <div class="text-center">
      <div class="mx-auto w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center"><?= icon('users', 'w-6 h-6') ?></div>
      <h1 class="mt-4 text-xl font-semibold"><?= $motivo === MSG_SOLO_SEMIPERSONALIZADOS ? 'La app es para planes con coach' : 'Sin plan registrado' ?></h1>
      <p class="mt-2 text-dark/70"><?= e($motivo) ?></p>
      <a href="<?= e(url('login')) ?>" class="mt-6 inline-block btn-secondary">Volver</a>
    </div>
  <?php elseif ($state === 'no_existe'): ?>
    <div class="text-center">
      <h1 class="text-xl font-semibold">No encontramos ese documento</h1>
      <p class="mt-2 text-dark/70">Si ya tienes plan, acércate a recepción para revisar tus datos.</p>
      <a href="<?= e(url('login/recuperar')) ?>" class="mt-6 inline-block btn-secondary">Intentar de nuevo</a>
    </div>
  <?php else: ?>
    <a href="<?= e(url('login')) ?>" class="inline-flex items-center gap-1 text-sm text-dark/60 mb-6"><?= icon('left', 'w-4 h-4') ?>Volver</a>
    <h1 class="text-2xl font-semibold">Crear o recuperar contraseña</h1>
    <p class="mt-1 text-dark/65">Te enviaremos un código a tu correo registrado.</p>
    <?php foreach (flashes() as $f): ?><div class="mt-4 rounded-lg px-4 py-3 text-sm bg-danger/5 border border-danger/30 text-danger"><?= e($f['msg']) ?></div><?php endforeach; ?>
    <form method="post" class="mt-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="doc_tipo" value="<?= e($data['doc_tipo']) ?>" data-doc-tipo>
      <div>
        <label class="label" for="doc" data-doc-label><?= $data['doc_tipo'] === 'rut' ? 'RUT' : 'Pasaporte o DNI' ?></label>
        <input id="doc" name="doc" class="input text-lg" autocomplete="username" required value="<?= e($data['doc']) ?>" placeholder="12.345.678-9" <?= $data['doc_tipo'] === 'rut' ? 'data-rut' : '' ?> autofocus>
      </div>
      <button class="btn-primary w-full">Enviar código</button>
    </form>
    <div class="mt-8 border-t border-dark/10 pt-4 text-sm"><button type="button" class="text-primary" data-doc-switch><?= $data['doc_tipo'] === 'rut' ? 'No tengo RUT (pasaporte / DNI)' : 'Usar RUT' ?></button></div>
  <?php endif; ?>
</div>
