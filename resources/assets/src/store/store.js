import Vuex from "vuex";
import VuexPersist from "vuex-persist";
import projectStore from "../../../../app/MainApp/resources/js/store/store";
import templateStore from "./modules/template";
import transStore from "./modules/trans";
import configStore from "./modules/config";
import tenantStore from "./modules/tenant";
import storeRepo from "./storeRepo";
import globals from "@/globals";

window.Vue.use(Vuex);

let vuexConfig = {
    modules: {
        config: configStore,
        tenant: tenantStore,
        trans: transStore,
        template: templateStore,
        storeRepo: storeRepo,
        ...projectStore
    }
};

const vuexPersist = new VuexPersist({
    //cache semua state kecuali template state
    reducer: (state) => {
        let newState = {
            'auth': state.auth,
            'trans': state.trans
        };
        if (globals().AppConfig.system.multitenant.active) {
            newState.tenant = state.tenant;
        }

        _.forEach(state, (value, index) => {
            //registrasikan vuexPersist jika diaktikan atau jika diset per store nya
            if ((globals().AppConfig.system.web_state_persistant && value.persistant == undefined)
                || value.persistant == true) {
                if (index != 'template') {
                    newState[index] = value;
                }
            }
        });

        return newState;
    },
    key: globals().AppConfig.client.apps_id,
    storage: localStorage
});
vuexConfig.plugins = [vuexPersist.plugin];

export const store = new Vuex.Store(vuexConfig);
