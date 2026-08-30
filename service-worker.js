const CACHE_NAME = 'goalspace-pwa-v1';
const PRECACHE_URLS = [
  '/',
  '/pages/index.php',
  '/pages/ground.php',
  '/pages/courts.php',
  '/pages/faq.php',
  '/pages/contact.php',
  '/pages/how_to_use.php',
  '/pages/map.php',
  '/assets/css/style.css',
  '/assets/vendor/fontawesome/css/all.min.css',
  '/assets/img/icon-192.png',
  '/assets/img/icon-512.png',
  '/assets/img/favicon.svg'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(PRECACHE_URLS);
    })
  );
});

self.addEventListener('activate', (event) => {
  const currentCache = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (currentCache.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  event.respondWith(
    caches.match(event.request).then((response) => {
      if (response) {
        return response;
      }
      return fetch(event.request).then((fetchResponse) => {
        if (!fetchResponse || fetchResponse.status !== 200 || fetchResponse.type !== 'basic') {
          return fetchResponse;
        }
        const responseToCache = fetchResponse.clone();
        caches.open(CACHE_NAME).then((cache) => {
          cache.put(event.request, responseToCache);
        });
        return fetchResponse;
      });
    })
  );
});