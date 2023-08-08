import globals from "@/globals";

const state = {
    // persistant: true, jika ingin store ini disave di local storage
    isAppReady: false,

    nextAlert: { // route yg ditampilkan dihalaman selanjutnya
        show: false,
        alertStyleType: "hover",
        isInstant: 1, //1/0
        data: {
            type: "",
            title: "",
            content: ""
        }
    },
    alertData: [],
    alertModal: {
        title: "Warning",
        text: "",
        onOk: null,
        onShow: null,
        onClose: null,
        modalButtonCancel: 'Close',
        modalButtonOk: 'Ok'
    },
    breadcrumb: [
        {
            text: "Home",
            href: "/",
            active: true
        }
    ],
    frontend: {
        title: globals().AppConfig.system.template.frontend.title,
    },
    admin: {
        title: globals().AppConfig.system.template.admin.title,//digunakan sebagai brand title
        navbar: {
            show: true,
            title: "",
            appsbar: {

            },
            //untuk konsep tampilan multi tab (setiap klik menu akan buka tab baru)
            tabs: [
                {
                    closable: 0,
                    title: "Home",
                    route: "/dashboard"
                }
            ],
            menu: {}
        },
        sidenav: {
            show: true,
            isHorizontal: globals().AppConfig.system.web_admin.sidenav_horizontal == 1 ? true : false,//jika null berarti ikut setingan template di system.json nya
            menu: {},
            customMenu: {}
        },
        body: {
            withPadding: true,
        },
        footer: {
            show: globals().AppConfig.system.template.admin.footer.show,
            text: globals().AppConfig.system.template.admin.footer.text,
            menu: globals().AppConfig.system.template.admin.footer.menu
        }
    },
    showLoading: false,
    messageLoading: 'wekdut',
    curModule: ''//namespace module saat ini, diset manual
};

const getters = {
    isAppReady(state) {
        return state.isAppReady;
    },
    getMessageLoading(state) {
        return state.messageLoading;
    },
    getModule(state) {
        return state.curModule;
    },
    //---------------navbar (header-------------------
    getAdminTitle(state) {
        return state.admin.title;
    },
    getNavbarTitle(state) {
        return state.admin.navbar.title;
    },
    //untuk konsep tampilan multi tab (setiap klik menu akan buka tab baru)
    getTabs(state) {
        return state.admin.navbar.tabs;
    },
    isNavbarShowed(state) {
        return state.admin.navbar.show;
    },
    //---------------sidenav-------------------
    getSidenavMenu(state) {
        return state.admin.sidenav.menu;
    },
    getCustomSidenavMenu(state) {
        return state.admin.sidenav.customMenu;
    },
    isSidenavShowed(state) {
        return state.admin.sidenav.show;
    },
    isSidenavHorizontal(state) {
        return state.admin.sidenav.isHorizontal;
    },
    //---------------body-------------------
    getBreadcrumb(state) {
        return state.breadcrumb;
    },
    isBodyWithPadding(state) {
        return state.admin.body.withPadding;
    },
    //---------------footer-------------------
    isFooterShowed(state) {
        return state.admin.footer.show;
    },
    getFooterText(state) {
        return state.admin.footer.text;
    },
    getFooterMenu(state) {
        return state.admin.footer.menu;
    }
};

const mutations = {
    setAppReady(state, data) {
        state.isAppReady = true;
    },
    addAlert(state, alert) {
        if (state.alertData.length >= 3) delete state.alertData[0];
        state.alertData.push(alert);
    },
    deleteAlert(state, k) {
        delete state.alertData[k];
    },
    setPageLoading(state, showLoading) {
        state.showLoading = showLoading;
    },
    setMessagePageLoading(state, message = '') {
        state.messageLoading = message;
    },
    setModal(state, v) {
        state.alertModal.title = v.title;
        state.alertModal.text = v.text;
        state.alertModal.onShow = v.onShow;
        state.alertModal.onOk = v.onOk;
        state.alertModal.onClose = v.onClose;
        state.alertModal.modalButtonCancel = v.modalButtonCancel
            ? v.modalButtonCancel
            : globals().Trans.get('alert.modal_cancel_caption');
        state.alertModal.modalButtonOk = v.modalButtonOk
            ? v.modalButtonOk
            : globals().Trans.get('alert.modal_ok_caption');
    },
    setModule(state, module) {
        state.admin.curModule = module;
    },
    /**
     * NAVBAR (header)
     * --------------------------------------------------------------
     */
    // admin title tidak boleh diubah
    // setAdminTitle (state, newTitle) {
    //     state.admin.title = newTitle;
    // },
    setNavbarTitle(state, newTitle) {
        state.admin.navbar.title = newTitle;
    },
    setShowNavbar(state, setShowNavbar) {
        state.admin.navbar.show = setShowNavbar ? true : false;
    },
    /**
     * SIDENAV (menu utama)
     * --------------------------------------------------------------
     */
    setSidenavMenu(state) {
        state.admin.sidenav.menu = globals().AppConfig.sidenav;
        state.admin.sidenav.customMenu = globals().AppConfig.customSidenav;
        // _.forEach(globals().AppConfig.packageLocal, (value, index) => {
        //     if(value.access.has_acl == 0 ||(value.access &&  value.enable &&  value.access.has_access)){
        //         state.admin.sidenav[index] =value.access;
        //     }
        // });
    },
    setShowSidenav(state, setShowSidenav) {
        state.admin.sidenav.show = setShowSidenav ? true : false;
    },
    setSidenavHorizontal(state, isHorizontal) {
        state.admin.sidenav.isHorizontal = isHorizontal ? true : false;
    },
    /**
     * BODY (content utama)
     * --------------------------------------------------------------
     */
    addBreadcrumb(state, data) {
        if (state.breadcrumb.length > 0)
            state.breadcrumb[state.breadcrumb.length - 1].active = false;
        state.breadcrumb.push(data);
    },
    resetBreadcrumb(state) {
        state.breadcrumb = [];
    },
    setBodyWithPadding(state, isWithPadding) {
        state.admin.body.withPadding = isWithPadding ? true : false;
    },
    /**
     * FOOTER (content footer utama)
     * --------------------------------------------------------------
     */
    setShowFooter(state, setShowFooter) {
        state.admin.footer.show = setShowFooter ? true : false;
    }
};

const actions = {
    //initialize yang perlu diinitialize
    //action ini dieksekusi saat vue instace utama created
    initTemplateState({ commit }) {
        commit('setSidenavMenu');
    },
    // admin title tidak boleh diubah
    // setAdminTitle({commit}, newTitle) {
    //     commit('setAdminTitle', newTitle);
    // },
    updateTemplate({ commit }, data) {
        commit('changeData', data);
    },
    addBreadcrumb({ commit }, data) {
        commit('addBreadcrumb', data);
    }
};

export default {
    state,
    mutations,
    actions,
    getters
};
