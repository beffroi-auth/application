/*
 * The service worker of the provider (Push API §5, architecture §5.6): what receives a push for a browser
 * of a user and shows it. It reads the three strings the provider encrypted to this browser (RFC 8291)
 * and nothing else, and it is registered from the page of the account, under the path the provider is
 * mounted at, so that its scope covers the pages it opens.
 *
 * A tap opens the page of the provider where a user finds what is waiting for them, found there by who
 * they are (BCP 10027 §5.2): the notification carries no identifier of a request and answers none, so
 * nothing that reached this browser from outside could carry anybody into an approval.
 */
'use strict';

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }
    let message;
    try {
        message = event.data.json();
    } catch (error) {
        return;
    }
    if (!message || typeof message.title !== 'string' || typeof message.body !== 'string') {
        return;
    }
    event.waitUntil(self.registration.showNotification(message.title, {
        body: message.body,
        requireInteraction: true,
        data: { url: typeof message.url === 'string' ? message.url : '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data && event.notification.data.url;
    if (typeof url !== 'string') {
        return;
    }
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if (client.url === url && 'focus' in client) {
                return client.focus();
            }
        }

        return self.clients.openWindow(url);
    })());
});
