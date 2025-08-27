var staticCacheName = "pwa-v" + new Date().getTime();
importScripts('https://unpkg.com/dexie@3.2.2/dist/dexie.js');
const db = new Dexie('ticketDB');
db.version(1).stores({ tickets: '++id, payload' });

var filesToCache = [
    '/offline',
    '/css/app.css',
    '/js/app.js',
    '/js/id-scan.js',
    '/vendor/html5-qrcode/html5-qrcode.min.js',
    '/vendor/tesseract/tesseract.min.js',
    '/vendor/tesseract/worker.min.js',
    '/vendor/tesseract/tesseract-core.wasm.js',
    '/vendor/tesseract/tesseract-core.wasm',
    '/vendor/tesseract/eng.traineddata.gz',
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
            const resp = await fetch('/enforcerTicket', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(rec.payload)
            });
            if (resp.ok) {
              await db.tickets.delete(rec.id);
            }
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

// Clear old caches on activation
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(cacheName => (cacheName.startsWith("pwa-")))
                    .filter(cacheName => (cacheName !== staticCacheName))
                    .map(cacheName => caches.delete(cacheName))
            );
        })
    );
});

// Serve from Cache with Network Fallback
self.addEventListener("fetch", event => {

   if (event.request.method !== 'GET') {
    event.respondWith(fetch(event.request));
    return;
  }
    event.respondWith(
    caches.match(event.request).then(cached => {
      return cached || fetch(event.request).then(response => {
        // optionally cache the response here...
        return response;
      });
    })
  );
});

self.addEventListener('message', e => {
  const { title, options } = e.data;
  self.registration.showNotification(title, options);
});
