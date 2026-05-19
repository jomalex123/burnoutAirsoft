const BURNOUT_GAMES_CACHE = 'burnout-games-v1';

const BURNOUT_GAMES_ASSETS = [
  './burnoutgames.html',
  './burnoutgames_c4.html',
  './burnoutgames_dominio.html',
  './burnoutgames.webmanifest',
  './assets/css/burnoutgames.css',
  './assets/css/burnoutgames_c4.css',
  './assets/css/burnoutgames_dominio.css',
  './assets/js/burnoutgames.js',
  './assets/js/burnoutgames_c4.js',
  './assets/js/burnoutgames_dominio.js',
  './assets/js/burnoutgames_pwa.js',
  './images/resources/logoBurnout-3.png'
];

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(BURNOUT_GAMES_CACHE)
      .then(function(cache) {
        return cache.addAll(BURNOUT_GAMES_ASSETS);
      })
      .then(function() {
        return self.skipWaiting();
      })
  );
});

self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys()
      .then(function(keys) {
        return Promise.all(keys.map(function(key) {
          if (key !== BURNOUT_GAMES_CACHE && key.indexOf('burnout-games-') === 0) {
            return caches.delete(key);
          }

          return null;
        }));
      })
      .then(function() {
        return self.clients.claim();
      })
  );
});

self.addEventListener('fetch', function(event) {
  if (event.request.method !== 'GET') {
    return;
  }

  var requestUrl = new URL(event.request.url);

  if (requestUrl.origin !== self.location.origin) {
    return;
  }

  if (event.request.mode === 'navigate') {
    event.respondWith(networkFirst(event.request, './burnoutgames.html'));
    return;
  }

  event.respondWith(cacheFirst(event.request));
});

function cacheFirst(request) {
  return caches.match(request).then(function(cachedResponse) {
    if (cachedResponse) {
      return cachedResponse;
    }

    return fetch(request).then(function(networkResponse) {
      if (networkResponse && networkResponse.ok) {
        var responseClone = networkResponse.clone();

        caches.open(BURNOUT_GAMES_CACHE).then(function(cache) {
          cache.put(request, responseClone);
        });
      }

      return networkResponse;
    });
  });
}

function networkFirst(request, fallbackUrl) {
  return fetch(request)
    .then(function(networkResponse) {
      if (networkResponse && networkResponse.ok) {
        var responseClone = networkResponse.clone();

        caches.open(BURNOUT_GAMES_CACHE).then(function(cache) {
          cache.put(request, responseClone);
        });
      }

      return networkResponse;
    })
    .catch(function() {
      return caches.match(request).then(function(cachedResponse) {
        return cachedResponse || caches.match(fallbackUrl);
      });
    });
}
