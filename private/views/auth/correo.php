<div class="max-w-md">
  <a href="<?= e(url('perfil', ['tab' => 'seguridad'])) ?>" class="inline-flex items-center gap-1 text-sm text-dark/60 mb-4"><?= icon('left', 'w-4 h-4') ?>Volver</a>
  <h1 class="text-2xl font-semibold">Cambiar correo</h1>
  <?php if ($paso === 'correo'): ?>
    <p class="mt-1 text-dark/65">Correo actual: <b class="font-medium"><?= e($u['email'] ?: 'sin correo') ?></b>. Te enviaremos un código al correo nuevo para confirmarlo.</p>
    <form method="post" class="mt-6 space-y-4">
      <?= csrf_field() ?>
      <div><label class="label">Correo nuevo</label><input type="email" name="email" class="input" autocomplete="email" required autofocus></div>
      <?php if ($u['password_hash']) partial('shared/pwd', ['name' => 'contrasena', 'label' => 'Tu contraseña (para confirmar que eres tú)', 'ac' => 'current-password']); ?>
      <button class="btn-primary w-full">Enviar código</button>
    </form>
  <?php else: ?>
    <p class="mt-1 text-dark/65">Ingresa el código que enviamos a <b class="font-medium"><?= e($nuevo) ?></b>.</p>
    <form method="post" class="mt-6 space-y-4" data-otp-form>
      <?= csrf_field() ?>
      <input name="codigo" class="input text-center text-2xl tracking-[0.5em] font-medium" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus placeholder="••••••" data-otp>
      <button class="btn-primary w-full">Confirmar correo</button>
    </form>
    <form method="post" class="mt-3"><?= csrf_field() ?><button name="cancelar" value="1" class="text-sm text-dark/55" formnovalidate>Usar otro correo</button></form>
  <?php endif; ?>
</div>
