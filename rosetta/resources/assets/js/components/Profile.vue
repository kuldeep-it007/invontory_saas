<template>
	<div v-if="abilities.manageProfile">
		<section class="content">
			<div class="row">
				<div class="col-md-9">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-12">
									<h4>General</h4>
									<hr>
								</div>
								<div class="col-md-12">
									<div class="form-group" :class="{'has-error':errors.has('name')}">
										<label>Name *</label>
										<input type="text" class="form-control" disabled v-model="profile.name" v-validate="'required'" data-vv-name="name">
										<span class="text-danger" v-show="errors.has('name')">Please enter name.</span>
									</div>
				        		</div>
				        	</div>
							<div class="row">
	          					<div class="col-md-12">
	          						<div class="form-group" :class="{'has-error':errors.has('address')}">
										<label>Address *</label>
										<input type="text" class="form-control" v-validate="'required'" v-model="profile.address">
									</div>
	          					</div>
	       						<div class="col-md-12">
	       							<div class="form-group" :class="{'has-error':errors.has('address2')}">
										<label>Address 2 *</label>
										<input type="text" class="form-control" v-validate="'required'" v-model="profile.address2">
									</div>
	          					</div>
	          				</div>
	          				<div class="row">
	          					<div class="col-md-4">
	          						<div class="form-group" :class="{'has-error':errors.has('state')}">
										<label>State *</label>
										<states v-model="profile.state" :company="customer.company" v-validate="'required'" data-vv-name="state"></states>
									</div>
		  						</div>
	       						<div class="col-md-4">         							
	       							<div class="form-group" :class="{'has-error':errors.has('city')}">
										<label>City *</label>
										<input type="text" class="form-control" v-model="profile.city" v-validate="'required'" data-vv-name="city">
									</div>
	          					</div>
	          					<div class="col-md-4">
	          						<div class="form-group" :class="{'has-error':errors.has('zipcode')}">
										<label>Post code *</label>
										<input type="text" class="form-control" v-model="profile.zipcode" v-validate="'required'" data-vv-name="zipcode">				
									</div>
	          					</div>
	          				</div>
	          				<div class="row">
	      						<div class="col-md-12">
	      							<div class="form-group">
										<label>Email</label>
										<input type="text" class="form-control" disabled v-model="profile.email">
									</div>
	      						</div>
	      						<div class="col-md-6">
	          							<div class="form-group">
											<label>Phone</label>
											<input type="text" class="form-control" v-model="profile.phone">
										</div>
	          					</div>
	      						<div class="col-md-6">
	      							<div class="form-group">
										<label>Fax</label>
										<input type="text" class="form-control" v-model="profile.fax">
									</div>
	      						</div>
	      					</div>
      						<div class="row">
								<div class="col-md-12">
									<h4>Tax Information</h4>
									<hr>
								</div>
								<div class="col-md-6" v-if="gstInformation">
									<div class="form-group">
										<label>GST Registration No.</label>
										<input type="text" class="form-control" disabled v-model="profile.tax_information.gst.number">
									</div>
								</div>
								<div class="clearfix"></div>
								<div class="col-md-6" v-if="vatInformation">
									<div class="form-group">
										<label>T.I.N. No.</label>
										<input type="text" class="form-control" disabled v-model="profile.tax_information.vat.tin">
									</div>
								</div>
								<div class="clearfix"></div>
							</div>
						</div>
						<div class="box-footer">
							<span class="pull-right">
								<button class="btn btn-primary" @click="save">SAVE</button>
								<button class="btn btn-default" @click="cancel">CANCEL</button>
							</span>
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
	import appMixin from '../vuex-mixin.js'
	import VeeValidate from 'vee-validate'
	import Vue from 'vue'
	import {EventBus} from '../configs/EventBus'
	import Switches from 'vue-switches'
	import States from './companies/States'
	Vue.use(VeeValidate)
	export default{
		mixins : [appMixin],
		components:{
			Switches,
			States
		},
		data(){
			return {
				profile:{
					id: null,
					name: "",
					address: null,
					address2: null,
					zipcode: null,
					state: null,
					country: null,
					phone: "",
					email: "",
					fax: "",
					tax_information:{
						vat:{
							tin:""
						},
						gst:{
							number:"",
							registration_type:""
						},
					}
				}
			}
		},
		mounted(){
			//console.log(this.distributor,this.dealer,this.customer)
			let url = ""
			if(this.distributor)
			{
				url = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}`
			}
			else
			{
				url = `companies/${this.dealer.company.id}/dealers/${this.dealer.id}`
			}
			EventBus.$emit('loader:show')
				Vue.axios.get(url)
				.then(response => {
					EventBus.$emit('loader:hide')
					this.profile = Object.assign({},response.data)
				})
				.catch(error => {
					EventBus.$emit('loader:hide')
				})
		},
		computed:{
			gstInformation(){
				return _.contains(this.customer.company.region.tax_information,'gst')
			},
			vatInformation(){
				return _.contains(this.customer.company.region.tax_information,'vat')
			}
		},
		methods:{
			save(){

				let self = this 
				let input = {
					name : self.profile.name,
					address : self.profile.address,
					address2 : self.profile.address2,
					state : self.profile.state.code,
					city : self.profile.city,
					zipcode : self.profile.zipcode,
					phone : self.profile.phone,
					fax : self.profile.fax,
				//	tax_information:{},
				}
			/*	if(self.vatInformation)
				{
					input.tax_information.vat = {
							tin : self.profile.tax_information.vat.tin
						}
				}
				if(self.gstInformation)
				{
					input.tax_information.gst = {
						number:self.profile.tax_information.gst.number,
						registration_type:"GSTIN"
					}
				}

	         */
				let url = ""
				let method = 'put'
				let message = 'Profile Updated'
				if(this.distributor)
				{
					url = `companies/${this.customer.company.id}/distributors/${this.distributor.id}/profile`
				}
				else
				{
					url = `companies/${this.customer.company.id}/dealers/${this.dealer.id}/profile`
				}
				
				this.$validator.validateAll()
				.then(result => {
					if(result)
					{
						EventBus.$emit('loader:show')
						Vue.axios[method](url,input)
						.then(response => {
							EventBus.$emit('loader:hide')
							swal({
								text:message,
								icon:"success"
							})
							.then(value=>{
								EventBus.$emit('loader:hide')
								this.profile = Object.assign({},response.data)
							})
						})
						.catch(error => {})
					}
					else
					{
						swal({
							title:"Required data missing",
							text:"Please enter all required fields",
							icon:"error"
						})
						.then()
						.catch()
					}
				})



			},
			cancel(){
				this.$router.push({name:'dashboard'})
			}
		}
	}
</script>