<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
	        		<h4 class="modal-title" v-if="currentCustomer.id">Edit Customer</h4>
	        		<h4 class="modal-title" v-else>Add Customer</h4>
	      		</div>
	      		<div class="modal-body">
	      			<div class="row">
	      				<div class="col-md-12">
		        			<div class="form-group" :class="{'has-error':errors.has('currentCustomer.name')}">
		        				<label>Name *</label>
		        				<input class="form-control" v-model="currentCustomer.name" v-validate="'required'" data-vv-name="currentCustomer.name">
		        			</div>
		        			<div class="form-group" :class="{'has-error':errors.has('currentCustomer.phone_no')}">
		        				<label>Phone *</label>
		        				<input class="form-control" v-model="currentCustomer.phone_no" v-validate="'phone'" data-vv-name="currentCustomer.phone_no">
		        			</div>
		        		<!--	<div class="form-group" :class="{'has-error':errors.has('currentCustomer.address')}">
		        				<label>Address</label>
		        				<input class="form-control" v-model="currentCustomer.address" v-validate="'required'" data-vv-name="currentCustomer.address">
		        			</div>
		        			<div class="form-group">
		        				<label>Address 2</label>
		        				<input class="form-control" v-model="currentCustomer.address_2">
		        			</div>-->
		        		</div>
		        	</div>
		        <!--	<div class="row">
	        			<div class="col-md-4">
			        		<div class="form-group" :class="{'has-error':errors.has('currentCustomer.state')}">
			        			<label>State</label>
			        			<states :company="company" v-model="currentCustomer.state" v-validate="'required'" data-vv-name="currentCustomer.state"></states>
			        		</div>
			        	</div>
			        	<div class="col-md-4">
			        		<div class="form-group" :class="{'has-error':errors.has('currentCustomer.city')}">
			        			<label>City</label>
			        			<input class="form-control" v-model="currentCustomer.city" v-validate="'required'" data-vv-name="currentCustomer.city">
			        		</div>
			        	</div>
			        	<div class="col-md-4">
		        			<div class="form-group" :class="{'has-error':errors.has('currentCustomer.post_code')}">
		        				<label>Post Code</label>
		        				<input class="form-control" v-model="currentCustomer.post_code" v-validate="'required'" data-vv-name="currentCustomer.post_code">
		        			</div>
		        		</div>
			        </div>
			        <div class="row">
		        		
			        	
		        	</div>
		        	<div class="row">
		        		<div class="col-md-6">
			        		<div class="form-group" :class="{'has-error':errors.has('currentCustomer.contact')}">
			        			<label>Contact</label>
			        			<input class="form-control" v-model="currentCustomer.contact" v-validate="'required'" data-vv-name="currentCustomer.contact">
			        		</div>
			        	</div>
			        	<div class="col-md-6">
			        		<div class="form-group" :class="{'has-error':errors.has('currentCustomer.phone_no')}">
			        			<label>Phone</label>
			        			<input class="form-control" v-model="currentCustomer.phone_no" v-validate="'required'" data-vv-name="currentCustomer.phone_no">
			        		</div>
			        	</div>
			        </div>-->
	      		</div>
	      		<div class="modal-footer">
	        		<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
	        		<button type="button" class="btn btn-primary" @click="editCustomer">Save changes</button>
	      		</div>
	    	</div>
	  	</div>
