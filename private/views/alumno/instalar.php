<?php partial('shared/head', ['t' => 'Instalar la app', 'sub' => 'No se descarga nada de la tienda: queda un acceso directo que se abre como app.', 'back' => url('perfil')]); ?>
<div class="card p-5" data-install-page>
  <div data-pwa="installed" class="hidden text-primary font-medium flex items-center gap-2"><?= icon('check', 'w-5 h-5') ?>Ya estás usando la app instalada.</div>
  <div data-pwa="android" class="hidden">
    <h2 class="font-semibold">Android</h2>
    <button type="button" class="mt-3 btn-primary w-full" data-pwa-install>Agregar a inicio</button>
    <p class="mt-3 text-sm text-dark/60">Si el botón no aparece: menú ⋮ de Chrome → <b>Instalar app</b> o <b>Agregar a pantalla de inicio</b>.</p>
  </div>
  <div data-pwa="ios" class="hidden">
    <h2 class="font-semibold">iPhone (Safari)</h2>
    <ol class="mt-3 space-y-3 text-[15px]">
      <li class="flex gap-3"><span class="step">1</span><span>Abre esta página en <b>Safari</b>.</span></li>
      <li class="flex gap-3"><span class="step">2</span><span>Toca <b>Compartir</b> <?= icon('share', 'w-4 h-4 inline align-text-bottom') ?> abajo al centro.</span></li>
      <li class="flex gap-3"><span class="step">3</span><span>Baja y toca <b>Agregar a pantalla de inicio</b>.</span></li>
      <li class="flex gap-3"><span class="step">4</span><span>Abre la app desde el ícono y activa las notificaciones en tu perfil.</span></li>
    </ol>
  </div>
  <div data-pwa="other" class="hidden text-[15px] text-dark/70">Abre el menú del navegador y elige <b>Instalar app</b>.</div>
</div>
