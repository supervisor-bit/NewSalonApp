// Minimal Service Worker for PWA compliance
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Standard network-first approach, we just need the SW to be present for PWA install prompt
    event.respondWith(fetch(event.request));
});
