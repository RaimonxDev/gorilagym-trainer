/* GorilaGym · service worker
 * - Cachea la app para que cargue con mala señal.
 * - Las páginas y la API usan red primero con respaldo en caché (nunca se cachean POST).
 * - Push sin payload: al recibirlo consulta al servidor qué mostrar.
 */
const VERSION = 'gg-v1.6.0';
const SCOPE = self.registration.scope; // termina en "/"
const SHELL = [
  'assets/css/app.css', 'assets/js/app.js', 'assets/js/sesion.js', 'assets/fonts/google-sans-latin.woff2',
  'assets/img/icons/icon-192.png', 'assets/img/icons/badge-96.png', 'assets/img/brand/logo-horizontal.svg',
].map((p) => SCOPE + p);

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => Promise.all(SHELL.map((u) => c.add(u).catch(() => null)))).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

function isCacheableApi(url) {
  return /[?&]r=api%2Fsesion(&|$)|[?&]r=api\/sesion(&|$)/.test(url.search);
}

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin || !req.url.startsWith(SCOPE)) return;
  if (url.pathname.includes('/media/') && /\.(mp4|webm)$/i.test(url.pathname)) return; // videos: rango de bytes, dejar a la red

  // Estáticos: caché primero
  if (url.pathname.includes('/assets/')) {
    e.respondWith(caches.match(req, { ignoreSearch: false }).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(VERSION).then((c) => c.put(req, copy)); }
      return res;
    })));
    return;
  }
  // Navegación y API de sesión: red primero, respaldo en caché
  if (req.mode === 'navigate' || isCacheableApi(url)) {
    e.respondWith(fetch(req).then((res) => {
      if (res.ok && res.type === 'basic') { const copy = res.clone(); caches.open(VERSION).then((c) => c.put(req, copy)); }
      return res;
    }).catch(() => caches.match(req).then((hit) => hit || new Response(
      '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;padding:32px;background:#F5F9E5;color:#111D13"><h2>Sin conexión</h2><p>Revisa tu señal e intenta de nuevo. Si estabas en una rutina, ábrela otra vez: tus series se guardan y se envían al volver la conexión.</p></body>',
      { headers: { 'Content-Type': 'text/html; charset=utf-8' } }))));
  }
});

// ------------------------------------------------------------- Push
self.addEventListener('push', (e) => {
  e.waitUntil((async () => {
    let items = [];
    try {
      const r = await fetch(SCOPE + '?r=api/push/pendientes', { credentials: 'include', cache: 'no-store' });
      const j = await r.json();
      items = j.items || [];
    } catch (err) {}
    if (!items.length) items = [{ titulo: 'Gorila Gym', cuerpo: 'Tienes una novedad.', url: SCOPE, tipo: 'generico' }];
    await Promise.all(items.map((n) => self.registration.showNotification(n.titulo, {
      body: n.cuerpo || '',
      icon: SCOPE + 'assets/img/icons/icon-192.png',
      badge: SCOPE + 'assets/img/icons/badge-96.png',
      tag: n.tipo === 'descanso' ? 'descanso' : 'gg-' + (n.id || Date.now()),
      renotify: true,
      vibrate: n.tipo === 'descanso' ? [200, 100, 200] : [100],
      data: { url: n.url || SCOPE },
    })));
  })());
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const target = new URL(e.notification.data && e.notification.data.url ? e.notification.data.url : SCOPE, SCOPE).href;
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    for (const c of list) { if (c.url.startsWith(SCOPE) && 'focus' in c) { c.navigate(target).catch(() => {}); return c.focus(); } }
    return self.clients.openWindow(target);
  }));
});
