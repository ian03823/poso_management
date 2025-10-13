/* serviceworker.js — PWA runtime + OCR + background sync (unified) */

const SW_VERSION   = 'v2025-10-13'; // bump each deploy
const ORIGIN       = self.location.origin;

const STATIC_CACHE  = `pwa-static-${SW_VERSION}`;
const PAGES_CACHE   = `pwa-pages-${SW_VERSION}`;
const RUNTIME_CACHE = `pwa-runtime-${SW_VERSION}`;

// Dexie in SW
(async () => {
  try { importScripts('/vendor/dexie/dexie.min.js'); }
  catch (e) {
    try { importScripts('https://unpkg.com/dexie@3.2.4/dist/dexie.min.js'); }
    catch (e2) {}
  }
})();

const DB_NAME    = 'ticketDB';
const DB_VERSION = 221; // match page
const DB_SCHEMA  = { tickets: '++id,client_uuid,created_at' };
let db = null;
function initDexie() {
  if (self.Dexie && !db) {
    db = new Dexie(DB_NAME);
    db.version(DB_VERSION).stores(DB_SCHEMA).upgrade(()=>{});
  }
}
initDexie();

// helpers
async function broadcast(msg) {
  const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  clients.forEach(c => c.postMessage(msg));
}
async function queueCount() {
  try { initDexie(); return db ? await db.tickets.count() : 0; } catch { return 0; }
}

async function drainQueueOnce() {
  initDexie();
  if (!db) {
    await broadcast({ type: 'SYNC_TICKETS' });
    return;
  }

  const records = await db.tickets.toArray();
  for (const rec of records) {
    try {
      const uuid = rec.client_uuid || rec.payload?.client_uuid || '';
      const resp = await fetch(`${ORIGIN}/pwa/sync/ticket`, {
        method: 'POST',
        credentials: 'omit',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Idempotency-Key': uuid
        },
        body: JSON.stringify(rec.payload)
      });
      if (resp.ok) {
        await db.tickets.delete(rec.id);
        await broadcast({ type: 'SYNC_TICKET_OK', client_uuid: uuid }); // <- defined uuid
      }
    } catch (e) {
      // keep for next round
    }
  }

  await broadcast({ type: 'SYNC_TICKETS_DONE' });
  await broadcast({ type: 'QUEUE_COUNT', count: await queueCount() });
}

// pre-cache
const filesToCache = [
  '/', '/css/app.css', '/js/app.js', '/js/issueTicket.js', '/js/id-scan.js',
  '/js/enforcer.offline.auth.js',
  '/vendor/dexie/dexie.min.js',
  '/vendor/sweetalert2/sweetalert2.all.min.js',
  '/vendor/bootstrap/bootstrap.bundle.min.js',
  '/vendor/tesseract/tesseract.min.js', '/vendor/tesseract/worker.min.js',
  '/wasm/tesseract-core-simd-lstm.wasm.js', '/wasm/tesseract-core-simd-lstm.wasm',
  '/wasm/eng.traineddata.gz',
  '/images/icons/POSO-Logo.png',
  '/images/icons/icon-72x72.png','/images/icons/icon-96x96.png','/images/icons/icon-128x128.png',
  '/images/icons/icon-144x144.png','/images/icons/icon-152x152.png','/images/icons/icon-192x192.png',
  '/images/icons/icon-384x384.png','/images/icons/icon-512x512.png',
];

self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-tickets') {
    event.waitUntil(drainQueueOnce());
  }
});

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil((async () => {
    const cache = await caches.open(STATIC_CACHE);
    await Promise.all(filesToCache.map(async (url) => {
      try {
        const res = await fetch(url, { cache: 'no-cache' });
        if (res.ok) await cache.put(url, res.clone());
      } catch (_) {}
    }));
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => {
      if (![STATIC_CACHE, PAGES_CACHE, RUNTIME_CACHE].includes(k)) {
        return caches.delete(k);
      }
    }));
    await self.clients.claim();
    await broadcast({ type: 'QUEUE_COUNT', count: await queueCount() });
  })());
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method !== 'GET') return;
  if (url.origin !== self.location.origin) return;

  const path = url.pathname;
  const bypassPrefixes = [
    '/violator/phone','/violator/otp/verify','/violator/otp/resend',
    '/vlogin','/vlogout','/alogin','/plogin','/pwa/sync'
  ];
  if (bypassPrefixes.some(p => path.startsWith(p))) return;

  const isNavigate = req.mode === 'navigate' || req.destination === 'document';
  const isOCR = path.startsWith('/vendor/tesseract/') || path.startsWith('/wasm/');
  const isCriticalJS =
    path.endsWith('/js/issueTicket.js') || path.endsWith('/js/id-scan.js') || path.endsWith('/js/app.js');

  if (isNavigate || isCriticalJS) {
    event.respondWith(networkFirst(req, isNavigate ? PAGES_CACHE : RUNTIME_CACHE, isNavigate ? '/offline' : null));
    return;
  }
  if (isOCR) { event.respondWith(cacheFirst(req, STATIC_CACHE)); return; }
  if (['script','style','image','font','worker'].includes(req.destination)) {
    event.respondWith(staleWhileRevalidate(req, RUNTIME_CACHE)); return;
  }
  const isAnalyticsReport =
    url.pathname.endsWith('.docx') || url.pathname.endsWith('.xlsx') || url.pathname.includes('/reports/download');
  if (isAnalyticsReport) { event.respondWith(fetch(event.request)); return; }

  event.respondWith(cacheFirstWithFallback(req, isNavigate ? '/offline' : null));
});

// messages
self.addEventListener('message', (e) => {
  const data = e.data || {};
  if (data.type === 'SYNC_NOW')       e.waitUntil(drainQueueOnce());
  else if (data.type === 'QUEUE_POLL') e.waitUntil(queueCount().then(count => broadcast({ type: 'QUEUE_COUNT', count })));
  else if (data.title)                 self.registration.showNotification(data.title, data.options || {});
});

/* fetch helpers */
async function networkFirst(request, cacheName, offlinePath = null) {
  try {
    const res = await fetch(request, { cache: 'no-cache' });
    const cache = await caches.open(cacheName);
    cache.put(request, res.clone());
    return res;
  } catch (err) {
    const cached = await caches.match(request);
    if (cached) return cached;
    if (offlinePath && (request.mode === 'navigate' || request.destination === 'document')) {
      const offline = await caches.match(offlinePath);
      if (offline) return offline;
    }
    throw err;
  }
}
async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  if (cached) return cached;
  const res = await fetch(request);
  cache.put(request, res.clone());
  return res;
}
async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const networkPromise = fetch(request).then((res) => { cache.put(request, res.clone()); return res; })
    .catch(() => null);
  return cached || networkPromise || fetch(request);
}
async function cacheFirstWithFallback(request, offlinePath = null) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try { return await fetch(request); }
  catch (err) {
    if (offlinePath && (request.mode === 'navigate' || request.destination === 'document')) {
      const offline = await caches.match(offlinePath);
      if (offline) return offline;
    }
    throw err;
  }
}
