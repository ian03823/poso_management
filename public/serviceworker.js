/* serviceworker.js — PWA runtime + background sync
   - Network-first for HTML + /js/issueTicket.js (so new code deploys immediately)
   - Stale-while-revalidate for other static assets
   - Offline fallback to /offline for navigations
   - Background sync for tickets via Dexie
*/

const SW_VERSION = 'v2025-09-08-03'; // ⬅ bump on every deploy
const ORIGIN = self.location.origin || 'https://poso_management.test';

const STATIC_CACHE  = `pwa-static-${SW_VERSION}`;
const PAGES_CACHE   = `pwa-pages-${SW_VERSION}`;
const RUNTIME_CACHE = `pwa-runtime-${SW_VERSION}`;

// ---- Dexie for background sync ----
importScripts('https://unpkg.com/dexie@3.2.4/dist/dexie.min.js');
const db = new Dexie('ticketDB');
db.version(2).stores({ tickets: '++id,client_uuid' });

// ---- Pre-cache (optional, safe defaults) ----
const filesToCache = [
  '/offline',
  '/pwa',
  '/',
  '/css/app.css',
  '/js/app.js',
  '/js/id-scan.js',
  '/js/issueTicket.js',
  '/vendor/html5-qrcode/html5-qrcode.min.js',
  '/vendor/tesseract/tesseract.min.js',
  '/vendor/tesseract/worker.min.js',
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

// ---- Background Sync: tickets ----
self.addEventListener('sync', event => {
  if (event.tag === 'sync-tickets') {
    event.waitUntil((async () => {
      const records = await db.tickets.toArray();
      await Promise.all(records.map(async rec => {
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
      }));
    })());
  }
});

// ---- Install: pre-cache and activate immediately ----
self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil((async () => {
    const cache = await caches.open(STATIC_CACHE);
    await Promise.all(filesToCache.map(async (url) => {
      try {
        const res = await fetch(url, { cache: 'no-cache' });
        if (res.ok) await cache.put(url, res.clone());
        else console.warn('[SW] skip pre-cache', url, 'status', res.status);
      } catch (err) {
        console.warn('[SW] failed to cache', url, err);
      }
    }));
  })());
});

// ---- Activate: clean old caches & take control ----
self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => {
      if (![STATIC_CACHE, PAGES_CACHE, RUNTIME_CACHE].includes(k)) {
        return caches.delete(k);
      }
    }));
    await self.clients.claim();
  })());
});

// ---- Fetch strategies ----
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Only handle same-origin GETs
  if (request.method !== 'GET') {
    event.respondWith(fetch(request, { credentials: 'include' }));
    return;
  }
  if (url.origin !== self.location.origin) {
    // Let cross-origin requests pass through
    return;
  }

  const isNavigate = request.mode === 'navigate' || request.destination === 'document';
  const isIssueTicketJs =
    url.pathname.endsWith('/js/issueTicket.js') ||
    url.pathname.includes('/js/issueTicket.js');

  // 1) Network-first for documents and issueTicket.js
  if (isNavigate || isIssueTicketJs) {
    event.respondWith(networkFirst(request, isNavigate ? PAGES_CACHE : RUNTIME_CACHE));
    return;
  }

  // 2) Stale-while-revalidate for other static assets
  if (['script', 'style', 'image', 'font', 'worker'].includes(request.destination)) {
    event.respondWith(staleWhileRevalidate(request, RUNTIME_CACHE));
    return;
  }

  // 3) Default: try cache, then network (with offline fallback for navigations)
  event.respondWith(cacheFirstWithFallback(request, isNavigate ? '/offline' : null));
});

// ---- Notifications bridge ----
self.addEventListener('message', (e) => {
  const { title, options } = e.data || {};
  if (title) self.registration.showNotification(title, options || {});
});

/* ========== Helpers ========== */

async function networkFirst(request, cacheName) {
  try {
    const res = await fetch(request, { cache: 'no-cache' });
    const cache = await caches.open(cacheName);
    // Put a copy for offline use
    cache.put(request, res.clone());
    return res;
  } catch (err) {
    const cached = await caches.match(request);
    if (cached) return cached;

    // Fallback for navigations
    if (request.mode === 'navigate' || request.destination === 'document') {
      const offline = await caches.match('/offline');
      if (offline) return offline;
    }
    throw err;
  }
}

async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const networkPromise = fetch(request).then((res) => {
    cache.put(request, res.clone());
    return res;
  }).catch(() => null);
  return cached || networkPromise || fetch(request);
}

async function cacheFirstWithFallback(request, offlinePath = null) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const res = await fetch(request);
    return res;
  } catch (err) {
    if (offlinePath && (request.mode === 'navigate' || request.destination === 'document')) {
      const offline = await caches.match(offlinePath);
      if (offline) return offline;
    }
    throw err;
  }
}
