const CACHE_NAME = 'cric-score-v1';
const ASSETS = [
  '/',
  '/index.php',
  '/style.css',
  '/assets/logo.png',
  '/assets/icon-192.png',
  '/assets/icon-512.png'
];

// Install Service Worker
self.addEventListener('install', (evt) => {
  self.skipWaiting(); // Forces the new SW to take over immediately
  evt.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS);
    })
  );
});

// Activate Event - cleanup old caches if needed
self.addEventListener('activate', (evt) => {
  evt.waitUntil(clients.claim());
});

// Fetch resources
self.addEventListener('fetch', (evt) => {
  const url = new URL(evt.request.url);

  // STRATEGY: Network First for HTML/PHP (dynamic content)
  // This ensures you see the logged-in state and live scores immediately.
  if (url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
    evt.respondWith(
      fetch(evt.request)
        .then((fetchRes) => {
          // Optional: Update cache with new version
          return caches.open(CACHE_NAME).then((cache) => {
            cache.put(evt.request, fetchRes.clone());
            return fetchRes;
          });
        })
        .catch(() => {
          // If offline, fallback to cache
          return caches.match(evt.request);
        })
    );
    return;
  }

  // STRATEGY: Cache First for static assets (CSS, JS, Images)
  evt.respondWith(
    caches.match(evt.request).then((cacheRes) => {
      return cacheRes || fetch(evt.request);
    })
  );
});