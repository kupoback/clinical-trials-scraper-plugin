import "bootstrap/js/dist/tab";
import axios from "axios";
import Vue from "vue";
import {store} from "./vue/Vuex/store.js";
import VueSimpleAlert from "vue-simple-alert";

window.Vue = Vue;

Vue.use(VueSimpleAlert);

/**
 * Init Vue
 * @type {Vue}
 */
const VueInstance = new Vue({
    store,
    created() {
    }
});

document.addEventListener("DOMContentLoaded", (event) => {
    const apiImportElm = document.getElementById('merck-scraper-api');
    const apiLogElm = document.getElementById('merck-scraper-log');
    const geolocationElm = document.getElementById('merck-geolocation');
    
    /**
     * Mount the API Import Component
     */
    if (apiImportElm) {
        const apiImportComponent = Vue.component("ScraperApi", require('./vue/Components/Pages/ApiImport.vue').default);
        const apiImportVueElm = new Vue({
            el: "#merck-scraper-api",
            store,
            render: h => h(apiImportComponent)
        });
    }
    
    /**
     * Mount the API Logger Component
     */
    if (apiLogElm) {
        const apiLogComponent = Vue.component("ScraperLog", require('./vue/Components/Pages/ApiLog.vue').default);
        const apiLogVueElm = new Vue({
            el: "#merck-scraper-log",
            store,
            render: h => h(apiLogComponent)
        });
    }
    
    if (geolocationElm) {
        const geolocationComponent = Vue.component("LocationGeoFetch", require('./vue/Components/Pages/LocationGeoFetch.vue').default);
        const geolocationVueElm = new Vue({
            el: "#merck-geolocation",
            store,
            render: h => h(geolocationComponent)
        });
    }
    
});
