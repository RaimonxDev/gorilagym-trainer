/* GorilaGym · tarjeta para historias de Instagram */
(function () {
  'use strict';
  const GG = window.GG;
  const c = document.getElementById('sharecard');
  if (!c) return;
  const d = c.dataset;
  const ctx = c.getContext('2d');
  const font = (w, s) => w + ' ' + s + 'px "Google Sans", system-ui, sans-serif';
  function paint(logo) {
    ctx.fillStyle = d.primary; ctx.fillRect(0, 0, 1080, 1920);
    ctx.fillStyle = d.light;
    if (logo) { const h = 110, w = (logo.width / logo.height) * h; ctx.drawImage(logo, 120, 170, w, h); }
    ctx.globalAlpha = 0.75; ctx.font = font(500, 54); ctx.fillText(d.mes + ' en ' + d.brand, 120, 520); ctx.globalAlpha = 1;
    ctx.font = font(700, 300); ctx.fillText(d.dias, 110, 860);
    ctx.font = font(500, 70); ctx.fillText(+d.dias === 1 ? 'día entrenando' : 'días entrenando', 120, 960);
    const rows = [];
    if (+d.records) rows.push(d.records + (+d.records === 1 ? ' récord personal nuevo' : ' récords personales nuevos'));
    if (+d.racha > 1) rows.push(d.racha + ' semanas seguidas');
    ctx.font = font(500, 60);
    rows.forEach((r, i) => { ctx.globalAlpha = 0.9; ctx.fillText('· ' + r, 120, 1180 + i * 100); });
    ctx.globalAlpha = 0.6; ctx.font = font(400, 44); ctx.fillText('🦍', 120, 1760); ctx.globalAlpha = 1;
  }
  const img = new Image();
  img.onload = () => paint(img);
  img.onerror = () => paint(null);
  img.src = d.logo;
  if (document.fonts) document.fonts.ready.then(() => paint(img.complete && img.naturalWidth ? img : null));
  const blob = () => new Promise((res) => c.toBlob(res, 'image/png'));
  document.querySelector('[data-download-card]').addEventListener('click', async () => {
    const b = await blob(); const a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = 'mi-mes-' + d.mes.toLowerCase() + '.png'; a.click();
  });
  document.querySelector('[data-share-card]').addEventListener('click', async () => {
    const b = await blob();
    const file = new File([b], 'mi-mes.png', { type: 'image/png' });
    if (navigator.canShare && navigator.canShare({ files: [file] })) { try { await navigator.share({ files: [file] }); } catch (e) {} }
    else { GG.toast('Tu navegador no permite compartir directo: usa Descargar.'); }
  });
})();
