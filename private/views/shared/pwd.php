<?php /* Campo de contraseña con botón para ver/ocultar. Params: name, label, ac (autocomplete), ph, id */ $pid = $id ?? ('pwd-' . $name); ?>
<div>
  <?php if (!empty($label)): ?><label class="label" for="<?= e($pid) ?>"><?= e($label) ?></label><?php endif; ?>
  <div class="relative">
    <input type="password" id="<?= e($pid) ?>" name="<?= e($name) ?>" class="input pr-12" autocomplete="<?= e($ac ?? 'current-password') ?>" placeholder="<?= e($ph ?? '') ?>" <?= !empty($minlength) ? 'minlength="' . (int) $minlength . '"' : '' ?> <?= ($required ?? true) ? 'required' : '' ?> <?= !empty($autofocus) ? 'autofocus' : '' ?> autocapitalize="off" spellcheck="false">
    <button type="button" class="absolute inset-y-0 right-0 px-3.5 text-dark/45 hover:text-dark" data-ver-contrasena aria-label="Mostrar contraseña" aria-pressed="false">
      <span data-ojo-abierto><?= icon('eye', 'w-5 h-5') ?></span>
      <span data-ojo-cerrado class="hidden"><?= icon('eyeOff', 'w-5 h-5') ?></span>
    </button>
  </div>
</div>
