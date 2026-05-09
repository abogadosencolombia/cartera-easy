self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('push', (event) => {
  let payload = {};
  try {
    payload = event.data.json();
  } catch (e) {
    payload = {};
  }

  const title = payload.title || (payload.data?.final ? 'Alerta programada' : 'Recordatorio');
  const body = payload.body || '';
  const icon = payload.icon || '/icons/icon-192.png';
  const tag = 'alerta-' + (payload.data?.id ?? Date.now());

  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon,
      tag,
      data: {
        url: payload.data?.url || '/',
        id: payload.data?.id,
        final: payload.data?.final,
        prioridad: payload.data?.prioridad,
      },
      renotify: true,
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if (client.url === url && 'focus' in client) {
          return client.focus();
        }
      }

      return clients.openWindow(url);
    })
  );
});
