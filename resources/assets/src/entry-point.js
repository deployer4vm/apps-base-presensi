window._ = {};
window._.merge = require("lodash/merge");
window._.forEach = require("lodash/forEach");
window.moment = require('moment');
window.$ = require('jquery');

// Polyfills maintained by core-js 3.
require("core-js/stable");

import Vue from "vue";
import axios from "axios";

//register vue on window level
window.Vue = Vue;

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

/**
 * Next we will register the CSRF Token as a common header with Axios so that
 * all outgoing HTTP requests automatically have it attached. This is just
 * a simple convenience so we don't have to attach every token manually.
 */

let token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    window.axios.defaults.headers.common["X-CSRF-TOKEN"] = token.content;
} else {
    console.error(
        "CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token"
    );
}

/**
 * Load Vue.js app
 */

require("./main.js");
