/* GorilaGym · utilidades de gestión */
(function () {
  'use strict';
  const GG = window.GG;
  // Usar mi ubicación (sede)
  const b = document.querySelector('[data-mi-ubicacion]');
  if (b) b.addEventListener('click', () => {
    if (!navigator.geolocation) return GG.toast('Este navegador no permite ubicación');
    b.disabled = true;
    navigator.geolocation.getCurrentPosition((p) => {
      document.querySelector('[data-lat]').value = p.coords.latitude.toFixed(6);
      document.querySelector('[data-lng]').value = p.coords.longitude.toFixed(6);
      GG.toast('Ubicación capturada (precisión ±' + Math.round(p.coords.accuracy) + ' m). Guarda para aplicar.');
      b.disabled = false;
    }, () => { GG.toast('No se pudo obtener la ubicación'); b.disabled = false; }, { enableHighAccuracy: true, timeout: 15000 });
  });
  // Filtro de alternativas
  const fa = document.querySelector('[data-filtro-alt]');
  if (fa) fa.addEventListener('input', () => {
    const v = fa.value.toLowerCase();
    document.querySelectorAll('[data-lista-alt] label').forEach((l) => { l.style.display = l.textContent.toLowerCase().includes(v) ? '' : 'none'; });
  });
  // Permisos críticos: mostrar confirmación
  const form = document.querySelector('[data-permisos]');
  if (form) {
    const box = form.querySelector('[data-critico-box]');
    const initial = new Set(Array.from(form.querySelectorAll('[data-critico]')).filter((c) => c.checked).map((c) => c.name));
    form.addEventListener('change', () => {
      const nuevos = Array.from(form.querySelectorAll('[data-critico]')).filter((c) => c.checked && !initial.has(c.name));
      box.classList.toggle('hidden', !nuevos.length);
    });
  }
})();
