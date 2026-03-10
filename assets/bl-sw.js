/**
 * BattleLedger Push Notification Service Worker
 *
 * Handles incoming push events and notification clicks.
 */

/* eslint-env serviceworker */

self.addEventListener('push', function (event) {
  if (!event.data) return;

  let data;
  try {
    data = event.data.json();
  } catch {
    data = { title: 'BattleLedger', body: event.data.text() };
  }

  const title = data.title || 'BattleLedger';
  const options = {
    body: data.body || '',
    icon: data.icon || '/wp-content/plugins/BattleLedger/assets/icon-192.png',
    badge: '/wp-content/plugins/BattleLedger/assets/badge-72.png',
    data: data.data || {},
    tag: 'bl-notification-' + Date.now(),
    renotify: true,
    requireInteraction: false,
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();

  const url = event.notification.data?.url || '/dashboard';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
      // Focus existing tab if open
      for (const client of clientList) {
        if (client.url.includes('/dashboard') && 'focus' in client) {
          return client.focus();
        }
      }
      // Otherwise open new window
      return clients.openWindow(url);
    })
  );
});
