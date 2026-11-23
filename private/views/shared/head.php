<div class="mb-6 flex items-start justify-between gap-4">
  <div class="min-w-0">
    <?php if (!empty($back)): ?>
      <a href="<?= e($back) ?>" class="inline-flex items-center gap-1 text-sm text-dark/60 hover:text-primary mb-2"><?= icon('left', 'w-4 h-4') ?>Volver</a>
    <?php endif; ?>
    <h1 class="text-[22px] leading-tight font-semibold truncate"><?= e($t) ?></h1>
    <?php if (!empty($sub)): ?><p class="mt-1 text-dark/60 text-[15px]"><?= $sub ?></p><?php endif; ?>
  </div>
  <?php if (!empty($actions)): ?><div class="flex shrink-0 items-center gap-2"><?= $actions ?></div><?php endif; ?>
</div>
