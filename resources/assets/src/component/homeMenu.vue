<template>
    <div>
        <template v-for="(item, i) in menu">
            <!-- <div :key="i" class="my-4">

                <h5 class="text-nowrap font-weight-normal" v-if="item.title">{{ item.title }}</h5>

                <div class="row">
                    <div class="col-md-4 mb-4" v-for="(data, j) in item.list" :key="j">
                        <div class="d-flex card align-items-left">
                            <div class="card-body media align-items-left text-body">
                                <i :class="data.icon + ' display-4 d-block text-primary'"></i>
                                <span class="media-body d-block m-1 ml-3">
                                    <span class="text-big" v-html="data.title"></span><br>
                                    <small class="text-muted" v-if="data.description" v-html="data.description"></small>
                                </span>
                                <router-link
                                    :to="{name: data.route.name}" v-if="data.route.name"
                                    class="btn btn-sm btn-info"
                                >
                                    Go <span class="ion ion-ios-arrow-forward" />
                                </router-link>
                                <b-btn
                                    v-else
                                    @click="topWindowHref(data.url)"
                                    size="sm"
                                    variant="info"
                                >
                                    Go <span class="ion ion-ios-arrow-forward" />
                                </b-btn>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->

            <b-card no-body :key="i" class="my-3">
                <b-card-header>
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h5 class="my-2" v-if="item.title">{{ item.title }}</h5>
                    </div>
                </b-card-header>

                <b-card-body class="px-3 pt-3 pb-0">
                    <b-container fluid>
                        <div class="row">
                            <div class="col-md-6 col-lg-4 col-xl-3" v-for="(data, j) in item.list" :key="j">
                                <router-link
                                    :to="{name: data.route.name}" v-if="data.route"
                                    class="card bg-hover-gradient card-bordered shadow-none gr-purple mb-3 h-100">
                                    <b-card-body class="p-4">
                                        <div class="d-flex flex-column align-items-start">
                                            <div class="position-relative">
                                                <i :class="data.icon + ' text-gd-purple'" style="font-size: 2rem;" /></i>
                                                <b-badge variant="danger indicator" v-if="data.indicator">{{ data.indicator }}</b-badge>
                                            </div>
                                            <div class="text-right card-title w-100 mb-0">
                                                <h5 class="m-0 mt-3">{{ data.title }}</h5>
                                                <small class="text-muted">{{ data.description }}</small>
                                            </div>
                                        </div>
                                    </b-card-body>
                                </router-link>
                                <div
                                    v-else
                                    @click="topWindowHref(data.url)"
                                    style="cursor: pointer;"
                                    class="card bg-hover-gradient card-bordered shadow-none gr-purple mb-3 h-100">
                                    <b-card-body class="p-4">
                                        <div class="d-flex flex-column align-items-start">
                                            <i :class="data.icon + ' text-gd-purple'" style="font-size: 2rem;" /></i>
                                            <b-badge variant="danger indicator" v-if="data.indicator">{{ data.indicator }}</b-badge>
                                            <div class="text-right card-title w-100 mb-0">
                                                <h5 class="m-0 mt-3">{{ data.title }}</h5>
                                                <small class="text-muted">{{ data.description }}</small>
                                            </div>
                                        </div>
                                    </b-card-body>
                                </div>
                            </div>
                        </div>
                    </b-container>
                </b-card-body>
            </b-card>
        </template>
    </div>
</template>

<script>
    export default {
        name: "home-menu",
        props: ["menu"]
    }
</script>
