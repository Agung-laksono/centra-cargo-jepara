const CACHE_NAME = 'jepara-cargo-v10';
const ASSET_CACHE = 'jepara-assets-v10';

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(key => key !== CACHE_NAME && key !== ASSET_CACHE)
                    .map(key => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);
    const isAsset = url.pathname.match(/\.(js|css|png|jpg|jpeg|svg|gif|woff2?|ttf|eot)$/) || url.pathname.startsWith('/build/');
    const isInertia = event.request.headers.has('x-inertia');

    let cacheKey;
    if (isInertia) {
        const keyUrl = new URL(event.request.url);
        keyUrl.searchParams.set('inertia_cache_key', '1');
        cacheKey = keyUrl.toString();
    } else {
        cacheKey = event.request;
    }

    if (isAsset) {
        event.respondWith(
            caches.match(event.request, { ignoreSearch: true }).then((cached) => {
                return cached || fetch(event.request).then((res) => {
                    const clone = res.clone();
                    caches.open(ASSET_CACHE).then(cache => cache.put(event.request, clone));
                    return res;
                });
            })
        );
    } else {
        event.respondWith(
            fetch(event.request).then((networkResponse) => {
                const clone = networkResponse.clone();
                caches.open(CACHE_NAME).then(cache => cache.put(cacheKey, clone));
                return networkResponse;
            }).catch(async () => {
                // ignoreVary wajib karena axios dan inertia punya Accept header yang berbeda!
                const cached = await caches.match(cacheKey, { ignoreVary: true });
                if (cached) {
                    return cached;
                }
                
                if (isInertia) {
                    return new Response(JSON.stringify({
                        component: 'Dashboard',
                        props: { error: 'Offline' },
                        url: url.pathname,
                        version: ''
                    }), {
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Inertia': 'true'
                        }
                    });
                }
                
                if (event.request.mode === 'navigate') {
                    return new Response('<h2 style="text-align:center;margin-top:50px;">Anda sedang offline dan halaman ini belum tersimpan.</h2>', {
                        headers: { 'Content-Type': 'text/html' }
                    });
                }
                
                throw new Error('Offline');
            })
        );
    }
});
