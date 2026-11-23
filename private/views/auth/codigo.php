<div class="min-h-[80vh] flex flex-col justify-center max-w-sm mx-auto">
  <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center"><?= icon('mail', 'w-6 h-6') ?></div>
  <h1 class="mt-4 text-2xl font-semibold">Revisa tu correo</h1>
  <p class="mt-1 text-dark/65"><?= $motivo === 'recuperar' ? 'Para recuperar tu contraseña, ingresa' : 'Para crear tu contraseña, ingresa' ?> el código de 6 dígitos que enviamos a <b class="font-medium"><?= e($mail) ?></b>. Si no aparece, revisa spam.</p>
  <?php foreach (flashes() as $f): ?><div class="mt-4 rounded-lg px-4 py-3 text-sm bg-danger/5 border border-danger/30 text-danger"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <form method="post" action="<?= e(url('login/codigo')) ?>" class="mt-6 space-y-4" data-otp-form>
    <?= csrf_field() ?>
    <input name="codigo" class="input text-center text-2xl tracking-[0.5em] font-medium" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus placeholder="••••••" data-otp>
    <button class="btn-primary w-full">Continuar</button>
  </form>
  <a href="<?= e(url('login/recuperar')) ?>" class="mt-6 text-sm text-primary">Pedir otro código</a>
</div>
