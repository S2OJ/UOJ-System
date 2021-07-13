/**
 * @name ServiceWorkerScriptForS2OJ
 * @author Ren Baoshuo <i@baoshuo.ren>
 * @description For more information about this script, please visit https://blog.baoshuo.ren/post/workbox-service-worker/ .
*/

importScripts('https://cdn.jsdelivr.net/npm/workbox-cdn@5.1.3/workbox/workbox-sw.js');

workbox.setConfig({
    modulePathPrefix: 'https://cdn.jsdelivr.net/npm/workbox-cdn@5.1.3/workbox/'
});

const { core, precaching, routing, strategies, expiration, cacheableResponse, backgroundSync } = workbox;
const { CacheFirst, NetworkFirst, NetworkOnly, StaleWhileRevalidate } = strategies;
const { ExpirationPlugin } = expiration;
const { CacheableResponsePlugin } = cacheableResponse;

const cacheSuffixVersion = '-210713';
const maxEntries = 100;

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(keys.map((key) => {
                if (key.includes('disqus-cdn-cache')) return caches.delete(key);
                if (key.includes('disqus-img-cache')) return caches.delete(key);
                if (!key.includes(cacheSuffixVersion)) return caches.delete(key);
            }));
        })
    );
});


core.setCacheNameDetails({
    prefix: 's2oj',
    suffix: cacheSuffixVersion
});

core.skipWaiting();
core.clientsClaim();
precaching.cleanupOutdatedCaches();

/*
 * Precache
 */
precaching.precacheAndRoute(
    [],
);

/*
 * Cache File From BootCDN
 * cdn.bootcss.com
 *
 * Method: CacheFirst
 * cacheName: static-immutable
 * cacheTime: 30d
 */
routing.registerRoute(
    /.*cdn\.bootcss\.com/,
    new CacheFirst({
        cacheName: 'static-immutable' + cacheSuffixVersion,
        fetchOptions: {
            mode: 'cors',
            credentials: 'omit'
        },
        plugins: [
            new ExpirationPlugin({
                maxAgeSeconds: 30 * 24 * 60 * 60,
                purgeOnQuotaError: true
            })
        ]
    })
);

/*
 * Images from SM.MS
 * cdn.jsdelivr.net
 *
 * Method: CacheFirst
 * cacheName: img-cache
 * cacheTime: 30d
 */
routing.registerRoute(
    /.*(?:i|vip1|vip2)\.loli\.(?:io|net)/,
    new CacheFirst({
        cacheName: 'img-cache' + cacheSuffixVersion,
        fetchOptions: {
            mode: 'cors',
            credentials: 'omit'
        },
        plugins: [
            new ExpirationPlugin({
                maxAgeSeconds: 30 * 24 * 60 * 60,
                purgeOnQuotaError: true
            })
        ]
    })
);

/*
 * Others img
 * Method: staleWhileRevalidate
 * cacheName: img-cache
 */
routing.registerRoute(
    /.*\.(?:png|jpg|jpeg|svg|gif|webp)/,
    new StaleWhileRevalidate()
);

/*
 * Static Assets
 * Method: staleWhileRevalidate
 * cacheName: static-assets-cache
 */
routing.registerRoute(
    /.*\.(css|js)/,
    new StaleWhileRevalidate()
);

/*
 * sw.js - Revalidate every time
 * staleWhileRevalidate
 */
routing.registerRoute(
    '/sw.js',
    new StaleWhileRevalidate()
);

/*
 * Default - Serve as it is
 * networkFirst
 */
routing.setDefaultHandler(
    new NetworkFirst({
        networkTimeoutSeconds: 3
    })
);
