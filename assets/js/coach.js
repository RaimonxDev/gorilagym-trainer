/* GorilaGym · inicio del coach: buscador de alumnos */
(function () {
  'use strict';
  const GG = window.GG;
  const inp = document.querySelector('[data-buscar-alumno]');
  if (!inp) return;
  const box = document.querySelector('[data-resultados]');
  let t;
  inp.addEventListener('input', () => {
    clearTimeout(t);
    if (inp.value.trim().length < 2) { box.classList.add('hidden'); return; }
    t = setTimeout(async () => {
      const r = await GG.get('api/alumnos', { q: inp.value.trim() }).catch(() => null);
      if (!r) return;
      box.innerHTML = (r.items || []).map((a) => {
        let accion = '';
        if (a.sesion) accion = '<a href="' + a.url_sesion + '" class="btn-secondary btn-sm">Rutina de hoy</a>';
        else if (a.acceso && r.puede_armar && !a.sin_coach) accion = '<form method="post" action="' + GG.url('coach/nueva') + '"><input type="hidden" name="_csrf" value="' + GG.csrf + '"><input type="hidden" name="alumno_id" value="' + a.id + '"><button class="btn-primary btn-sm">Armar rutina</button></form>';
        return '<div class="flex items-center gap-3 px-4 py-3 border-b border-dark/5 last:border-0"><a href="' + a.url + '" class="flex-1 min-w-0"><div class="font-medium truncate">' + GG.esc(a.nombre) + '</div><div class="text-sm truncate ' + (a.acceso ? 'text-dark/55' : 'text-warning') + '">' + GG.esc(a.doc) + ' · ' + GG.esc(a.acceso ? (a.plan || 'Pase diario') : a.motivo) + (a.sin_correo ? ' · sin correo' : '') + '</div></a>' + accion + '</div>';
      }).join('') || '<p class="p-4 text-sm text-dark/55">Sin resultados.</p>';
      box.classList.remove('hidden');
    }, 220);
  });
  document.addEventListener('click', (e) => { if (!e.target.closest('[data-buscador]')) box.classList.add('hidden'); });
})();
