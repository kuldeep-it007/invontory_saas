<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
	        		<h4 class="modal-title" v-if="currentAddress.code">Edit Shipping Address</h4>
	        		<h4 class="modal-title" v-else>Add Shipping Address</h4>
	      		</div>
	      		<div class="modal-body">
	      			<div class="row">
	      				<div class="col-md-12">
		        			<div class="form-group" :class="{'has-error':errors.has('currentAddress.name')}">
		        				<label>Name</label>
		        				<input class="form-control" v-model="currentAddress.name" v-validate="'required'" data-vv-name="currentAddress.name" autocomplete="none" autocorrect="off" autocapitalize="none" spellcheck="false">
		        			</div>
		        			<div class="form-group" :class="{'has-error':errors.has('currentAddress.address')}">
		        				<label>Address</label>
		        				<input class="form-control" v-model="currentAddress.address" v-validate="'required|max:50'" data-vv-name="currentAddress.address" autocomplete="none" autocorrect="off" autocapitalize="none" spellcheck="false">
		        				<span class="text-danger" v-show="errors.has('currentAddress.address')">Address can not exceed 50 characters. Please enter remaining address in Address 2.</span>
		        			</div>
		        			<div class="form-group" :class="{'has-error':errors.has('currentAddress.address_2')}">
		        				<label>Address 2</label>
		        				<input class="form-control" v-model="currentAddress.address_2" autocomplete="none" autocorrect="off" autocapitalize="none" spellcheck="false" v-validate="'max:50'" data-vv-name="currentAddress.address_2">
		        				<span class="text-danger" v-show="errors.has('currentAddress.address_2')">Address 2 can not exceed 50 characters.</span>
		        			</div>
		        		</div>
		        	</div>
		        	<div class="row">
	        			<div class="col-md-4">
			        		<div class="form-group" :class="{'has-error':errors.has('currentAddress.state')}">
			        			<label>State</label>
			        			<states :company="company" v-model="currentAddress.state" v-validate="'required'" data-vv-name="currentAddress.state"></states>
			        		</div>
			        	</div>
			        	<div class="col-md-4">
			        		<div class="form-group" :class="{'has-error':errors.has('currentAddress.city')}">
			        			<label>City</label>
			        			<input class="form-control" v-model="currentAddress.city" v-validate="'required'" data-vv-name="currentAddress.city" autocomplete="none" autocorrect="off" autocapitalize="none" spellcheck="false">
			        		</div>
			        	</div>
			        	<div class="col-md-4">
		        			<div class="form-group" :class="{'has-error':errors.has('currentAddress.post_code')}">
		        				<label>Post Code</label>
		        				<input class="form-control" v-model="currentAddress.post_code" v-validate="'required'" data-vv-name="currentAddress.post_code" autocomplete="none" autocorrect="off" autocapitalize="none" spellcheck="false">
		        			</div>
		        		</div>
			        </div>
			        <div class="row">
		        		
			        	
		        	</div>
		        	<div class="row">
		        		<div class="col-md-6">
			        		<div class="form-group" :class="{'has-error':errors.has('currentAddress.contact')}">
			        			<label>Contact Name</label>
			        			<input class="form-control" v-model="currentAddress.contact" v-validate="'required'" data-vv-name="currentAddress.contact" autocomplete="none" autocorrect="off" autocapitalize="none" spellcheck="false">
			        		</div>
			        	</div>
			        	<div class="col-md-6">
			        		<div class="form-group" :class="{'has-error':errors.has('currentAddress.phone_no')}">
			        			<label>Phone</label>
			        			<input class="form-control" v-model="currentAddress.phone_no" v-validate="'required'" data-vv-name="currentAddress.phone_no" autocomplete="none" autocorrect="off" autocapitalize="none" spellcheck="false">
			        		</div>
			        	</div>
			        </div>
			        <!--<div class="row" v-if="allowCustomerAddress">
			        	<div class="col-md-12">
			        		<div class="checkbox">
    							<label>
      								<input type="checkbox" v-model="customerAddress"> This is customer address
    							</label>
  							</div>
			        	</div>
			        </div>-->
	      		</div>
	      		<div class="modal-footer">
	        		<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
	        		<button type="button" class="btn btn-primary" @click="editAddress" v-if="allowCustomerAddress">Save as My Address</button>
	        		<button type="button" class="btn btn-primary" @click="editAddress" v-if="!(allowCustomerAddress)">Save Address</button>
	        		<button type="button" class="btn btn-primary" @click="editCustomerAddress" v-if="allowCustomerAddress">Save as Customer's Address</button>
	      		</div>
	    	</div>
	  	</div>
