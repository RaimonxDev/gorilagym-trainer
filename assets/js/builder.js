/* GorilaGym · armado de rutinas y plantillas */
(function () {
  'use strict';
  const GG = window.GG;
  const root = document.getElementById('builder');
  if (!root) return;
  const CTX = root.dataset.ctx, ID = +root.dataset.id, ALUMNO = +root.dataset.alumno || 0;
  const $ = (s) => root.querySelector(s);
  let D = null;
  let grupo = '';
  let sortable = null;
  const saveTimers = {};

  async function call(accion, data) {
    const r = await GG.api('api/builder', Object.assign({ ctx: CTX, id: ID, accion }, data || {})).catch(() => ({ ok: false, error: 'Sin conexión' }));
    if (!r.ok) { GG.toast(r.error || 'No se pudo guardar'); return null; }
    D = r;
    render();
    return r;
  }

  function render() {
    // tiempo
    const tot = D.total_min, max = D.max_min, obj = D.objetivo_min;
    $('[data-total]').textContent = '~' + tot + ' / ' + max + ' min';
    const bar = $('[data-bar]');
    bar.style.width = Math.min(100, (tot / max) * 100) + '%';
    bar.className = 'h-full transition-all ' + (tot > max ? 'bg-danger' : tot > obj ? 'bg-warning' : 'bg-primary');
    const rc = $('[data-recortes]');
    if (tot > max && D.recortes.length) {
      rc.classList.remove('hidden');
      rc.innerHTML = '<div class="text-sm text-danger font-medium">Se pasa ' + (tot - max) + ' min del máximo. Opciones para recortar:</div><div class="mt-2 flex flex-col gap-1.5">' +
        D.recortes.map((x) => '<button type="button" class="text-left text-sm rounded-md border border-dark/10 px-3 py-2 hover:border-primary/40" data-recorte="' + x.tipo + '" data-item="' + x.item_id + '">' + GG.esc(x.texto) + ' <span class="text-dark/50">(−' + x.ahorro + ' min)</span></button>').join('') + '</div>';
    } else { rc.classList.add('hidden'); rc.innerHTML = ''; }

    // lista por bloques
    const list = $('[data-lista]');
    if (!D.items.length) {
      list.innerHTML = '<div class="card p-8 text-center text-dark/55">Agrega ejercicios desde el panel ' + '<span class="hidden lg:inline">de la derecha</span><span class="lg:hidden">(botón "Ejercicio")</span> o parte desde una plantilla.</div>';
      return;
    }
    const bloques = [];
    D.items.forEach((it) => { const l = bloques[bloques.length - 1]; if (l && l.b === it.bloque) l.items.push(it); else bloques.push({ b: it.bloque, items: [it] }); });
    let n = 0;
    list.innerHTML = '<div data-sortable class="space-y-3">' + bloques.map((bl, bi) => {
      const sup = bl.items.length > 1;
      let h = '<div class="bloque ' + (sup ? 'rounded-xl border border-dashed border-primary/30 p-2 space-y-2' : '') + '" data-bloque="' + bl.items.map((i) => i.id).join(',') + '">';
      if (sup) h += '<div class="flex items-center gap-2 px-2 pt-1 text-xs uppercase tracking-wide text-primary/80 font-medium"><span class="handle cursor-grab">' + dragIcon() + '</span>Superserie</div>';
      bl.items.forEach((it, idx) => { n++; h += itemHtml(it, n, sup, idx, bi); });
      return h + '</div>';
    }).join('') + '</div>';
    if (window.Sortable) {
      if (sortable) sortable.destroy();
      sortable = window.Sortable.create(list.querySelector('[data-sortable]'), {
        handle: '.handle', animation: 150, delay: 120, delayOnTouchOnly: true, ghostClass: 'opacity-40',
        onEnd: () => {
          const bloquesIds = Array.from(list.querySelectorAll('[data-bloque]')).map((b) => b.dataset.bloque.split(',').map(Number));
          call('ordenar', { bloques: bloquesIds });
        },
      });
    }
  }

  function dragIcon() { return '<svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>'; }

  function itemHtml(it, n, sup, idx, bi) {
    const done = it.estado === 'hecho';
    let chips = '';
    if (it.extra) chips += '<span class="chip chip-neutral">extra</span>';
    if (done) chips += '<span class="chip chip-ok">hecho</span>';
    if (it.conflictos.length) chips += '<span class="chip chip-warn">restricción: ' + GG.esc(it.conflictos.join(', ')) + '</span>';
    if (it.maquina_estado === 'mantencion') chips += '<span class="chip chip-danger">máquina en mantención</span>';
    else if (it.ocupada && !done) chips += '<span class="chip chip-neutral">ocupada · libre en ~' + it.ocupada.libre_en + ' min (' + GG.esc(it.ocupada.coach || it.ocupada.alumno || '') + ')</span>';
    let h = '<div class="card ' + (done ? 'opacity-60' : '') + '" data-it="' + it.id + '">';
    h += '<div class="flex items-start gap-2 px-3 pt-3">';
    h += sup ? '<span class="w-5"></span>' : '<span class="handle cursor-grab text-dark/30 pt-0.5" aria-label="Arrastrar">' + dragIcon() + '</span>';
    h += '<div class="flex-1 min-w-0"><div class="font-medium"><span class="text-dark/40 mr-1">' + n + '.</span>' + GG.esc(it.nombre) + '</div>';
    h += '<div class="text-sm text-dark/55">' + GG.esc([it.grupo, it.maquina].filter(Boolean).join(' · ')) + ' · ~' + it.min + ' min</div>';
    if (chips) h += '<div class="mt-1.5 flex flex-wrap gap-1.5">' + chips + '</div>';
    h += '</div>';
    h += '<details class="relative"><summary class="list-none cursor-pointer p-1.5 rounded-md hover:bg-dark/5 text-dark/50" aria-label="Acciones"><svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg></summary>';
    h += '<div class="absolute right-0 z-20 mt-1 w-56 card shadow-lg py-1 text-sm">';
    h += '<button type="button" class="menu-item" data-alt="' + it.id + '" data-ej="' + it.ejercicio_id + '">Cambiar por alternativa</button>';
    h += '<button type="button" class="menu-item" data-final="' + it.id + '">Mover al final</button>';
    if (n > 1 && idx === 0 && bi > 0) h += '<button type="button" class="menu-item" data-unir="' + it.id + '">Unir con el anterior (superserie)</button>';
    if (sup && idx > 0) h += '<button type="button" class="menu-item" data-separar="' + it.id + '">Separar de la superserie</button>';
    h += '<button type="button" class="menu-item text-danger" data-quitar="' + it.id + '">Quitar</button>';
    h += '</div></details></div>';
    // campos
    h += '<div class="grid grid-cols-4 ' + (CTX === 'sesion' ? 'sm:grid-cols-5' : '') + ' gap-2 px-3 pb-3 pt-3">';
    h += field('series', it.id, it.series, 'Series');
    h += field('reps_min', it.id, it.reps_min, 'Reps mín');
    h += field('reps_max', it.id, it.reps_max, 'Reps máx');
    h += field('descanso', it.id, it.descanso, 'Desc. (s)', 15);
    if (CTX === 'sesion') h += field('peso_sugerido', it.id, it.peso_sugerido == null ? '' : it.peso_sugerido, 'Peso (kg)', 2.5, 'col-span-4 sm:col-span-1');
    h += '</div>';
    if (CTX === 'sesion' && it.sugerencia) {
      h += '<div class="px-3 pb-3 -mt-1 text-xs ' + (it.sugerencia.subir ? 'text-primary' : 'text-dark/50') + '">' + (it.sugerencia.subir ? '↑ ' : '') + GG.esc(it.sugerencia.motivo) + ' (última: ' + GG.kg(it.sugerencia.anterior) + ')</div>';
    }
    return h + '</div>';
  }
  function field(name, id, val, label, step, cls) {
    return '<label class="block ' + (cls || '') + '"><span class="block text-[11px] text-dark/50 mb-0.5">' + label + '</span><input class="input py-1.5 px-2 text-center tabular-nums" inputmode="decimal" type="number" step="' + (step || 1) + '" min="0" value="' + val + '" data-f="' + name + '" data-id="' + id + '"></label>';
  }

  // ---------------------------------------------------------------- panel de ejercicios
  const panel = document.querySelector('[data-panel]');
  const res = panel.querySelector('[data-resultados]');
  const q = panel.querySelector('[data-buscar]');
  async function buscar() {
    const r = await GG.get('api/ejercicios', { q: q.value, grupo, alumno: ALUMNO }).catch(() => ({ items: [] }));
    res.innerHTML = (r.items || []).map((x) => {
      const dis = x.mantencion;
      let meta = [x.grupo, x.maquina].filter(Boolean).join(' · ');
      let ch = '';
      if (x.conflictos.length) ch += '<span class="chip chip-warn">' + GG.esc(x.conflictos.join(', ')) + '</span>';
      if (x.mantencion) ch += '<span class="chip chip-danger">mantención</span>';
      else if (x.ocupada) ch += '<span class="chip chip-neutral">~' + x.ocupada + ' min</span>';
      return '<button type="button" class="w-full text-left px-2 py-2.5 flex items-center gap-3 hover:bg-dark/[0.03] ' + (dis ? 'opacity-50' : '') + '" data-add="' + x.id + '" ' + (dis ? 'disabled' : '') + '>' +
        (x.foto ? '<img src="' + GG.esc(x.foto) + '" class="w-10 h-10 rounded-md object-cover bg-dark/5" alt="">' : '<span class="w-10 h-10 rounded-md bg-dark/5"></span>') +
        '<span class="flex-1 min-w-0"><span class="block font-medium truncate">' + GG.esc(x.nombre) + '</span><span class="block text-xs text-dark/55 truncate">' + GG.esc(meta) + '</span></span><span class="flex flex-col items-end gap-1">' + ch + '<span class="text-primary text-xl leading-none">+</span></span></button>';
    }).join('') || '<p class="p-3 text-sm text-dark/55">Sin resultados. ' + (q.value ? '' : 'Carga ejercicios en Gestión → Ejercicios.') + '</p>';
  }
  let tq; q.addEventListener('input', () => { clearTimeout(tq); tq = setTimeout(buscar, 220); });
  panel.addEventListener('click', async (e) => {
    const g = e.target.closest('[data-grupo]');
    if (g) { grupo = g.dataset.grupo; panel.querySelectorAll('[data-grupo]').forEach((b) => b.classList.toggle('is-on', b === g)); buscar(); return; }
    const a = e.target.closest('[data-add]');
    if (a && !a.disabled) {
      a.disabled = true;
      const r = await call('agregar', { ejercicio_id: +a.dataset.add });
      a.disabled = false;
      if (r) GG.toast('Agregado');
    }
    if (e.target.closest('[data-cerrar-panel]')) panel.classList.remove('panel-open');
  });
  document.querySelectorAll('[data-abrir-panel]').forEach((b) => b.addEventListener('click', () => { panel.classList.add('panel-open'); q.focus(); }));

  // ---------------------------------------------------------------- eventos lista
  root.addEventListener('input', (e) => {
    const f = e.target.closest('[data-f]');
    if (!f) return;
    const id = +f.dataset.id;
    clearTimeout(saveTimers[id]);
    saveTimers[id] = setTimeout(() => {
      const card = root.querySelector('[data-it="' + id + '"]');
      const data = { item_id: id };
      card.querySelectorAll('[data-f]').forEach((i) => { data[i.dataset.f] = i.dataset.f === 'peso_sugerido' ? (i.value === '' ? null : parseFloat(i.value)) : parseInt(i.value || '0', 10); });
      const focused = document.activeElement && document.activeElement.dataset ? document.activeElement.dataset.f : null;
      call('actualizar', data).then(() => {
        if (focused) { const el = root.querySelector('[data-it="' + id + '"] [data-f="' + focused + '"]'); if (el) { el.focus(); const v = el.value; el.value = ''; el.value = v; } }
      });
    }, 700);
  });
  root.addEventListener('click', async (e) => {
    let el;
    const closeMenus = () => root.querySelectorAll('details[open]').forEach((d) => d.removeAttribute('open'));
    if ((el = e.target.closest('[data-quitar]'))) { closeMenus(); if (confirm('¿Quitar este ejercicio?')) call('quitar', { item_id: +el.dataset.quitar }); }
    if ((el = e.target.closest('[data-final]'))) { closeMenus(); call('al_final', { item_id: +el.dataset.final }); }
    if ((el = e.target.closest('[data-unir]'))) { closeMenus(); call('unir', { item_id: +el.dataset.unir }); }
    if ((el = e.target.closest('[data-separar]'))) { closeMenus(); call('separar', { item_id: +el.dataset.separar }); }
    if ((el = e.target.closest('[data-recorte]'))) { call('recorte', { tipo: el.dataset.recorte, item_id: +el.dataset.item }); }
    if ((el = e.target.closest('[data-alt]'))) { closeMenus(); alternativas(+el.dataset.alt, +el.dataset.ej); }
    if ((el = e.target.closest('[data-swap]'))) { call('reemplazar', { item_id: +el.dataset.swap, ejercicio_id: +el.dataset.ej }); }
  });
  document.addEventListener('click', (e) => { if (!e.target.closest('details')) root.querySelectorAll('details[open]').forEach((d) => d.removeAttribute('open')); });

  async function alternativas(itemId, ejId) {
    const card = root.querySelector('[data-it="' + itemId + '"]');
    let box = card.querySelector('[data-alts]');
    if (box) { box.remove(); return; }
    box = document.createElement('div');
    box.dataset.alts = '1';
    box.className = 'border-t border-dark/5 px-3 py-3 text-sm';
    box.textContent = 'Buscando alternativas…';
    card.appendChild(box);
    const r = await GG.get('api/ocupacion', { ejercicio: ejId, alumno: ALUMNO });
    const alts = r.alternativas || [];
    box.innerHTML = alts.length ? '<div class="text-dark/60 mb-1.5">Alternativas</div>' + alts.map((a) => '<button type="button" class="w-full flex items-center justify-between gap-2 py-1.5 text-left" data-swap="' + itemId + '" data-ej="' + a.id + '"><span>' + GG.esc(a.nombre) + ' <span class="text-dark/45">' + GG.esc(a.maquina || '') + '</span>' + (a.conflictos.length ? ' <span class="chip chip-warn">' + GG.esc(a.conflictos.join(', ')) + '</span>' : '') + '</span><span class="chip ' + (a.libre ? 'chip-ok' : 'chip-neutral') + '">' + (a.libre ? 'libre' : '~' + a.libre_en + ' min') + '</span></button>').join('')
      : '<span class="text-dark/55">Este ejercicio no tiene alternativas cargadas. Agrégalas en Gestión → Ejercicios.</span>';
  }

  // plantilla y vaciar
  const sel = document.querySelector('[data-plantilla]');
  if (sel) sel.addEventListener('change', async () => { if (!sel.value) return; await call('plantilla', { plantilla_id: +sel.value }); sel.value = ''; GG.toast('Plantilla agregada'); });
  const vac = document.querySelector('[data-vaciar]');
  if (vac) vac.addEventListener('click', () => { if (confirm('¿Quitar todos los ejercicios' + (CTX === 'sesion' ? ' sin series registradas' : '') + '?')) call('vaciar'); });

  // ocupación cambia en el tiempo
  setInterval(() => { if (document.visibilityState === 'visible' && !root.contains(document.activeElement) && CTX === 'sesion') call('get'); }, 30000);
  call('get');
  buscar();
})();
