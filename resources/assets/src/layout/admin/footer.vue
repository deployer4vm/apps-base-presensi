<template>
    <nav class="layout-footer footer" :class="getLayoutFooterBg()">
        <div
            class="container-fluid d-flex flex-wrap justify-content-between text-center pb-lg-0 pb-3"
        >
            <div>
                <!-- <span class="footer-text font-weight-bolder">{{
                    footerText
                }}</span> -->
                <span class="footer-text font-weight-bold">
                    © Copyright 2023 <a href="https://smartcoop.id/" target="_blacnk" class="text-main">Smartcoop.id </a>
                </span>
            </div>
            <div>
                <template v-for="(menu, i) in footerMenu">
                    <template v-if="menu.route.length == undefined">
                        <a
                            :key="i"
                            :href="menu.route"
                            :class="'footer-link pt-3 ml-4' + menu.class"
                            v-if="menu.show"
                            >{{ menu.caption }}</a
                        >
                    </template>
                    <template v-else>
                        <router-link
                            tag="a"
                            :to="menu.route"
                            :class="'footer-link pt-3 ml-4' + menu.class"
                            v-bind:key="menu.caption"
                            v-if="menu.show"
                            >{{ menu.caption }}</router-link
                        >
                    </template>
                </template>
            </div>
        </div>
    </nav>
</template>

<script>
export default {
    name: "app-layout-footer",

    computed: {
        footerText() {
            return this.$store.getters.getFooterText;
        },
        footerMenu() {
            return this.$store.getters.getFooterMenu;
        }
    },
    methods: {
        getLayoutFooterBg() {
            return `bg-${this.layoutFooterBg}`;
        }
    }
};
</script>
