<template>
    <div>
        <header class="main-header">
            <nav class="navbar navbar-static-top">
                <div class="navbar-header">
                    <router-link :to="{name:'dashboard'}" v-if="state.user" class="navbar-brand">{{siteShortName}}</router-link>
                    <router-link :to="{name:'login'}" v-else class="navbar-brand">{{siteShortName}}</router-link>
                </div>
                <div class="navbar-custom-menu">
                    <ul class="nav navbar-nav">
                        <li class="dropdown" v-if="state.user">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                <i class="fa fa-user"></i> <span class="hidden-xs">{{state.user.name}}</span>
                            </a>
                            <ul class="dropdown-menu" role="menu">
                                <li>
                                    <a href="#" @click.prevent="logout">Sign out</a>
                                </li>
                            </ul>
                        </li>
                        <li v-if="!state.user">
                            <router-link :to="{name:'login'}">Login</router-link>
                        </li>
                    </ul>
                </div>
            </nav>
        </header>
        <section class="content">
            <div class="container text-center">
                <div class="row">
                    <div class="col-md-12">
                        <h2 class="text-center">ABOUT ROSETTA</h2>
                    </div>
                </div>
                <div class="row" style="margin-top:20px;">
                    <div class="col-md-12">
                        <h3>Established in 1996, Rosetta Products is a flooring company with a global presence Our headquarters are based in Singapore with Offices in UAE & India.</h3>
                    </div>
                </div>
                <div class="row" style="margin-top:20px;">
                    <div class="col-md-12">
                        Our Main strength is our research and development department that focuses on studying market trends and innovations in the flooring industry and providing our clients with a wide range of flooring products
                    </div>
                </div>

                <div class="row" style="margin-top:20px;">
                    <div class="col-md-12">
                        At Rosetta, we believe that flooring is the most important aspect of space design. To say the least flooring is the soul of space design and that is what defines the end visuals and aesthetics.
Flooring is unquestionably its starting point and extends an influence over every element of it.
We do not try to simply sell you tiles, carpets, but we take great pride in our ability and capability in being able to customize the right floor to meet your special design requirements. The onus is on us to suggest the most appropriate solution for your need and budget.
                    </div>
                </div>

                <div class="row" style="margin-top:20px;">
                    <div class="col-md-12">
                        <b>If we do not have what you are looking for, we will develop it!!</b>
                    </div>
                </div>

                <div class="row" style="margin-top:20px;">
                    <div class="col-md-12">
                        The range of products include Wall to Wall Carpets, Carpet Tiles, Laminated Flooring, Leather Flooring, Cork Flooring, Vinyl Flooring, Blinds, Wall Paper and Artificial Turf.
                    </div>
                </div>

                <div class="row" style="margin-top:20px;">
                    <div class="col-md-12">
                        <h3>In short, our sole purpose is to ensure you end up with 'THE RIGHT FLOOR'</h3>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
</template>
<script>
import mixins from '../vuex-mixin'
export default {
    mixins:[mixins],
    mounted(){
        let body = document.getElementsByTagName('body')[0];
        body.classList.add('layout-top-nav')
    },
    beforeDestroy(){
        let body = document.getElementsByTagName('body')[0];
        body.classList.remove('layout-top-nav')
    },
    data(){
        return {
            siteShortName:window.config.brand_short,
        }
    },
    methods:{
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
                this.store.commit('SET_USER',null)
                this.$router.push('login')
            }
        }
    }
}
</script>