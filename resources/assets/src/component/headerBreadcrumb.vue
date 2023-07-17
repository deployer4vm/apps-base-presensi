<template>
    <div class="d-flex justify-content-between align-items-center w-100 mb-0 border-bottom flex-wrap">
        <div class="d-flex align-items-center">
            <b-btn
                v-if="showBack"
                class="p-2 rounded-0 btn btn-secondary d-inline-block borderless text-nowrap"
                @click="goBack"
            >
                <span class="ion ion-ios-arrow-back"></span>&nbsp; {{ Trans.get("lang.back") }}
            </b-btn>
            <h5 class="py-2 pr-4 m-0 d-inline-block text-nowrap font-weight-normal">
                {{ pageTitle }}
            </h5>
        </div>
        <div class="py-2 pl-4">
            <b-breadcrumb @click="breadcrumbLink" class="m-0" :items="Web.getBreadcrumb()" />
        </div>
    </div>
</template>
<script>
export default {
    name: "header-breadcrumb",
    props:{
        pageTitle: {
            default() {
                return '';
            }
        },
        showBack: {
            default() {
                return true;
            }
        },
        // bisa diiisi string path atau object router
        backPath: {
            default() {
                return '/';
            }
        },
        // -- khusus embed iframe
        // true jika link2 di header di alihkan ke top window nya (halaman utama tempat aplikasi diload di iframe)
        backToTopWindow: {
            default() {
                return false;
            }
        },
        breadcrumbToTopWindow: {
            default() {
                return false;
            }
        },
    },
    methods:{
        goBack() {
            if(this.backPath){
                if(this.backToTopWindow && typeof this.topWindowHref == 'function'){
                    this.topWindowHref(this.backPath);
                }else{
                    this.$router.push(this.backPath);
                }
            }else{
                this.$router.go(-1);
            }
        },
        breadcrumbLink(ev) {
            if(this.breadcrumbToTopWindow){
                this.linkTopWindowHref(ev);
            }
        }
    }
};
</script>
