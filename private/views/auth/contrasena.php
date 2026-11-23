<div class="<?= $crear ? 'min-h-[80vh] flex flex-col justify-center max-w-sm mx-auto pt-[env(safe-area-inset-top)]' : 'max-w-md' ?>">
  <?php if (!$crear): ?>
    <a href="<?= e(url('perfil', ['tab' => 'seguridad'])) ?>" class="inline-flex items-center gap-1 text-sm text-dark/60 mb-4"><?= icon('left', 'w-4 h-4') ?>Volver</a>
  <?php endif; ?>
  <h1 class="text-2xl font-semibold"><?= $crear ? 'Crea tu contraseña' : 'Cambiar contraseña' ?></h1>
  <p class="mt-1 text-dark/65"><?= $crear ? 'Con ella vas a entrar con tu RUT desde ahora. Tu celular te ofrecerá guardarla.' : 'Al cambiarla se cierran las sesiones guardadas en otros dispositivos.' ?></p>
  <?php foreach (flashes() as $f): ?><div class="mt-4 rounded-lg px-4 py-3 text-sm border <?= $f['type'] === 'error' ? 'bg-danger/5 border-danger/30 text-danger' : 'bg-primary/5 border-primary/20 text-primary' ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <form method="post" action="<?= e(url('cuenta/contrasena')) ?>" class="mt-6 space-y-4" data-guardar-credencial>
    <?= csrf_field() ?>
    <!-- Para que el gestor de contraseñas del celular asocie la contraseña al RUT -->
    <input type="text" name="usuario" value="<?= e($u['doc_tipo'] === 'rut' ? rut_format($u['doc_num']) : $u['doc_num']) ?>" autocomplete="username" class="sr-only" tabindex="-1" aria-hidden="true" readonly>
    <?php if (!$crear) partial('shared/pwd', ['name' => 'actual', 'label' => 'Contraseña actual', 'ac' => 'current-password', 'autofocus' => true]); ?>
    <?php partial('shared/pwd', ['name' => 'nueva', 'label' => 'Nueva contraseña', 'ac' => 'new-password', 'minlength' => 8, 'autofocus' => $crear]); ?>
    <?php partial('shared/pwd', ['name' => 'repetir', 'label' => 'Repite la contraseña', 'ac' => 'new-password', 'minlength' => 8]); ?>
    <p class="text-xs text-dark/55">Mínimo 8 caracteres, con letras y números. No uses tu RUT.</p>
    <button class="btn-primary w-full"><?= $crear ? 'Crear contraseña y entrar' : 'Guardar contraseña' ?></button>
  </form>
</div>
