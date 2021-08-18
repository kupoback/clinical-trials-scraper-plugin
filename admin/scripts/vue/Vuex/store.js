import Vue from "vue";
import Vuex from "vuex";
import axios from "axios";

Vue.use(Vuex);

export const store = new Vuex.Store({
    state: {
    
    },
    mutations: {
        // SET_MOBILE_NAV_STATUS(currentState, {menuOpen}) {
        //     currentState.menuOpen = menuOpen;
        // }
    },
    actions: {
        getNavigation(store, opts) {
            let api = NAV.api;
            let config;
            
            config = {
                params: {
                    primaryNav: opts.nav,
                }
            };
            
            axios.get(api, config)
                 .then(({data, status}) => {
                     if (status === 200 && data.status !== 404) {
                         store.commit("BUILD_NAVIGATION", {
                             data: data || [],
                         });
                     }
                 })
                 .catch(err => console.error(err));
            return;
        },
    },
    getters: {},
});
