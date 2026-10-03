/*
|--------------------------------------------------------------------------
| OJT Tracker service worker
|--------------------------------------------------------------------------
|
| Offline support for interns, most importantly in the Android app: the pages
| an intern needs with no connectivity (My QR Code at the kiosk, their
| dashboard, their journal) are kept in a cache as they browse, and served
| from it when the network is gone. Writes are never intercepted — the
| offline journal draft queue in app.js owns those.
|
| Strategy for page navigations: network-first (always prefer fresh data,
| refresh the cache on the way through), falling back to the last cached
| copy when the request fails, and finally to a small branded offline page.
|
| Bump CACHE_VERSION whenever shipped assets change shape so stale caches
| are dropped on the next visit.
|
*/

const CACHE_VERSION = 'ojt-v1';
const OFFLINE_URL = '/offline.html';

// Pages worth keeping for offline use. Everything else still works online
// exactly as before — it just isn't cached.
const CACHEABLE_PAGES = [
    '/intern/dashboard',
    '/intern/my-qr',
    '/intern/time-frame',
    '/intern/documentation',
    '/notifications',
];

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_VERSION);
        await cache.add(OFFLINE_URL);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names.filter((n) => n !== CACHE_VERSION).map((n) => caches.delete(n)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only page navigations are cached — assets come from Vite's hashed
    // build (short-lived browsing anyway) and API/file POSTs must always
    // reach the server or fail loudly.
    if (request.method !== 'GET' || request.mode !== 'navigate') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Staff tooling and the kiosk are online-only; caching them would risk
    // stale shared pages on somebody else's phone.
    // The app always boots at "/" (server.url). Offline, that maps to the
    // intern's cached dashboard, so a device that has signed in before opens
    // straight into the app — no login wall, no dead end. Uncached pages fall
    // back to offline.html.
    const path = url.pathname;
    const cacheable = CACHEABLE_PAGES.some((p) => path === p || path.startsWith(p + '/'));
    if (!cacheable && path !== OFFLINE_URL && path !== '/') {
        return;
    }

    event.respondWith((async () => {
        const cache = await caches.open(CACHE_VERSION);
        try {
            const fresh = await fetch(request);
            // Only cache real pages — logins, redirects and errors would
            // poison the offline copy.
            if (fresh.ok && !fresh.redirected && cacheable) {
                cache.put(request, fresh.clone());
            }
            return fresh;
        } catch (offlineError) {
            const fallbackKey = path === '/'
                ? new URL('/intern/dashboard', self.location.origin).href
                : request;
            const cached = await cache.match(fallbackKey, { ignoreSearch: path === '/intern/dashboard' });
            if (cached) {
                return cached;
            }
            return (await cache.match(OFFLINE_URL)) || Response.error();
        }
    })());
});
