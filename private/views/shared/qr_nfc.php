<div class="mt-4 grid sm:grid-cols-[180px_1fr] gap-5 items-start" data-qrnfc data-url="<?= e($qrUrl) ?>" data-nombre="<?= e($nombre) ?>">
  <div class="rounded-lg border border-dark/10 bg-white p-3"><div data-qr class="aspect-square"></div></div>
  <div class="space-y-3 text-sm">
    <div><div class="label">URL del código</div><div class="flex gap-2"><input class="input text-sm font-mono" value="<?= e($qrUrl) ?>" readonly data-copy-src><button type="button" class="btn-secondary btn-sm" data-copy>Copiar</button></div></div>
    <div class="flex flex-wrap gap-2">
      <button type="button" class="btn-secondary btn-sm" data-qr-png><?= icon('download', 'w-4 h-4') ?>PNG</button>
      <button type="button" class="btn-secondary btn-sm" data-qr-svg><?= icon('download', 'w-4 h-4') ?>SVG</button>
      <button type="button" class="btn-primary btn-sm hidden" data-nfc-write><?= icon('nfc', 'w-4 h-4') ?>Grabar tag NFC</button>
    </div>
    <p class="text-dark/55" data-nfc-help>NFC: graba esta URL como registro "URL/URI" en un tag NTAG213/215. En Chrome Android aparece el botón para grabarlo directo; en iPhone usa una app como NFC Tools.</p>
    <p class="hidden text-primary font-medium" data-nfc-msg></p>
  </div>
</div>
