/* GorilaGym · inicio del alumno: bienvenida por ubicación (solo con la app abierta) */
(function () {
  'use strict';
  const GG = window.GG;
  const flag = document.querySelector('[data-geo]');
  if (!flag || flag.dataset.geo !== '1' || !('geolocation' in navigator)) return;
  if (sessionStorage.getItem('gg_geo_done') === new Date().toDateString()) return;
  const box = document.getElementById('geo-welcome');
  navigator.geolocation.getCurrentPosition(async (pos) => {
    sessionStorage.setItem('gg_geo_done', new Date().toDateString());
    const r = await GG.api('api/checkin', { lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy }).catch(() => null);
    if (!r || !r.ok || !r.cerca || r.ya) return;
    box.querySelector('[data-geo-msg]').textContent = r.mensaje;
    if (!r.acceso) { box.querySelector('[data-geo-sub]').textContent = r.motivo; box.querySelector('[data-geo-confirm]').remove(); }
    box.classList.remove('hidden');
    const btn = box.querySelector('[data-geo-confirm]');
    if (btn) btn.addEventListener('click', async () => {
      const p2 = await GG.api('api/checkin', { lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy, confirmar: 1 });
      if (p2.hecho || p2.ya) { GG.vibrate(40); location.href = GG.url('inicio', { bienvenida: 1 }); }
      else GG.toast(p2.motivo || 'No se pudo registrar');
    });
  }, () => {}, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
})();
