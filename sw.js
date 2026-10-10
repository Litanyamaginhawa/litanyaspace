self.addEventListener("push", (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch {
    data = {
      title: "LitanyaSpace",
      body: event.data ? event.data.text() : "May bagong notification."
    };
  }

  const title = data.title || data.notification?.title || "LitanyaSpace";
  const options = {
    body: data.body || data.message || data.notification?.body || "May bagong post!",
    // Optional: add this image file to the site root if you have it.
    // Missing icons should not block the notification itself.
    icon: "/icon-192.png",
    badge: "/icon-192.png",
    data: {
      url: data.url || data.data?.url || "/"
    }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();

  const targetUrl = new URL(
    event.notification.data?.url || "/",
    self.location.origin
  ).href;

  event.waitUntil((async () => {
    const windows = await clients.matchAll({
      type: "window",
      includeUncontrolled: true
    });

    for (const client of windows) {
      if (client.url.startsWith(self.location.origin) && "focus" in client) {
        if ("navigate" in client) await client.navigate(targetUrl);
        return client.focus();
      }
    }

    return clients.openWindow(targetUrl);
  })());
});