</div>
</template>
<script>
	import States from '../companies/States'
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import phoneValidator from '../../validators/phoneNumber.js'
	import { Validator } from 'vee-validate';
	Validator.extend('phone',phoneValidator)
	
	export default {
		props:{
			customer:{
				type:Object,
				required:false,
				default:function(){
					return {
						id:null,
					/*	address: "",
						address_2: "",
						post_code: "",
						city: "",
						country_region_code: "",
						state: {},*/
						phone_no: "",
						name: "",
					//	contact: ""
					}
				}
			},
			company:{
				type:Object,
				required:true,
			},
			distributor:{
				type:Object,
				required:false,
			},
			dealer:{
				type:Object,
				required:false,
			},
			retailCustomer:{
				type:Object,
				required:false,
			}
		},
		components:{
			States
		},
		data(){
			return {
				currentCustomer:{
					id:null,
				/*	address: "",
					address_2: "",
					post_code: "",
					city: "",
					country_region_code: "",
					state: {},*/
					phone_no: "",
					name: "",
				//	contact: ""
				}
			}
		},
		mounted(){
			let self = this
			$(this.$refs.modal).on('show.bs.modal', function (e) {
  				if(self.customer.id)
  				{
  					self.currentCustomer = Object.assign({},self.customer)
  				}
  				self.errors.clear()
			})
			$(this.$refs.modal).on('hidden.bs.modal', function (e) {
				self.currentCustomer.id = null
			/*	self.currentCustomer.address =  ""
				self.currentCustomer.address_2 =  ""
				self.currentCustomer.post_code =  ""
				self.currentCustomer.city =  ""
				self.currentCustomer.country_region_code =  ""
				self.currentCustomer.state =  {}*/
				self.currentCustomer.phone_no =  ""
				self.currentCustomer.name =  ""
			//	self.currentCustomer.contact =  ""
  				self.errors.clear()
			})
		},
		methods:{
			saveData(){
				let out = {}
			/*	out.address = this.currentCustomer.address
				out.address_2 = this.currentCustomer.address_2
				out.post_code = this.currentCustomer.post_code
				out.city = this.currentCustomer.city
				out.state = this.currentCustomer.state.code
				out.country_region_code = this.currentCustomer.state.country*/
				out.phone_no = this.currentCustomer.phone_no
				out.name = this.currentCustomer.name
			//	out.contact = this.currentCustomer.contact
				return out
			},
			editCustomer(){
				let self = this
				let url = ""
				let method = "post"
				this.$validator.validateAll()
				.then(value => {
					if(value)
					{
						if(self.distributor && self.distributor.id)
						{
							if(self.currentCustomer.id)
							{
								method = "put"
								url = `companies/${self.company.id}/distributors/${self.distributor.id}/customers/${self.currentCustomer.id}`;
							}
							else
							{
								url = `companies/${self.company.id}/distributors/${self.distributor.id}/customers`;	
							}
							
						}
						if(self.dealer && self.dealer.id)
						{
							if(self.currentCustomer.id)
							{
								method = "put"
								url = `companies/${self.company.id}/dealers/${self.dealer.id}/customers/${self.currentCustomer.id}`;
							}
							else
							{
								url = `companies/${self.company.id}/dealers/${self.dealer.id}/customers`;	
							}
							
						}
						if(self.retailCustomer && self.retailCustomer.id)
						{
							if(self.currentCustomer.id)
							{
								method = "put"
								url = `companies/${self.company.id}/retailCustomers/${self.retailCustomer.id}/customers/${self.currentCustomer.id}`;
							}
							else
							{
								url = `companies/${self.company.id}/retailCustomers/${self.retailCustomer.id}/customers`;	
							}
							
						}
						EventBus.$emit('loader:show')
						Vue.axios[method](url,self.saveData())
						.then(response => {
							EventBus.$emit('loader:hide')
							if(self.currentCustomer.id)
							{
								self.$emit('customer:edited',response.data)
							}
							else
							{
								self.$emit('customer:added',response.data)
							}
							$(self.$refs.modal).modal('hide')
						})		
					}
					else
					{
						swal({
							text:"Name & Phone are required.",
							icon:"error"
						})
					}
				})
				
				/*if(this.currentCustomer.id)
				{
					this.$emit('customer:edited',this.currentCustomer)
				}
				else
				{
					this.$emit('customer:edited',this.currentCustomer)
				}*/
			},
			show(){
				$(this.$refs.modal).modal('show')
			}
		}
	}
</script>