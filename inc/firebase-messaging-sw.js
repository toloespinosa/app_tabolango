importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: "AIzaSyBRqOMYdwngqcZ0XbJORFaVGXRPUa22MPo",
    authDomain: "tabolangoapp.firebaseapp.com",
    projectId: "tabolangoapp",
    messagingSenderId: "610098222900",
    appId: "1:610098222900:web:ba00fb948ca020b888b761"
});

const messaging = firebase.messaging();

// --- ACTUALIZACIÓN FORZADA ---
// Esto obliga al navegador a usar este archivo NUEVO inmediatamente
self.addEventListener('install', function(event) {
    self.skipWaiting(); // Echa al service worker viejo
});

self.addEventListener('activate', function(event) {
    event.waitUntil(clients.claim()); // Toma el control de las pestañas abiertas
});
// -----------------------------

// 1. BACKGROUND: TOTALMENTE SILENCIOSO
// Al dejar esto vacío (o solo con log), evitamos que se genere la segunda notificación.
// La primera llega automática desde Firebase gracias al bloque 'notification' del PHP.
messaging.onBackgroundMessage((payload) => {
    console.log('[SW] Notificación recibida. Silenciando SW para evitar duplicados.');
    // ¡NO poner self.registration.showNotification aquí!
});

// 2. CLIC: ABRIR URL
self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    // Recuperar URL (soporte para varios formatos de payload)
    let urlToOpen = 'https://app.tabolango.cl/';
    if (event.notification.data && event.notification.data.url) {
        urlToOpen = event.notification.data.url;
    } else if (event.notification.data && event.notification.data.FCM_MSG && event.notification.data.FCM_MSG.data) {
        urlToOpen = event.notification.data.FCM_MSG.data.url;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
            for (var i = 0; i < windowClients.length; i++) {
                var client = windowClients[i];
                if (client.url === urlToOpen && 'focus' in client) return client.focus();
            }
            if (clients.openWindow) return clients.openWindow(urlToOpen);
        })
    );
});