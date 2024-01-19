import globals from "@/globals";
import modulesMultitenant from "@/../../../app/MainApp/resources/js/modulesMultitenant";

/**
 * TEMPLATE MAIN HELPER
 * Diakses via this.Web di vue instance atau via globals (globals().Web). Setiap proses yg berhubungan dengan template tidak langsung
 * akses store template.js, tapi via class ini
 */
export default {
    mode: "dev",
    store: null,
    router: null,
    notify: null, //vue-notification
    endpoint: null, //local full endpoint dari AppConfig.endpoint
    bvModal: null,
    tenantList: null,
    multitenantConfig: null,
    langDefault: {
        title: "Alert",
        text: "Someting went wrong!"
    },
    /**
     * =======================================================================
     * ROUTE & ENDPOINT RELATED FUNCTION
     * =======================================================================
     */
    //get path yg sedang diakses sekarang
    get curEndpoint() {
        return this.router.currentRoute.path;
    },
    get curAppEndpoint() {
        if (this.isAdminEndpoint()) {
            return this.endpoint.admin.app;
        }
        return this.endpoint.frontend.app;
    },
    get adminEndpoint() {
        return this.endpoint.admin.app;
    },
    getModuleEndpoint(packageNamespace, app = "admin") {
        return this.endpoint[app][packageNamespace];
    },
    /*
    cek apakah url yang sedang diakses sekarang adalah bagian (diawali dengan) path (parameter)
    */
    isOnEndpoint(path = null) {
        //get url yg sedang diakses sekarang
        let endpoint = this.getEndpoint(path);
        //ceka apakah diawali dengan 'path'
        return this.router.currentRoute.path == endpoint || this.router.currentRoute.path.indexOf(endpoint + '/') === 0;
    },
    //cek apakah 'path' adalah url auth endpoin di aplikasi 'app'
    isAuthEndpoint(path = null, app = "admin") {
        if (path == null) {
            path = this.router.currentRoute.path;
        }
        //get url/path auth
        let atuhEndpoint = this.getEndpoint(this.endpoint[app]["auth"]);
        //cek apakah parameter auth yg diinputkan berarawalan path auth
        return path.indexOf(atuhEndpoint) === 0;
    },
    // cek apakah 'path' adalah url system
    isSystemEndpoint(path = null) {
        if (path == null) {
            path = this.router.currentRoute.path;
        }
        //get url/path auth
        let systemEndpoint = this.getEndpoint(this.endpoint.admin.app + '\/system');
        //cek apakah parameter auth yg diinputkan berarawalan path auth
        return path.indexOf(systemEndpoint) === 0;
    },
    //cek apakah halaman yang diakses sekarang admin area
    isAdminEndpoint(path = null, app = null) {
        if (this.endpoint.admin.app == "") return true;
        if (path == null) {
            path = this.router.currentRoute.path;
        }
        let adminEndpoint = this.getEndpoint(this.endpoint.admin.app);

        return path.indexOf(adminEndpoint) === 0;
    },
    //----------go to------
    goToDefaultTenant() {
        // console.log('go to default tenant : ', this.router.resolve(this.multitenantConfig.default_route).href);
        this.router.push(this.multitenantConfig.default_route);
    },
    goToCurrentTenant() {
        // console.log('go to current tenant : ',this.getTenantGroupApp(), this.router.resolve({name: "home",params:{group_app: this.getTenantGroupApp()}}).href);
        this.goTo("home");
    },
    goToTenant(groupApp) {
        this.goTo("home", groupApp);
    },
    // goTo(routeName, groupApp = false) {
    //     // if (groupApp == false) groupApp = this.getTenantGroupApp();
    //     // console.log('go to : ', this.router.resolve({ name: routeName, params: { group_app: groupApp } }));
    //     // this.router.push({ name: routeName, params: { group_app: groupApp } });
    //     this.goTo(routeName,{}, groupApp);
    // },
    goTo(routeName, params = {}, groupApp = false) {
        if (groupApp == false) groupApp = this.getTenantGroupApp();
        params.groupApp = groupApp;

        console.log('go to : ', this.router.resolve({ name: routeName, params: params }));
        this.router.push({ name: routeName, params: params });
    },
    /**
     * =======================================================================
     * WEB TENANT MANAGE RELATED FUNCTION
     * =======================================================================
     */
    isMultiTenant() {
        return this.multitenantConfig.active ? true : false;
    },
    isTenantLoaded() {
        return this.store.getters.isTenantLoaded;
    },
    isOnTenantManager() {
        return isOnTenantManager//this.store.getters.isOnTenantManager;
    },
    getEndpoint(endPoint) {
        return endPoint.replace(':group_app', this.router.currentRoute.params.group_app);
    },
    loadTenant(groupApp) {
        if (!this.store.getters.isTenantLoaded || groupApp != this.store.getters.getTenantGroupApp) {
            return this.store.dispatch('reloadTenant', groupApp).then((val) => {
                if (!val) {
                    return false;
                }
                this.tenantList = this.store.getters.getTenantList;
                return true;
            });
        } else {
            this.tenantList = this.store.getters.getTenantList;
            return new Promise((resolve, reject) => {
                resolve(true);
            });
        }
    },
    //force reload tenant data from server
    reLoadTenant(groupApp) {
        return this.store.dispatch('reloadTenant', groupApp).then((val) => {
            this.tenantList = this.store.getters.getTenantList;
        });
    },
    // set tenant aktif adalah aplikasi tenant manager nya
    setTenantManagementIsActive(group_app = '') {
        this.store.commit('setTenant', {
            active_tenant_group: [],
            active_tenant: {
                id: 0,
                name: 'Tenant Management',
                group_app: group_app,
                is_main: 1,
            }
        });
        this.store.commit('setActiveTenant');
    },
    /**
     * get defautl tenant route, format :
     *  {"name":"homeadmin","params":{"group_app":"admin"}}
     */
    getDefaultTenantRoute() {
        return this.multitenantConfig.default_route;
    },
    //get active tenant record
    get getTenant() {
        return this.store.getters.getTenant;
    },
    //get active tenant name
    getTenantGoup() {
        return this.store.getters.getTenantGroup;
    },
    //get active tenant id
    getTenantId() {
        return this.store.getters.getTenantId;
    },
    //get active tenant name
    getTenantName() {
        return this.store.getters.getTenantName;
    },
    //get active tenant group_app
    getTenantGroupApp() {
        return this.store.getters.getTenantGroupApp;
    },
    /**
     * --- multi tenant component & data share
     */
    multiTenantLoadMixin(moduleNamepsace, componentName, tenantId = false) {
        if (!tenantId)
            tenantId = this.getTenantId();
        return modulesMultitenant[tenantId]
            && modulesMultitenant[tenantId][moduleNamepsace]
            && modulesMultitenant[tenantId][moduleNamepsace][componentName]
                ? modulesMultitenant[tenantId][moduleNamepsace][componentName]
                : [];
    },
    /**
     * =======================================================================
     * TEMPLATE RELATED FUNCTION
     * =======================================================================
     */
    //initialize template store vuex
    initTemplateState() {
        return this.store.dispatch("initTemplateState");
    },
    setLoadingPage(showLoading = false, message = '') {
        if (this.store != null) {
            this.store.commit("setMessagePageLoading", message);
            this.store.commit("setPageLoading", showLoading);
        }
    },
    /**
     * set show / hide element yg bisa hide/show berdasarkan config per module
     *
     * @param {string} module nama namespace module
     */
    setShow(module) {
        if (
            globals().AppConfig.packageLocal[module].template &&
            globals().AppConfig.packageLocal[module].template.admin &&
            globals().AppConfig.packageLocal[module].template.admin
        ) {
            //hide / show navbar (header)
            if ((!onIframe && globals().AppConfig.packageLocal[module].template.admin.navbar)
                || (onIframe && byPassViewConfig.showNavbar)) {
                this.setShowNavbar(true);
            } else {
                this.setShowNavbar(false);
            }

            //hide / show sidenave (menu utama)
            if ((!onIframe && globals().AppConfig.packageLocal[module].template.admin.sidenav)
                || (onIframe && byPassViewConfig.showSidenav)) {
                this.setShowSidenav(true);
            } else {
                this.setShowSidenav(false);
            }

            //hide / show footer
            if ((!onIframe && globals().AppConfig.packageLocal[module].template.admin.footer)
                || (onIframe && byPassViewConfig.showFooter)) {
                this.setShowFooter(true);
            } else {
                this.setShowFooter(false);
            }

        } else if (onIframe) {
            //hide / show navbar (header)
            if (byPassViewConfig.showNavbar) {
                this.setShowNavbar(true);
            } else {
                this.setShowNavbar(false);
            }

            //hide / show sidenave (menu utama)
            if (byPassViewConfig.showSidenav) {
                this.setShowSidenav(true);
            } else {
                this.setShowSidenav(false);
            }

            //hide / show footer
            if (byPassViewConfig.showFooter) {
                this.setShowFooter(true);
            } else {
                this.setShowFooter(false);
            }
        } else {
            this.setShowAll();
        }
        return this;
    },
    /**
     * tampilkan semua element yg hide/show
     */
    setShowAll() {
        this.setShowNavbar(true);
        this.setShowSidenav(true);
        this.setShowFooter(true);
        return this;
    },
    /**
     * sembunyikan semua element yg hide/show
     */
    setHideAll() {
        this.setShowNavbar(false);
        this.setShowSidenav(false);
        this.setShowFooter(false);
        return this;
    },
    // set module yang sedang dibuka saat ini
    setModule(module) {
        this.store.commit("setModule", module);
        return this;
    },
    getModule() {
        return this.store.getters.getModule;
    },
    /**
     * NAVBAR (header)
     * --------------------------------------------------------------
     */
    //admin title digunakan di meta title dan brand/apps bar
    getAdminTitle() {
        return this.store.getters.getAdminTitle;
    },
    // admin title tidak boleh diubah
    // appendAdminTitle(title)
    // {
    //     this.store.commit("setAdminTitle", this.store.getters.getAdminTitle + ' - ' + title);
    // },
    // setAdminTitle(newTitle)
    // {
    //     this.store.commit("setAdminTitle", newTitle);
    // },
    //title di navbar atas
    getNavbarTitle() {
        return this.store.getters.getNavbarTitle;
    },
    appendNavbarTitle(title) {
        this.store.commit("setNavbarTitle", this.store.getters.getNavbarTitle + ' \\ ' + title);
        return this;
    },
    setNavbarTitle(newTitle) {
        this.store.commit("setNavbarTitle", newTitle);
        return this;
    },
    setShowNavbar(showNavbar) {
        this.store.commit("setShowNavbar", showNavbar);
        return this;
    },
    /**
     * SIDENAV (menu utama)
     * --------------------------------------------------------------
     */
    getSidenavMenu() {
        return this.store.getters.getSidenavMenu;
    },
    getCustomSidenavMenu() {
        return this.store.getters.getCustomSidenavMenu;
    },
    setShowSidenav(showSidenav) {
        this.store.commit("setShowSidenav", showSidenav);
        return this;
    },
    setSidenavHorizontal(isHorizontal) {
        this.store.commit("setSidenavHorizontal", isHorizontal ? true : false);
        return this;
    },
    setSidenavHorizontalDefault() {
        this.store.commit(
            "setSidenavHorizontal",
            globals().AppConfig.system.web_admin.sidenav_horizontal == 1 ? true : false
        );
        return this;
    },
    /**
     * BODY (content utama)
     * --------------------------------------------------------------
     */
    getBreadcrumb() {
        return this.store.getters.getBreadcrumb;
    },
    addBreadcrumb(text, href = false) {
        var tmpLink = {
            text: text,
            active: true
        };
        if (href.name != undefined) {
            tmpLink.to = href;
        } else {
            tmpLink.href = href ? href : '#';
        }
        this.store.dispatch("addBreadcrumb", tmpLink);
        return this;
    },
    resetBreadcrumb() {
        this.store.commit("resetBreadcrumb");
        return this;
    },
    //set container utama dengan padding atau tidak
    setBodyWithPadding(isWithPadding) {
        this.store.commit("setBodyWithPadding", isWithPadding);
        return this;
    },
    /**
     * FOOTER (content footer utama)
     * --------------------------------------------------------------
     */
    setShowFooter(showFooter) {
        this.store.commit("setShowFooter", showFooter);
        return this;
    },
    /**
     * WEB FUNCTION
     * --------------------------------------------------------------
     */
    /*
    tampilkan alert instan
    params :
        type
        styleType : berisi 'alert', 'notif' atau 'modal'
        title
        text
        position

        onShow
        onClose
        onOk

        modalButtonCancel
        modalButtonOk
    */
    showAlert(params) {
        if (!params.type) params.type = "info";
        if (!params.title) params.title = this.langDefault.title;
        if (!params.text) params.text = this.langDefault.text;
        if (!params.styleType) params.styleType = "hover";
        if (!params.position) params.position = "top-center";

        if (this._showAlert_type[params.type] == undefined)
            params.type = "info";
        if (this._showAlert_position[params.position] == undefined)
            params.position = "top-center";

        if (params.styleType == "alert") {
            this.store.commit("addAlert", {
                text: params.text,
                type: params.type
            });
        } else if (params.styleType == "modal") {
            if (!params.onShow) params.onShow = null;
            if (!params.onCancel) params.onCancel = null;
            if (!params.onOk) params.onOk = null;
            if (!params.modalButtonCancel) params.modalButtonCancel = null;
            if (!params.modalButtonOk) params.modalButtonOk = null;
            this.store.commit("setModal", {
                title: params.title,
                text: params.text,
                onShow: params.onShow,
                onCancel: params.onCancel,
                onClose: params.onClose,
                onOk: params.onOk,
                modalButtonCancel: params.modalButtonCancel,
                modalButtonOk: params.modalButtonOk
            });
            this.bvModal.show("alert-modals");
        } else {
            let duration = 3000;
            let newDur = parseInt(params.text.length / 20) * 1000;
            if (newDur > duration) duration = newDur;
            this.notify({
                group: this._showAlert_position[params.position],
                type: this._showAlert_type[params.type],
                title: params.title,
                text: params.text,
                duration: duration
            });
        }
        return this;
    },
    _showAlert_position: {
        "top-left": "notifications-top-left",
        "top-center": "notifications-top-center",
        default: "notifications-default",
        "bottom-left": "notifications-bottom-left",
        "bottom-center": "notifications-bottom-center",
        "bottom-right": "notifications-bottom-right"
    },
    _showAlert_type: {
        warning: "bg-warning warn text-body",
        success: "bg-success success text-white",
        info: "bg-info text-white",
        danger: "bg-danger error text-white",
        primary: "bg-primary text-white",
        secondary: "bg-secondary text-white",
        dark: "bg-dark text-white"
    },
    //tampilkan alert di halaman selanjutnya
    showNextAlert() { }
};
