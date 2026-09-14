import Vuex from "vuex";
import VuexPersist from "vuex-persist";
import projectStore from "../../../../app/MainApp/resources/js/store/store";
import templateStore from "./modules/template";
import transStore from "./modules/trans";
import configStore from "./modules/config";
import tenantStore from "./modules/tenant";
import storeRepo from "./storeRepo";
import globals from "@/globals";
// Aktifkan encryptor jika local storage akan di endcrypt
// import { Encryptor } from "node_modules/node-laravel-encryptor";
// var encryptor = new Encryptor({
//     key: globals().AppConfig.client.secret_key,
// });

window.Vue.use(Vuex);

const persistedStore = {
    getItem(key) {
        return sessionStorage.getItem(key) || localStorage.getItem(key);
    },
    setItem(key, value) {
        let remember = false;

        try {
            const state = JSON.parse(value);
            // Keep legacy sessions persistent until the user explicitly logs in
            // again and chooses whether this browser should remember them.
            remember = state.auth && state.auth.remember !== false;
        } catch (e) {
            remember = false;
        }

        const target = remember ? localStorage : sessionStorage;
        const stale = remember ? sessionStorage : localStorage;
        target.setItem(key, value);
        stale.removeItem(key);
    },
    removeItem(key) {
        localStorage.removeItem(key);
        sessionStorage.removeItem(key);
    },
};

let vuexConfig = {
    modules: {
        config: configStore,
        tenant: tenantStore,
        trans: transStore,
        template: templateStore,
        storeRepo: storeRepo,
        ...projectStore,
    },
};

const vuexPersist = new VuexPersist({
    //cache semua state kecuali template state
    reducer: (state) => {
        let newState = {
            auth: state.auth,
            trans: state.trans,
        };
        if (globals().AppConfig.system.multitenant.active) {
            newState.tenant = state.tenant;
        }

        _.forEach(state, (value, index) => {
            //registrasikan vuexPersist jika diaktikan atau jika diset per store nya
            if (
                (globals().AppConfig.system.web_state_persistant &&
                    value.persistant == undefined) ||
                value.persistant == true
            ) {
                if (index != "template") {
                    newState[index] = value;
                }
            }
        });

        return newState;
    },
    key: globals().AppConfig.client.apps_id,
    storage: persistedStore
    // Aktifkan storage dibawah jika localstorage akan di encrypt
    // storage: {
    //     getItem: (storageKey) => {
    //         var now = Date.now();
    //         // Get the store from local storage.
    //         const store = window.localStorage.getItem(storageKey);

    //         if (store) {
    //             try {
    //                 // Decrypt the store retrieved from local storage
    //                 // using our encryption token stored in cookies.
    //                 const bytes = encryptor.decryptSync(store);
                    
    //                 console.log('storage.getItem', Date.now()-now);
    //                 return JSON.parse(bytes);
    //             } catch (e) {
    //                 // The store will be reset if decryption fails.
    //                 window.localStorage.removeItem(storageKey);
    //             }
    //         }

    //         return null;
    //     },
    //     setItem: (storageKey, value) => {
    //         var now = Date.now();
    //         // Encrypt the store using our encryption token stored in cookies.
    //         const store = encryptor.encryptSync(value);
    //         console.log('storage.setItem', Date.now()-now);

    //         // Save the encrypted store in local storage.
    //         return window.localStorage.setItem(storageKey, store);
    //     },
    //     removeItem: (storageKey) => window.localStorage.removeItem(storageKey),
    // },
});
vuexConfig.plugins = [vuexPersist.plugin];

export const store = new Vuex.Store(vuexConfig);
