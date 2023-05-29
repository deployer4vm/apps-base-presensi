<template>
    <sidenav :accordion="false" :orientation="orientation" :class="curClasses">
        <!-- Brand saat mode desktop (di sidebar) atau saat menu tampil di mobile -->
        <div class="app-brand demo sidenav-app-brand" v-if="orientation !== 'horizontal'">
            <!-- <span class="app-brand-logo demo bg-primary">
                <svg viewBox="0 0 148 80" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><defs><linearGradient id="a" x1="46.49" x2="62.46" y1="53.39" y2="48.2" gradientUnits="userSpaceOnUse"><stop stop-opacity=".25" offset="0"></stop><stop stop-opacity=".1" offset=".3"></stop><stop stop-opacity="0" offset=".9"></stop></linearGradient><linearGradient id="e" x1="76.9" x2="92.64" y1="26.38" y2="31.49" xlink:href="#a"></linearGradient><linearGradient id="d" x1="107.12" x2="122.74" y1="53.41" y2="48.33" xlink:href="#a"></linearGradient></defs><path style="fill: #fff;" transform="translate(-.1)" d="M121.36,0,104.42,45.08,88.71,3.28A5.09,5.09,0,0,0,83.93,0H64.27A5.09,5.09,0,0,0,59.5,3.28L43.79,45.08,26.85,0H.1L29.43,76.74A5.09,5.09,0,0,0,34.19,80H53.39a5.09,5.09,0,0,0,4.77-3.26L74.1,35l16,41.74A5.09,5.09,0,0,0,94.82,80h18.95a5.09,5.09,0,0,0,4.76-3.24L148.1,0Z"></path><path transform="translate(-.1)" d="M52.19,22.73l-8.4,22.35L56.51,78.94a5,5,0,0,0,1.64-2.19l7.34-19.2Z" fill="url(#a)"></path><path transform="translate(-.1)" d="M95.73,22l-7-18.69a5,5,0,0,0-1.64-2.21L74.1,35l8.33,21.79Z" fill="url(#e)"></path><path transform="translate(-.1)" d="M112.73,23l-8.31,22.12,12.66,33.7a5,5,0,0,0,1.45-2l7.3-18.93Z" fill="url(#d)"></path></svg>
            </span>-->
            
            <span class="app-brand-logo demo bg-transparent layout-sidenav-toggle disidenav">
                <img style="max-height: 30px; max-widht: 60px;" :src="logoPath" />
            </span>

            <!-- burger menu saat sidebar menutup -->
            <a href="javascript:void(0)" class="sidenav-button-onhover sidenav-link" @click="toggleSidenav()">
                <i class="ion ion-md-menu align-middle"></i>
            </a>

            <router-link
                :to="{name : 'home'}"
                class="app-brand-text demo sidenav-text font-weight-normal ml-2"
            >
                {{ title }}
            </router-link>

            <!-- burger menu saat sidebar membuka -->
            <a
                href="javascript:void(0)"
                class="layout-sidenav-toggle sidenav-link text-large ml-auto"
                @click="toggleSidenav()"
            >
                <i class="ion ion-md-menu align-middle"></i>
            </a>

        </div>

        <!-- Inner -->
        <div class="sidenav-inner" :class="{ 'py-1': orientation !== 'horizontal' }">
            <!-- <div class="sidenav-divider mt-0" v-if="orientation !== 'horizontal'"></div> -->

            <!--looping level 1-->      
            <template v-for="(menus, packageNamespace) in sidebarMenu">

                <template v-if="menus.has_acl == 0 || (menus.has_access==1 && isInGroup(menus.tenant_group_id))">

                    <sidenav-router-link
                        v-if="menus.route"
                        v-bind:key="menus.id"
                        :icon="menus.icon"
                        :class="menus.class?menus.class:''"
                        :to="menus.route"
                        :exact="true"
                        :active="isMenuActive(Web.getModuleEndpoint(packageNamespace))"
                    >
                        {{ Trans.chose(menus.caption) }}
                    </sidenav-router-link>
                    <sidenav-menu
                        v-else 
                        :icon="menus.icon"
                        :class="menus.class?menus.class:''"
                        v-bind:key="menus.id"
                        :active="isMenuActive(Web.getModuleEndpoint(packageNamespace))"
                        :open="isMenuOpen(Web.getModuleEndpoint(packageNamespace))"
                    >

                        <template slot="link-text">{{ Trans.chose(menus.caption) }}</template>

                        <!-- looping level 2 -->
                        <template v-for="(menu, aclIdLv1) in menus.children">

                        <template v-if="menu.enable && (menus.has_acl == 0 || (menu.is_navbar && menu.active_acl.has_access==1 && isInGroup(menu.tenant_group_id)))">

                            <template v-if="menu.children == undefined">

                                <sidenav-router-link
                                    :to="menu.route"
                                    :class="menu.class?menu.class:''"
                                    :active="isMenuActive(menu.route)"
                                    v-bind:key="aclIdLv1"
                                    :exact="true"
                                >
                                    <i :class="'sidenav-icon ' + menu.icon" v-if="menu.icon"></i> 
                                    {{ Trans.chose(menu.caption) }}
                                </sidenav-router-link>

                            </template>
                            <template v-else>

                            <!-- looping level 3 -->
                            <sidenav-menu
                                v-bind:key="aclIdLv1"
                                :class="menu.class?menu.class:''"
                                :active="isMenuActive(menu.route)"
                                :open="isMenuOpen(menu.route)"
                            >
                                <template slot="link-text">
                                    <i :class="'sidenav-icon ' + menu.icon" v-if="menu.icon"></i> 
                                    {{ Trans.chose(menu.caption) }}
                                </template>

                                <template v-for="(submenu,aclIdLv2) in menu.children">

                                <template v-if="submenu.enable && (menus.has_acl == 0 || (submenu.is_navbar && submenu.active_acl.has_access==1 && isInGroup(submenu.tenant_group_id)))">

                                    <template v-if="submenu.children == undefined">

                                    <sidenav-router-link
                                        :to="submenu.route"
                                        :class="submenu.class?submenu.class:''"
                                        v-bind:key="aclIdLv2"
                                        :active="isMenuActive(submenu.route)"
                                        :exact="true"
                                    ><i :class="'sidenav-icon ' + submenu.icon" v-if="submenu.icon"></i> {{ Trans.chose(submenu.caption) }}</sidenav-router-link>

                                    </template>
                                    <template v-else>

                                    <!-- looping level 4 -->
                                    <sidenav-menu
                                        v-bind:key="submenu.aclIdLv2"
                                        :class="submenu.class?submenu.class:''"
                                        :active="isMenuActive(submenu.route)"
                                        :open="isMenuOpen(submenu.route)"
                                    >

                                        <template slot="link-text"><i :class="'sidenav-icon ' + submenu.icon" v-if="submenu.icon"></i> {{ Trans.chose(submenu.caption) }}</template>

                                        <template v-for="(subsubmenu,aclIdLv3) in submenu.children">

                                        <template v-if="subsubmenu.enable && (menus.has_acl == 0 || (subsubmenu.is_navbar && subsubmenu.active_acl.has_access==1 && isInGroup(subsubmenu.tenant_group_id)))">

                                            <sidenav-router-link
                                            :to="subsubmenu.route"
                                            :class="subsubmenu.class?subsubmenu.class:''"
                                            v-bind:key="aclIdLv3"
                                            :exact="true"
                                            ><i :class="'sidenav-icon ' + subsubmenu.icon" v-if="subsubmenu.icon"></i> {{ Trans.chose(subsubmenu.caption) }}</sidenav-router-link>

                                        </template>

                                        </template>

                                    </sidenav-menu>
                                    <!-- end - looping level 4 -->
                                    </template>
                                </template>

                                </template>

                            </sidenav-menu>
                            <!-- end - looping level 3 -->
                            </template>

                        </template>

                        </template>
                        <!-- end - looping level 2 -->
                    </sidenav-menu>

                </template>

            </template>      
            <!--end - looping level 1-->
        

        </div>

    </sidenav>
