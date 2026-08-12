/* ExchangePro service worker
   Strategy:
   - App shell static assets (CSS/JS/icons): stale-while-revalidate so the
     app opens instantly from cache and refreshes in the background.
   - Never cache HTML/API responses: they are session-authed and dynamic.
   Bump CACHE_VERSION after any deploy that changes app.css / app.js. */

var CACHE_VERSION = 'exchangepro-v1';
var SHELL_ASSETS = [
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/img/icons.svg',
  '/assets/img/icon-192.png',
  '/assets/img/icon-512.png',
  '/assets/img/icon-512-maskable.png',
  '/assets/img/apple-touch-icon.png',
  '/manifest.webmanifest'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_VERSION).then(function (cache) {
      return cache.addAll(SHELL_ASSETS).catch(function () {
        // addAll is all-or-nothing; if one asset fails (e.g. offline first
        // install) fall back to adding what we can.
        return Promise.all(
          SHELL_ASSETS.map(function (url) {
            return cache.add(url).catch(function () { return true; });
          })
        );
      });
    }).then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (k) { return k !== CACHE_VERSION; })
            .map(function (k) { return caches.delete(k); })
      );
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (event) {
  var req = event.request;
  if (req.method !== 'GET') return;

  var url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  var path = url.pathname;

  // Static app-shell assets → serve from cache, refresh in background.
  if (path.indexOf('/assets/') === 0 || path === '/manifest.webmanifest') {
    event.respondWith(
      caches.match(req).then(function (cached) {
        var network = fetch(req).then(function (res) {
          if (res && res.ok) {
            var copy = res.clone();
            caches.open(CACHE_VERSION).then(function (cache) { cache.put(req, copy); });
          }
          return res;
        }).catch(function () { return cached; });
        return cached || network;
      })
    );
    return;
  }

  // Everything else (HTML, auth'd pages, API) → network only.
});
