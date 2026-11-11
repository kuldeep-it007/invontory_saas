<template>
    <div>
        <header class="main-header">
            <router-link :to="{name:'dashboard'}" class="logo">
                <span class="logo-mini"><b>{{siteShortName}}</b></span>
                <span class="logo-lg"><b>{{siteName}}</b></span>
            </router-link>
            <nav class="navbar navbar-static-top">
                <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                    <span class="sr-only">Toggle navigation</span>
                </a>
                <a href="#" class="animated infinite flash impersonated delay-2s" v-if="state.impersonated">
                    You are impersonating {{state.user.name}}
                </a>
                <div class="marquee" v-if="state.notice && state.notice.message && !state.impersonated">
                        <span v-html="notice"></span>
                </div>
                <!--<a href="#" class="animated infinite flash  delay-2s" v-if="state.notice && state.notice.message">
                    
                </a>-->
                <div class="navbar-custom-menu">
                    <ul class="nav navbar-nav">
                        <li class="dropdown messages-menu">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                <i class="fas fa-bell"></i>
                                <span class="label label-warning" v-if="notifications.length > 0">{{notifications.length}}</span>
                            </a>
                            <ul class="dropdown-menu" v-if="notifications.length > 0">
                                <li class="header">You have {{notifications.length}} notifications</li>
                                <li>
                                    <ul class="menu" >
                                        <li v-for="(notification, index) in notifications" :key="notification.id">
                                            <a>
                                                <p class="notification-title">{{notification.title}}</p>
                                                <p>{{notification.text}}</p>
                                                <p>
                                                    <a class="pull-left">{{notification.created_at|moment}}</a>
                                                    <a href="#" class="pull-right" @click.prevent="markAsRead(notification.id, index)">Mark as read</a>
                                                </p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                                <li class="footer" v-if="notifications.length > 0"><a href="#" class="action" @click.prevent="markAllAsRead()">Mark all as read</a></li>
                            </ul>
                        </li>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                <i class="fa fa-user"></i> <span class="hidden-xs">{{state.user.name}}</span>
                            </a>
                            <!--<ul class="dropdown-menu">
                                <li class="user-footer">
                                    <div class="pull-left">
                                        
                                        <button class="btn btn-default btn-flat" @click="resetPassword()">Reset Password</button>
                                    </div>
                                    <div class="pull-right">
                                        
                                    </div>
                                </li>
                            </ul>-->
                            <ul class="dropdown-menu" role="menu">
                                <li>
                                    <a v-if="isCustomer">Customer ID: {{state.user.cust_id}}</a>
                                </li>
                                <li>
                                    <router-link :to="{name:'profile'}" v-if="isCustomer">Profile</router-link>
                                </li>
                                <li>
                                    <a href="#" @click.prevent="resetPassword">Reset Password</a>
                                </li>
                                <li class="divider"></li>
                                <li>
                                    <a href="#" @click.prevent="logout">Sign out</a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
        </header>
        <reset-password-modal ref="resetPasswordModal" :change-password="changePassword"></reset-password-modal>
        <notice-modal ref="NoticeModal"></notice-modal>
        <flash-message class="flashpool"></flash-message>
    </div>
</template>

