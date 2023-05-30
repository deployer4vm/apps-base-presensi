/**
 *
 */
export default {
    store: null,
    tmpFunc: {},
    setModule(module) {
        if (!this.tmpFunc[module])
            this.initNewModule(module);

        return this.tmpFunc[module];
    },
    initNewModule(module) {
        this.tmpFunc[module] = {
            module: module,
            store: this.store,
            //--------
            //getter
            get listData() {
                return this.store.state.storeRepo.data[this.module].listData;
            },
            get listDataParams() {
                return this.store.state.storeRepo.data[this.module].listDataParams;
            },
            get oneData() {
                return this.store.state.storeRepo.data[this.module].oneData;
            },
            //
            readList(params) {
                params.module = this.module;
                return this.store.dispatch("storeRepo/readList", params);
            },
            readOne(params) {
                params.module = this.module;
                return this.store.dispatch("storeRepo/readOne", params);
            },
            create(params) {
                params.module = this.module;
                return this.store.dispatch("storeRepo/create", params);
            },
            update(params) {
                params.module = this.module;
                return this.store.dispatch("storeRepo/update", params);
            },
            delete(params) {
                params.module = this.module;
                return this.store.dispatch("storeRepo/delete", params);
            },
            //
        }
    }
}
