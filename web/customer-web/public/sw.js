const CACHE_NAME = 'utterlazy-v1'
const STATIC_ASSETS = [
  '/',
  '/search',
  '/offline',
  '/manifest.json',
]

// Install: cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS)
    })
  )
  self.skipWaiting()
})

// Activate: clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      )
    })
  )
  self.clients.claim()
})

// Fetch: cache-first for API, network-first for HTML
self.addEventListener('fetch', (event) => {
  const { request } = event
  const url = new URL(request.url)

  // API calls: network first, cache fallback
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const clone = response.clone()
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(request, clone)
          })
          return response
        })
        .catch(() => caches.match(request).then((cached) => {
          return cached || new Response(JSON.stringify({ offline: true, message: 'You are offline. Showing cached data.' }), {
            headers: { 'Content-Type': 'application/json' },
          })
        }))
    )
    return
  }

  // HTML/navigation: network first, cache fallback
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => {
        return caches.match('/offline') || caches.match('/')
      })
    )
    return
  }

  // Static assets: cache first
  event.respondWith(
    caches.match(request).then((cached) => {
      return cached || fetch(request).then((response) => {
        return caches.open(CACHE_NAME).then((cache) => {
          if (request.method === 'GET') {
            cache.put(request, response.clone())
          }
          return response
        })
      })
    })
  )
})
