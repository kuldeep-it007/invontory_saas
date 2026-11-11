<template>
    <div class="lockscreen-wrapper">
        <div class="lockscreen-logo">
            <a href="#" @click.prevent>{{siteName}}</a>
        </div>
        <div class="lockscreen-item">
            <div class="lockscreen-image">
                <i class="fas fa-redo fa-spin" v-if="!statusChecked"></i>
                <i class="far fa-smile text-green" v-if="paid"></i>
                <i class="far fa-frown text-maroon" v-if="cancelled"></i>
                <i class="far fa-frown text-red" v-if="declined"></i>
                <i class="far fa-frown text-orange" v-if="on_hold"></i>
            </div>
            <div class="lockscreen-credentials">
                <p v-if="!statusChecked">Checking status</p>
                <p v-if="paid">paid</p>
                <p v-if="declined">declined</p>
                <p v-if="cancelled">cancelled</p>
                <p v-if="on_hold">on hold</p>
            </div>
        </div>
        <div class="help-block text-center">
            <template v-if="!statusChecked">
                <p>
                    Please wait while we check payment status
                </p>
            </template>
            <template v-if="paid">
                <p>
                    Your transaction of <b>{{order.amount}} {{order.currency}}</b> was succcessful.
                </p>
                <p>
                    <template v-if="!order.blanketOrder">
                        <i class="fas fa-redo fa-spin"></i> We are processing your order. You'll recieve confirmation mail once order is placed.
                    </template>
                    <template v-else>
                        <i class="far fa-check-circle text-green"></i> Your order with order number <b>{{order.blanketOrder.number}}</b> is placed. You'll be redirected to order in a while.
                    </template>
                </p>
            </template>
            <template v-if="declined">
                <p> Your transaction of <b>{{order.amount}} {{order.currency}}</b> was declined by your bank with message <b class="text-red">{{order.payment.message}}</b></p>
                <p>
                    We have reserved products for you under Blocked items. You can place order again.
                </p>
                <p>
                    <router-link :to="{name:'dashboard'}">Click here</router-link> to go back to dashboard.
                </p>
            </template>

            <template v-if="cancelled">
                <p> Your transaction of <b>{{order.amount}} {{order.currency}}</b> was cancelled by you</p>
                <p>
                    We have reserved products for you under Blocked items. You can place order again.
                </p>
                <p>
                    <router-link :to="{name:'dashboard'}">Click here</router-link> to go back to dashboard.
                </p>
            </template>

            <template v-if="on_hold">
                <p> Your transaction of <b>{{order.amount}} {{order.currency}}</b> was authorized but kept on hold. We'll place your order once we check transtaction</p>
                <p>
                    <router-link :to="{name:'dashboard'}">Click here</router-link> to go back to dashboard.
                </p>
            </template>
        </div>
        <!--
        <div class="text-center">
            <a href="login.html">Or sign in as a different user</a>
        </div>
        <div class="lockscreen-footer text-center">
            Copyright © 2014-2016 <b><a href="https://adminlte.io" class="text-black">Almsaeed Studio</a></b><br>
            All rights reserved
        </div>
        -->
    </div>
</template>
<script>
import Vue from 'vue'
export default {
    data(){
        return {
            siteName : window.config.brand,
            statusChecked: false,
            order:null,
            orderCheckCall:false,
        }
    },
    computed:{
        paid(){
            if(!this.order)
                return false
            if(this.order.status == 'paid')
                return true
        },
        cancelled(){
            if(!this.order)
                return false
            if(this.order.status == 'cancelled')
                return true
        },
        declined(){
            if(!this.order)
                return false
            if(this.order.status == 'declined')
                return true
        },
        on_hold(){
            if(!this.order)
                return false
            if(this.order.status == 'on_hold')
                return true
        }
        
    },
    methods:{
        checkStatus(){
            let self = this
            Vue.axios.get(`pg/${this.$route.params.ref}`)
            .then(response => {
                this.statusChecked = true
                this.order = Object.assign({},response.data)
                if(response.status == 202)
                {
                    this.orderCheckCall = true
                    if(this.order.status == 'paid')
                    {
                        setTimeout(function(){
                            self.checkStatus()
                        },1000)
                    }
                }
                if(response.status == 201)
                {
                    setTimeout(function(){
                        self.$router.push({name:'orders.view',params:{id:self.order.blanketOrder.id}})
                    },1000)
                    
                }
            })
        }
    },
    mounted(){
        let body = document.getElementsByTagName('body')[0];
        body.classList.add('lockscreen')
        this.checkStatus()
    },
    beforeDestroy(){
        let body = document.getElementsByTagName('body')[0];
        body.classList.remove('lockscreen')
    }
}
</script>
<style scoped>
.lockscreen-credentials{
    height: 34px;
    line-height: 34px;
    padding-right: 30px;
}
.lockscreen-image i{
    font-size: 70px;
}
.lockscreen-credentials p{
    text-transform: uppercase;
    font-size: 20px;
    text-align: center;
}
</style>