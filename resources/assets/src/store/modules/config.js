import globals from "@/globals";

const state = {
    // persistant: true, jika ingin store ini disave di local storage

    allConfig: {},
    formatedConfig: {},
    isConfigSet: null,
    lastReload: null
};

const getters = {
    isConfigSet(state) {
        return state.isConfigSet != null;
    },
    getConfig(state) {
        return state.allConfig;
    },
    apiEndpoint(state) {        
        return globals().Web.getEndpoint(globals().AppConfig.endpoint.api.app) + globals().AppConfig.system.config_endpoint;
    }
};

const mutations = {   
    setConfig(state, allConfig) {
        state.allConfig = allConfig;
        state.isConfigSet = true; 
        // console.log(allConfig);
        
        _.forEach(allConfig, (value, index) => {            
            if(state.formatedConfig[value.group]==undefined)
                state.formatedConfig[value.group] = {};

            state.formatedConfig[value.group][value.key] = value.value; 
            
        });  

        // _.forEach(state.allConfig, (value, index) => {            
        //     if(state.formatedConfig[value.group]==undefined)
        //         state.formatedConfig[value.group] = {};

        //     state.formatedConfig[value.group][value.key] = value.value;          
        // }); 
            
        // const now = new Date()
        // const expirationDate = new Date(now.getTime() + res.data.expiresIn * 1000)
        state.lastReload = new Date();
    }
};

const actions = {
    reloadConfig({commit,getters},data={}){

        var saveState = data.saveState;
        if(data.saveState)
            delete data.saveState;

        return globals()
            .LocalApi.get(getters.apiEndpoint,{params: data}).then((val)=>{
                
                if(saveState==undefined||saveState)
                    commit('setConfig',val.data);   
                               
                return true;
            }).catch((err)=>{
                console.log('Config file error.',err);
            });
    },
    saveConfig({commit,getters},data={}){
        return globals()
            .LocalApi.post(getters.apiEndpoint,{data: data.data}).then((val)=>{
                if(data.saveState==undefined||data.saveState)
                    commit('setConfig',val.data);                
                return true;
            }).catch((err)=>{
                console.log('Config file error.',err);
            });
    }
};

export default {
    state,
    mutations,
    actions,
    getters
};