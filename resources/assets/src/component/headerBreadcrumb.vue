<template>
    <div class="d-flex justify-content-between align-items-center w-100 mb-0 flex-wrap border-bottom">
        <div class="d-flex align-items-center py-2">
            <b-btn
                v-if="showBack"
                class="btn btn-xs w-icon btn-outline-dark mr-2"
                @click="goBack"
            >
            <i class="fi fi-rs-caret-left"></i>
                <span>{{ Trans.get("lang.back") }}</span>
            </b-btn>
            <h5 class="my-2 pr-1 m-0 d-inline-block text-nowrap title-breadcrumb">
                {{ pageTitle }}
            </h5>
        </div>
        <b-breadcrumb @click="breadcrumbLink" class="m-0 d-none d-md-flex" :items="Web.getBreadcrumb()" />
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
