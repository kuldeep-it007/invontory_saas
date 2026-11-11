<template>
	<div v-if="abilities.createSuperStockists">
		<section class="content-header">
			<h1>Sync Super Stockist</h1>
			<div class="row" style="margin-top:10px;">
				<div class="col-md-12">
					<div class="alert alert-info">
						<p>Please provide <b>NAV ID</b> for the Super Stockist that you wish to create in {{brand}}. Also provide <b>{{brand}} User</b> credentials.</p>
					</div>
				</div>
			</div>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-3">
									<div class="form-group">
										<label>NAV ID</label>
										<input type="text" class="form-control" v-model="nav_id">
									</div>
								</div>
          						<div class="col-md-3">
          							<div class="form-group" :class="{'has-error':errors.has('email')}">
										<label>Email</label>
										<input type="text" class="form-control" v-model="email" v-validate="'required|email'" data-vv-name="email">
									</div>
          						</div>
          						<div class="col-md-3">
          							<div class="form-group" :class="{'has-error':errors.has('password')}">
										<label>Password</label>
										<input type="text" class="form-control" v-model="password" v-validate="'required'" data-vv-name="password">
									</div>
          						</div>
          						<div class="col-md-1">
									<div class="form-group">
										<label style="display: block;">&nbsp;</label>
										<button class="btn btn-default" :disabled= "!email || !password || !nav_id" @click="sync">Sync</button>
									</div>
								</div>
								<div class="col-md-1">
									<div class="form-group">
										<label style="display: block;">&nbsp;</label>
										<button class="btn btn-default" @click="cancel">Cancel</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>
	</div>
	<div v-else>
		<section class="content-header">
			<h1>Sync Super Stockist</h1>
		</section>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	export default{
		mixins : [appMixin],
		data(){
			return {
				nav_id:null,
				company_id:null,
				email:null,
				password:null,
				brand:window.config.brand_short
			}
		},
		mounted(){
			this.company_id = this.$route.params.id
		},
		methods:{
			cancel(){
				this.$router.push({name:'companies.super_stockists',params:{id:this.company_id}})
			},
			sync(){
				let self = this 
				let	url = `companies/${self.company_id}/sync_super_stockist`
				let input = {
					nav_id:self.nav_id,
                    password:self.password,
                   	email:self.email 
                }
				EventBus.$emit('loader:show')
				Vue.axios.post(url, input)
				.then(response => {
					swal({
						text:"Sync successful!",
						icon:"success"
					})
					.then(value=>{
						self.$router.push({name:'companies.super_stockists',params:{id:self.company_id}})
					})
				})
				.catch(error => {
					if (error.response.data.email) 
					{
						swal ( "Error" ,  "The user email has already been taken",  "error" )
    					.then(value =>{
    						self.email = null
    					})
					}
					if(error.response.status == 412)
                    {
                        swal ("Super Stockist already synced !")
    					.then(value =>{
    						self.$router.push({name:'companies.super_stockists',params:{id:self.company_id}})
    					})
                    }
	            })
        	},
		}
	}
</script>