/* HaloDek service worker.
 * Aman untuk aplikasi yang memuat data pribadi: HALAMAN (HTML) TIDAK PERNAH DISIMPAN.
 * Yang disimpan hanya berkas statis (CSS, JS, font, gambar) agar buka lebih cepat,
 * ditambah halaman offline sederhana saat tidak ada koneksi. */
var VERSION = 'halodek-v3';
var CACHE = VERSION + '-static';
var OFFLINE = 'offline.html';
var STATIC_RE = /^(css|js|assets|images)\//;
var MAX_ENTRIES = 60;

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE).then(function (c) {
            return c.addAll([OFFLINE, 'images/halodek-icon-192.png', 'images/halodek-icon.svg']);
        }).then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
        }).then(function () { return self.clients.claim(); })
    );
});

function trim(cache) {
    return cache.keys().then(function (keys) {
        if (keys.length <= MAX_ENTRIES) { return; }
        return Promise.all(keys.slice(0, keys.length - MAX_ENTRIES).map(function (k) { return cache.delete(k); }));
    });
}

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') { return; }
    var url = new URL(req.url);
    if (url.origin !== self.location.origin) { return; }

    var base = new URL(self.registration.scope).pathname;
    var path = url.pathname.indexOf(base) === 0 ? url.pathname.slice(base.length) : '';

    // Halaman: selalu dari jaringan; offline -> halaman offline. Tidak pernah di-cache.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(function () { return caches.match(OFFLINE); }));
        return;
    }

    // Berkas statis: tampilkan dari cache, perbarui di latar belakang.
    if (STATIC_RE.test(path)) {
        event.respondWith(
            caches.open(CACHE).then(function (cache) {
                return cache.match(req).then(function (hit) {
                    var net = fetch(req).then(function (res) {
                        if (res && res.ok) { cache.put(req, res.clone()).then(function () { return trim(cache); }); }
                        return res;
                    }).catch(function () { return hit; });
                    return hit || net;
                });
            })
        );
    }
    // selain itu (lampiran, halaman pantau, API, dll.) tidak disentuh sama sekali.
});
