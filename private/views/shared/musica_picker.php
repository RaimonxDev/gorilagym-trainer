<?php /* Buscador de canciones. Requiere assets/js/musica.js. Envía musica_ref (o spotify como respaldo). */ ?>
<div data-musica class="space-y-2">
  <input type="hidden" name="musica_ref" data-musica-ref>
  <div data-musica-elegida class="hidden"></div>
  <div class="relative" data-musica-buscador>
    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-dark/40"><?= icon('music') ?></span>
    <input class="input pl-11" placeholder="<?= e($placeholder ?? 'Busca una canción o artista') ?>" autocomplete="off" data-musica-q>
  </div>
  <div class="hidden card divide-y divide-dark/5 max-h-80 overflow-y-auto" data-musica-res></div>
  <p class="hidden text-xs text-dark/50" data-musica-nota></p>
  <details class="text-xs text-dark/50">
    <summary class="cursor-pointer">¿No aparece? Pega un link de Spotify</summary>
    <input name="spotify" class="input mt-2 text-sm" placeholder="https://open.spotify.com/track/…" inputmode="url">
  </details>
</div>
