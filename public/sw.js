const VERSION = 'optical-erp-v1';
const SHELL_CACHE = `${VERSION}-shell`;
const PAGE_CACHE = `${VERSION}-pages`;
const SHELL = ['/', '/offline', '/manifest.webmanifest', '/icons/optical-erp.svg', '/favicon.ico'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key)))));
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).then((response) => {
            if (response.ok && !url.pathname.startsWith('/login') && !url.pathname.startsWith('/profile')) {
                caches.open(PAGE_CACHE).then((cache) => cache.put(request, response.clone()));
            }
            return response;
        }).catch(async () => (await caches.match(request)) || caches.match('/offline')));
        return;
    }

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/fonts/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(caches.match(request).then((cached) => cached || fetch(request).then((response) => {
            if (response.ok) caches.open(SHELL_CACHE).then((cache) => cache.put(request, response.clone()));
            return response;
        })));
    }
});

self.addEventListener('message', (event) => {
    if (event.data === 'CLEAR_PRIVATE_CACHE') caches.delete(PAGE_CACHE);
});
