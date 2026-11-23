/* GorilaGym · ejecución de la sesión (alumno y coach en sala) */
(function () {
  'use strict';
  const GG = window.GG;
  const root = document.getElementById('sesion-app');
  if (!root) return;
  const SID = +root.dataset.id;
  const $ = (s) => root.querySelector(s);
  const QKEY = 'gg_queue';

  let D = null;            // payload del servidor
  let clockOffset = 0;     // ms (cliente - servidor)
  let active = +root.dataset.focus || 0;
  let restEnd = 0;         // ms (hora cliente)
  let restTimer = null;
  let restCancelSent = false;
  const drafts = {};       // item_id -> {reps, peso}
  let editando = 0;        // id de serie en edición
  let autoEnVuelo = false;
  let wakeLock = null;

  const now = () => Date.now();
  const srvNow = () => now() - clockOffset;

  // ---------------------------------------------------------------- datos
  async function load() {
    try {
      const r = await GG.get('api/sesion', { id: SID });
      if (!r.ok) { $('[data-items]').innerHTML = '<div class="card p-6 text-center text-dark/60">' + GG.esc(r.error || 'No se pudo cargar') + '</div>'; return; }
      apply(r);
      if (root.dataset.auto === '1' && active) { root.dataset.auto = '0'; iniciarItem(active); }
    } catch (e) {
      const cache = localStorage.getItem('gg_ses_' + SID);
      if (cache) { apply(JSON.parse(cache), true); GG.toast('Sin conexión: mostrando la última versión guardada'); }
    }
  }

  function apply(r, fromCache) {
    D = r;
    clockOffset = now() - r.now * 1000;
    if (!fromCache) { try { localStorage.setItem('gg_ses_' + SID, JSON.stringify(r)); } catch (e) {} }
    applyQueuedLocally();
    if (!active || !D.items.find((i) => i.id === active)) {
      const cur = D.items.find((i) => i.estado === 'en_curso') || D.items.find((i) => i.estado === 'pendiente');
      active = cur ? cur.id : 0;
    }
    if (r.rest_end && r.rest_end * 1000 > srvNow() && !restEnd) startRest(r.rest_end * 1000 + clockOffset, '');
    render();
  }

  // ---------------------------------------------------------------- cola offline
  function queue() { try { return JSON.parse(localStorage.getItem(QKEY) || '[]'); } catch (e) { return []; } }
  function saveQueue(q) { localStorage.setItem(QKEY, JSON.stringify(q)); }
  function applyQueuedLocally() {
    queue().filter((x) => x.sid === SID && x.route === 'api/serie').forEach((x) => {
      const it = D.items.find((i) => i.id === x.data.item_id);
      if (it && !it.hechas.find((h) => h.uuid === x.data.uuid)) it.hechas.push({ id: 0, n: it.hechas.length + 1, reps: x.data.reps, peso: x.data.peso, uuid: x.data.uuid, pendiente: true });
    });
  }
  GG.flushQueue = async function () {
    let q = queue();
    if (!q.length) return;
    const rest = [];
    for (const x of q) {
      try {
        const r = await GG.api(x.route, x.data);
        if (!r.ok && r._status >= 500) rest.push(x);
      } catch (e) { rest.push(x); }
    }
    saveQueue(rest);
    if (!rest.length) GG.toast('Series sincronizadas ✓');
    load();
  };

  async function send(route, data) {
    try {
      const r = await GG.api(route, data);
      return r;
    } catch (e) {
      return { ok: false, offline: true };
    }
  }

  // ---------------------------------------------------------------- render
  function fmtMin(m) { return m >= 60 ? Math.floor(m / 60) + ' h ' + (m % 60) + ' min' : m + ' min'; }

  function render() {
    if (!D) return;
    const s = D.sesion;
    const ed = root.querySelector('[data-editar]');
    if (ed) ed.classList.toggle('hidden', !D.yo.puede_editar || s.estado === 'finalizada');
    $('[data-titulo]').textContent = s.titulo || 'Rutina de hoy';
    $('[data-sub]').textContent = (D.alumno ? D.alumno.nombre : '') + (D.coach ? ' · coach ' + D.coach.nombre : (s.modo === 'libre' ? ' · entrenamiento libre' : ''));

    // tiempo
    const lbl = $('[data-tiempo-label]'), val = $('[data-tiempo-valor]'), bar = $('[data-tiempo-bar]');
    const aviso = $('[data-aviso-coach]');
    aviso.classList.add('hidden');
    if (s.estado === 'en_curso' && s.inicio) {
      const trans = Math.floor((srvNow() / 1000 - s.inicio) / 60);
      if (s.modo === 'coach') {
        lbl.textContent = 'Con coach · quedan ~' + Math.max(0, s.restante_min) + ' min de rutina';
        val.textContent = trans + ' / ' + s.max_min + ' min';
        bar.style.width = Math.min(100, (trans / s.max_min) * 100) + '%';
        bar.className = 'h-full transition-all ' + (trans >= s.max_min ? 'bg-danger' : trans >= s.max_min - 10 ? 'bg-warning' : 'bg-primary');
        if (s.fin_coach && srvNow() / 1000 >= s.fin_coach) {
          aviso.innerHTML = '<b class="font-medium">Terminó el tiempo con coach.</b> Puedes seguir entrenando por tu cuenta según tu plan.';
          aviso.classList.remove('hidden');
        }
      } else {
        lbl.textContent = 'Entrenando · quedan ~' + Math.max(0, s.restante_min) + ' min';
        val.textContent = trans + ' min';
        bar.style.width = Math.min(100, (trans / Math.max(1, s.est_min)) * 100) + '%';
      }
    } else if (s.estado === 'finalizada') {
      lbl.textContent = 'Sesión finalizada';
      val.textContent = '';
      bar.style.width = '100%';
    } else {
      lbl.textContent = 'Tiempo estimado';
      val.textContent = '~' + fmtMin(s.est_min);
      bar.style.width = Math.min(100, (s.est_min / s.max_min) * 100) + '%';
      bar.className = 'h-full transition-all ' + (s.est_min > s.max_min ? 'bg-danger' : 'bg-primary');
    }

    // items agrupados por bloque
    const wrap = $('[data-items]');
    if (!D.items.length) {
      wrap.innerHTML = '<div class="card p-6 text-center text-dark/60">Aún no hay ejercicios en esta rutina.' + (D.yo.puede_editar ? ' <a class="text-primary" href="' + D.urls.editar + '">Armarla</a>' : ' Tu coach la está armando.') + '</div>';
    } else {
      let html = '';
      let i = 0;
      const bloques = [];
      D.items.forEach((it) => { const last = bloques[bloques.length - 1]; if (last && last.b === it.bloque) last.items.push(it); else bloques.push({ b: it.bloque, items: [it] }); });
      bloques.forEach((bl) => {
        if (bl.items.length > 1) html += '<div class="rounded-xl border border-dashed border-primary/30 p-2 space-y-2"><div class="px-2 pt-1 text-xs uppercase tracking-wide text-primary/80 font-medium">Superserie · alterna los ejercicios</div>';
        bl.items.forEach((it) => { i++; html += itemHtml(it, i); });
        if (bl.items.length > 1) html += '</div>';
      });
      wrap.innerHTML = html;
    }
    renderFinal();
    const act = wrap.querySelector('[data-item="' + active + '"]');
    if (act && root.dataset.scrolled !== String(active)) { root.dataset.scrolled = String(active); setTimeout(() => act.scrollIntoView({ behavior: 'smooth', block: 'start' }), 60); }
  }

  function itemHtml(it, n) {
    const isAct = it.id === active;
    const done = it.estado === 'hecho', skip = it.estado === 'omitido';
    const hechas = it.hechas.length;
    const reps = it.reps_min === it.reps_max ? it.reps_min : it.reps_min + '–' + it.reps_max;
    let chips = '';
    if (it.extra) chips += '<span class="chip chip-neutral">extra</span>';
    if (it.conflictos.length) chips += '<span class="chip chip-warn">ojo: ' + GG.esc(it.conflictos.join(', ')) + '</span>';
    if (it.maquina_estado === 'mantencion') chips += '<span class="chip chip-warn">en mantención</span>';
    else if (it.ocupada && !done) chips += '<span class="chip chip-neutral">ocupada · ~' + it.ocupada.libre_en + ' min</span>';
    if (it.compartido) chips += '<span class="chip chip-neutral">compartida</span>';

    let h = '<article class="card overflow-hidden ' + (isAct ? 'ring-1 ring-primary/30' : '') + '" data-item="' + it.id + '">';
    h += '<button type="button" class="w-full text-left px-4 py-3.5 flex items-center gap-3" data-abrir="' + it.id + '">';
    h += '<span class="w-7 h-7 shrink-0 rounded-full flex items-center justify-center text-sm ' + (done ? 'bg-primary text-light' : skip ? 'bg-dark/10 text-dark/40' : isAct ? 'bg-primary/10 text-primary font-medium' : 'bg-dark/5 text-dark/60') + '">' + (done ? '✓' : n) + '</span>';
    h += '<span class="flex-1 min-w-0"><span class="block font-medium truncate ' + (skip ? 'line-through text-dark/40' : '') + '">' + GG.esc(it.nombre) + '</span>';
    h += '<span class="block text-sm text-dark/55 truncate">' + it.series + ' × ' + reps + (it.peso_sugerido ? ' · ' + GG.kg(it.peso_sugerido) : '') + (it.maquina ? ' · ' + GG.esc(it.maquina) : '') + '</span></span>';
    h += '<span class="text-sm tabular-nums text-dark/50">' + hechas + '/' + it.series + '</span></button>';
    if (chips) h += '<div class="px-4 -mt-1 pb-3 flex flex-wrap gap-1.5">' + chips + '</div>';

    if (isAct) {
      h += '<div class="border-t border-dark/5 px-4 py-4 space-y-4">';
      if (it.video) h += '<video class="w-full rounded-lg bg-dark/5 aspect-video object-cover" src="' + GG.esc(it.video) + '" autoplay muted loop playsinline preload="metadata"></video>';
      else if (it.foto) h += '<img class="w-full rounded-lg bg-dark/5 aspect-video object-cover" src="' + GG.esc(it.foto) + '" alt="">';
      if (it.instrucciones || it.errores) {
        h += '<details class="text-sm"><summary class="cursor-pointer text-primary">Cómo se hace</summary><div class="mt-2 space-y-2 text-dark/75">';
        if (it.instrucciones) h += '<div>' + GG.esc(it.instrucciones).replace(/\n/g, '<br>') + '</div>';
        if (it.errores) h += '<div><b class="font-medium text-dark">Errores comunes:</b><br>' + GG.esc(it.errores).replace(/\n/g, '<br>') + '</div>';
        h += '</div></details>';
      }
      if (it.ultimo) h += '<div class="text-sm text-dark/60">Última vez (' + GG.esc(it.ultimo.fecha) + '): ' + it.ultimo.series.map((x) => GG.kg(x.peso).replace(' kg', '') + '×' + x.reps).join(' · ') + ' kg</div>';
      if (it.ocupada && !done && !hechas) {
        h += '<div class="rounded-lg border border-dark/10 bg-light/60 px-3 py-3 text-sm"><div><b class="font-medium">Máquina ocupada</b> por ' + GG.esc(it.ocupada.alumno || 'otro alumno') + (it.ocupada.coach ? ' (coach ' + GG.esc(it.ocupada.coach) + ')' : '') + ' · libre en ~' + it.ocupada.libre_en + ' min</div>';
        h += '<div class="mt-2 flex flex-wrap gap-2"><button type="button" class="btn-secondary btn-sm" data-compartir="' + it.id + '">Compartir</button><button type="button" class="btn-secondary btn-sm" data-al-final="' + it.id + '">Mover al final</button><button type="button" class="btn-secondary btn-sm" data-alternativas="' + it.id + '">Cambiar por alternativa</button></div></div>';
      }
      // series hechas
      if (hechas) {
        h += '<div class="divide-y divide-dark/5 rounded-lg border border-dark/10">';
        it.hechas.forEach((x) => {
          if (x.id && editando === x.id) {
            h += '<div class="flex items-center gap-2 px-3 py-2 text-[15px]" data-edit-row="' + x.id + '"><span class="text-dark/55 shrink-0">Serie ' + x.n + '</span>';
            h += '<input class="input py-1.5 w-16 text-center tabular-nums" inputmode="numeric" value="' + x.reps + '" data-edit-reps aria-label="Reps"><span class="text-dark/40">×</span>';
            h += '<input class="input py-1.5 w-20 text-center tabular-nums" inputmode="decimal" value="' + x.peso + '" data-edit-peso aria-label="Peso kg"><span class="text-dark/40 text-sm">kg</span>';
            h += '<button type="button" class="ml-auto text-primary font-medium text-sm" data-edit-ok="' + x.id + '">Guardar</button></div>';
            return;
          }
          h += '<div class="flex items-center justify-between gap-2 px-3 py-2 text-[15px]"><span class="text-dark/55">Serie ' + x.n + (x.auto ? ' <span class="chip chip-neutral ml-1" title="Marcada automáticamente al pasar el descanso">auto</span>' : '') + '</span>';
          h += '<span class="flex items-center gap-1"><span class="tabular-nums font-medium">' + x.reps + ' × ' + GG.kg(x.peso) + (x.pendiente ? ' <span class="text-xs text-warning">sin enviar</span>' : '') + '</span>';
          if (x.id) h += '<button type="button" class="text-dark/40 hover:text-primary px-1.5 text-sm" data-editar-serie="' + x.id + '">Editar</button><button type="button" class="text-dark/30 hover:text-danger px-1" data-borrar-serie="' + x.id + '" aria-label="Borrar">×</button>';
          h += '</span></div>';
        });
        h += '</div>';
      }
      if (D.sesion.estado !== 'finalizada' && !done && !skip) {
        const dr = draftFor(it);
        h += '<div class="grid grid-cols-2 gap-3">';
        h += stepper('reps', it.id, dr.reps, 'Reps', 1);
        h += stepper('peso', it.id, dr.peso, 'Peso (kg)', 2.5);
        h += '</div>';
        h += '<button type="button" class="btn-primary w-full py-3.5 text-[16px]" data-serie="' + it.id + '">Serie ' + (hechas + 1) + ' hecha</button>';
        h += '<div class="flex justify-between text-sm"><a class="text-dark/55" href="' + GG.url('ejercicio', { id: it.ejercicio_id }) + '">Ver ficha</a>';
        h += '<span class="flex gap-4">' + (hechas >= 1 ? '<button type="button" class="text-dark/55" data-terminar="' + it.id + '">Terminar ejercicio</button>' : '') + '<button type="button" class="text-dark/55" data-omitir="' + it.id + '">Saltar</button></span></div>';
      } else if (done || skip) {
        h += '<button type="button" class="text-sm text-dark/55" data-reabrir="' + it.id + '">Reabrir ejercicio</button>';
      }
      h += '</div>';
    }
    h += '</article>';
    return h;
  }

  function stepper(kind, id, val, label, step) {
    return '<div><div class="label">' + label + '</div><div class="flex items-stretch rounded-lg border border-dark/15 bg-white overflow-hidden">' +
      '<button type="button" class="px-3.5 text-xl text-dark/60 active:bg-dark/5" data-step="' + kind + '" data-id="' + id + '" data-d="-' + step + '">−</button>' +
      '<input class="w-full text-center text-xl font-medium tabular-nums py-2.5 bg-transparent outline-none" inputmode="decimal" value="' + val + '" data-draft="' + kind + '" data-id="' + id + '">' +
      '<button type="button" class="px-3.5 text-xl text-dark/60 active:bg-dark/5" data-step="' + kind + '" data-id="' + id + '" data-d="' + step + '">+</button></div></div>';
  }

  function draftFor(it) {
    if (!drafts[it.id]) {
      const last = it.hechas[it.hechas.length - 1];
      const ult = it.ultimo && it.ultimo.series[0];
      drafts[it.id] = {
        reps: last ? last.reps : it.reps_max,
        peso: last ? last.peso : (it.peso_sugerido != null ? it.peso_sugerido : (ult ? ult.peso : 0)),
      };
    }
    return drafts[it.id];
  }

  function renderFinal() {
    const f = $('[data-acciones-finales]');
    const s = D.sesion;
    let h = '';
    if (s.estado !== 'finalizada') {
      if (s.estado === 'en_curso' && !D.yo.solo_plantillas) h += '<button type="button" class="btn-secondary w-full" data-extra>+ Agregar ejercicio extra</button>';
      if (D.items.length) h += '<button type="button" class="btn-ghost w-full" data-finalizar>Finalizar sesión</button>';
    } else {
      const series = D.items.reduce((a, it) => a + it.hechas.length, 0);
      const vol = D.items.reduce((a, it) => a + it.hechas.reduce((b, x) => b + x.reps * x.peso, 0), 0);
      h += '<div class="card p-5 text-center"><div class="text-lg font-semibold">¡Sesión terminada! 🦍</div><div class="mt-1 text-dark/60">' + series + ' series · ' + Math.round(vol).toLocaleString('es-CL') + ' kg de volumen</div>';
      if (D.yo.es_alumno) {
        h += '<div class="mt-4 text-sm text-dark/70">¿Cómo te sentiste?</div><div class="mt-2 grid grid-cols-5 gap-2 rate">';
        for (let i = 1; i <= 5; i++) h += '<label><input type="radio" name="fb" value="' + i + '" ' + (s.feedback == i ? 'checked' : '') + ' data-feedback><span>' + ['😵', '😮‍💨', '🙂', '💪', '🔥'][i - 1] + '</span></label>';
        h += '</div>';
        if (D.yo.puede_calificar) h += '<a href="' + D.urls.calificar + '" class="mt-4 btn-secondary w-full">Calificar a ' + GG.esc(D.coach.nombre) + '</a>';
      }
      h += '</div><a href="' + D.urls.inicio + '" class="btn-ghost w-full">Volver al inicio</a>';
    }
    f.innerHTML = h;
  }

  // ---------------------------------------------------------------- acciones
  async function iniciarItem(id, compartir) {
    const r = await send('api/item/iniciar', { item_id: id, compartir: compartir ? 1 : 0 });
    if (r.offline) return;
    if (r._status === 409 && r.ocupada) { modalOcupada(id, r); return; }
    if (!r.ok) { GG.toast(r.error || 'No se pudo iniciar'); return; }
    apply(r);
  }

  async function registrarSerie(id) {
    const it = D.items.find((i) => i.id === id);
    const dr = draftFor(it);
    const reps = parseInt(dr.reps, 10), peso = parseFloat(String(dr.peso).replace(',', '.')) || 0;
    if (!reps || reps < 1) { GG.toast('Ingresa las repeticiones'); return; }
    const data = { item_id: id, reps, peso, uuid: GG.uuid() };
    const btn = root.querySelector('[data-serie="' + id + '"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Guardando…'; }
    if (it.ocupada && !it.hechas.length && !it.compartido) {
      const ok = await send('api/item/iniciar', { item_id: id });
      if (ok._status === 409 && ok.ocupada) { modalOcupada(id, ok); if (btn) btn.disabled = false; render(); return; }
    }
    const r = await send('api/serie', data);
    if (r.offline || (r.ok === false && !r._status)) {
      const q = queue(); q.push({ sid: SID, route: 'api/serie', data }); saveQueue(q);
      it.hechas.push({ id: 0, n: it.hechas.length + 1, reps, peso, uuid: data.uuid, pendiente: true });
      if (it.hechas.length >= it.series) it.estado = 'hecho';
      if (it.descanso) startRest(now() + it.descanso * 1000, siguienteTexto(it));
      if (it.estado === 'hecho') avanzar(it);
      render();
      return;
    }
    if (!r.ok) { GG.toast(r.error || 'No se pudo guardar'); if (btn) btn.disabled = false; render(); return; }
    GG.vibrate(30);
    if (r.record) { GG.toast('🏆 ' + r.record.texto + '!', 4000); GG.vibrate([60, 40, 60]); }
    const itNuevo = r.items.find((x) => x.id === id);
    apply(r);
    if (r.rest_end) startRest(r.rest_end * 1000 + clockOffset, siguienteTexto(itNuevo));
    if (itNuevo && itNuevo.estado === 'hecho') { avanzar(itNuevo); render(); }
  }

  function siguienteTexto(it) {
    if (!it) return '';
    if (it.hechas.length < it.series) return 'Siguiente: serie ' + (it.hechas.length + 1) + ' de ' + it.nombre;
    const sig = D.items.find((x) => x.estado === 'pendiente' && x.id !== it.id);
    return sig ? 'Siguiente: ' + sig.nombre : 'Último ejercicio terminado 💪';
  }

  function avanzar(it) {
    // dentro de superserie: pasar al siguiente del bloque; si no, siguiente pendiente
    const sig = D.items.find((x) => x.estado === 'pendiente' || x.estado === 'en_curso');
    if (sig) { active = sig.id; delete root.dataset.scrolled; }
  }

  async function estadoItem(id, estado) {
    const r = await send('api/item/estado', { item_id: id, estado });
    if (r.ok) { apply(r); if (estado !== 'pendiente') { avanzar(); render(); } } else GG.toast(r.error || 'Sin conexión');
  }

  async function borrarSerie(id) {
    if (!confirm('¿Borrar esta serie?')) return;
    const r = await send('api/serie/borrar', { id });
    if (r.ok) apply(r); else GG.toast(r.error || 'No se pudo borrar');
  }

  async function guardarEdicion(id) {
    const row = root.querySelector('[data-edit-row="' + id + '"]');
    if (!row) return;
    const reps = parseInt(row.querySelector('[data-edit-reps]').value, 10);
    const peso = parseFloat(String(row.querySelector('[data-edit-peso]').value).replace(',', '.')) || 0;
    if (!reps || reps < 1) { GG.toast('Ingresa las repeticiones'); return; }
    const r = await send('api/serie/editar', { id, reps, peso });
    if (!r.ok) { GG.toast(r.error || 'No se pudo guardar'); return; }
    editando = 0;
    Object.keys(drafts).forEach((k) => delete drafts[k]);
    if (r.record) { GG.toast('🏆 ' + r.record.texto + '!', 4000); GG.vibrate([60, 40, 60]); }
    apply(r);
  }

  // ---------------------------------------------------------------- serie automática
  // Si el alumno se olvida de tocar "Serie hecha", al pasar descanso + tiempo de una serie (+20 s de holgura)
  // se marca sola con las mismas reps y peso de la anterior. Solo en el celular del alumno, y no después de 15 min sin actividad.
  async function revisarAuto() {
    if (!D || autoEnVuelo || !D.yo.es_alumno || D.sesion.estado !== 'en_curso' || restEnd || editando || typing()) return;
    if (!navigator.onLine || queue().some((x) => x.sid === SID)) return;
    const t = srvNow() / 1000;
    for (const it of D.items) {
      if (it.estado !== 'en_curso' || !it.hechas.length || it.hechas.length >= it.series) continue;
      const last = it.hechas[it.hechas.length - 1];
      if (!last.id || !last.ts) continue;
      const ciclo = (it.descanso || 0) + (it.sps || 45) + 20;
      const pasado = t - last.ts;
      if (pasado < ciclo || pasado >= 900) continue;
      autoEnVuelo = true;
      try {
        const r = await send('api/serie', { item_id: it.id, reps: last.reps, peso: last.peso, uuid: GG.uuid(), auto: 1 });
        if (r.ok && !r.auto_rechazada) {
          const nuevo = r.items.find((x) => x.id === it.id);
          const n = nuevo ? nuevo.hechas.length : it.hechas.length + 1;
          delete drafts[it.id];
          apply(r);
          GG.toast('Serie ' + n + ' de ' + it.nombre + ' marcada automáticamente. Puedes editarla.', 4500);
          GG.vibrate(30);
          if (r.rest_end) startRest(r.rest_end * 1000 + clockOffset, siguienteTexto(nuevo));
          if (nuevo && nuevo.estado === 'hecho') { avanzar(nuevo); render(); }
        } else if (r.ok) apply(r);
      } finally { autoEnVuelo = false; }
      return;
    }
  }
  setInterval(revisarAuto, 5000);

  async function finalizar() {
    const pend = D.items.filter((i) => i.estado === 'pendiente' && !i.hechas.length).length;
    if (!confirm(pend ? 'Quedan ' + pend + ' ejercicios sin hacer. ¿Finalizar igual?' : '¿Finalizar la sesión?')) return;
    const r = await send('api/sesion/finalizar', { id: SID });
    if (r.ok) { stopRest(true); apply(r); window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); } else GG.toast(r.error || 'Sin conexión');
  }

  // ---------------------------------------------------------------- ocupada / alternativas
  const modal = document.getElementById('modal');
  function openModal(html) { modal.querySelector('[data-modal-body]').innerHTML = html; modal.classList.remove('hidden'); modal.classList.add('flex'); }
  function closeModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); }
  modal.addEventListener('click', (e) => { if (e.target === modal || e.target.closest('[data-cerrar-modal]')) closeModal(); });

  function modalOcupada(id, r) {
    let h = '<div class="p-5"><div class="font-semibold text-lg">Máquina ocupada</div><p class="text-dark/65 mt-1">' + GG.esc(r.alumno || 'Otro alumno') + (r.coach ? ' con ' + GG.esc(r.coach) : '') + ' · libre en ~' + r.libre_en + ' min</p>';
    h += '<div class="mt-4 grid gap-2"><button class="btn-secondary" data-compartir="' + id + '">Compartir (alternar series)</button><button class="btn-secondary" data-al-final="' + id + '">Mover al final de la rutina</button></div>';
    h += altList(id, r.alternativas || []);
    h += '<button class="mt-3 w-full btn-ghost" data-cerrar-modal>Cancelar</button></div>';
    openModal(h);
  }
  function altList(id, alts) {
    if (!alts.length) return '<p class="mt-4 text-sm text-dark/55">Este ejercicio no tiene alternativas cargadas.</p>';
    let h = '<div class="mt-5 text-sm font-medium">Alternativas</div><div class="mt-2 divide-y divide-dark/5 rounded-lg border border-dark/10">';
    alts.forEach((a) => {
      h += '<button class="w-full text-left px-3 py-2.5 flex items-center justify-between gap-2" data-reemplazar="' + id + '" data-ej="' + a.id + '"><span><span class="block">' + GG.esc(a.nombre) + '</span><span class="text-xs text-dark/55">' + GG.esc(a.maquina || '') + (a.conflictos.length ? ' · ojo: ' + GG.esc(a.conflictos.join(', ')) : '') + '</span></span>' +
        '<span class="chip ' + (a.libre ? 'chip-ok' : 'chip-neutral') + '">' + (a.libre ? 'libre' : '~' + a.libre_en + ' min') + '</span></button>';
    });
    return h + '</div>';
  }
  async function modalAlternativas(id) {
    const it = D.items.find((i) => i.id === id);
    const r = await GG.get('api/ocupacion', { ejercicio: it.ejercicio_id, alumno: D.alumno.id });
    openModal('<div class="p-5"><div class="font-semibold text-lg">Cambiar ' + GG.esc(it.nombre) + '</div>' + altList(id, r.alternativas || []) + '<button class="mt-3 w-full btn-ghost" data-cerrar-modal>Cancelar</button></div>');
  }
  async function builderAccion(data) {
    const r = await send('api/builder', Object.assign({ ctx: 'sesion', id: SID }, data));
    if (!r.ok) { GG.toast(r.error || 'No se pudo'); return false; }
    closeModal();
    await load();
    return true;
  }

  // ---------------------------------------------------------------- ejercicio extra
  async function modalExtra() {
    openModal('<div class="p-5"><div class="font-semibold text-lg">Agregar ejercicio extra</div><input class="input mt-3" placeholder="Buscar ejercicio" data-extra-q autofocus><div class="mt-3 max-h-[50vh] overflow-y-auto divide-y divide-dark/5" data-extra-res></div><button class="mt-3 w-full btn-ghost" data-cerrar-modal>Cerrar</button></div>');
    const inp = modal.querySelector('[data-extra-q]');
    const buscar = async () => {
      const r = await GG.get('api/ejercicios', { q: inp.value, alumno: D.alumno.id });
      modal.querySelector('[data-extra-res]').innerHTML = (r.items || []).filter((x) => !x.mantencion).map((x) =>
        '<button class="w-full text-left py-2.5 px-1 flex justify-between gap-2" data-add-extra="' + x.id + '"><span><span class="block">' + GG.esc(x.nombre) + '</span><span class="text-xs text-dark/55">' + GG.esc([x.grupo, x.maquina].filter(Boolean).join(' · ')) + '</span></span>' + (x.ocupada ? '<span class="chip chip-neutral">~' + x.ocupada + ' min</span>' : '') + '</button>').join('') || '<p class="text-sm text-dark/55 py-2">Sin resultados</p>';
    };
    let t; inp.addEventListener('input', () => { clearTimeout(t); t = setTimeout(buscar, 250); });
    buscar();
  }

  // ---------------------------------------------------------------- descanso
  const restEl = document.getElementById('rest');
  function startRest(endMs, next) {
    restEnd = endMs;
    restCancelSent = false;
    restEl.querySelector('[data-rest-next]').textContent = next || '';
    restEl.classList.remove('hidden'); restEl.classList.add('flex');
    clearInterval(restTimer);
    tickRest();
    restTimer = setInterval(tickRest, 250);
    requestWake();
  }
  function tickRest() {
    const left = Math.max(0, Math.ceil((restEnd - now()) / 1000));
    restEl.querySelector('[data-rest-time]').textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
    if (left <= 6 && !restCancelSent && document.visibilityState === 'visible') {
      restCancelSent = true;
      GG.api('api/descanso/cancelar', {}).catch(() => {});
    }
    if (left <= 0) { stopRest(false); GG.beep(); GG.vibrate([200, 100, 200]); GG.toast('¡Terminó el descanso! A darle 💪'); }
  }
  function stopRest(cancel) {
    clearInterval(restTimer);
    restEnd = 0;
    restEl.classList.add('hidden'); restEl.classList.remove('flex');
    if (cancel) GG.api('api/descanso/cancelar', {}).catch(() => {});
  }
  restEl.querySelector('[data-rest-skip]').addEventListener('click', () => stopRest(true));
  restEl.querySelector('[data-rest-add]').addEventListener('click', () => { restEnd += 15000; restCancelSent = false; tickRest(); });
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') { if (restEnd) tickRest(); requestWake(); load(); } });

  // Wake lock
  const wl = restEl.querySelector('[data-wakelock]');
  wl.checked = localStorage.getItem('gg_wake') === '1';
  wl.addEventListener('change', () => { localStorage.setItem('gg_wake', wl.checked ? '1' : '0'); if (wl.checked) requestWake(); else if (wakeLock) { wakeLock.release(); wakeLock = null; } });
  async function requestWake() {
    if (!wl.checked || !('wakeLock' in navigator) || wakeLock) return;
    try { wakeLock = await navigator.wakeLock.request('screen'); wakeLock.addEventListener('release', () => (wakeLock = null)); } catch (e) {}
  }

  // ---------------------------------------------------------------- eventos
  root.addEventListener('click', async (e) => {
    const t = e.target;
    let el;
    if ((el = t.closest('[data-abrir]'))) {
      const id = +el.dataset.abrir;
      active = active === id ? 0 : id;
      delete root.dataset.scrolled;
      render();
      return;
    }
    if ((el = t.closest('[data-step]'))) {
      const id = +el.dataset.id, kind = el.dataset.step, d = parseFloat(el.dataset.d);
      const it = D.items.find((i) => i.id === id); const dr = draftFor(it);
      let v = parseFloat(String(dr[kind]).replace(',', '.')) || 0;
      v = Math.max(0, Math.round((v + d) * 100) / 100);
      dr[kind] = kind === 'reps' ? Math.round(v) : v;
      const inp = root.querySelector('[data-draft="' + kind + '"][data-id="' + id + '"]'); if (inp) inp.value = dr[kind];
      return;
    }
    if ((el = t.closest('[data-serie]'))) { registrarSerie(+el.dataset.serie); return; }
    if ((el = t.closest('[data-terminar]'))) { estadoItem(+el.dataset.terminar, 'hecho'); return; }
    if ((el = t.closest('[data-omitir]'))) { if (confirm('¿Saltar este ejercicio?')) estadoItem(+el.dataset.omitir, 'omitido'); return; }
    if ((el = t.closest('[data-reabrir]'))) { estadoItem(+el.dataset.reabrir, 'pendiente'); return; }
    if ((el = t.closest('[data-borrar-serie]'))) { borrarSerie(+el.dataset.borrarSerie); return; }
    if ((el = t.closest('[data-editar-serie]'))) { editando = +el.dataset.editarSerie; render(); const i = root.querySelector('[data-edit-reps]'); if (i) { i.focus(); i.select(); } return; }
    if ((el = t.closest('[data-edit-ok]'))) { guardarEdicion(+el.dataset.editOk); return; }
    if (t.closest('[data-finalizar]')) { finalizar(); return; }
    if (t.closest('[data-extra]')) { modalExtra(); return; }
    if ((el = t.closest('[data-compartir]'))) { iniciarItem(+el.dataset.compartir, true); return; }
    if ((el = t.closest('[data-al-final]'))) { builderAccion({ accion: 'al_final', item_id: +el.dataset.alFinal }); return; }
    if ((el = t.closest('[data-alternativas]'))) { modalAlternativas(+el.dataset.alternativas); return; }
  });
  modal.addEventListener('click', async (e) => {
    let el;
    if ((el = e.target.closest('[data-compartir]'))) { closeModal(); iniciarItem(+el.dataset.compartir, true); }
    if ((el = e.target.closest('[data-al-final]'))) builderAccion({ accion: 'al_final', item_id: +el.dataset.alFinal });
    if ((el = e.target.closest('[data-reemplazar]'))) builderAccion({ accion: 'reemplazar', item_id: +el.dataset.reemplazar, ejercicio_id: +el.dataset.ej });
    if ((el = e.target.closest('[data-add-extra]'))) {
      const ok = await builderAccion({ accion: 'agregar', ejercicio_id: +el.dataset.addExtra, extra: 1 });
      if (ok) { const last = D.items[D.items.length - 1]; if (last) { active = last.id; delete root.dataset.scrolled; render(); } }
    }
  });
  root.addEventListener('input', (e) => {
    const el = e.target.closest('[data-draft]');
    if (!el) return;
    const it = D.items.find((i) => i.id === +el.dataset.id);
    draftFor(it)[el.dataset.draft] = el.value;
  });
  root.addEventListener('change', async (e) => {
    const el = e.target.closest('[data-feedback]');
    if (el) { await send('api/feedback', { id: SID, valor: +el.value }); GG.toast('¡Gracias!'); }
  });

  const typing = () => document.activeElement && document.activeElement.matches && document.activeElement.matches('input, textarea');
  setInterval(() => { if (D && D.sesion.estado === 'en_curso' && document.visibilityState === 'visible' && !restEnd && !typing()) render(); }, 30000);
  setInterval(() => { if (D && D.sesion.estado !== 'finalizada' && document.visibilityState === 'visible' && navigator.onLine && !typing() && modal.classList.contains('hidden')) load(); }, 45000);
  if (navigator.onLine) GG.flushQueue();
  load();
})();
