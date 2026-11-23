<div id="pwa-hint" class="hidden fixed inset-x-0 bottom-0 z-50 p-3 pb-[calc(env(safe-area-inset-bottom)+12px)]">
  <div class="mx-auto max-w-md rounded-xl bg-white shadow-xl border border-dark/10 p-5">
    <div class="flex items-start gap-3">
      <img src="<?= asset('assets/img/icons/icon-192.png') ?>" alt="" class="w-12 h-12 rounded-lg">
      <div class="flex-1">
        <div class="font-semibold">Deja <?= e(setting('brand_nombre', 'Gorila Gym')) ?> en tu pantalla de inicio</div>
        <p class="text-sm text-dark/70 mt-1">Se abre como app, sin descargar nada. En iPhone es necesario para recibir el aviso de fin de descanso.</p>
      </div>
    </div>
    <div data-pwa="android" class="hidden mt-4">
      <button type="button" data-pwa-install class="w-full btn-primary">Agregar a inicio</button>
    </div>
    <ol data-pwa="ios" class="hidden mt-4 space-y-2 text-sm">
      <li class="flex gap-2"><span class="step">1</span><span>Toca el botón <b>Compartir</b> <span class="inline-block align-middle"><?= icon('share', 'w-4 h-4 inline') ?></span> en la barra de Safari.</span></li>
      <li class="flex gap-2"><span class="step">2</span><span>Elige <b>Agregar a pantalla de inicio</b>.</span></li>
      <li class="flex gap-2"><span class="step">3</span><span>Abre la app desde el nuevo ícono.</span></li>
    </ol>
    <p data-pwa="other" class="hidden mt-4 text-sm text-dark/70">Abre el menú del navegador y elige <b>Instalar app</b> o <b>Agregar a pantalla de inicio</b>.</p>
    <button type="button" data-pwa-close class="mt-3 w-full text-sm text-dark/60 py-2">Ahora no</button>
  </div>
</div>
