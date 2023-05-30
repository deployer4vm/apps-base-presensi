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
            
            <div :key="i" class="my-4">

                <h4 v-if="item.title">{{ item.title }}</h4>

                <div class="row my-4">
                    <div class="col-md-3" v-for="(data, j) in item.list" :key="j">
                        <router-link
                            :to="{name: data.route.name}" v-if="data.route"
                            class="card card-hover text-body my-2">
                            <b-card-body class="text-center py-5">
                                <span :class="data.icon + ' display-3 text-primary'" />
                                <b-badge class="ml-3" variant="danger indicator" v-if="data.indicator">{{ data.indicator }}</b-badge>
                                <h5 class="m-0 mt-3">{{ data.title }}</h5>
                                <small>{{ data.description }}</small>
                            </b-card-body>
                        </router-link>
                        <div
                            v-else
                            @click="topWindowHref(data.url)"
                            style="cursor: pointer;"
                            class="card card-hover text-body my-2">
                            <b-card-body class="text-center py-5">
                                <span :class="data.icon + ' display-3 text-primary'" />
                                <b-badge class="ml-3" variant="danger indicator" v-if="data.indicator">{{ data.indicator }}</b-badge>
                                <h5 class="m-0 mt-3">{{ data.title }}</h5>
                                <small>{{ data.description }}</small>
                            </b-card-body>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
    export default {
        name: "home-menu",
        props: ["menu"]
    }
</script>