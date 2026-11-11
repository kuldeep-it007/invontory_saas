<template>
    <div class="wrapper" v-if="loaded">
        <main-header :change-password="changePassword"></main-header>
        <sidebar></sidebar>
        <div class="content-wrapper">
            <router-view ref="routerRef"></router-view>
        </div>
        <footer class="main-footer">
            <strong>Copyright &copy; {{siteName}} Products.</strong> All rights reserved. 
           <!-- <template v-if="tnc">
               <a href="#" @click.prevent="showTnc" >Terms & conditions</a>
                <i class="fab fa-cc-visa"></i>
                <i class="fab fa-cc-mastercard"></i>
                <router-link :to="{name:'about'}" style="margin-left:2px;">About Us</router-link>
            </template>-->
            <div class="pull-right">Powered by Fluid Cube Technologies.</div>
        </footer>
        <tnc ref="tnc"></tnc>
    </div>
    <div class="loader" v-else>
        <svg width="100" height="100" viewBox="0 0 44 44" xmlns="http://www.w3.org/2000/svg" stroke="#000">
            <g fill="none" fill-rule="evenodd" stroke-width="2">
                <circle cx="22" cy="22" r="1">
                    <animate attributeName="r"
                    begin="0s" dur="1.8s"
                    values="1; 20"
                    calcMode="spline"
                    keyTimes="0; 1"
                    keySplines="0.165, 0.84, 0.44, 1"
                    repeatCount="indefinite" />
                    <animate attributeName="stroke-opacity"
                        begin="0s" dur="1.8s"
                        values="1; 0"
                        calcMode="spline"
                        keyTimes="0; 1"
                        keySplines="0.3, 0.61, 0.355, 1"
                        repeatCount="indefinite" />
                </circle>
                <circle cx="22" cy="22" r="1">
                    <animate attributeName="r"
                        begin="-0.9s" dur="1.8s"
                        values="1; 20"
                        calcMode="spline"
                        keyTimes="0; 1"
                        keySplines="0.165, 0.84, 0.44, 1"
                        repeatCount="indefinite" />
                    <animate attributeName="stroke-opacity"
                        begin="-0.9s" dur="1.8s"
                        values="1; 0"
                        calcMode="spline"
                        keyTimes="0; 1"
                        keySplines="0.3, 0.61, 0.355, 1"
                        repeatCount="indefinite" />
                </circle>
            </g>
        </svg>
    </div>
</template>

<script>
import MainHeader from '../partials/mainheader.vue'
import Sidebar from '../partials/sidebar.vue'
import Vue from 'vue'
import {EventBus} from '../configs/EventBus.js'
import Tnc from '../components/tnc'
export default {
    updated:function(){
        Vue.nextTick(function(){
            $(document).ready(function($) {
                $.AdminLTE.layout.fix()
            });
        })
    },
    mounted:function(){
        Vue.nextTick(function(){
            $(".loader").css('min-height', $(window).height())
        })
        if(this.state.impersonated)
        {
            $('body').removeClass(window.config.theme).addClass('skin-red')
        }
        let self = this
        Vue.axios.all([this.getSelf(),this.getMaster()])
        .then(Vue.axios.spread(function (selfResponse,masterResponse){
            let data = selfResponse.data
            let abilities = data.abilities
            let distributor = data.distributor
            let superStockist = data.superStockist
            let dealer = data.dealer
            let changePassword = data.change_password
            let notice = data.notice
            delete(data.change_password)
            delete(data.abilities)
            if(data.notice)
                delete(data.notice)
            if(distributor)
                delete(data.distributor)
            if(dealer)
                delete(data.dealer)
            if(superStockist)
                delete(data.superStockist)
            self.store.commit('SET_USER',data)
            self.store.commit('SET_ABILITIES',abilities)
            self.store.commit('SET_DISTRIBUTOR',distributor)
            self.store.commit('SET_SUPER_STOCKIST',superStockist)
            self.store.commit('SET_DEALER',dealer)
            self.store.commit('SET_MASTER',masterResponse.data)
            self.store.commit('SET_NOTICE',notice)
            self.changePassword = changePassword
            self.loaded = true
        }))
            

        /*Vue.axios.get('users/self')
            .then(response =>{
                
            })
            .catch(error => {

            })*/
    },
    data(){
        return {
            loaded:false,
            siteName:window.config.brand,
            changePassword:false,
            tnc: window.config.tnc
        }
    },
    components:{
        MainHeader,
        Sidebar,
        Tnc
    },
    methods:{
        showTnc(){
            this.$refs.tnc.show(window.config.prefix)
		},
        getSelf(){
            return Vue.axios.get('users/self')
        },
        getMaster(){
            return Vue.axios.get('master')
        }
    },
    computed: {
        state () {
            return this.store.state
        },
        store () {
            return this.$store
        }
    }
}
</script>
<style scoped>
.loader{
    display: flex;
    align-items: center;
    height: 100%;
}
.loader svg{
        margin: 0 auto;
    display: block;
}
.fab{
    font-size: 16px;
}
</style>