
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */


import Vue from 'vue'
import VueRouter from 'vue-router'
import routes from './routes.js'
import AppView from './App.vue'
import store from './store'
import axios from 'axios'
import VueAxios from 'vue-axios'
import VeeValidate from 'vee-validate'
import {EventBus} from './configs/EventBus'
import VueFlashMessage from 'vue-flash-message';
require('vue-flash-message/dist/vue-flash-message.min.css');
Vue.use(VeeValidate,{fieldsBagName:'fieldsBag'})
Vue.use(VueAxios, axios)
Vue.use(VueRouter)
Vue.use(VueFlashMessage);

window.moment = require('moment')
window._ = require('underscore')


var router = new VueRouter({
    routes: routes,
    mode :"history"
})

axios.defaults.baseURL = window.config.api_url

let login_name = 'login'
let login_path = '/login'

if(window.config.enable_token)
{
    login_name = 'token'
    login_path = '/token'
}
 
router.beforeEach((to, from, next) => {
    if ((to.name != 'login' && to.name != 'about') && (!store.state.user))
    {
        if(to.name == 'token' && window.config.enable_token)
        {
            next()
        }
        else
        {
            next({
                path: '/login'
            })
        }
    }
    else
    {
        console.log(window.config.enable_token,store.state.token_verified)
        if(to.name == 'login' && window.config.enable_token && !store.state.token_verified)
        {   
            return next({name:'token'})
        }
        if(to.name== 'login' && store.state.user)
            next({path:'/'})
        else
        {
            if(to.meta.perm)
            {
                if(store.state.abilities.length)
                {
                    if(store.state.abilities[to.meta.perm])
                    {
                        next()
                    }
                    else
                    {
                        next({path:'403'})
                    }
                }
                else
                    next()
            }
            else
                next()
        }
    }
})
axios.interceptors.request.use(function (config) {
  let token = null;
  if(store.state.token)
    token = store.state.token
  if (token != null) {

    config.headers.Authorization = "Bearer " +token
    config.headers.RequestSource = 'ROS'//#719 Auditing
  }

  return config
}, function (err) {
  return Promise.reject(err)
})

axios.interceptors.response.use(function (response) {
    // Do something with response data
    if(response.data.error && response.data.error == 'token_expired')
    {
        store.commit('RESET');
        router.push({name:"login"})
        return;
    }
    return response;
  }, function (error) {
    if(error.response.status == 403)
    {
        //EventBus.$emit('load-abilities')
    }
    if(error.response.status == 401)
    {
        store.commit('RESET');
        router.push({name:"login"})
    }
    
    // Do something with response error
    return Promise.reject(error);
  });
/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const app = new Vue({
    el: '#app',
    router: router,
    store:store,
    render: h=> h(AppView),data: function() {
    	return{}
    },
    mounted(){
        EventBus.$on('loader:show',function(message){
            if(!message)
            {
                message = 'Please wait'
            }
            swal(message,{
                    button: false,
                    closeOnClickOutside: false,
                    closeOnEsc: false
            })
            .catch(e=>{})
        })

        EventBus.$on('loader:hide',function(message){
            swal.close()
        })
    }
});
