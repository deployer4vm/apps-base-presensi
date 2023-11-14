<template>
    <div id="app">
        <router-view :key="$route.fullPath"></router-view>

        <!-- <transition name="fade">
            <router-view :key="$route.fullPath"></router-view>
        </transition> -->

        <BlockUI :message="message" :html="html" v-if="loading" />

        <alert-modal />
        <notifications group="notifications-default" />
        <notifications group="notifications-top-left" position="top left" />
        <notifications group="notifications-top-center" position="top center" />
        <notifications group="notifications-bottom-right" position="bottom right" />
        <notifications group="notifications-bottom-left" position="bottom left" />
        <notifications group="notifications-bottom-center" position="bottom center" />
    </div>
</template>
<style src="@/vendor/libs/vue-notification/vue-notification.scss" lang="scss"></style>
<style src="@/vendor/libs/spinkit/spinkit.scss" lang="scss"></style>
<style>
    .loading-container .loading-backdrop {
        opacity: 0.3 !important;
    }
    .loading-container .loading {
        box-shadow: none !important;
        background-color: transparent !important;
    }
    .loading-container .loading-label {
        font-weight: bold;
        background: rgba(255,255,255,0.4);
        border-radius: 5px;
        padding: 0 10px;
    }

    .fade-enter-active, .fade-leave-active {
        transition: opacity .2s ease;
    }
    .fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
        opacity: 0.1;
    }
</style>
<script>
import BlockUI from 'node_modules/vue-blockui';
Vue.use(BlockUI);
export default {
    name: 'app',
    metaInfo() {
        return {
            title: 'Home',
            titleTemplate: '%s - ' + this.$store.state.template.admin.title
        }
    },
    data: () => ({
        html_old: `
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
        `,
        html: `
            <div class="sk-circle sk-primary">
                <div class="sk-circle1 sk-child"></div>
                <div class="sk-circle2 sk-child"></div>
                <div class="sk-circle3 sk-child"></div>
                <div class="sk-circle4 sk-child"></div>
                <div class="sk-circle5 sk-child"></div>
                <div class="sk-circle6 sk-child"></div>
                <div class="sk-circle7 sk-child"></div>
                <div class="sk-circle8 sk-child"></div>
                <div class="sk-circle9 sk-child"></div>
                <div class="sk-circle10 sk-child"></div>
                <div class="sk-circle11 sk-child"></div>
                <div class="sk-circle12 sk-child"></div>
            </div>
        `
    }),
    computed: {
        loading() {
            return this.$store.state.template.showLoading;
        },
        message() {
            return this.$store.state.template.messageLoading;
        }
    },
    updated () {
        // Remove loading state
        setTimeout(() => document.body.classList.remove('app-loading'), 1);
    },
    created() {
        // this.Web.setAdminTitle(this.$store.state.template.admin.title);
    },
    methods: {
        // appendCSS(){
        //     let file = document.createElement('link');
        //     file.lang = 'scss';
        //     file.href = 'myfile.css';
        //     document.head.appendChild(file);
        // }
    }
}
</script>