</div>
</template>
<script>
	import States from '../companies/States'
	import Vue from 'vue'
	import VeeValidate from 'vee-validate'
	import {EventBus} from '../../configs/EventBus'
	Vue.use(VeeValidate)
	export default {
		props:{
			address:{
				type:Object,
				required:false,
				default:function(){
					return {
						code:null,
						address: "",
						address_2: "",
						post_code: "",
						city: "",
						country_region_code: "",
						state: {},
						phone_no: "",
						name: "",
						contact: ""
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
			superStockist:{
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
			},
			allowCustomerAddress:{
				type:Boolean,
				required:false,
				default:false,
			}
		},
		components:{
			States
		},
		data(){
			return {
				currentAddress:{
					code:null,
					address: "",
					address_2: "",
					post_code: "",
					city: "",
					country_region_code: "",
					state: {},
					phone_no: "",
					name: "",
					contact: "",
				},
				customerAddress:false,
			}
		},
		mounted(){
			let self = this
			$(this.$refs.modal).on('show.bs.modal', function (e) {
  				if(self.address.code)
  				{
  					self.currentAddress = Object.assign({},self.address)
  				}
  				self.errors.clear()
			})
			$(this.$refs.modal).on('hidden.bs.modal', function (e) {
				self.currentAddress.code = null
				self.currentAddress.address =  ""
				self.currentAddress.address_2 =  ""
				self.currentAddress.post_code =  ""
				self.currentAddress.city =  ""
				self.currentAddress.country_region_code =  ""
				self.currentAddress.state =  {}
				self.currentAddress.phone_no =  ""
				self.currentAddress.name =  ""
				self.currentAddress.contact =  ""
				self.customerAddress = false
  				self.errors.clear()
			})
		},
		methods:{
			saveData(){
				let out = {}
				out.address = this.currentAddress.address
				out.address_2 = this.currentAddress.address_2
				out.post_code = this.currentAddress.post_code
				out.city = this.currentAddress.city
				out.state = this.currentAddress.state.code
				out.country_region_code = this.currentAddress.state.country
				out.phone_no = this.currentAddress.phone_no
				out.name = this.currentAddress.name
				out.contact = this.currentAddress.contact
				return out
			},
			editCustomerAddress()
			{
				this.customerAddress = true
				this.editAddress()
			},
			editAddress(){
				let self = this
				let url = ""
				let method = "post"
				this.$validator.validateAll()
				.then(value => {
					if(value)
					{
						if(this.customerAddress)
						{
							self.$emit('address:added',{
								//code :  'customer-address-' + moment().format('X'),//#737
								code :  null,
								address : this.currentAddress.address,
								address_2 : this.currentAddress.address_2,
								post_code : this.currentAddress.post_code,
								city : this.currentAddress.city,
								state : Object.assign({},this.currentAddress.state),
								country_region_code : this.currentAddress.state.country,
								phone_no : this.currentAddress.phone_no,
								name : this.currentAddress.name,
								contact : this.currentAddress.contact
							})
							$(self.$refs.modal).modal('hide')
							return
						}
						if(self.distributor && self.distributor.id)
						{
							if(self.currentAddress.code)
							{
								method = "put"
								url = `companies/${self.company.id}/distributors/${self.distributor.id}/addresses/${self.currentAddress.code}`;
							}
							else
							{
								url = `companies/${self.company.id}/distributors/${self.distributor.id}/addresses`;	
							}
							
						}
						if(self.superStockist && self.superStockist.id)
						{
							if(self.currentAddress.code)
							{
								method = "put"
								url = `companies/${self.company.id}/super_stockists/${self.superStockist.id}/addresses/${self.currentAddress.code}`;
							}
							else
							{
								url = `companies/${self.company.id}/super_stockists/${self.superStockist.id}/addresses`;	
							}
							
						}
						if(self.dealer && self.dealer.id)
						{
							if(self.currentAddress.code)
							{
								method = "put"
								url = `companies/${self.company.id}/dealers/${self.dealer.id}/addresses/${self.currentAddress.code}`;
							}
							else
							{
								url = `companies/${self.company.id}/dealers/${self.dealer.id}/addresses`;	
							}
							
						}
						if(self.retailCustomer && self.retailCustomer.id)
						{
							if(self.currentAddress.code)
							{
								method = "put"
								url = `companies/${self.company.id}/retailCustomers/${self.retailCustomer.id}/addresses/${self.currentAddress.code}`;
							}
							else
							{
								url = `companies/${self.company.id}/retailCustomers/${self.retailCustomer.id}/addresses`;	
							}
							
						}
						EventBus.$emit('loader:show')
						Vue.axios[method](url,self.saveData())
						.then(response => {
							EventBus.$emit('loader:hide')
							if(self.currentAddress.code)
							{
								self.$emit('address:edited',response.data)
							}
							else
							{
								self.$emit('address:added',response.data)
							}
							$(self.$refs.modal).modal('hide')
						})		
					}
					else
					{
						swal({
							text:"Please enter required fields",
							icon:"error"
						})
					}
				})
				
				/*if(this.currentAddress.code)
				{
					this.$emit('address:edited',this.currentAddress)
				}
				else
				{
					this.$emit('address:edited',this.currentAddress)
				}*/
			},
			show(){
				$(this.$refs.modal).modal('show')
			}
		}
	}
</script>