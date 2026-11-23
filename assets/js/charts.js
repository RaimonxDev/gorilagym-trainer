/* GorilaGym · gráficos SVG livianos (línea y barras) */
(function () {
  'use strict';
  const GG = window.GG;
  const css = (v) => 'rgb(' + getComputedStyle(document.documentElement).getPropertyValue(v).trim() + ')';
  function draw(el) {
    let data = [];
    try { data = JSON.parse(el.dataset.json || '[]'); } catch (e) {}
    data = data.filter((d) => d && d.y !== null && !isNaN(d.y));
    if (!data.length) { el.innerHTML = '<div class="h-full flex items-center justify-center text-sm text-dark/45">Sin datos todavía</div>'; return; }
    const W = el.clientWidth || 320, H = el.clientHeight || 160;
    const pl = 34, pr = 8, pt = 10, pb = 22;
    const max = Math.max(...data.map((d) => d.y)) || 1;
    const min = el.dataset.chart === 'line' ? Math.min(...data.map((d) => d.y)) : 0;
    const lo = el.dataset.chart === 'line' ? Math.max(0, min - (max - min) * 0.15) : 0;
    const hi = max + (max - lo) * 0.1 || 1;
    const x = (i) => pl + (data.length === 1 ? (W - pl - pr) / 2 : (i * (W - pl - pr)) / (data.length - 1));
    const y = (v) => pt + (H - pt - pb) * (1 - (v - lo) / (hi - lo));
    const prim = css('--c-primary'), grid = 'rgba(17,29,19,0.08)', txt = 'rgba(17,29,19,0.5)';
    let s = '<svg viewBox="0 0 ' + W + ' ' + H + '" width="100%" height="100%" role="img">';
    for (let k = 0; k <= 3; k++) {
      const v = lo + ((hi - lo) * k) / 3, yy = y(v);
      s += '<line x1="' + pl + '" x2="' + (W - pr) + '" y1="' + yy + '" y2="' + yy + '" stroke="' + grid + '"/>';
      s += '<text x="' + (pl - 6) + '" y="' + (yy + 4) + '" text-anchor="end" font-size="10" fill="' + txt + '">' + (Math.round(v * 10) / 10).toLocaleString('es-CL') + '</text>';
    }
    const step = Math.max(1, Math.ceil(data.length / 6));
    if (el.dataset.chart === 'bars') {
      const bw = Math.max(4, Math.min(28, ((W - pl - pr) / data.length) * 0.6));
      data.forEach((d, i) => {
        const cx = data.length === 1 ? (W + pl - pr) / 2 : pl + bw / 2 + (i * (W - pl - pr - bw)) / (data.length - 1);
        s += '<rect x="' + (cx - bw / 2) + '" y="' + y(d.y) + '" width="' + bw + '" height="' + Math.max(0, H - pb - y(d.y)) + '" rx="2" fill="' + prim + '"><title>' + GG.esc(d.x) + ': ' + d.y + ' ' + (el.dataset.unit || '') + '</title></rect>';
        if (i % step === 0) s += '<text x="' + cx + '" y="' + (H - 6) + '" text-anchor="middle" font-size="10" fill="' + txt + '">' + GG.esc(String(d.x).slice(-5)) + '</text>';
      });
    } else {
      const pts = data.map((d, i) => x(i) + ',' + y(d.y)).join(' ');
      s += '<polyline points="' + pts + '" fill="none" stroke="' + prim + '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';
      data.forEach((d, i) => {
        s += '<circle cx="' + x(i) + '" cy="' + y(d.y) + '" r="3" fill="' + prim + '"><title>' + GG.esc(d.x) + ': ' + d.y + ' ' + (el.dataset.unit || '') + '</title></circle>';
        if (i % step === 0) s += '<text x="' + x(i) + '" y="' + (H - 6) + '" text-anchor="middle" font-size="10" fill="' + txt + '">' + GG.esc(d.x) + '</text>';
      });
    }
    el.innerHTML = s + '</svg>';
  }
  const all = () => document.querySelectorAll('[data-chart]').forEach(draw);
  all();
  let t; window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(all, 150); });
})();
