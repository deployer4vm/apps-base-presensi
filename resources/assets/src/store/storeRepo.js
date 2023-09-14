import globals from "@/globals";
import storeConfig from "../../../../app/MainApp/resources/js/store/storeConfig";

const state = {
    defData: {            
        module:'',
        apiEndpoint : '',
        listDataParams : {},
        listData: {
            data: [],
            count: 0,
            limit: 10,
            offset: 0,
            currentPage: 1,
            pageCount: 1,
        },
        oneData: {},
    },
    // all resource data
    data:{}
};

_.forEach(storeConfig,(v,k)=>{
    state.data[k] = v;
    state.data[k].listData = JSON.parse(JSON.stringify(state.defData.listData));
    state.data[k].listDataParams = {};//JSON.parse(JSON.stringify(state.defData.listDataParams))
    state.data[k].oneData = {};//JSON.parse(JSON.stringify(state.defData.oneData))
    
    if(v.pathPrefix)
        state.data[k].pathSuffix = v.pathPrefix;
    // if(v.methods)
        // _.forEach(v.methods,(v2)=>{
        //     state.data[k].methods = v.pathPrefix;
        // });
});

const getters = {
    
};

const mutations = {
    setListData( state, params ) {
        state.data[params.module].listData = params.data;
        state.data[params.module].listDataParams = JSON.parse(JSON.stringify(params.params));
    },
    setOneData( state, params ) {
        state.data[params.module].oneData = params.data;
    }
};

const actions = { 
    method({ commit, state }, params={}){
        let apiEndpoint = state.data[params.module].methods[params.methodName].apiEndpoint;
        apiEndpoint = globals().Helper.replaceAttribute(apiEndpoint,params);

        var res;

        switch (state.data[params.module].methods[params.methodName].httpMethod.toLowerCase()) {
            case 'post':                
                res = globals().LocalApi.post(apiEndpoint, params.data?params.data:{});
                break;   
            case 'put':
                res = globals().LocalApi.put(apiEndpoint, params.data?params.data:{});                
                break;   
            case 'delete':
                res = globals().LocalApi.delete(apiEndpoint);                
                break;   
            case 'get':
            default:
                res = globals()
                    .LocalApi.get(apiEndpoint, {
                        params: params.params?params.params:{}
                    });
                break;
        }

        return res.then(res => {
            return res.data.data;
        });
    },
    readList({ commit, state }, params={}) {
        let suffix = '';
        if(state.data[params.module].pathSuffix)
            suffix = globals().Helper.replaceAttribute(state.data[params.module].pathSuffix,params);

        let apiEndpoint = globals().Helper.replaceAttribute(state.data[params.module].apiEndpoint,params);
        
        return globals()
            .LocalApi.get(apiEndpoint + suffix, {
                params: params.params?params.params:{}
            })
            .then(res => {
                if(params.saveState==undefined||params.saveState)
                    commit("setListData", {
                        module: params.module,
                        data: res.data.data,
                        params: params.params?params.params:{}
                    });
                return res.data.data;
            });
    },
    readOne({ commit, state }, params) {
        let suffix = '';
        if(state.data[params.module].pathSuffix)
        suffix = '/' + globals().Helper.replaceAttribute(state.data[params.module].pathSuffix,params);

        let apiEndpoint = globals().Helper.replaceAttribute(state.data[params.module].apiEndpoint,params);

        return globals()
            .LocalApi.get(
                apiEndpoint + params.id + suffix,
                {
                    params: params.params?params.params:{}
                }
            )
            .then(res => {
                if(params.saveState==undefined||params.saveState)
                    commit("setOneData", {
                        module: params.module,
                        data: res.data.data
                    });
                return res.data.data;
            });
    },
    create({ dispatch, state }, params) {
        let suffix = '';
        if(state.data[params.module].pathSuffix)
            suffix = globals().Helper.replaceAttribute(state.data[params.module].pathSuffix,params);
            
        let apiEndpoint = globals().Helper.replaceAttribute(state.data[params.module].apiEndpoint,params);

        return globals()
            .LocalApi.post(
                apiEndpoint + suffix, 
                params.data
            )
            .then(res => {
                return params.reload==undefined||!params.reload?res.data.data:dispatch("readList",{module: params.module});
            });
    },
    update({ dispatch, state }, params) {        
        let suffix = '';
        if(state.data[params.module].pathSuffix)
            suffix = '/' + globals().Helper.replaceAttribute(state.data[params.module].pathSuffix,params);

        let apiEndpoint = globals().Helper.replaceAttribute(state.data[params.module].apiEndpoint,params);

        return globals()
            .LocalApi.put(
                apiEndpoint + params.id + suffix, 
                params.data
            )
            .then(res => {
                return params.reload==undefined||!params.reload?res.data.data:dispatch("readList",{module: params.module});
            });
    },
    delete({ dispatch, state }, params) {
        let suffix = '';
        if(state.data[params.module].pathSuffix)
            suffix = '/' + globals().Helper.replaceAttribute(state.data[params.module].pathSuffix,params);

        let apiEndpoint = globals().Helper.replaceAttribute(state.data[params.module].apiEndpoint,params);

        return globals()
            .LocalApi.delete(apiEndpoint + params.id + suffix)
            .then(res => {
                return params.reload==undefined||!params.reload?res.data.data:dispatch("readList",{module: params.module});
            });
    },
};

const storeRepo = {
    namespaced: true,
    state,
    mutations,
    actions,
    getters,
};

export default storeRepo;
