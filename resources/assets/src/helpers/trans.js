/*
language helper
diakses via window.AppTemplate atau langsung AppTemplate
*/
export default {
    store: null,
    router: null,
    allLang: null,
    lang: null,
    //load language file from cache if exist, reload from server if not exist
    loadLang(onLoadComplete = null) {
        // jika lang belum diset mak load ulang
        if (!this.store.getters.isLangSet) {
            this.store.dispatch('reloadLang').then((val) => {
                this.allLang = this.store.getters.getLang;
                // EventBus.$emit('onLangLoaded');
                if (onLoadComplete) onLoadComplete();
            });
            // jika sudah diload maka
        } else {
            this.allLang = this.store.getters.getLang;
            this.reLoadLang(this.getLocale());
            if (onLoadComplete) onLoadComplete();
        }
    },
    //force reload languange from server
    reLoadLang(newLang = null) {
        this.store.dispatch('reloadLang', newLang).then((val) => {
            this.allLang = this.store.getters.getLang;
            // EventBus.$emit('onLangLoaded');
        });
    },
    getLocale() {
        return this.store.getters.getLocale;
    },
    /**
     * pilih salah satu item sesuai lang yg aktif
     */
    choose(langFile) {
        if (langFile[this.store.getters.getLocale] != undefined)
            return langFile[this.store.getters.getLocale];
        return langFile;
    },
    /**
     * Mirror dari choose, biar gak break
     */
    chose(langFile) {
        return this.choose(langFile);
    },
    get(langKey, replace = {}, defaultVal='') {
        const blockedKeys = ['__proto__', 'prototype', 'constructor'];
        let lang = this.allLang;
        for (const key of String(langKey).split('.')) {
            if (!key || blockedKeys.includes(key) || lang === null || typeof lang !== 'object') {
                lang = undefined;
                break;
            }
            lang = Object.prototype.hasOwnProperty.call(lang, key) ? lang[key] : undefined;
        }
        if (typeof lang !== 'string') lang = defaultVal || langKey;
        _.forEach(replace, (v, k) => {
            lang = lang.replace(":" + k, v);
        });
        return lang;
    }
};