</template>

<script>
import {
    Sidenav,
    SidenavLink,
    SidenavRouterLink,
    SidenavMenu,
    SidenavHeader,
    SidenavBlock,
    SidenavDivider
} from "@/vendor/libs/sidenav";

export default {
    name: "app-layout-sidenav",
    components: {
        /* eslint-disable vue/no-unused-components */
        Sidenav,
        SidenavLink,
        SidenavRouterLink,
        SidenavMenu,
        SidenavHeader,
        SidenavBlock,
        SidenavDivider
        /* eslint-enable vue/no-unused-components */
    },

    props: {
        orientation: {
            type: String,
            default: "vertical"
        }
    },
    created() {
        this.isCollapsed = this.layoutHelpers.isCollapsed();
    },
    mounted() {

    },
    data() {
        return {
            isCollapsed: false
        };
    },
    computed: {
        tenantGroup() {
            return this.$store.getters.getTenantGroup;
        },
        title() {
            return this.$store.getters.getAdminTitle;
        },
        sidebarMenu() {
            return this.$store.getters.getSidenavMenu;
        },
        curClasses() {
            let bg = this.layoutSidenavBg;

            if (
                this.orientation === "horizontal" &&
                (bg.indexOf(" sidenav-dark") !== -1 ||
                    bg.indexOf(" sidenav-light") !== -1)
            ) {
                bg = bg
                    .replace(" sidenav-dark", "")
                    .replace(" sidenav-light", "")
                    .replace("-darker", "")
                    .replace("-dark", "");
            }

            return (
                `bg-${bg} ` +
                (this.orientation !== "horizontal"
                    ? "layout-sidenav"
                    : "layout-sidenav-horizontal container-p-x flex-grow-0")
            );
        },
        logoPath() {
            return this.publicUrl + (this.AppConfig.system.template.logo?this.AppConfig.system.template.logo:'assets/images/logo.png');
        }
    },
    methods: {
        /**
         * cek apakah curTenantGroup (tenant_group_id dari menu) menampilkan menu atau tidak
         * param :
         *      curTenantGroup : array berisi list id group tenant menu yg dicek (dari tenant_group_id di item access nya)
         * 
         * return 
         */
        isInGroup(curTenantGroup) {
            // jika multi tenant aktif
            if(this.AppConfig.system.multitenant.active){
                // jika tenant_group_id menu yg dicek berbentuk array, maka detek bandingkan dengan activeGroup nya
                if(curTenantGroup.length && curTenantGroup.length > 0){
                    var arr = this.tenantGroup;// list id tenant group tenant aktif                    
                    //jika tidak ada group berarti sedang di tenant manager
                    if (arr == null || arr.length == undefined)                        
                        arr = [0];                    
                    return curTenantGroup.some(r => arr.indexOf(r) >= 0);
                }else{
                    return curTenantGroup == 0 || (curTenantGroup == 1 && !isOnTenantManager) || (curTenantGroup == 2 && isOnTenantManager);
                }          
            }else{
                return curTenantGroup == 0 || curTenantGroup == 1;
            }            
        },
        isMenuActive(route,viewLog=false) {
            let routePath = "";
            if (typeof route == "string") {
                routePath = route;
            } else {
                let routePathObj = this.$router.resolve(route);
                routePath = routePathObj.route.path;
            }
            if(viewLog)
                console.log(routePath,this.Web.curEndpoint,this.$router.currentRoute.path,this.Web.isOnEndpoint(routePath));
                
            return routePath == "/" && this.Web.curEndpoint != "/"
                ? false
                : routePath==this.Web.curEndpoint || this.Web.isOnEndpoint(routePath);
        },
        isMenuOpen(route,viewLog=false) {
            return (
                this.isMenuActive(route,viewLog) && this.orientation !== "horizontal"
            );
        },
        toggleSidenav() {            
            this.layoutHelpers.toggleCollapsed();
            this.isCollapsed = this.layoutHelpers.isCollapsed();
        },
        // isHover() {
        //     console.log($('html.layout-sidenav-hover').length());
        // }
    }
};
</script>
