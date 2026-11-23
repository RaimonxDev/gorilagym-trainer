<div class="py-16 text-center">
  <div class="mx-auto w-12 h-12 rounded-full bg-dark/5 flex items-center justify-center text-dark/60"><?= icon('alert', 'w-6 h-6') ?></div>
  <h1 class="mt-4 text-xl font-semibold"><?= e($title ?? 'Error') ?></h1>
  <p class="mt-2 text-dark/70"><?= e($msg ?? '') ?></p>
  <a href="<?= e(url()) ?>" class="inline-block mt-6 btn-secondary">Volver al inicio</a>
</div>
