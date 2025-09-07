var staticCacheName = "pwa-v" + new Date().getTime();
const ORIGIN = self.location.origin || 'https://poso_management.test';

importScripts('https://unpkg.com/dexie@3.2.4/dist/dexie.min.js');
const db = new Dexie('ticketDB');
db.version(2).stores({ tickets: '++id,client_uuid' });

var filesToCache = [
  '/offline',
  '/pwa',
  '/',
  '/css/app.css',
  '/js/app.js',
  '/js/id-scan.js',
  '/js/issueTicket.js',
  '/vendor/html5-qrcode/html5-qrcode.min.js',
  // If you use local vendor tesseract files, keep these:
  '/vendor/tesseract/tesseract.min.js',
  '/vendor/tesseract/worker.min.js',
  // But you serve WASM via /wasm/* routes — cache those:
  '/wasm/tesseract-core.wasm',
  '/wasm/eng.traineddata.gz',
  '/images/icons/icon-72x72.png',
  '/images/icons/icon-96x96.png',
  '/images/icons/icon-128x128.png',
  '/images/icons/icon-144x144.png',
  '/images/icons/icon-152x152.png',
  '/images/icons/icon-192x192.png',
  '/images/icons/icon-384x384.png',
  '/images/icons/icon-512x512.png',
];

self.addEventListener('sync', event => {
  if (event.tag === 'sync-tickets') {
    event.waitUntil(
      db.tickets.toArray().then(records =>
        Promise.all(records.map(async rec => {
          try {
            const resp = await fetch(`${ORIGIN}/pwa/sync/ticket`, {
              method: 'POST',
              credentials: 'include',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Idempotency-Key': rec.client_uuid || rec.payload?.client_uuid || ''
              },
              body: JSON.stringify(rec.payload)
            });
            if (resp.ok) await db.tickets.delete(rec.id);
          } catch (e) {
            console.error('Sync failed for ticket', rec.id, e);
          }
        }))
      )
    );
  }
});

// Cache on install
self.addEventListener("install", event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(staticCacheName).then(cache => {
      return Promise.all(filesToCache.map(url =>
        fetch(url).then(res => {
          if (!res.ok) {
            console.warn('skipping', url, '– status', res.status);
            return;
          }
          return cache.put(url, res.clone());
        }).catch(err => console.error('failed to cache', url, err))
      ));
    })
  );
});

// Clear old caches + take control
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => k.startsWith('pwa-') && k !== staticCacheName).map(k => caches.delete(k))
    ))
  );
  self.clients.claim();
});

// Cache-first with offline fallback for navigations
self.addEventListener('fetch', event => {
  const { request } = event;
  if (request.method !== 'GET') {
    event.respondWith(fetch(request, { credentials: 'include' }));
    return;
  }
  event.respondWith(
    caches.match(event.request).then(cached => {
      const network = fetch(event.request).then(res => res);
      return cached || network.catch(() => {
        if (event.request.mode === 'navigate') {
          return caches.match('/offline');
        }
      });
    })
  );
});

self.addEventListener('message', e => {
  const { title, options } = e.data || {};
  if (title) self.registration.showNotification(title, options || {});
});
