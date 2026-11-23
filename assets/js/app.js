/* GorilaGym · núcleo del cliente */
(function () {
  'use strict';
  const body = document.body;
  const BASE = body.dataset.base || '';
  const GG = (window.GG = window.GG || {});
  GG.base = BASE;
  GG.csrf = body.dataset.csrf || '';

  // ------------------------------------------------------------ utilidades
  GG.$ = (s, el) => (el || document).querySelector(s);
  GG.$$ = (s, el) => Array.from((el || document).querySelectorAll(s));
  GG.esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  GG.url = (route, params) => {
    const q = new URLSearchParams(Object.assign(route ? { r: route } : {}, params || {}));
    return BASE + '/?' + q.toString();
  };
  GG.kg = (n) => (n == null || n === '' ? '—' : (Math.round(n * 100) / 100).toLocaleString('es-CL') + ' kg');
  GG.uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2));

  GG.api = async function (route, data, opts) {
    opts = opts || {};
    const isGet = opts.method === 'GET' || data === undefined;
    const url = isGet ? GG.url(route, data || {}) : GG.url(route);
    const res = await fetch(url, {
      method: isGet ? 'GET' : 'POST',
      credentials: 'same-origin',
      headers: isGet ? { Accept: 'application/json' } : { 'Content-Type': 'application/json', 'X-CSRF-Token': GG.csrf, Accept: 'application/json' },
      body: isGet ? undefined : JSON.stringify(data),
    });
    let json = null;
    try { json = await res.json(); } catch (e) { json = { ok: false, error: 'Respuesta inválida del servidor' }; }
    json._status = res.status;
    if (res.status === 401) { location.href = GG.url('login'); }
    return json;
  };

  GG.get = (route, params) => GG.api(route, params || {}, { method: 'GET' });

  let toastTimer;
  GG.toast = function (msg, ms) {
    const t = GG.$('#toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.remove('hidden');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.add('hidden'), ms || 2800);
  };

  GG.vibrate = (p) => { try { navigator.vibrate && navigator.vibrate(p); } catch (e) {} };

  GG.beep = function () {
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      GG._ac = GG._ac || new Ctx();
      const ac = GG._ac;
      [0, 0.25].forEach((d) => {
        const o = ac.createOscillator(); const g = ac.createGain();
        o.type = 'sine'; o.frequency.value = 880;
        g.gain.setValueAtTime(0.0001, ac.currentTime + d);
        g.gain.exponentialRampToValueAtTime(0.4, ac.currentTime + d + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime + d + 0.2);
        o.connect(g).connect(ac.destination); o.start(ac.currentTime + d); o.stop(ac.currentTime + d + 0.22);
      });
    } catch (e) {}
  };
  // desbloquear audio en iOS con el primer toque
  document.addEventListener('touchstart', function unlock() {
    try { const Ctx = window.AudioContext || window.webkitAudioContext; if (Ctx && !GG._ac) GG._ac = new Ctx(); GG._ac && GG._ac.resume(); } catch (e) {}
    document.removeEventListener('touchstart', unlock);
  }, { passive: true });

  // ------------------------------------------------------------ RUT
  GG.rutClean = (r) => String(r || '').replace(/[^0-9kK]/g, '').toUpperCase();
  GG.rutValid = function (r) {
    const c = GG.rutClean(r);
    if (c.length < 2) return false;
    const num = c.slice(0, -1), dv = c.slice(-1);
    if (!/^\d+$/.test(num) || parseInt(num, 10) < 100000) return false;
    let s = 0, m = 2;
    for (let i = num.length - 1; i >= 0; i--) { s += parseInt(num[i], 10) * m; m = m === 7 ? 2 : m + 1; }
    const r2 = 11 - (s % 11);
    const exp = r2 === 11 ? '0' : r2 === 10 ? 'K' : String(r2);
    return exp === dv;
  };
  GG.rutFormat = function (r) {
    const c = GG.rutClean(r);
    if (c.length < 2) return c;
    const num = c.slice(0, -1).replace(/^0+/, ''), dv = c.slice(-1);
    return num.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '-' + dv;
  };
  function bindRut(input) {
    let err = input.parentElement.querySelector('[data-rut-error]');
    if (!err) {
      err = document.createElement('p');
      err.className = 'hidden mt-1.5 text-sm text-danger';
      err.setAttribute('data-rut-error', '');
      err.textContent = 'RUT inválido: revisa el dígito verificador';
      input.insertAdjacentElement('afterend', err);
    }
    input.setAttribute('maxlength', '12');
    input.setAttribute('autocapitalize', 'characters');
    input.addEventListener('input', () => {
      if (!input.hasAttribute('data-rut')) return;
      input.value = GG.rutFormat(input.value);
      err.classList.add('hidden');
      input.classList.remove('is-invalid');
      // feedback apenas el RUT está completo
      const c = GG.rutClean(input.value);
      if (c.length >= 8) input.classList.toggle('is-valid', GG.rutValid(input.value));
      else input.classList.remove('is-valid');
    });
    input.addEventListener('blur', () => {
      if (!input.value || !input.hasAttribute('data-rut')) return;
      const ok = GG.rutValid(input.value);
      if (err) err.classList.toggle('hidden', ok);
      input.classList.toggle('is-invalid', !ok);
    });
    const form = input.form;
    if (form) form.addEventListener('submit', (e) => {
      if (!input.hasAttribute('data-rut')) return;
      const sub = e.submitter;
      if (!input.value && (!input.required || (sub && sub.hasAttribute('formnovalidate')))) return;
      if (!GG.rutValid(input.value)) { e.preventDefault(); if (err) err.classList.remove('hidden'); input.classList.add('is-invalid'); input.focus(); }
    });
  }
  new Set([...GG.$$('[data-rut]'), ...GG.$$('input[name=doc]')]).forEach(bindRut);

  // Login: cambiar entre RUT y otro documento
  const sw = GG.$('[data-doc-switch]');
  if (sw) sw.addEventListener('click', () => {
    const tipo = GG.$('[data-doc-tipo]'); const input = GG.$('#doc'); const lbl = GG.$('[data-doc-label]');
    const toRut = tipo.value !== 'rut';
    tipo.value = toRut ? 'rut' : 'pasaporte';
    lbl.textContent = toRut ? 'RUT' : 'Pasaporte o DNI';
    input.placeholder = toRut ? '12.345.678-9' : 'N° de pasaporte o DNI';
    input.value = '';
    if (toRut) input.setAttribute('data-rut', ''); else input.removeAttribute('data-rut');
    const err = GG.$('[data-rut-error]'); if (err) err.classList.add('hidden');
    input.classList.remove('is-invalid', 'is-valid');
    sw.textContent = toRut ? 'No tengo RUT (pasaporte / DNI)' : 'Usar RUT';
    input.focus();
  });
  // Formularios con selector de tipo de documento
  GG.$$('[data-doc-select]').forEach((sel) => {
    const input = sel.form.querySelector('[name=doc]');
    sel.addEventListener('change', () => {
      if (sel.value === 'rut') { input.setAttribute('data-rut', ''); input.value = GG.rutFormat(input.value); }
      else { input.removeAttribute('data-rut'); input.classList.remove('is-invalid', 'is-valid'); const er = input.parentElement.querySelector('[data-rut-error]'); if (er) er.classList.add('hidden'); }
    });
  });

  // ------------------------------------------------------------ montos con separador de miles
  GG.$$('[data-money]').forEach((i) => {
    const fmt = () => {
      const d = i.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
      i.value = d ? d.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
    };
    i.addEventListener('input', fmt);
    fmt();
  });

  // ------------------------------------------------------------ comportamientos genéricos
  document.addEventListener('click', (e) => {
    const c = e.target.closest('[data-confirm]');
    if (c && !confirm(c.dataset.confirm)) { e.preventDefault(); e.stopPropagation(); }
  }, true);
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-toggle]');
    if (t) { const el = GG.$(t.dataset.toggle); if (el) el.classList.toggle('hidden'); }
    const p = e.target.closest('[data-print]');
    if (p) window.print();
    const cp = e.target.closest('[data-copy]');
    if (cp) {
      const src = cp.parentElement.querySelector('[data-copy-src]');
      if (src) { navigator.clipboard ? navigator.clipboard.writeText(src.value).then(() => GG.toast('Copiado')) : (src.select(), document.execCommand('copy'), GG.toast('Copiado')); }
    }
  });
  // Portadas externas caídas: dejar el recuadro vacío en vez del ícono roto
  document.addEventListener('error', (e) => {
    const im = e.target;
    if (im && im.tagName === 'IMG' && im.getAttribute('referrerpolicy') === 'no-referrer') im.style.visibility = 'hidden';
  }, true);
  // Ver / ocultar contraseña
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-ver-contrasena]');
    if (!b) return;
    const inp = b.parentElement.querySelector('input');
    const ver = inp.type === 'password';
    inp.type = ver ? 'text' : 'password';
    b.setAttribute('aria-pressed', ver ? 'true' : 'false');
    b.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');
    b.querySelector('[data-ojo-abierto]').classList.toggle('hidden', ver);
    b.querySelector('[data-ojo-cerrado]').classList.toggle('hidden', !ver);
    inp.focus({ preventScroll: true });
  });
  // Al enviar, volver a ocultar (para que el gestor de contraseñas la detecte)
  document.addEventListener('submit', (e) => {
    e.target.querySelectorAll?.('[data-ver-contrasena][aria-pressed="true"]').forEach((b) => b.click());
  }, true);

  // Código de 6 dígitos: al pegarlo o completarlo, entra altiro
  GG.$$('[data-otp]').forEach((inp) => {
    let enviado = false;
    const go = () => {
      const v = inp.value.replace(/\D/g, '').slice(0, 6);
      if (inp.value !== v) inp.value = v;
      if (v.length === 6 && !enviado) {
        enviado = true;
        const btn = inp.form.querySelector('button:not([name])');
        if (btn) { btn.disabled = true; btn.textContent = 'Verificando…'; }
        inp.form.submit();
      }
    };
    inp.addEventListener('input', go);
    inp.addEventListener('paste', () => setTimeout(go, 0));
    go();
  });

  GG.$$('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));
  GG.$$('[data-color-sync]').forEach((c) => c.addEventListener('input', () => { const l = c.parentElement.querySelector('[data-color-label]'); if (l) l.textContent = c.value.toUpperCase(); }));

  // Mantención de máquinas (staff)
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-mantencion]');
    if (!b) return;
    const estado = b.dataset.estado;
    if (estado === 'mantencion' && !confirm('¿Marcar en mantención? Saldrá de las sugerencias.')) return;
    const r = await GG.api('api/mantencion', { maquina_id: +b.dataset.mantencion, estado });
    if (r.ok) location.reload(); else GG.toast(r.error || 'No se pudo cambiar');
  });

  // ------------------------------------------------------------ offline
  const badge = GG.$('#offline-badge');
  const setOnline = () => { if (badge) { badge.classList.toggle('hidden', navigator.onLine); badge.classList.toggle('flex', !navigator.onLine); } };
  window.addEventListener('online', () => { setOnline(); GG.flushQueue && GG.flushQueue(); });
  window.addEventListener('offline', setOnline);
  setOnline();

  // Al no haber sesión, limpiar páginas cacheadas (privacidad en equipos compartidos)
  if (body.dataset.user === '0' && 'caches' in window) caches.keys().then((ks) => ks.forEach((k) => caches.delete(k))).catch(() => {});

  // ------------------------------------------------------------ Service worker y PWA
  GG.isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  GG.platform = () => {
    const ua = navigator.userAgent;
    if (/iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) return 'ios';
    if (/android/i.test(ua)) return 'android';
    return 'other';
  };
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(BASE + '/sw.js', { scope: BASE + '/' }).then((reg) => { GG.swReg = reg; }).catch(() => {});
  }
  let deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    GG.$$('[data-pwa=android]').forEach((el) => el.classList.remove('hidden'));
    GG.$$('[data-pwa=other]').forEach((el) => el.classList.add('hidden'));
  });
  function showPwaBlocks() {
    const p = GG.platform();
    GG.$$('[data-pwa]').forEach((el) => el.classList.add('hidden'));
    if (GG.isStandalone()) { GG.$$('[data-pwa=installed]').forEach((el) => el.classList.remove('hidden')); return; }
    if (p === 'ios') GG.$$('[data-pwa=ios]').forEach((el) => el.classList.remove('hidden'));
    else if (deferredPrompt) GG.$$('[data-pwa=android]').forEach((el) => el.classList.remove('hidden'));
    else GG.$$('[data-pwa=other]').forEach((el) => el.classList.remove('hidden'));
  }
  document.addEventListener('click', async (e) => {
    if (e.target.closest('[data-pwa-install]') && deferredPrompt) {
      deferredPrompt.prompt();
      const r = await deferredPrompt.userChoice;
      deferredPrompt = null;
      if (r.outcome === 'accepted') closeHint();
    }
    if (e.target.closest('[data-pwa-close]')) closeHint();
  });
  function closeHint() {
    const h = GG.$('#pwa-hint');
    if (h) h.classList.add('hidden');
    GG.api('api/pwa/visto', {});
    body.dataset.pwaHint = '0';
  }
  showPwaBlocks();
  const ruta = new URLSearchParams(location.search).get('r') || '';
  const rutaHome = ['', 'inicio', 'coach'].includes(ruta);
  if (body.dataset.pwaHint === '1' && rutaHome && GG.platform() !== 'other' && !GG.isStandalone() && GG.$('#pwa-hint')) {
    setTimeout(() => { showPwaBlocks(); GG.$('#pwa-hint').classList.remove('hidden'); }, 1200);
  }
  if (GG.isStandalone() && body.dataset.pwaHint === '1') GG.api('api/pwa/visto', {});

  // ------------------------------------------------------------ Push
  function b64ToUint8(b64) {
    const pad = '='.repeat((4 - (b64.length % 4)) % 4);
    const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
  }
  GG.pushSupported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  GG.pushState = async function () {
    if (!GG.pushSupported()) return GG.platform() === 'ios' && !GG.isStandalone() ? 'ios-no-instalada' : 'no-soportado';
    if (Notification.permission === 'denied') return 'bloqueado';
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    return sub ? 'activo' : 'inactivo';
  };
  GG.pushEnable = async function () {
    if (!GG.pushSupported()) throw new Error(GG.platform() === 'ios' ? 'En iPhone primero agrega la app a tu pantalla de inicio y ábrela desde el ícono.' : 'Este navegador no soporta notificaciones.');
    const perm = await Notification.requestPermission();
    if (perm !== 'granted') throw new Error('Permiso de notificaciones no concedido.');
    const k = await GG.api('api/push/clave');
    if (!k.key) throw new Error('El servidor no tiene claves de notificación configuradas.');
    const reg = await navigator.serviceWorker.ready;
    let sub = await reg.pushManager.getSubscription();
    if (!sub) sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToUint8(k.key) });
    const r = await GG.api('api/push/suscribir', sub.toJSON());
    if (!r.ok) throw new Error(r.error || 'No se pudo registrar');
    return true;
  };
  GG.pushDisable = async function () {
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    if (sub) { await GG.api('api/push/suscribir', { endpoint: sub.endpoint, baja: 1 }); await sub.unsubscribe(); }
  };
  async function paintPush() {
    const btns = GG.$$('[data-push-toggle]');
    if (!btns.length) return;
    const st = await GG.pushState().catch(() => 'no-soportado');
    const txt = { activo: 'Activadas en este dispositivo', inactivo: 'Desactivadas', bloqueado: 'Bloqueadas en el navegador: actívalas en los ajustes del sitio', 'no-soportado': 'Este navegador no las soporta', 'ios-no-instalada': 'En iPhone: agrega la app a inicio y ábrela desde el ícono' }[st];
    GG.$$('[data-push-status]').forEach((el) => (el.textContent = txt));
    btns.forEach((b) => { b.textContent = st === 'activo' ? 'Desactivar' : 'Activar notificaciones'; b.disabled = st === 'bloqueado' || st === 'no-soportado' || st === 'ios-no-instalada'; b.dataset.state = st; });
  }
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-push-toggle]');
    if (!b) return;
    try {
      if (b.dataset.state === 'activo') { await GG.pushDisable(); GG.toast('Notificaciones desactivadas'); }
      else { await GG.pushEnable(); GG.toast('Notificaciones activadas'); }
    } catch (err) { GG.toast(err.message, 5000); }
    paintPush();
  });
  if ('serviceWorker' in navigator) navigator.serviceWorker.ready.then(paintPush); else paintPush();
})();
