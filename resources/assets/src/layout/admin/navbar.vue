<template>
    <b-navbar toggleable="lg" :variant="getLayoutNavbarBg()" class="layout-navbar navbar-expand align-items-lg-center container-p-x" >
        <!-- Sidenav toggle -->
        <b-navbar-nav class="layout-sidenav-toggle d-lg-none align-items-lg-center" v-if="sidenavToggle">
            <a class="nav-item nav-link px-0 mr-lg-4" href="javascript:void(0)" @click="toggleSidenav">
                <i class="fi fi-rs-burger-menu"></i>
            </a>
        </b-navbar-nav>
        <!-- Brand saat mode mobile (tidak ada sidebar) -->
        <b-navbar-brand :to="{name : 'home'}" class="app-brand demo py-0 mr-4">
            <span class="app-brand-logo demo square py-2">
                <img v-if="koperasi && koperasi.logo.length > 0" :src="`${uploadedUrl}${koperasi.logo[0].   filepath}`" alt="">
                <img v-else :src="`${publicUrl}assets/images/koperasi_logo.png`" alt="">
            </span>
            <div class="app-brand-text d-none d-md-block  nav-item text-big font-weight-light line-height-1 opacity-50 mr-2 ml-3">|</div>
            <h5 class="app-brand-text d-none d-md-block  demo ml-2 mb-0">{{ title }}</h5>
        </b-navbar-brand>

        <!-- Navbar toggle -->
        <b-navbar-toggle target="app-layout-navbar"></b-navbar-toggle>

        <b-navbar-nav class="align-items-center ml-auto" style="min-height: 42px;">
            <template  v-if="UserAuth.isActive()">
                <template v-if="showNotif">
                    <notif-navbar />
                    <div class="nav-item d-none d-lg-block text-big font-weight-light line-height-1 opacity-25 mr-2 ml-1">|</div>
                </template>

                <template v-if="AppConfig.system.multilang==1">
                    <b-nav-item-dropdown :right="!isRTL">
                        <template slot="button-content">
                            <span class="d-inline-flex flex-lg-row-reverse align-items-center align-middle">
                                <div class="avatar-header-block d-block rounded-circle text-center">
                                    <i class="ion ion-ios-globe"></i>
                                </div>
                                <span class="px-1 mr-lg-2 ml-2 ml-lg-0">{{ Trans.getLocale().toUpperCase() }}</span>
                            </span>
                        </template>
                        <template v-for="(lang,langId) in AppConfig.system.locale">
                            <b-dd-item v-if="Trans.getLocale()==langId" :key="'lang-item-' + langId">
                                <i class="ion ion-md-radio-button-on text-danger"></i> &nbsp; <span class="text-danger"> {{lang}}</span>
                            </b-dd-item>
                            <b-dd-item v-else @click="Trans.reLoadLang(langId)" :key="'lang-item-' + langId">
                                <i class="ion ion-md-radio-button-off text-muted"></i> &nbsp; {{lang}}
                            </b-dd-item>
                        </template>
                    </b-nav-item-dropdown>
                    <div class="nav-item d-none d-lg-block text-big font-weight-light line-height-1 opacity-25 mr-3 ml-1">|</div>
                </template>

                <b-nav-item-dropdown :right="!isRTL" class="demo-navbar-user d-flex align-items-center">
                    <template slot="button-content">
                        <!-- <span class="d-inline-flex flex-lg-row-reverse align-items-center align-middle">
                            <div class="avatar-header-block d-block rounded-circle text-center">
                                <i class="ion ion-ios-person"></i>
                            </div>
                            <span class="px-1 mr-lg-2 ml-2 ml-lg-0">{{ UserAuth.getUser('name') }}</span>
                        </span> -->
                        <span class="d-inline-flex flex-lg-row-reverse align-items-center align-middle">
                            <div class="d-block ui-w-30 rounded-circle overflow-hidden box-avatar">
                                <div class="thumb-img">
                                    <img :src="`${publicUrl}assets/images/avatar.png`">
                                </div>
                            </div>
                            <div class="px-1 mr-lg-2 ml-2 ml-lg-0 d-none d-lg-block text-right line-height-1">
                                <small class="text-muted mb-0">{{ UserAuth.getUser('name') }}</small>
                                <span class="font-weight-bold d-block">{{ UserAuth.getUser('name') }}</span>
                            </div>
                        </span>
                    </template>
                    <template v-if="AppConfig.isModuleEnable('moduser')">
                        <b-dd-item :to="(typeof AppConfig.packageLocal.moduser.user_profiles.custom_link.name != 'undefined') ? AppConfig.packageLocal.moduser.user_profiles.custom_link : {name: 'myprofile'}">
                            <i class="fi fi-rr-man-head"></i>
                            &nbsp; {{ Trans.get('user.my_profile') }}
                        </b-dd-item>

                        <!-- list role -->
                        <template v-if="AppConfig.packageLocal.moduser.user_role.multi_role==1 && UserAuth.getAuthRoleCount()>1">
                            <b-dd-divider />
                            <template v-for="role in UserAuth.getAuthRoleList()">
                                <b-dd-item v-if="role.role_type!=2" @click="changeRole(role.role_code)" :key="'header-chose-role-' + role.id">
                                    <!-- hanya tampilkan role tipe login -->
                                    <i :class="{ion:true, 'ion-md-radio-button-on': ActiveRoleCode==role.role_code, 'ion-md-radio-button-off': ActiveRoleCode!=role.role_code, 'text-success':true}"></i> &nbsp; {{role.name}}
                                </b-dd-item>
                            </template>
                            <b-dd-divider />
                        </template>

                        <b-dd-item v-if="showNotif" :to="{name: 'notification'}">
                            <i class="fi fi-rr-bell"></i>
                            &nbsp; {{ Trans.get('notif.notification_title') }}
                        </b-dd-item>

                        <b-dd-divider />

                        <b-dd-item v-if="AppConfig.isModuleEnable('moduser')" @click="UserAuth.logout()">
                            <i class="fi fi-rr-sign-out-alt text-danger"></i>
                            &nbsp; {{ Trans.get('auth.logout') }}
                        </b-dd-item>
                    </template>

                    <template v-if="AppConfig.system.mode=='dev'">
                        <b-dd-divider />
                        <div class="text-center text-muted">
                            <small>Dev Mode Only Action</small>
                        </div>
                        <b-dd-item @click="Trans.reLoadLang()">
                            <i class="ion ion-md-sync text-lightest"></i> &nbsp; Reload Language
                        </b-dd-item>
                    </template>
                </b-nav-item-dropdown>
            </template>
        </b-navbar-nav>
    </b-navbar>
