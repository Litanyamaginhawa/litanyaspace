/* Litanya Safespace service worker — offline app-shell caching.
   Bump CACHE version when deploying a big update to refresh stale shells. */
const CACHE = "litanya-shell-v19";
const CORE = [
  "/",
  "/index.html",
  "/manifest.json",
  "/apple-touch-icon.png",
  "/icon-192.png",
  "/icon-512.png",
  "/icon-maskable-512.png",
  "/favicon-32.png"
];

self.addEventListener("install", (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE);
    await Promise.allSettled(CORE.map((url) => cache.add(new Request(url, { cache: "reload" }))));
    self.skipWaiting();
  })());
});

self.addEventListener("activate", (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)));
    await self.clients.claim();
  })());
});

/* ---- v2.7 Web Push: this is what makes iOS(16.4+ installed PWA) + Android
   background notifications actually display. Without a push listener the
   payload the Supabase Edge Function sends is delivered but never shown. ---- */
function parsePush(data) {
  if (!data) return { title: "Litanya Safespace", body: "May bagong update ka.", url: "/", tag: "litanya" };
  let p = null;
  try { p = data.json(); } catch (_) { try { p = { body: data.text() }; } catch (e) { p = {}; } }
  p = p || {};
  const opts = p.options || p;
  return {
    title: opts.title || p.title || "Litanya Safespace",
    body: opts.body || p.body || opts.message || "May bagong update ka.",
    icon: opts.icon || p.icon || "/apple-touch-icon.png",
    badge: opts.badge || p.badge || "/icon-192.png",
    tag: opts.tag || p.tag || "litanya-" + Date.now(),
    url: opts.url || p.url || p.link || "/",
    data: p.data || opts.data || {}
  };
}

self.addEventListener("push", (event) => {
  const n = parsePush(event.data);
  event.waitUntil(self.registration.showNotification(n.title, {
    body: n.body,
    icon: n.icon,
    badge: n.badge,
    tag: n.tag,
    data: { url: n.url, ...n.data },
    vibrate: [80, 40, 80],
    renotify: !!n.tag,
    lang: "fil"
  }));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const raw = (event.notification.data && event.notification.data.url) || "/";
  const route = String(raw).replace(/^\/+/, "").split("?")[0].split("#")[0] || "";
  const targetUrl = "/" + (route ? "?r=" + encodeURIComponent(route) : "");
  event.waitUntil((async () => {
    const all = await clients.matchAll({ type: "window", includeUncontrolled: true });
    for (const c of all) {
      if ("focus" in c) {
        try { await c.focus(); c.postMessage({ type: "litanya-navigate", route }); return; } catch (_) {}
      }
    }
    if (clients.openWindow) { await clients.openWindow(targetUrl); }
  })());
});

self.addEventListener("notificationclose", () => {});

self.addEventListener("pushsubscriptionchange", (event) => {
  event.waitUntil((async () => {
    try { await self.registration.pushManager.subscribe({ userVisibleOnly: true }); } catch (_) {}
  })());
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return;

  let url;
  try { url = new URL(req.url); } catch (_) { return; }

  // Never intercept Supabase API/realtime or external CDNs — pass through.
  if (url.origin !== location.origin) return;

  // Page navigations: network-first, fall back to the cached app shell offline.
  if (req.mode === "navigate") {
    event.respondWith((async () => {
      try {
        const fresh = await fetch(req);
        const cache = await caches.open(CACHE);
        cache.put("/index.html", fresh.clone()).catch(() => {});
        return fresh;
      } catch (_) {
        const cache = await caches.open(CACHE);
        return (await cache.match("/index.html")) || (await cache.match("/")) || Response.error();
      }
    })());
    return;
  }

  // Same-origin assets: cache-first with background refresh.
  event.respondWith((async () => {
    const cache = await caches.open(CACHE);
    const hit = await cache.match(req);
    const net = fetch(req)
      .then((res) => {
        if (res && res.ok) cache.put(req, res.clone()).catch(() => {});
        return res;
      })
      .catch(() => hit);
    return hit || net;
  })());
});
