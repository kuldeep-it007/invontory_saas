<template>
	<div v-if="abilities.companies">
		<section class="content-header">
			<h1 v-if="company.id">Edit Company</h1>
			<h1 v-else>Create Company</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-6">
					<div class="box box-widget">
						<div class="box-body">
							<div class="form-group" :class="{'has-error':errors.has('name')}">
								<label>Name</label>
								<input type="text" class="form-control" v-model="company.name" v-validate="'required'" data-vv-name="name">
								<span class="text-danger" v-show="errors.has('name')">Please enter name.</span>
							</div>
							<div class="form-group" :class="{'has-error':errors.has('database')}">
								<label>Company name as in Navision</label>
								<input type="text" class="form-control" v-model="company.database" v-validate="'required'" data-vv-name="database" @blur="validateDatabase">
								<span class="text-danger" v-show="errors.has('database')">Please enter comany name as it appears in Navision.</span>
							</div>
							<div class="form-group">
								<label>Region</label>
								<region-select v-model="company.region"></region-select>
								<span class="text-danger" v-show="errors.has('region')">Please select region.</span>
							</div>
						</div>
						<div class="box-footer">
							<button class="btn btn-primary" @click="save" :disabled="!((databaseValidated && databaseValid) && company.name)">SAVE</button>
							<button class="btn btn-default" @click="cancel">CANCEL</button>
						</div>
					</div>
				</div>
				<div class="col-md-6"  v-if="!company.id">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-12">
									<h4>Admin User</h4>
									<hr>
								</div>
          					<!--	<div class="col-md-6">
          							<div class="form-group">
										<label>Username</label>
										<input type="text" class="form-control" v-model="user.username" v-validate="'required'" data-vv-name="username">
										<span class="text-danger" v-show="errors.has('username')">Please enter Username.</span>
									</div>
          						</div>-->
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Email</label>
										<input type="text" class="form-control" v-model="user.email" v-validate="'required'" data-vv-name="userEmail">
									<span class="text-danger" v-show="errors.has('userEmail')">Please enter email.</span>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('password')}">
										<label>Password</label>
										<input type="text" class="form-control" v-model="user.password" v-validate="'required'" data-vv-name="password">
									</div>
          						</div>
          					</div>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="alert alert-info" v-if="validatingDatabase">
						Please wait while we check connection with Navision.
					</div>
					<div class="alert alert-danger" v-if="databaseValidated && !databaseValid">
						Unable to connect to Navision. please check <strong>Company name as in Navision</strong>
					</div>
					<div class="alert alert-success" v-if="databaseValidated && databaseValid && !databaseChanged">
						Connection to navision successful.
					</div>
				</div>
			</div>
		</section>
	</div>
	<div v-else>
		<section class="content-header">
			<h1>Company</h1>
		</section>
		<section class="content">
			<div class="alert alert-danger">
				you do not have permission to view this page
			</div>

		</section>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import VeeValidate from 'vee-validate'
	import Vue from 'vue'
	import RegionSelect from '../region/Select'
	import {EventBus} from '../../configs/EventBus'
	Vue.use(VeeValidate)
	export default{
		mixins : [appMixin],
		components:{
			RegionSelect,
		},
		data(){
			return {
				company:{
					id:null,
					name: "",
					region:null,
					database:"",
				},
				user:{
						email:null,
					//	username:null,
						password:null
					},
				databaseValid:true,
				databaseValidated:false,
				validatingDatabase:false,
				databaseChanged:false,
			}
		},
		mounted(){
			if(this.$route.params.id)
			{
				this.company.id = this.$route.params.id
			}
			if(this.company.id)
			{
				EventBus.$emit('loader:show')
				Vue.axios.get(`companies/${this.company.id}`)
				.then(response => {
					this.databaseValidated = true
					this.databaseChanged = true
					EventBus.$emit('loader:hide')
					this.company = response.data
				})
			}
		},
		methods:{
			validateDatabase(){
				if(!this.company.database)
					return
				this.databaseChanged = true
				this.databaseValidated = false
				this.validatingDatabase = true
				Vue.axios.post('companies/validate',{database:this.company.database})
				.then(response => {
					this.validatingDatabase = false
					this.databaseValidated = true
					this.databaseValid = true
					this.databaseChanged = false
				})
				.catch(error => {
					this.validatingDatabase = false
					this.databaseValidated = true
					this.databaseValid = false
					this.databaseChanged = false
				})
			},
			cancel(){
				this.$router.push({name:'companies'})
			},
			save(){
				let self = this

			    let input = {
	               name : self.company.name,
	               region : self.company.region.id,
	               database : self.company.database,
	            }
	            if(this.user){
					input.user = {
						email:this.user.email,
						//username:this.user.username,
						password:this.user.password
					}
				}

				//this.error.clear()
				this.$validator.validateAll().then(result => {
    				if(result == true)
    				{
    					if(self.company.id)
    					{
    						Vue.axios.put(`companies/${self.company.id}`,input)
	    					.then(response => {
	    						swal ( "Success" ,  "Company updated" ,  "success" )
	    						.then(value =>{
	    							self.$router.push({name:'companies'})
	    						})
	    					})
    					}
    					else
    					{
    						Vue.axios.post('companies',input)
	    					.then(response => {
	    						swal ( "Success" ,  "Company added" ,  "success" )
	    						.then(value =>{
	    							self.$router.push({name:'companies'})
	    						})
	    					})	
    					}
    					
    				}
    			})
				 
			}
		}
	}
</script>
<style>
.has-error .multiselect__tags{
	border-color:#dd4b39;
}
</style>