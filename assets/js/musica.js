/* GorilaGym · buscador de canciones (muro y cola de la sala) */
(function () {
  'use strict';
  const GG = window.GG;
  document.querySelectorAll('[data-musica]').forEach((box) => {
    const q = box.querySelector('[data-musica-q]');
    const res = box.querySelector('[data-musica-res]');
    const ref = box.querySelector('[data-musica-ref]');
    const sel = box.querySelector('[data-musica-elegida]');
    const buscador = box.querySelector('[data-musica-buscador]');
    const nota = box.querySelector('[data-musica-nota]');
    const form = box.closest('form');
    let items = [];
    let t, seq = 0;

    const cover = (src) => src ? '<img src="' + GG.esc(src) + '" alt="" class="w-12 h-12 rounded-md object-cover bg-dark/5 shrink-0" referrerpolicy="no-referrer" loading="lazy">'
      : '<span class="w-12 h-12 rounded-md bg-dark/5 shrink-0"></span>';

    async function buscar() {
      const term = q.value.trim();
      if (term.length < 2) { res.classList.add('hidden'); return; }
      const my = ++seq;
      res.classList.remove('hidden');
      res.innerHTML = '<p class="p-3 text-sm text-dark/50">Buscando…</p>';
      const r = await GG.get('api/musica', { q: term }).catch(() => ({ ok: false, error: 'Sin conexión' }));
      if (my !== seq) return;
      items = r.items || [];
      if (!r.ok && !items.length) { res.innerHTML = '<p class="p-3 text-sm text-danger">' + GG.esc(r.error || 'No se pudo buscar') + '</p>'; return; }
      res.innerHTML = items.map((it, i) =>
        '<button type="button" class="w-full flex items-center gap-3 px-3 py-2.5 text-left hover:bg-dark/[0.03]" data-i="' + i + '">' + cover(it.portada) +
        '<span class="flex-1 min-w-0"><span class="block font-medium truncate">' + GG.esc(it.titulo) + '</span><span class="block text-sm text-dark/55 truncate">' + GG.esc(it.artista) + '</span></span>' +
        '<span class="text-primary text-sm font-medium">Elegir</span></button>').join('') || '<p class="p-3 text-sm text-dark/55">Sin resultados.</p>';
      if (nota) {
        nota.classList.toggle('hidden', r.fuente !== 'catalogo');
        nota.textContent = 'Resultados del catálogo de respaldo: al tocar la canción se abre en Spotify.';
      }
    }

    function elegir(it) {
      ref.value = it.ref;
      sel.innerHTML = '<div class="flex items-center gap-3 rounded-lg border border-primary/30 bg-primary/5 p-2.5">' + cover(it.portada) +
        '<div class="flex-1 min-w-0"><div class="font-medium truncate">' + GG.esc(it.titulo) + '</div><div class="text-sm text-dark/60 truncate">' + GG.esc(it.artista) + '</div></div>' +
        '<button type="button" class="p-1.5 text-dark/50 hover:text-dark" data-musica-quitar aria-label="Cambiar">✕</button></div>';
      sel.classList.remove('hidden');
      buscador.classList.add('hidden');
      res.classList.add('hidden');
      if (nota) nota.classList.add('hidden');
      GG.vibrate(15);
      if (box.dataset.autoenviar === '1' && form) form.requestSubmit();
    }

    q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(buscar, 300); });
    q.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(t); buscar(); } });
    res.addEventListener('click', (e) => { const b = e.target.closest('[data-i]'); if (b) elegir(items[+b.dataset.i]); });
    sel.addEventListener('click', (e) => {
      if (!e.target.closest('[data-musica-quitar]')) return;
      ref.value = ''; sel.classList.add('hidden'); sel.innerHTML = ''; buscador.classList.remove('hidden'); q.value = ''; q.focus();
    });
  });
})();
