/* GoalSpace service worker: enables install-as-app and a basic offline shell. */
'use strict';

const CACHE = 'goalspace-v3';

// Core styles/scripts used on every page plus the offline shell, manifest and
// icons so the installed app can open and render even fully offline. HTML
// pages are never precached because they can contain personalized data.
const PRECACHE = [
  './assets/css/style.css',
  './assets/js/core.js',
  './assets/img/favicon.svg',
  './offline.html',
  './manifest.json',
  './assets/img/icon-192.png',
  './assets/img/icon-512.png'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE).then(function (cache) {
      return cache.addAll(PRECACHE).catch(function () { /* individual assets may 404 harmlessly */ });
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function (event) {
  const req = event.request;

  // Only handle same-origin GET requests.
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  // Navigations: try the network first, fall back to the offline page.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).then(function (res) {
        // Cache a successful page response so the fallback has company.
        const copy = res.clone();
        caches.open(CACHE).then(function (c) { c.put(req, copy); });
        return res;
      }).catch(function () {
        return caches.match(req).then(function (cached) {
          return cached || caches.match('./offline.html');
        });
      })
    );
    return;
  }

  // Static assets (CSS/JS/images/fonts): stale-while-revalidate. Serve the
  // cached copy instantly, but ALWAYS refresh from the network in the
  // background and update the cache. Cache-first used to let one broken
  // response pin itself forever (the versioned ?v= URL never changed), which
  // left the whole site unstyled on live even after the file was fixed.
  event.respondWith(
    caches.match(req).then(function (cached) {
      const network = fetch(req).then(function (res) {
        if (res && res.ok && (res.type === 'basic' || res.type === 'cors')) {
          const copy = res.clone();
          caches.open(CACHE).then(function (c) { c.put(req, copy); });
        }
        return res;
      }).catch(function () {
        return cached;
      });
      return cached || network;
    })
  );
});
