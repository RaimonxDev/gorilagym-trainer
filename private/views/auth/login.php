<?php $logo = setting('brand_logo') ? media_url(setting('brand_logo')) : asset('assets/img/brand/logo-horizontal.svg'); ?>
<div class="min-h-[80vh] flex flex-col justify-center max-w-sm mx-auto pt-[env(safe-area-inset-top)]">
  <img src="<?= e($logo) ?>" alt="<?= e(setting('brand_nombre')) ?>" class="h-12 mx-auto mb-10">

  <?php if ($state === 'sin_correo'): ?>
    <div class="text-center">
      <div class="mx-auto w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center"><?= icon('mail', 'w-6 h-6') ?></div>
      <h1 class="mt-4 text-xl font-semibold">Falta activar tu cuenta</h1>
      <p class="mt-2 text-dark/70">Para crear tu contraseña necesitamos enviarte un código, y no tenemos un correo registrado. Pídele a tu coach o en recepción que lo registren: toma un minuto.</p>
      <a href="<?= e(url('login')) ?>" class="mt-6 inline-block btn-secondary">Volver</a>
    </div>
  <?php elseif ($state === 'sin_plan'): ?>
    <div class="text-center">
      <div class="mx-auto w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center"><?= icon('users', 'w-6 h-6') ?></div>
      <h1 class="mt-4 text-xl font-semibold"><?= $motivo === MSG_SOLO_SEMIPERSONALIZADOS ? 'La app es para planes con coach' : 'Sin plan registrado' ?></h1>
      <p class="mt-2 text-dark/70"><?= e($motivo) ?></p>
      <a href="<?= e(url('login')) ?>" class="mt-6 inline-block btn-secondary">Volver</a>
    </div>
  <?php elseif ($state === 'inactivo'): ?>
    <div class="text-center">
      <h1 class="text-xl font-semibold">Tu cuenta está inactiva</h1>
      <p class="mt-2 text-dark/70">Acércate a recepción para reactivarla.</p>
      <a href="<?= e(url('login')) ?>" class="mt-6 inline-block btn-secondary">Volver</a>
    </div>
  <?php else: ?>
    <h1 class="text-2xl font-semibold">Entrar</h1>
    <p class="mt-1 text-dark/65">Con tu RUT y tu contraseña.</p>
    <?php foreach (flashes() as $f): ?><div class="mt-4 rounded-lg px-4 py-3 text-sm border <?= $f['type'] === 'error' ? 'bg-danger/5 border-danger/30 text-danger' : 'bg-warning/5 border-warning/30 text-warning' ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
    <form method="post" action="<?= e(url('login')) ?>" class="mt-6 space-y-4" data-login>
      <?= csrf_field() ?>
      <input type="hidden" name="doc_tipo" value="<?= e($data['doc_tipo']) ?>" data-doc-tipo>
      <div>
        <label class="label" for="doc" data-doc-label><?= $data['doc_tipo'] === 'rut' ? 'RUT' : 'Pasaporte o DNI' ?></label>
        <input id="doc" name="doc" class="input text-lg" autocomplete="username" required
               value="<?= e($data['doc']) ?>" placeholder="<?= $data['doc_tipo'] === 'rut' ? '12.345.678-9' : 'N° de pasaporte o DNI' ?>" <?= $data['doc_tipo'] === 'rut' ? 'data-rut' : '' ?> <?= $data['doc'] === '' ? 'autofocus' : '' ?>>
        <p class="hidden mt-1.5 text-sm text-danger" data-rut-error>RUT inválido: revisa el dígito verificador</p>
      </div>
      <?php partial('shared/pwd', ['name' => 'contrasena', 'label' => 'Contraseña', 'ac' => 'current-password', 'required' => false, 'autofocus' => $data['doc'] !== '']); ?>
      <button class="btn-primary w-full">Entrar</button>
    </form>
    <div class="mt-5 text-center">
      <a href="<?= e(url('login/recuperar')) ?>" class="text-sm text-primary" data-link-recuperar>¿Primera vez o se te olvidó la contraseña?</a>
    </div>
    <div class="mt-8 border-t border-dark/10 pt-4 text-sm text-dark/65">
      <button type="button" class="text-primary" data-doc-switch><?= $data['doc_tipo'] === 'rut' ? 'No tengo RUT (pasaporte / DNI)' : 'Usar RUT' ?></button>
    </div>
    <p class="mt-6 text-xs text-dark/45">La primera vez: escribe tu RUT y toca Entrar sin contraseña. Te enviaremos un código a tu correo para crearla.</p>
  <?php endif; ?>
</div>
