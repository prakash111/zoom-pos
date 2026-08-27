const CACHE_VERSION = 'zoom-pos-static-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [
    OFFLINE_URL,
    '/pwa/icon-192.png',
    '/pwa/icon-512.png',
    '/pwa/icon-maskable-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_VERSION).then((cache) => cache.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key.startsWith('zoom-pos-') && key !== CACHE_VERSION)
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Never cache authenticated documents or mutable application traffic.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    const isViteAsset = url.pathname.startsWith('/build/assets/');
    const isPublicLibraryAsset = url.pathname.startsWith('/assets/')
        && ['script', 'style', 'font', 'image'].includes(request.destination);
    const isPwaAsset = url.pathname.startsWith('/pwa/');

    if (!isViteAsset && !isPublicLibraryAsset && !isPwaAsset) return;

    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) return cached;

            return fetch(request).then((response) => {
                if (response.ok && response.type === 'basic') {
                    const copy = response.clone();
                    caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy));
                }

                return response;
            });
        }),
    );
});
