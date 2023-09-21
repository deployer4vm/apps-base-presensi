// resources/js/utils/initialize.js
import { initializeApp } from 'firebase/app';

// TODO: Replace the following with your app's Firebase project configuration
const firebaseConfig = {
    apiKey: "AIzaSyBFRIbH019yQIKJlflYGJ8E_XqXGwGfEDw",
    authDomain: "smartcoopnotiffcm.firebaseapp.com",
    projectId: "smartcoopnotiffcm",
    storageBucket: "smartcoopnotiffcm.appspot.com",
    messagingSenderId: "864559764072",
    appId: "1:864559764072:web:bab7220c72e900038421dd",
};

const app = initializeApp(firebaseConfig);

export default app;
