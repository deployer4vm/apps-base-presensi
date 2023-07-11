<template>
    <div :class="{'layout-wrapper':true,'layout-2':!isSidenavHorizontal,'layout-without-sidenav':isSidenavHorizontal,'layout-1':isSidenavHorizontal}">
        <div class="layout-inner" v-if="isMainAppReady && showComponent">
            <app-layout-sidenav v-if="showSidenav && !isSidenavHorizontal" />
            <app-layout-navbar :sidenavToggle="false"  v-if="showNavbar && isSidenavHorizontal" />

            <div :class="{'layout-container':true,'no-sidenav':!showSidenav,'no-navbar':!showNavbar}">
                <app-layout-navbar v-if="showNavbar && !isSidenavHorizontal" />

                <div class="layout-content">
                    <app-layout-sidenav orientation="horizontal" v-if="showSidenav && isSidenavHorizontal" />

                    <div :class="{
                            // 'router-transitions': true,
                            // 'container-fluid': true,
                            // 'flex-grow-1': true,
                            // 'container-p-y': bodyWithPadding,
                            // 'p-0': !bodyWithPadding,
                            // 'pt-0': !bodyWithPadding,
                            // 'pb-0': !bodyWithPadding

                            'router-transitions': true,
                            'container-fluid': true,
                            'flex-grow-1': true,
                            'container-p-y': true,
                            'p-0': true,
                            // 'pt-0': bodyWithPadding,
                            // 'pb-0': bodyWithPadding
                        }"
                    >
                        <router-view />
                    </div>
                    <app-layout-footer v-if="showFooter" />
                </div>
            </div>
        </div>
        <div class="layout-inner" v-else>
            <div
                class="text-mutted h- row align-items-center"
                style="width: 100%;"
            >
                <div class="col">
                    <div class="sk-cube-grid sk-primary">
                        <div class="sk-cube sk-cube1"></div>
                        <div class="sk-cube sk-cube2"></div>
                        <div class="sk-cube sk-cube3"></div>
                        <div class="sk-cube sk-cube4"></div>
                        <div class="sk-cube sk-cube5"></div>
                        <div class="sk-cube sk-cube6"></div>
                        <div class="sk-cube sk-cube7"></div>
                        <div class="sk-cube sk-cube8"></div>
                        <div class="sk-cube sk-cube9"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="layout-overlay" @click="closeSidenav"></div>
    </div>
</template>

<style>
    .avatar-header-block {
        height: 22px;
        width: 22px;
    }
    .avatar-header-block i.ion {
        padding-top: 5px;
    }
    .sidenav-app-brand {
        height: 58px;
    }
    .default-style .sidenav .app-brand.sidenav-app-brand {
        height: 58px;
    }
    .sidenav-button-onhover {
        font-size: 180%;
        padding: 0 25px;
        display: none;
    }
    .layout-sidenav-hover .sidenav-button-onhover,
    .layout-expanded .sidenav-button-onhover {
        display: none !important;
    }
    .layout-collapsed .sidenav-button-onhover {
        display: inline;
    }

    .layout-container.no-sidenav {
        padding-left:0 !important;
    }

    .layout-container.no-navbar {
        padding-top:0 !important;
    }

    /* *****************************************************************************
    * Navbar
    */

    .demo-navbar-messages .dropdown-toggle,
    .demo-navbar-notifications .dropdown-toggle,
    .demo-navbar-user .dropdown-toggle,
    .demo-navbar-messages.b-nav-dropdown .nav-link,
    .demo-navbar-notifications.b-nav-dropdown .nav-link,
    .demo-navbar-user.b-nav-dropdown .nav-link {
        white-space: nowrap;
    }

    .demo-navbar-messages .dropdown-menu,
    .demo-navbar-notifications .dropdown-menu {
        overflow: hidden;
        padding: 0;
    }

    @media (min-width: 992px) {
        .demo-navbar-messages .dropdown-menu,
        .demo-navbar-notifications .dropdown-menu {
            margin-top: 0.5rem;
            width: 22rem;
        }

        .demo-navbar-user .dropdown-menu {
            margin-top: 0.25rem;
        }
    }
</style>

<script>
import navbar from "./navbar";
import sidenav from "./sidenav";
import footer from "./footer";

export default {
    name: "app-admin-1",
    components: {
        "app-layout-navbar": navbar,
        "app-layout-sidenav": sidenav,
        "app-layout-footer": footer
    },
    mounted() {
        this.layoutHelpers.init();
        this.layoutHelpers.update();
        // this.layoutHelpers._bindSidenavMouseEvents();
        this.layoutHelpers.setAutoUpdate(true);
    },
    beforeDestroy() {
        this.layoutHelpers.destroy();
    },
    computed: {
        bodyWithPadding() {
            var tmp = this.$store.getters.isBodyWithPadding;
            return tmp;
        },
        isSidenavHorizontal() {
            // var tmp = this.AppConfig.system.web_admin.sidenav_horizontal == 1 ? true : false;
            var tmp = this.$store.getters.isSidenavHorizontal;
            return tmp;
        },
        //----------------
        isMainAppReady() {
            var tmp = this.$store.getters.isAppReady;
            return tmp;
        },
        showNavbar() {
            var tmp = this.$store.getters.isNavbarShowed;
            return tmp;
        },
        showSidenav() {
            var tmp = this.$store.getters.isSidenavShowed;
            return tmp;
        },
        showFooter() {
            var tmp = this.$store.getters.isFooterShowed;
            return tmp;
        },
        showComponent() {
            return this.AppConfig.system.has_acl == 0 || this.UserAuth.isLogin()
                ? true
                : false;
        }
    },
    methods: {
        closeSidenav() {
            console.log('icloseSidenav clicked');
            this.layoutHelpers.setCollapsed(true);
        }
    }
};
</script>
