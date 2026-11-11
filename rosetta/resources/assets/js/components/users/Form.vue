<template>
	<div v-if="abilities.createUser">
		<section class="content-header">
			<h1 v-if="id">Update User</h1>
			<h1 v-else>Create User</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-6">
					<div class="box box-widget">
						<div class="box-body">
							<div class="col-md-12">
								<div class="form-group" :class="{'has-error':errors.has('name')}">
									<label>Name</label>
									<input type="text" class="form-control" v-model="name" v-validate="'required'" data-vv-name="name" :disabled="id">
									<span class="text-danger" v-show="errors.has('name')">Please enter name.</span>
								</div>
								<div class="form-group" :class="{'has-error':errors.has('email')}">
									<label>Email</label>
									<input type="text" class="form-control" v-model="email" v-validate="'required'" data-vv-name="email" :disabled="id">
									<span class="text-danger" v-show="errors.has('email')">Please enter email.</span>
								</div>
		                        <div class="form-group">
		                            <label style="display: block; margin-bottom: 15px;">Active</label>
		                        	<switches v-model="active" theme="bootstrap" color="success"></switches>
		                        </div>
			        		</div>
						</div>
						<div class="box-footer">
							<button class="btn btn-primary" @click="save">SAVE</button>
							<button class="btn btn-default" @click="cancel">CANCEL</button>
						</div>
					</div>
				</div>
			</div>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import VeeValidate from 'vee-validate'
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import Switches from 'vue-switches'
	Vue.use(VeeValidate)
	export default{
		mixins : [appMixin],
		components:{
			Switches
		},
		data(){
			return {
				id: null,
				name: "",
				email:null,
				active:true
			}
		},
		mounted(){
			if(this.$route.params.id)
			{
				this.id = this.$route.params.id
			}
			if(this.id)
			{
				EventBus.$emit('loader:show')
				Vue.axios.get(`users/${this.id}`)
				.then(response => {
					EventBus.$emit('loader:hide')
					this.name = response.data.name
					this.email = response.data.email
					this.active = response.data.active
				})
			}
		},
		methods:{
			cancel(){
				this.$router.push({name:'users'})
			},
			save(){
				let self = this
            	let input = {
	                username : this.name,
	                email : this.email,
	                active : this.active
	         	}
				this.$validator.validateAll().then(result => {
    				if(result == true)
    				{
    					if(self.id)
    					{
    						Vue.axios.put(`users/${self.id}`,input)
		    				.then(response => {
		    					swal ( "Success" ,  "User updated" ,  "success" )
		    					.then(value =>{
		    						self.$router.push({name:'users'})
		    					})
		    				})
		    				.catch(error => {
								if (error.response.status === 412) 
								{
	        						swal ( "Error" ,  error.response.data ,  "error" )
			    					.then(value =>{
			    						self.$router.push({name:'users'})
			    					})
	        					}
			                })
    					}
    					else
    					{
	    					Vue.axios.post('users',input)
		    				.then(response => {
		    					swal ( "Success" ,  "User added" ,  "success" )
		    					.then(value =>{
		    						self.$router.push({name:'users'})
		    					})
		    				})
		    				.catch(error => {
								if (error.response.data.email) 
								{
	        						swal ( "Error" ,  error.response.data.email[0] ,  "error" )
			    					.then(value =>{
			    						self.$router.push({name:'users.create'})
			    						self.email = null
			    					})
	        					}
			                })
		    			}
    				}
    			})
			}
		}
	}
</script>