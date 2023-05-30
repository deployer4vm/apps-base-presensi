import globals from "@/globals";

const state = {
    isTenantLoaded: 0,
    // tenantList: null,
    activeTenant: {
        id: 0,
        name: '',
        group_app: '',
        is_main: 0,
    },
    activeGroup: null,//jika ada akan berisi list id group tenant yang aktif
    //tenant group
    listTenantGroup:[]
};

const getters = {
    isTenantLoaded(state) {
        return state.isTenantLoaded;
    },
    isOnTenantManager(state) {
        return isOnTenantManager;//state.isTenantLoaded && state.activeTenant.id==0;
    },
    getTenantList(state) {
        return state.tenantList;
    },
    getTenantGroup(state) {
        return state.activeGroup;
    },
    getTenant(state) {
        return state.activeTenant;
    },
    getTenantId(state) {
        return state.activeTenant.id;
    },
    getTenantName(state) {
        return state.activeTenant.name;
    },
    getTenantGroupApp(state) {
        return state.activeTenant.group_app;
    },
};

const mutations = {
    setTenant(state, data) {
        // state.tenantList = data.tenant_list?data.tenant_list:null;
        state.activeTenant = data.active_tenant?data.active_tenant:{id: 0,name: '',group_app: '',is_main: 0};
        state.activeGroup = data.active_tenant_group?data.active_tenant_group:null;
    },
    setActiveTenant(state) {
        state.isTenantLoaded = 1;
    },
    //tenant group
    setListTenantGroup(state) {
        state.listTenantGroup = 1;
    }
};

const actions = {
    reloadTenant({commit},groupApp){
        var apiPath =
            globals().Web.getEndpoint(globals().AppConfig.endpoint.api.app) +
            globals().AppConfig.system.multitenant.api_endpoint.tenant +
            globals().AppConfig.system.multitenant.api_endpoint.tenant_active;
        return axios.get(apiPath,{
            params: {
                group_app: groupApp
            }
        }).then((val)=>{
            commit('setTenant',val.data);
            if(val.data.active_tenant){
                commit('setActiveTenant');
                return true;
            }
            return false;
        }).catch((err)=>{
            console.log('Tenant config error : ',err);
            return false;
        });
    },
    listTenantGroup({commit},params={}) {
        var apiPath =
            globals().Web.getEndpoint(globals().AppConfig.endpoint.api.app) +
            globals().AppConfig.system.multitenant.api_endpoint.tenant +
            globals().AppConfig.system.multitenant.api_endpoint.tenant_group;
        return axios.get(apiPath,{
            params: params
        }).then((val)=>{
            commit('setListTenantGroup',val.data);
            return val.data;
        }).catch((err)=>{
            console.log('Tenant group config error : ',err);
            return false;
        });

    }
};

export default {
    state,
    mutations,
    actions,
    getters
};