<script>
    import moment from 'moment'
    import AppMixin from '../vuex-mixin'
    import Vue from 'vue'
    import ResetPasswordModal from './ResetPasswordModal'
    import NoticeModal from './NoticeModal'
    import Pusher from 'pusher-js'
    import {EventBus} from '../configs/EventBus'
    Vue.prototype.moment = moment
    export default{
        mixins:[AppMixin],
        components:{
            ResetPasswordModal,
            NoticeModal,
        },
        props:{
            changePassword:{
                type: Boolean,
                required: false,
                default:false,
            },
        },
        data(){
            return {
                siteName:window.config.brand,
                siteShortName:window.config.brand_short,
                pusherSocket:null,
                pusherChannel:null,
                notifications:[],
            }
        },
        computed: {
            notice(){
                if(this.state.notice && this.state.notice.message)
                    return this.state.notice.message.replaceAll('<br>',' ')
                return ""
            },
            state () {
                return this.store.state
            },
            store () {
                return this.$store
            },
            abilities(){
                return this.state.abilities
            }
        },
        filters: {
            moment: function (date) {
                return moment.unix(date).format('DD MMM YYYY hh:mm a');
            }
        },
        mounted(){
            let self = this
            this.pusherSocket = new Pusher(window.config.pusher.key,{
                cluster: window.config.pusher.cluster,
                encrypted: true,
                authEndpoint: `${window.config.api_url}pusherAuth`,
                auth: {
                    headers: {
                        'Authorization': `Bearer ${this.state.token}`
                    }
                }
            })
            this.pusherChannel = this.pusherSocket.subscribe(`private-${window.config.prefix}-user-${this.state.user.id}`)
            
            self.pusherChannel.bind('notification',function(data){
                if(data.notification)
                {
                    self.notifications.unshift(data.notification)
                    self.flash(data.notification.title, 'success');
                }
            })
            this.viewAllNotifications()
            /**
             if(this.changePassword)
            {
                this.$refs.resetPasswordModal.show()
            }
             */
            setTimeout(function(){
                if(self.state.impersonated)
                    return
                self.$refs.NoticeModal.show()
            },500)
          //  this.$refs.NoticeModal.show()
            EventBus.$on('reset-pusher',(userid)=>{
                console.log('reset-pusher',userid)
                self.viewAllNotifications()
                self.pusherSocket.unsubscribe(`private-${window.config.prefix}-user-${userid}`)
                self.pusherChannel = self.pusherSocket.subscribe(`private-${window.config.prefix}-user-${this.state.user.id}`)
                self.pusherChannel.bind('notification',function(data){
                    if(data.notification)
                    {
                        self.notifications.unshift(data.notification)
                        self.flash(data.notification.title, 'success');
                    }
                })
            })
        },
        methods:{
            moment: function () {
                return moment();
            },
            logout(){
                let self = this
                if(this.state.impersonated)
                {
                    let olduserid = this.state.user.id
                    let imp = Object.assign({},this.state.impersonated)
                    this.store.commit('SET_IMPERSONATED',null)
                    this.store.commit('SET_TOKEN',imp.token)
                    this.store.commit('SET_USER',imp.user)
                    this.store.commit('SET_ABILITIES',imp.abilities)
                    this.store.commit('SET_DISTRIBUTOR',null)
                    this.store.commit('SET_SUPER_STOCKIST',null)
                    this.store.commit('SET_DEALER',null)
                    
                    Vue.nextTick(function(){
                        $('body').removeClass('skin-red').addClass(window.config.theme)
                        self.$router.push('/')    
                        EventBus.$emit('reset-pusher',olduserid)
                    })
                    
                }
                else
                {
                    this.pusherSocket.disconnect()
                    this.store.commit('SET_TOKEN_VERIFIED',false)
                    this.store.commit('SET_USER',null)
                    this.$router.push('login')
                }
            },
            resetPassword(){
                this.$refs.resetPasswordModal.show()
            },
            viewAllNotifications(){
                let self = this
                let url = `users/${self.state.user.id}/notifications`
                self.notifications = []
                Vue.axios.get(url)
                .then(response => {
                    let notificationsArray = response.data
                    _.each(notificationsArray,function(element){
                        self.notifications.push(element)
                    })
                })
            },
            markAsRead(notificationId, index){
                let self  = this
                let url = `notifications/${notificationId}`
               Vue.axios.put(url)
                .then(response => {
                   self.notifications.splice(index,1)
                })
            },
            markAllAsRead(){
                let self  = this
                let url = `notifications`
                Vue.axios.put(url)
                .then(response => {
                 self.notifications = []
                })
            },
        }
    }

</script>
<style scoped>
.main-actions a{
    padding: 8px;
}
.main-actions a i{
    font-size: 34px;
}
.navbar-nav>.messages-menu>.dropdown-menu>li .menu>li>a>h4{
    margin:0;
}
.navbar-nav>.messages-menu>.dropdown-menu>li .menu>li>a>p{
    margin:0;
    white-space: normal;
}
.navbar-nav>.messages-menu>.dropdown-menu>li .menu>li>a>p.notification-title{
    font-weight: bold;
}
.impersonated{
    float: left;
    background-color: transparent;
    background-image: none;
    padding: 10px 15px;
    color:#fff;
    font-size: 20px;
}
/*.marquee {
  height: 33px;
    overflow: hidden;
    position: relative;
    width: 70%;
    margin-top: 12px;
    display: inline-block;
}

.marquee div {
  display: block;
    width: 200%;
    height: 30px;
    position: absolute;
    overflow: hidden;
    animation: marquee-data-v-c06a443e 20s linear infinite;
}

.marquee span {
  float: left;
    color: #fff;
    font-size: 16px;
}

@keyframes marquee {
  0% { left: 0; }
  100% { left: -100%; }
}*/
@media (min-width: 768px) {
    .marquee {
        width: 60% !important;
    }
}
.marquee {
  width: 50%;
    /* margin: 0 0 0 0; */
    overflow: hidden;
    box-sizing: border-box;
    color: #fff;
    font-size: 16px;
    display: inline-block;
    margin-top: 11px;
}

.marquee span {
  display: inline-block;
  width: max-content;

  padding-left: 100%;
  /* show the marquee just outside the paragraph */
  will-change: transform;
  animation: marquee 30s linear infinite;
}

.marquee span:hover {
  animation-play-state: paused
}


@keyframes marquee {
  0% { transform: translate(0, 0); }
  100% { transform: translate(-100%, 0); }
}
</style>