/* GorilaGym · QR (descarga PNG/SVG) y grabación de tags NFC (Web NFC, Chrome Android) */
(function () {
  'use strict';
  const GG = window.GG;
  if (typeof window.qrcode !== 'function') return;
  const dark = getComputedStyle(document.documentElement).getPropertyValue('--c-dark').trim();
  const color = dark ? 'rgb(' + dark.split(' ').join(',') + ')' : '#111D13';
  function make(url) { const q = window.qrcode(0, 'M'); q.addData(url); q.make(); return q; }
  function svgOf(q, size) {
    const n = q.getModuleCount(), m = 2, cell = size / (n + m * 2);
    let p = '';
    for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (q.isDark(r, c)) p += 'M' + ((c + m) * cell).toFixed(2) + ' ' + ((r + m) * cell).toFixed(2) + 'h' + cell.toFixed(2) + 'v' + cell.toFixed(2) + 'h-' + cell.toFixed(2) + 'z';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" width="100%" height="100%"><rect width="100%" height="100%" fill="#fff"/><path d="' + p + '" fill="' + color + '"/></svg>';
  }
  function slug(s) { return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'qr'; }
  function download(blob, name) { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 2000); }

  document.querySelectorAll('[data-qrnfc]').forEach((box) => {
    const url = box.dataset.url, name = box.dataset.nombre;
    const q = make(url);
    const svg = svgOf(q, 600);
    const target = box.querySelector('[data-qr]');
    if (target) target.innerHTML = svg;
    const png = box.querySelector('[data-qr-png]');
    if (png) png.addEventListener('click', () => {
      const img = new Image();
      img.onload = () => { const cv = document.createElement('canvas'); cv.width = cv.height = 1200; cv.getContext('2d').drawImage(img, 0, 0, 1200, 1200); cv.toBlob((b) => download(b, 'qr-' + slug(name) + '.png')); };
      img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    });
    const sv = box.querySelector('[data-qr-svg]');
    if (sv) sv.addEventListener('click', () => download(new Blob([svg], { type: 'image/svg+xml' }), 'qr-' + slug(name) + '.svg'));
    const nfc = box.querySelector('[data-nfc-write]');
    if (nfc && 'NDEFReader' in window) {
      nfc.classList.remove('hidden');
      nfc.addEventListener('click', async () => {
        const msg = box.querySelector('[data-nfc-msg]');
        try {
          msg.classList.remove('hidden'); msg.textContent = 'Acerca el tag NFC a la parte trasera del celular…';
          const w = new window.NDEFReader();
          await w.write({ records: [{ recordType: 'url', data: url }] });
          msg.textContent = '✓ Tag grabado. Pruébalo acercando otro celular.';
          GG.vibrate(60);
        } catch (e) { msg.textContent = 'No se pudo grabar: ' + (e.message || e.name); }
      });
    }
  });
})();
