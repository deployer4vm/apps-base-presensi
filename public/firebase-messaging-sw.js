//public/firebase-messaging-sw.js

importScripts(
    "https://www.gstatic.com/firebasejs/10.0.0/firebase-app-compat.js"
);
importScripts(
    "https://www.gstatic.com/firebasejs/10.0.0/firebase-messaging-compat.js"
);

firebase.initializeApp({
    apiKey: "AIzaSyBFRIbH019yQIKJlflYGJ8E_XqXGwGfEDw",
    authDomain: "smartcoopnotiffcm.firebaseapp.com",
    projectId: "smartcoopnotiffcm",
    storageBucket: "smartcoopnotiffcm.appspot.com",
    messagingSenderId: "864559764072",
    appId: "1:864559764072:web:bab7220c72e900038421dd",
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage(({ notification }) => {
    console.log("[firebase-messaging-sw.js] Received background message ");
    // Customize notification here
    const notificationTitle = notification.title;
    const notificationOptions = {
        body: notification.body,
    };

    if (notification.icon) {
        notificationOptions.icon = notification.icon;
    }

    self.registration.showNotification(notificationTitle, notificationOptions);
});
