<template>
	<div v-if="abilities.impersonate || impersonated">
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../vuex-mixin.js'
	import {EventBus} from '../configs/EventBus'
	import Vue from 'vue'
	export default{
		mixins:[appMixin],
		data(){
			return {
				impersonated:false,
			}
		},
		mounted(){
			let self = this
			EventBus.$emit('loader:show')
			Vue.axios.post('users/impersonate',{user:this.$route.params.userid})
			.then(response => {
				let self = this
				EventBus.$emit('loader:hide')
				this.impersonated = true
				let data = response.data.data.self
            	let abilities = data.abilities
            	let distributor = data.distributor
            	let superStockist = data.superStockist
            	let dealer = data.dealer
            	delete(data.change_password)
            	delete(data.abilities)

            	if(distributor)
                	delete(data.distributor)
                if(superStockist)
                	delete(data.superStockist)
            	if(dealer)
                	delete(data.dealer)
                let impersonated = {
                	token: this.state.token,
                	user: this.state.user,
                	abilities: this.state.abilities

                }
                this.store.commit('SET_IMPERSONATED',impersonated)
                self.store.commit('SET_TOKEN',response.data.token)
            	self.store.commit('SET_USER',response.data.data.login)
            	self.store.commit('SET_ABILITIES',abilities)
            	self.store.commit('SET_DISTRIBUTOR',distributor)
            	self.store.commit('SET_SUPER_STOCKIST',superStockist)
            	self.store.commit('SET_DEALER',dealer)
            	EventBus.$emit('reset-pusher',impersonated.user.id)
                Vue.nextTick(function(){
                	$('body').removeClass(window.config.theme).addClass('skin-red')
	            	self.$router.push('/')
                })
            	
			})
		}
	}
</script>