</template>

<script>
export default {
    data(){
        return {
            title: '',
            koperasi: {},
            ActiveRoleCode: ''
        };
    },
    computed: {
        brandTitle() {
            return this.Web.getAdminTitle();
        },
        navbarTitle() {
            return this.Web.getNavbarTitle()?this.Web.getNavbarTitle():this.Web.getTenantName();
        },
        showNotif() {
            return this.AppConfig.isModuleEnable('moduser') && this.AppConfig.packageLocal.moduser.notification.enable==1 && this.AppConfig.packageLocal.moduser.notification.show==1;
        },
    },
    name: "app-layout-navbar",
    props: {
        sidenavToggle: {
            type: Boolean,
            default: true
        }
    },
    created(){
        if(this.UserAuth.isActive())
            this.ActiveRoleCode = this.UserAuth.getAuthRole().role_code;

        this.koperasi = this.Web.getTenant.instance_data
        this.title = this.koperasi ? this.koperasi.nama : this.Web.getAdminTitle()
    },
    methods: {
        changeRole(roleCode) {
            var that = this;
            if(this.ActiveRoleCode!=roleCode){
                this.UserAuth.changeRole(roleCode).then(res=>{
                    that.Web.showAlert({type: 'info', text: 'Role changed to <b>' + this.UserAuth.getAuthRole().name + '</b>'});
                    that.ActiveRoleCode = roleCode;
                });
            }
        },
        toggleSidenav() {
            this.layoutHelpers.toggleCollapsed();
        },
        getLayoutNavbarBg() {
            return this.layoutNavbarBg;
        }
    }
};
</script>
