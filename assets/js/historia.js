/* GorilaGym · foto para historia de Instagram
 * - La cámara se abre DENTRO de la app (getUserMedia): la foto no pasa por la app de cámara
 *   del teléfono, así que no queda en la galería. Tampoco se sube al servidor.
 * - Se compone con el marco de la marca y se entrega al menú de compartir del sistema
 *   (Instagram → Historia). Una web no puede abrir el editor de historias directo.
 * - Respaldo: si el navegador no permite cámara en vivo, se usa el selector de cámara del sistema.
 */
(function () {
  'use strict';
  const GG = window.GG;
  const W = 1080, H = 1920;
  let modal = null, video = null, stream = null, facing = 'user', data = {}, logo = null;

  function cargarLogo() {
    if (logo) return Promise.resolve(logo);
    return new Promise((res) => {
      const im = new Image();
      im.onload = () => { logo = im; res(im); };
      im.onerror = () => res(null);
      im.src = GG.base + '/assets/img/brand/logo-horizontal-claro.svg';
    });
  }

  function detener() {
    if (stream) stream.getTracks().forEach((t) => t.stop());
    stream = null;
  }

  function cerrar() {
    detener();
    if (modal) {
      const c = modal.querySelector('canvas');
      if (c) { c.getContext('2d').clearRect(0, 0, W, H); c.width = 1; c.height = 1; } // libera la imagen de memoria
      modal.remove();
    }
    modal = null; video = null;
    document.documentElement.classList.remove('overflow-hidden');
  }

  function base(html) {
    cerrar();
    modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-50 bg-black text-light flex flex-col';
    modal.innerHTML = html;
    document.body.appendChild(modal);
    document.documentElement.classList.add('overflow-hidden');
    modal.querySelectorAll('[data-h-cerrar]').forEach((b) => b.addEventListener('click', cerrar));
  }

  // ------------------------------------------------------------ visor de cámara
  async function abrirVisor() {
    if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) return abrirSelector();
    base(
      '<div class="relative flex-1 overflow-hidden">' +
      '<video playsinline autoplay muted class="absolute inset-0 w-full h-full object-cover"></video>' +
      '<div class="absolute inset-x-0 top-0 p-5 pt-[calc(env(safe-area-inset-top)+16px)] bg-gradient-to-b from-black/60 to-transparent flex items-start justify-between">' +
      '<img src="' + GG.base + '/assets/img/brand/logo-horizontal-claro.svg" alt="" class="h-9">' +
      '<button type="button" class="p-2 -m-2 text-2xl leading-none" data-h-cerrar aria-label="Cerrar">✕</button></div>' +
      '<div class="absolute inset-x-0 bottom-0 p-6 bg-gradient-to-t from-black/70 to-transparent"><div class="text-2xl font-semibold leading-snug" data-h-texto></div><div class="text-sm text-light/75 mt-1" data-h-fecha></div></div>' +
      '</div>' +
      '<div class="flex items-center justify-between px-8 py-5 pb-[calc(env(safe-area-inset-bottom)+20px)]">' +
      '<button type="button" class="w-12 text-sm text-light/80" data-h-cerrar>Cancelar</button>' +
      '<button type="button" class="w-[72px] h-[72px] rounded-full border-4 border-light flex items-center justify-center" data-h-shot aria-label="Tomar foto"><span class="w-14 h-14 rounded-full bg-light"></span></button>' +
      '<button type="button" class="w-12 text-sm text-light/80" data-h-girar>Girar</button></div>');
    modal.querySelector('[data-h-texto]').textContent = data.texto;
    modal.querySelector('[data-h-fecha]').textContent = fechaTxt();
    video = modal.querySelector('video');
    modal.querySelector('[data-h-shot]').addEventListener('click', disparar);
    modal.querySelector('[data-h-girar]').addEventListener('click', () => { facing = facing === 'user' ? 'environment' : 'user'; iniciar(); });
    await iniciar();
  }

  async function iniciar() {
    detener();
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false });
      video.srcObject = stream;
      video.style.transform = facing === 'user' ? 'scaleX(-1)' : '';
      await video.play().catch(() => {});
    } catch (e) {
      cerrar();
      if (e && e.name === 'NotAllowedError') GG.toast('Necesitamos permiso de cámara para la foto. Actívalo en los ajustes del navegador.', 5000);
      abrirSelector();
    }
  }

  function disparar() {
    if (!video || !video.videoWidth) return;
    const src = document.createElement('canvas');
    src.width = video.videoWidth; src.height = video.videoHeight;
    const sx = src.getContext('2d');
    if (facing === 'user') { sx.translate(src.width, 0); sx.scale(-1, 1); } // selfie como espejo, igual que se ve
    sx.drawImage(video, 0, 0);
    detener();
    GG.vibrate(20);
    componer(src, src.width, src.height);
  }

  // ------------------------------------------------------------ respaldo: selector de cámara del sistema
  function abrirSelector() {
    const inp = document.createElement('input');
    inp.type = 'file'; inp.accept = 'image/*'; inp.setAttribute('capture', 'user');
    inp.addEventListener('change', () => {
      const f = inp.files && inp.files[0];
      if (!f) return;
      const url = URL.createObjectURL(f);
      const img = new Image();
      img.onload = () => { componer(img, img.naturalWidth, img.naturalHeight); URL.revokeObjectURL(url); };
      img.src = url;
    });
    inp.click();
  }

  // ------------------------------------------------------------ composición y compartir
  function fechaTxt() {
    const f = new Date().toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long' });
    return f.charAt(0).toUpperCase() + f.slice(1) + (data.ig ? '  ·  ' + data.ig : '');
  }
  function wrap(ctx, text, maxW) {
    const words = String(text).split(' '); const lines = []; let line = '';
    words.forEach((w) => { const t = line ? line + ' ' + w : w; if (ctx.measureText(t).width > maxW && line) { lines.push(line); line = w; } else line = t; });
    if (line) lines.push(line);
    return lines.slice(0, 3);
  }

  async function componer(source, sw, sh) {
    base(
      '<div class="flex-1 flex items-center justify-center p-4 pt-[calc(env(safe-area-inset-top)+16px)] overflow-hidden"><canvas width="' + W + '" height="' + H + '" class="max-h-full max-w-full rounded-lg" style="aspect-ratio:9/16"></canvas></div>' +
      '<div class="px-4 pb-[calc(env(safe-area-inset-bottom)+16px)] space-y-2 max-w-sm w-full mx-auto">' +
      '<button type="button" class="btn w-full bg-light text-dark hover:bg-white" data-h-share>Compartir en tu historia</button>' +
      '<div class="grid grid-cols-2 gap-2"><button type="button" class="btn bg-light/10 text-light hover:bg-light/20" data-h-otra>Otra foto</button><button type="button" class="btn bg-light/10 text-light hover:bg-light/20" data-h-cerrar>Cancelar</button></div>' +
      '<p class="text-xs text-light/60 text-center" data-h-ayuda>En el menú elige <b>Instagram → Historia</b>. La foto no se guarda en tu celular ni en el gimnasio.</p></div>');
    const canvas = modal.querySelector('canvas');
    const ctx = canvas.getContext('2d');
    const s = Math.max(W / sw, H / sh), w = sw * s, h = sh * s;
    ctx.fillStyle = '#111D13'; ctx.fillRect(0, 0, W, H);
    ctx.drawImage(source, (W - w) / 2, (H - h) / 2, w, h);
    let g = ctx.createLinearGradient(0, 0, 0, 420); g.addColorStop(0, 'rgba(17,29,19,0.65)'); g.addColorStop(1, 'rgba(17,29,19,0)');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, 420);
    g = ctx.createLinearGradient(0, H - 760, 0, H); g.addColorStop(0, 'rgba(17,29,19,0)'); g.addColorStop(1, 'rgba(17,29,19,0.85)');
    ctx.fillStyle = g; ctx.fillRect(0, H - 760, W, 760);
    const lg = await cargarLogo();
    if (lg) { const lh = 96, lw = (lg.naturalWidth / lg.naturalHeight) * lh || 230; ctx.drawImage(lg, 72, 120, lw, lh); }
    ctx.fillStyle = '#F5F9E5';
    ctx.font = '600 76px "Google Sans", system-ui, sans-serif';
    const lines = wrap(ctx, data.texto, W - 144);
    let y = H - 430 - (lines.length - 1) * 88; // deja libre la franja inferior que usa Instagram
    lines.forEach((l) => { ctx.fillText(l, 72, y); y += 88; });
    ctx.globalAlpha = 0.85; ctx.font = '400 42px "Google Sans", system-ui, sans-serif';
    ctx.fillText(fechaTxt(), 72, y + 20); ctx.globalAlpha = 1;
    if (source.getContext) { source.width = 1; source.height = 1; } // descarta el cuadro original

    modal.querySelector('[data-h-otra]').addEventListener('click', abrirVisor);
    modal.querySelector('[data-h-share]').addEventListener('click', async (e) => {
      const btn = e.currentTarget;
      btn.disabled = true;
      const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.9));
      btn.disabled = false;
      const file = new File([blob], 'historia.jpg', { type: 'image/jpeg' });
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        try { await navigator.share({ files: [file] }); cerrar(); GG.toast('¡Listo! 🦍'); } catch (err) { /* canceló */ }
        return;
      }
      modal.querySelector('[data-h-ayuda]').innerHTML = 'Este navegador no permite enviar la foto a Instagram. Usa Safari (iPhone) o Chrome (Android).';
    });
  }

  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-historia]');
    if (!b) return;
    data = { texto: b.dataset.texto || 'Entrenando en la jungla', ig: b.dataset.ig || '' };
    abrirVisor();
  });
})();
