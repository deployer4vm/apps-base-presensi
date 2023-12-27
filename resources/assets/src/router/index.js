import Vue from "vue";
import Router from "vue-router";
// import NProgress from 'node_modules/nprogress';
import Meta from "vue-meta";
// import authAxios from "axios";
import BlankRouterContainer from '@/layout/BlankRouterContainer';
import globals from "@/globals";

//load routes level project
import projectRoutes from "../../../../app/MainApp/resources/js/router/index";
//load all routes web modules general
import modulesRoutes from "../../../../app/MainApp/resources/js/router/modules";
//load all routes web modules admin
import modulesAdminRoutes from "../../../../app/MainApp/resources/js/router/modulesAdmin";
//load all routes web modules general
import modulesRoutesPerTenant from "../../../../app/MainApp/resources/js/router/modulesPerTenant";
//load all routes web modules admin
import modulesAdminRoutesPerTenant from "../../../../app/MainApp/resources/js/router/modulesAdminPerTenant";

Vue.use(Router);
Vue.use(Meta);

var newModulesAdminRoutes = [];
newModulesAdminRoutes = newModulesAdminRoutes.concat(modulesAdminRoutes);

if (globals().AppConfig.system.multitenant.active && typeof tenantId !== 'undefined' && modulesAdminRoutesPerTenant[tenantId]) {
    newModulesAdminRoutes = newModulesAdminRoutes.concat(modulesAdminRoutesPerTenant[tenantId]);
}

let tmpRoutes = [...projectRoutes];
// if (globals().AppConfig.system.web_admin.autoload_router.frontend) {
tmpRoutes.push({
    path: globals().AppConfig.endpoint.admin.app,
    component: BlankRouterContainer,
    children: newModulesAdminRoutes
});
// }
tmpRoutes = tmpRoutes.concat(modulesRoutes);

if (globals().AppConfig.system.multitenant.active && typeof tenantId !== 'undefined' && modulesAdminRoutesPerTenant[tenantId]) {
    tmpRoutes.concat(modulesRoutesPerTenant[tenantId]);
}

const router = new Router({
    base: "/",
    mode: "history",
    routes: tmpRoutes
});

var _groupApp = '';
router.afterEach((to, from) => {

    if (globals().AppConfig.system.multitenant.active) {

        if (tenantData == undefined) {
            _groupApp = to.params.group_app ? to.params.group_app : '';
        } else {
            _groupApp = isOnTenantManager ? '' : tenantData.group_app;
        }

        if (globals().LocalApi.defaults.headers.common["Group-App"] != _groupApp)
            globals().LocalApi.defaults.headers.common["Group-App"] = _groupApp;

        //jika tenant berubah atau jika saat pertama kali akses
        if (_groupApp != globals().Web.getTenantGroupApp()) {
            console.log('tenant berubah : old=', globals().Web.getTenantGroupApp(), ' , new=', _groupApp);

            // jika sedang di owner apps
            if (isOnTenantManager) {
                console.log('tenant management active');
                _groupApp = globals().AppConfig.system.multitenant.owner_subfolder ? globals().AppConfig.system.multitenant.owner_subfolder : '';
                globals().Web.setTenantManagementIsActive(_groupApp);

                //jika pertama kali akses dan tidak mengakses tenant maka redirect ke default tenant
            } else if (_groupApp == undefined && globals().Web.getTenantGroupApp() == '') {
                console.log('First access, go to default tenant (from main router)');
                globals().Web.goToDefaultTenant();
                return;

                //jika tidak mengakses tenant tapi sebelumnya sudah ada tenant yg aktif maka redirect ke tenant tersebut
            } else if (_groupApp == undefined) {
                console.log('go to previouse active tenant (from main router)');
                globals().Web.goToCurrentTenant();
                return;

                //jika tenant berubah atau saat pertama kali akses
            } else {
                console.log('load tenant baru : ', to.params);
                globals().Web.loadTenant(_groupApp).then((val) => {
                    console.log('tenant baru : ', val);
                    EventBus.$emit('onTenantChange', val);
                    //jika tenant tidak ditemukan
                    if (!val) {
                        //jika tenant yang tidak ditemukan adalah default tenant maka error
                        if (_groupApp == globals().Web.getDefaultTenantRoute().params.group_app) {
                            alert('Tenant Api Error');
                        } else {
                            globals().Web.goToDefaultTenant();
                        }
                    }
                });
            }
        }
    }

    /*
    jika mengakses halaman admin maka detek dan proteksi halaman admin dengan auth (jika fitur auth diaktifkan di config)
    */
    if (
        globals().AppConfig.system.has_auth &&
        globals().AppConfig.system.web_admin.protected_by_auth &&
        globals().Web.isAdminEndpoint()
    ) {
        //jika tidak login dan mengakses halaman selain auth maka redirect ke halaman login
        if (!globals().UserAuth.isLogin() && !globals().Web.isAuthEdnpoint()) {
            console.log('redirect ke login (from main router)',globals().UserAuth.isLogin(),globals().Web.isAuthEdnpoint());
            globals().UserAuth.goToLogin();
            return;
            //jika sudah login tapi mengakses halaman auth maka redirect
        } else if (globals().UserAuth.isLogin() && globals().Web.isAuthEdnpoint()) {
            globals().UserAuth.goToHome();
            return;
        }

        //jika berpindah tenant maka logout kan dahulu, jika hanya mengakses halaman utama maka redirect ke home
        if (
            globals().UserAuth.isLogin()
            && globals().AppConfig.system.multitenant.active
            && _groupApp != globals().Web.getTenantGroupApp()
        ) {
            globals().UserAuth.logout();
            return;
        }
    }


    // Remove initial splash screen
    var splashScreen = document.querySelector(".app-splash-screen");
    if (splashScreen) {
        var op = 1;
        var timer = setInterval(function () {
            if (op <= 0.1) {
                clearInterval(timer);
                splashScreen.style.opacity = 0;
                if (splashScreen.parentNode) splashScreen.parentNode.removeChild(splashScreen);
            }
            splashScreen.style.opacity = op;
            splashScreen.style.filter = 'alpha(opacity=' + op * 100 + ")";
            op -= op * 0.1;
        }, 50);
    }

    // On small screens collapse sidenav
    if (
        globals().layoutHelpers &&
        globals().layoutHelpers.isSmallScreen() &&
        !globals().layoutHelpers.isCollapsed()
    ) {
        setTimeout(() => globals().layoutHelpers.setCollapsed(true, true), 10);
    }

    //reset
    globals().Web.setSidenavHorizontalDefault();
    globals().Web.setBodyWithPadding(true);

    // Scroll to top of the page
    globals().scrollTop(0, 0);
    globals().Web.setLoadingPage(false);
    // NProgress.done();
    EventBus.$emit('onAfterEach', { to, from });
});

router.beforeEach((to, from, next) => {
    if (to.name) {
        globals().Web.setLoadingPage(true);
        // NProgress.start();
    }
    // Set loading state
    document.body.classList.add("app-loading");

    EventBus.$emit('onBeforeEach', { to, from });
    // Add tiny timeout to finish page transition
    setTimeout(() => next(), 10);
});

export default router;
