/* GorilaGym · muro: reacciones y reportes */
(function () {
  'use strict';
  const GG = window.GG;
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-react]');
    if (b) {
      const nEl = b.querySelector('[data-n]');
      const on = !b.classList.contains('is-on');
      // optimista: se ve al instante, se corrige con la respuesta
      b.classList.toggle('is-on', on);
      nEl.textContent = Math.max(0, (+nEl.textContent || 0) + (on ? 1 : -1)) || '';
      if (on) { b.classList.remove('pop'); void b.offsetWidth; b.classList.add('pop'); GG.vibrate(12); }
      const r = await GG.api('api/muro/reaccion', { post_id: +b.dataset.react, emoji: b.dataset.emoji }).catch(() => null);
      if (r && r.ok) { b.classList.toggle('is-on', r.on); b.setAttribute('aria-pressed', r.on ? 'true' : 'false'); nEl.textContent = r.n || ''; }
      else { b.classList.toggle('is-on', !on); nEl.textContent = Math.max(0, (+nEl.textContent || 0) + (on ? -1 : 1)) || ''; GG.toast((r && r.error) || 'Sin conexión'); }
      return;
    }
    const del = e.target.closest('[data-borrar-post]');
    if (del) {
      if (!confirm('¿Borrar esta publicación? Igual cuenta para tu límite de hoy.')) return;
      const r = await GG.api('api/muro/borrar', { post_id: +del.dataset.borrarPost }).catch(() => null);
      if (r && r.ok) { const art = del.closest('[data-post]'); if (art) art.remove(); GG.toast('Publicación borrada'); }
      else GG.toast((r && r.error) || 'No se pudo borrar');
      return;
    }
    const rp = e.target.closest('[data-reportar]');
    if (rp) {
      const motivo = prompt('¿Por qué reportas esta publicación? (opcional)');
      if (motivo === null) return;
      const r = await GG.api('api/muro/reportar', { post_id: +rp.dataset.reportar, motivo });
      if (r.ok) GG.toast('Gracias, el staff lo revisará.');
    }
  });
})();
