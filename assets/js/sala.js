/* GorilaGym · sala en vivo */
(function () {
  'use strict';
  const GG = window.GG;
  const root = document.getElementById('sala');
  if (!root) return;
  const $ = (s) => root.querySelector(s);
  async function load() {
    const r = await GG.api('api/sala').catch(() => null);
    if (!r || !r.ok) return;
    document.querySelector('[data-hora]').textContent = r.hora;
    $('[data-presentes]').textContent = r.presentes;
    $('[data-nses]').textContent = r.sesiones.length;
    $('[data-grupos]').innerHTML = r.grupos.map((g) => '<span class="chip chip-neutral">' + GG.esc(g.nombre) + ' · ' + g.n + '</span>').join('') || '<span class="text-sm text-dark/50">—</span>';
    $('[data-sesiones]').innerHTML = r.sesiones.map((s) => {
      const pct = Math.min(100, (s.minutos / s.max) * 100);
      const warn = s.modo === 'coach' && s.minutos >= s.max - 10;
      return '<a href="' + s.url + '" class="card p-4 block hover:border-primary/40">' +
        '<div class="flex justify-between gap-2"><span class="font-medium">' + GG.esc(s.alumno) + '</span><span class="text-sm ' + (warn ? 'text-warning font-medium' : 'text-dark/55') + '">' + s.minutos + (s.modo === 'coach' ? '/' + s.max : '') + ' min</span></div>' +
        '<div class="text-sm text-dark/55">' + (s.coach ? 'Coach ' + GG.esc(s.coach) : 'Libre') + ' · ' + s.hechos + '/' + s.total + ' ejercicios · quedan ~' + s.restante + ' min</div>' +
        (s.actual ? '<div class="mt-2 text-[15px]">' + (s.actual.estado === 'en_curso' ? 'Haciendo: ' : 'Sigue: ') + '<b class="font-medium">' + GG.esc(s.actual.nombre) + '</b>' + (s.actual.ultima ? ' <span class="text-dark/55">· ' + s.actual.ultima.reps + '×' + GG.kg(s.actual.ultima.peso) + '</span>' : '') + '</div>' : '') +
        '<div class="mt-2 h-1 rounded-full bg-dark/10 overflow-hidden"><div class="h-full ' + (warn ? 'bg-warning' : 'bg-primary') + '" style="width:' + pct + '%"></div></div></a>';
    }).join('') || '<p class="text-sm text-dark/55">Nadie con sesión en curso.</p>';
    $('[data-ocupacion]').innerHTML = r.ocupacion.map((o) => '<div class="flex justify-between px-4 py-2.5"><span>' + GG.esc(o.maquina) + '</span><span class="text-dark/55">' + GG.esc(o.alumno) + ' · ~' + o.libre_en + ' min</span></div>').join('') || '<p class="p-4 text-dark/55">Todas libres.</p>';
    $('[data-mantencion]').innerHTML = r.mantencion.map((m) => '<div class="flex items-center justify-between px-4 py-2.5"><span>' + GG.esc(m.nombre) + '</span><button type="button" class="text-primary" data-mantencion="' + m.id + '" data-estado="ok">Reactivar</button></div>').join('') || '<p class="p-4 text-dark/55">Ninguna.</p>';
  }
  load();
  setInterval(() => { if (document.visibilityState === 'visible') load(); }, 15000);
})();
