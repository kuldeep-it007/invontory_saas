<template>
	<div v-if="abilities.createDealers">
		<section class="content-header">
			<h1>
				<template v-if="new_dealer.id">Edit </template>
				<template v-else>Add </template> Customer
			</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-6">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-12">
									<label>Customer Type *</label> 
									<span class="text-danger pull-right animated infinite flash delay-2s">Select appropriate Customer Type</span>
									<div class="row">
										<div class="col-md-6">
											<h4>
												<div class="form-group">
													<input type="radio" v-model="new_dealer.dealer_type" value="1" @click="dealerTypeClicked(1)"> Dealer &nbsp;&nbsp;
		                                    	</div>
											</h4>
										</div>
										<div class="col-md-6">
											<h4>
												<div class="form-group">
													<input type="radio" v-model="new_dealer.dealer_type" value="2" @click="dealerTypeClicked(2)"> Retail Customer
		                                		</div>
											</h4>
										</div>
									</div>
									<hr v-if="new_dealer.dealer_type != ''">
								</div>
              				</div>
              				<template v-if="new_dealer.dealer_type != ''">
	          					<div class="row">
	          						<div class="col-md-12">
	              						<div class="form-group" :class="{'has-error':errors.has('dealer.name')}">
											<label>Name *</label>
											<input type="text" class="form-control" v-model="new_dealer.name" v-validate="'required'" data-vv-name="dealer.name">
										</div>
	              					</div>
	              					<div class="col-md-12">
	              						<div class="form-group">
											<label>Contact Person</label>
											<input type="text" class="form-control" v-model="contact" :disabled="new_dealer.contact">
										</div>
	              					</div>
	          						<div class="col-md-6">
	          							<div class="form-group" :class="{'has-error':errors.has('dealer.email')}">
											<label>Email</label>
											<input type="text" class="form-control" v-model="new_dealer.email" v-validate="{required:new_dealer.phone?false:true,email:true}" data-vv-name="dealer.email" autocomplete="off" autocorrect="off" autocapitalize="off">
											<span class="text-danger" v-show="errors.has('dealer.email')">Email invalid</span>
										</div>
									</div>
									<div class="col-md-6"><!--#742-->
										<div class="form-group" :class="{'has-error':errors.has('password')}">
											<label>Password</label>
											<template v-if="!new_dealer.id">
												<input type="text" class="form-control" v-model="new_dealer.password" v-validate="'required|min:8'" data-vv-name="password">
											</template>
											<template v-if="new_dealer.id">
												<input type="text" class="form-control" v-model="new_dealer.password" v-validate="'min:8'" data-vv-name="password">
											</template>
											<span class="text-danger" v-show="errors.has('password')">Please enter minimum 8 characters</span>
										</div>
	          						</div>
	          						<div class="col-md-6">
	          							<div class="form-group" :class="{'has-error':errors.has('new_dealer.phone')}">
											<label>Phone</label>
											<input type="text" class="form-control" v-model="new_dealer.phone" v-validate="`phone:${company.region.country.code}|numeric`" data-vv-name="new_dealer.phone" autocomplete="off" autocorrect="off" autocapitalize="off">
										</div>
										
	          						<!--	<div class="form-group" :class="{'has-error':errors.has('dealer.phone')}">
											<label>Phone</label>
											<input class="form-control" v-model="new_dealer.phone" v-validate="{required:new_dealer.email?false:true,numeric:true,phone:true}" data-vv-name="dealer.phone" autocomplete="off" autocorrect="off" autocapitalize="off">
											<span class="text-danger" v-show="errors.has('dealer.phone')">Phone inavlid</span>
										</div>-->
	          						</div>
	          						<div class="col-md-6">
	          							<div class="form-group">
											<label>Fax</label>
											<input type="text" class="form-control" v-model="new_dealer.fax">
										</div>
	          						</div>
	          						<div class="col-md-12">
										<p>Email or Phone is mandatory</p>
									</div>
	          					</div>
	          				</template>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="box box-widget">
						<div class="box-body" v-if="new_dealer.dealer_type != ''">
          					<div class="row">
								<div class="col-md-12">
									<h4>Address</h4>
									<hr>
									<div class="row">
		          						<div class="col-md-12">
		          							<div class="form-group" :class="{'has-error':errors.has('dealer.address')}">
												<label>Address</label>
												<input type="text" class="form-control" v-model="new_dealer.address" v-validate="'max:50'" data-vv-name="dealer.address">
												<span class="text-danger" v-show="errors.has('dealer.address')">Address can not exceed 50 characters. Please enter remaining address in Address 2.</span>
											</div>
		          						</div>
		          						<div class="col-md-12">
		          							<div class="form-group" :class="{'has-error':errors.has('dealer.address2')}">
												<label>Address 2</label>
												<input type="text" class="form-control" v-model="new_dealer.address2" v-validate="'max:50'" data-vv-name="dealer.address2">
												<span class="text-danger" v-show="errors.has('dealer.address2')">Address 2 can not exceed 50 characters.</span>
											</div>
		          						</div>
		          					</div>
		          					<div class="row">
		          						<div class="col-md-4">
		          							<div class="form-group" :class="{'has-error':errors.has('dealer.state')}">
												<label>State *</label>
												<states v-model="new_dealer.state" :company="company" v-validate="'required'" data-vv-name="dealer.state" v-if="company.id"></states>
											</div>
		          						</div>
		          						<div class="col-md-4">
		          							<div class="form-group">
												<label>City</label>
												<input type="text" class="form-control" v-model="new_dealer.city">
											</div>
		          						</div>
		          						<div class="col-md-4">
		          							<div class="form-group">
												<label>Post code</label>
												<input type="text" class="form-control" v-model="new_dealer.zipcode">
											</div>
		          						</div>
		          					</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body" v-if="new_dealer.dealer_type != ''">
          					<div class="row">
								<div class="col-md-12">
									<h4>Payments</h4>
									<hr>
								</div>
          						<div class="col-md-3">
          							<div class="form-group">
										<label>Payment Terms</label>
										<payment-terms v-model="new_dealer.paymentTerms"></payment-terms>
									</div>
          						</div>
          						<div class="col-md-3">
          							<div class="form-group">
										<label>Payment Method</label>
										<payment-method v-model="new_dealer.paymentMethod"></payment-method>
									</div>
          						</div>
          						<div class="col-md-3">
          							<div class="form-group" :class="{'has-error':errors.has('dealer.credit_limit')}">
										<label>Credit Limit *</label>
										<input type="text" class="form-control" v-model="new_dealer.credit_limit" v-validate="'required|decimal:2'" data-vv-name="dealer.credit_limit">
										<span class="text-danger" v-show="errors.has('dealer.credit_limit')">Can not exceed 2 decimals</span>
									</div>
          						</div>
          						<div class="col-md-3">
          							<div class="form-group" :class="{'has-error':errors.has('dealer.overdue_balance')}">
										<label>Overdue Balance</label>
										<input type="text" class="form-control" v-model="new_dealer.overdue_balance" v-validate="{decimal:2}" data-vv-name="dealer.overdue_balance">
										<span class="text-danger" v-show="errors.has('dealer.overdue_balance')">Can not exceed 2 decimals</span>
									</div>
          						</div>
          					</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body" v-if="new_dealer.dealer_type != ''">
							<h4>Tax Information</h4>
							<hr>
							<template v-if="taxInformation">
								<h5>Tax</h5>
								<div class="col-md-4">
									<div class="form-group">
										<label>L.S.T. No.</label>
										<input type="text" class="form-control" v-model="new_dealer.tax_information.tax.lst">
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label>C.S.T. No.</label>
										<input type="text" class="form-control" v-model="new_dealer.tax_information.tax.cst">
									</div>
								</div>
								<div class="clearfix"></div>
							</template>
							<template v-if="gstInformation">
								<h5>GST</h5>
								<div class="col-md-4">
									<div class="form-group">
										<label>GST Customer Type</label>
										<gst-customer-type v-model="new_dealer.tax_information.gst.customer_type" :disabled="new_dealer.dealer_type == 1" @input="gstCustomerTypeChanged"></gst-customer-type>
									</div>
								</div>
								<div class="col-md-4" v-if="new_dealer.tax_information.gst.customer_type && (new_dealer.tax_information.gst.customer_type.key == 'Registered')">
									<div class="form-group" :class="{'has-error':errors.has('dealer.tax_information.gst.number')}">
										<label>GST Registration No.</label>
										<input type="text" class="form-control" style="text-transform: uppercase;" v-model="new_dealer.tax_information.gst.number" v-validate="`required|gstin:${selectedState}`" data-vv-name="dealer.tax_information.gst.number" :disabled="!new_dealer.state">
										<span class="text-danger" v-show="errors.has('dealer.tax_information.gst.number')">Please enter valid GST No.</span>
									</div>
								</div>
								<div class="clearfix"></div>
							</template>
							<template v-if="panInformation">
								<h5>Income Tax</h5>
								<div class="col-md-4">
									<div class="form-group">
										<label>P.A.N. Status</label>
										<pan-status v-model="new_dealer.tax_information.pan.status"></pan-status>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label>P.A.N. No.</label>
										<input type="text" class="form-control" v-model="new_dealer.tax_information.pan.number">
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label>P.A.N. Reference No.</label>
										<input type="text" class="form-control" v-model="new_dealer.tax_information.pan.reference_number">
									</div>
								</div>
								<div class="clearfix"></div>
							</template>
							<template v-if="vatInformation">
								<h5>VAT</h5>
								<!--<div class="col-md-4">
									<div class="form-group">
										<label>T.I.N. No.</label>
										<input type="text" class="form-control" v-validate="'max:12'" data-vv-name="dealer.tin" v-model="new_dealer.tax_information.vat.tin">
										<span class="text-danger" v-show="errors.has('dealer.tin')">Can not exceed 12 characters.</span>
									</div>
								</div>#675-->
								<div class="col-md-6">
									<div class="form-group">
										<label>VAT Registration No.</label>
										<input v-validate="'max:15'" data-vv-name="dealer.vat_registration_number" type="text" class="form-control" v-model="new_dealer.tax_information.vat.registration_number">
										<span class="text-danger" v-show="errors.has('dealer.vat_registration_number')">Can not exceed 15 characters.</span>
									</div>
								</div>
								<div class="clearfix"></div>
							</template>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<button class="btn btn-default btn-sm pull-right" @click="cancel" style="margin-left: 10px;">CANCEL</button>
					<button class="btn btn-primary btn-sm pull-right" v-if="new_dealer.dealer_type != ''" @click="save">SAVE</button>	
				</div>
			</div>
		</section>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Vue from 'vue'
	import Country from '../Country'
	import TaxType from '../TaxType'
	import {EventBus} from '../../configs/EventBus'
	import PaymentTerms from '../master/PaymentTerms'
	import PaymentMethod from '../master/PaymentMethod'
	import TaxTypes from '../companies/TaxTypes'
	import States from '../companies/States'
	import GstCustomerType from '../tax-information/gst/CustomerType'
	import PanStatus from '../tax-information/pan/PanStatus'
	import GSTINValidator from '../../validators/gstin.js'
	import phoneValidator from '../../validators/phoneNumber.js'
	import { Validator } from 'vee-validate';
	Validator.extend('phone',phoneValidator)
	Validator.extend('gstin',GSTINValidator)
	export default{
		mixins : [appMixin],
		components:{
			Country,
			PaymentTerms,
			PaymentMethod,
			TaxTypes,
			States,
			GstCustomerType,
			PanStatus,
		},
		data(){
			return {
				company:{
					id:null,
					region:{
						tax_information:[],
						country:{
							code:""
						}
					}
				},
				contact:"",
				new_dealer:{
					id:null,
					name: "",
					contact: null,
					address:"",
					address2:"",
					country:null,
					state:null,
					city:null,
					zipcode:"",
					paymentTerms:null,
					paymentMethod:null,
					credit_limit:null,
					overdue_balance:0,
					email:"",
					phone:"",
					password:"",
					fax:"",
					dealer_type:"",
					tax_information:{
						tax:{
							lst:"",
							cst:""
						},
						vat:{
							//tin:"",
							registration_number:"",
						},
						gst:{
							customer_type:{
								key:"Registered",
								value:"Registered",
							},//#780
							registration_type:"GSTIN",
							number:""
						},
						pan:{
							number:"",
							status:null,
							reference_number:""
						}
					},
					user:{
						email:null,
						phone:null,
						password:null
					}
				},
				brand:window.config.brand_short,
			}
		},
		mounted(){
			this.company.id = this.distributor.company.id
			Vue.axios.get(`companies/${this.company.id}`)
			.then(response => {
				this.company = Object.assign({},response.data)
			})

			if(this.$route.params.dealerid != null)
			{
				EventBus.$emit('loader:show')
				Vue.axios.get(`companies/${this.company.id}/distributors/${this.distributor.id}/dealers/${this.$route.params.dealerid}`)
				.then(response => {
					//EventBus.$emit('loader:hide')
				//	this.new_dealer = Object.assign({},response.data)
					EventBus.$emit('loader:hide')
					let d = Object.assign({},response.data)
					/*if(d.tax_information.gst)
					{
						d.tax_information.gst.customer_type = {
												key:"Registered",
												value:"Registered",
											}
					}//#780*/
					this.new_dealer = Object.assign({},d)

					this.contact = this.new_dealer.contact
				})
				.catch(error => {
					EventBus.$emit('loader:hide')
				})
			}
		},
		computed:{
			selectedState(){
				if(this.new_dealer.state)
					return this.new_dealer.state.code
				return null
			},
			taxInformation(){
				return _.contains(this.company.region.tax_information,'tax');
			},
			gstInformation(){
				return _.contains(this.company.region.tax_information,'gst');
			},
			panInformation(){
				return _.contains(this.company.region.tax_information,'pan');
			},
			vatInformation(){
				return _.contains(this.company.region.tax_information,'vat');
			},
		},
		methods:{
			dealerTypeClicked(a){
				let d = Object.assign({},this.new_dealer)
				if(a == 1)
				{
					if(d.tax_information.gst)
					{
						d.tax_information.gst.customer_type = {
												key:"Registered",
												value:"Registered",
											}
					}
					this.new_dealer = Object.assign({},d)
				}
				else
				{
					if(d.tax_information.gst)
					{
						if(d.tax_information.gst.number == null)
						{
							d.tax_information.gst.number = null
							d.tax_information.gst.customer_type = {
													key:"Unregistered",
													value:"Unregistered",
												}
						}
					}
					this.new_dealer = Object.assign({},d)
				}
			},
			gstCustomerTypeChanged(){
			   let d = Object.assign({},this.new_dealer)
			   if(d.tax_information.gst)
			   {
			       if(d.tax_information.gst.customer_type && d.tax_information.gst.customer_type.key == 'Unregistered')
			       {
			               d.tax_information.gst.number = null
			       }
			       this.new_dealer = Object.assign({},d)
			   } 
			},//#836
			cancel(){
				this.$router.push({name:'my-dealers'})
			},
			saveData(){
				let out = {}
				out.dealer_type = this.new_dealer.dealer_type ? this.new_dealer.dealer_type : 1,
				out.distributorId = this.distributor.id
				out.companyId = this.company.id
				out.name = this.new_dealer.name
				//out.contact = this.new_dealer.contact
				out.contact = this.contact
				out.address = this.new_dealer.address
				out.address2 = this.new_dealer.address2
				out.state = this.new_dealer.state.code
				out.city = this.new_dealer.city
				out.zipcode = this.new_dealer.zipcode
				out.paymentTerms = this.new_dealer.paymentTerms.key
				out.paymentMethod = this.new_dealer.paymentMethod.key
				out.credit_limit = this.new_dealer.credit_limit
				out.phone = this.new_dealer.phone
				out.email = this.new_dealer.email
				out.password = this.new_dealer.password
				out.fax = this.new_dealer.fax
				out.tax_information = {}
				if(isNaN(parseInt(this.new_dealer.overdue_balance)))
				{
					out.overdue_balance = 0	
				}
				else
				{
					out.overdue_balance = this.new_dealer.overdue_balance
				}
				if(this.vatInformation)
				{
					out.tax_information.vat = {
						//	tin:this.new_dealer.tax_information.vat.tin,
							registration_number:this.new_dealer.tax_information.vat.registration_number,
						}
				}
				if(this.gstInformation)
				{
					out.tax_information.gst = {
						customer_type:"",
						registration_type:"GSTIN",
						//number:this.new_dealer.tax_information.gst.number.toUpperCase()
						number:this.new_dealer.tax_information.gst.number
					}
				/*	if(this.new_dealer.tax_information.gst.number)
					{
						out.tax_information.gst.number = this.new_dealer.tax_information.gst.number.toUpperCase()
					}*/
					if(this.new_dealer.tax_information.gst.customer_type)
					{
						out.tax_information.gst.customer_type = this.new_dealer.tax_information.gst.customer_type.key
					}
				}
				if(this.taxInformation)
				{
					out.tax_information.tax = {
						lst:this.new_dealer.tax_information.tax.lst,
						cst:this.new_dealer.tax_information.tax.cst,
					}
				}
				if(this.panInformation)
				{
					out.tax_information.pan = {
						number:this.new_dealer.tax_information.pan.number,
						status:"",
						reference_number:this.new_dealer.tax_information.pan.reference_number
					}
					if(this.new_dealer.tax_information.pan.status)
					{
						out.tax_information.pan.status = this.new_dealer.tax_information.pan.status.key
					}
				}
				if(this.new_dealer){
					out.user = {
						email:this.new_dealer.email,
						phone:this.new_dealer.phone,
						password:this.new_dealer.password,
					}
				}
				return out
			},
			save(){
				let self = this 
				//let url = `temp_dealers`
				let url = `companies/${self.distributor.company.id}/distributors/${self.distributor.id}/dealers`
				let method = 'post'
				//let message = 'Dealer record sent for Approval to Company. You will be intimated over email/notification once the Dealer is Approved/Rejected.'
				let message = 'Dealer created!'
				if(self.new_dealer.dealer_type == '2')
					message = 'Retail Customer created!'

				if(self.new_dealer.id != null)
				{
					url = `companies/${self.distributor.company.id}/distributors/${self.distributor.id}/dealers/${self.new_dealer.id}`
					method = "put"
					message = 'Dealer record updated'
					if(self.new_dealer.dealer_type == '2')
						message = 'Retail Customer record updated!'
				}

				self.$validator.validateAll()
				.then(result => {
					if(result)
					{
						EventBus.$emit('loader:show', 'Please wait, this may take a minute...')
						Vue.axios[method](url,this.saveData())
						.then(response => {
							EventBus.$emit('loader:hide')
							swal({
								text:message,
								icon:"success"
							})
							.then(value=>{
							//	self.$router.push({name:'pending-approval-dealers'})
								self.$router.push({name:'my-dealers'})
							})
						})
						.catch(error => {
							if (error.response.data['user.email']) 
							{
								return swal ( "Error" ,  "Email has already been taken",  "error" )
		    					.then(value =>{
		    						self.new_dealer.email = null
		    					})
							}
							if (error.response.data['user.phone']) 
							{
								return swal ( "Error" ,  "Phone has already been taken",  "error" )
		    					.then(value =>{
		    						self.new_dealer.phone = null
		    					})
							}
							if(error.response.status == 400)
		                    {
		                    	return swal ( "Error" ,  "Duplicate GSTIN!",  "error" )
		                        .then(value =>{
		                            self.new_dealer.tax_information.gst.number = null
		                        })
		                    }
		                    if(error.response.status == 409)
		                    {
		                    	return swal ( "Error" ,  "Invalid GSTIN!",  "error" )
		                        .then(value =>{
		                            self.new_dealer.tax_information.gst.number = null
		                        })
		                    }
		                    if(error.response.status == 500)
		                    {
		                    	return swal({
		                       		title:'Oops...',
		                       		text:"Something went wrong while processing your request. Please contact your Relationship Manager.",
				    				icon:'error',
								})
								return
		                    }
			            })
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
			}
		}
	}
</script>