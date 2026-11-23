<div class="no-print sticky top-0 bg-light border-b border-dark/10 px-6 py-3 flex items-center justify-between">
  <span class="text-sm">Hoja de QR · tamaño recomendado 5×5 cm</span>
  <button type="button" class="btn-primary btn-sm" data-print>Imprimir</button>
</div>
<div class="p-6 grid grid-cols-2 sm:grid-cols-3 gap-6 print:grid-cols-3">
  <?php if ($entrada): ?>
    <div class="qr-card" data-qrnfc data-url="<?= e(abs_url('', ['entrada' => setting('entrada_token')])) ?>" data-nombre="Entrada">
      <div data-qr class="aspect-square"></div>
      <div class="mt-2 font-semibold">Entrada</div><div class="text-xs text-dark/60">Check-in · <?= e(setting('brand_bienvenida')) ?></div>
    </div>
  <?php endif; ?>
  <?php foreach ($maq as $m): ?>
    <div class="qr-card" data-qrnfc data-url="<?= e(abs_url('', ['m' => $m['token']])) ?>" data-nombre="<?= e($m['nombre']) ?>">
      <div data-qr class="aspect-square"></div>
      <div class="mt-2 font-semibold"><?= e($m['nombre']) ?></div><div class="text-xs text-dark/60">Escanea para ver ejercicios y tu peso</div>
    </div>
  <?php endforeach; ?>
  <?php foreach ($ejs as $e): ?>
    <div class="qr-card" data-qrnfc data-url="<?= e(abs_url('', ['e' => $e['token']])) ?>" data-nombre="<?= e($e['nombre']) ?>">
      <div data-qr class="aspect-square"></div>
      <div class="mt-2 font-semibold"><?= e($e['nombre']) ?></div>
    </div>
  <?php endforeach; ?>
</div>
