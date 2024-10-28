/*
class helper untuk mengakses Auth, Keterangan routing serta templating.
seluruh class disini bisa diakses via window dan diinitialize dari vue instace utama.
*/

//diakses via window.UserAuth
export default {
    store: null,
    router: null,

    //apakah menggunakan fitur UserAuth
    isActive() {
        return this.store != null ? true : false;
    },
    //apakah akses webdev
    isWebdev() {
        return this.store.getters.isLogin && this.store.state.auth.role_code == 'webdev' ? true : false;
    },
    isWebdevLevel() {
        return this.store.getters.isLogin && parseInt(this.store.state.auth.user.level) === 0 ? true : false;
    },
    //implement acl role user yang online ke navside menu
    implementAcl() {
        return this.store.dispatch('implementAcl');
    },
    hasAccess(key, subkey = 'has_access', defaultAccess = true) {
        if (!this.isActive() || this.store.getters.getAuthRole.rule == null) return true;
        let access = 1;
        access = this.store.getters.getAuthRole.rule[key];
        if (access == undefined)
            return defaultAccess;
        access = access[subkey];
        if (access == undefined)
            return defaultAccess;
        return access || access == 1 ? true : false;
    },
    getAccess(key) {
        return this.store.getters.getAuthRole.rule[key];
    },
    //=================================================
    login(userCredential) {
        userCredential.group_app = this.router.currentRoute.params.group_app;
        return this.store.dispatch('login', userCredential).then((res) => {
            return res;
        });
    },
    changeRole(roleCode) {
        return this.store.dispatch('changeRole', roleCode).then((res) => {
            return res;
        });
    },
    register(userData) {
        userData.group_app = this.router.currentRoute.params.group_app;
        return this.store.dispatch('register', userData).then((res) => {
            return res;
        });
    },
    forgotPassword(email) {
        return this.store.dispatch('forgotPassword', { email: email }).then((res) => {
            return res;
        });
    },
    //logoutkan session yg sekarang
    // return promise
    logout(goToLogin = true) {
        this.store.dispatch('logout');
        if (goToLogin) {
            this.router.push({
                name: "login",
                params: { group_app: this.store.getters.getTenantGroupApp }
            });
        }
    },
    // logout & reset local storage, lalu redirect ke halaman login
    logoutAndReset() {
        this.store.dispatch('logout');
        var tmpLoginUrl = this.router.resolve({
            name: "login",
            params: { group_app: this.store.getters.getTenantGroupApp }
        }).href; 
        window.localStorage.clear();
        window.location.href = tmpLoginUrl;
    },
    //cek apakah sedang login atau tidak
    isLogin() {
        return this.store.getters.isLogin ? true : false;
    },
    //get data user yang sedang login
    getUser(field = null) {
        if (this.store.state.auth.user != null) {
            if (field == null) return this.store.state.auth.user;
            return this.store.state.auth.user[field];
        }
        return null;

    },
    getToken() {
        return this.store.getters.getAuthToken;
    },
    getApiWebToken(){
        return this.store.getters.getApiWebToken;
    },
    //get active role
    getAuthRole() {
        return this.store.getters.getAuthRole;
    },
    //get active role_code
    getAuthRoleCode() {
        return this.store.state.auth.role_code;
    },
    //get list active role
    getAuthRoleList() {
        return this.store.getters.getAuthRoleList;
    },
    //get active role
    getAuthRoleCount() {
        return this.store.getters.roleCount;
    },
    //----------go to------
    goToLogin() {
        console.log('go to login : ', this.router.resolve({
            name: "login",
            params: { group_app: this.store.getters.getTenantGroupApp }
        }).href);
        this.router.push({
            name: "login",
            params: { group_app: this.store.getters.getTenantGroupApp }
        });
    },
    goToForgotpassword() {
        this.router.push({
            name: "forgotpassword",
            arams: { group_app: this.store.getters.getTenantGroupApp }
        });
    },
    goToRegister() {
        this.router.push({
            name: "register",
            params: { group_app: this.store.getters.getTenantGroupApp }
        });
    },
    goToMyProfile() {
        this.router.push({
            name: "myprofile",
            params: { group_app: this.store.getters.getTenantGroupApp }
        });
    },
    goToHome() {
        this.router.push({ name: "home", params: { group_app: this.store.getters.getTenantGroupApp } });
    }
};
