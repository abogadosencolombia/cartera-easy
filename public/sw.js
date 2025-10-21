self.addEventListener('push', (event) => {
  let payload = {};
  try { payload = event.data.json(); } catch (e) { payload = {}; }

  const title = payload.title || (payload.data?.final ? 'Alerta programada' : 'Recordatorio');
  const body  = payload.body  || '';
  const icon  = payload.icon  || '/icons/icon-192.png';
  const url   = payload.data?.url || '/';

  // tag único por notificación (usa el id que mandamos desde Laravel)
  const tag = 'alerta-' + (payload.data?.id ?? Date.now());

  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon,
      tag,
      data: { url, id: payload.data?.id, final: payload.data?.final, prioridad: payload.data?.prioridad },
      renotify: true, // vuelve a sonar si llega otra con el mismo tag (opcional)
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (const c of list) { if ('focus' in c) return c.focus(); }
      return clients.openWindow(url);
    })
  );
});
