<template>
	<div v-if="abilities.createSuperStockists">
		<section class="content-header">
			<h1>
				<template v-if="new_super_stockist.id">Edit </template>
				<template v-else>Create </template> Super Stockist 
				<span class="pull-right">
					<button class="btn btn-primary btn-sm" @click="save">SAVE</button> 
					<button class="btn btn-default btn-sm" @click="cancel">CANCEL</button>
				</span>
			</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-6">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-12">
									<h4>General</h4>
									<hr>
								</div>
              					<div class="col-md-6">
              						<div class="form-group" :class="{'has-error':errors.has('super_stockist.name')}">
										<label>Name *</label>
										<input type="text" class="form-control" v-model="new_super_stockist.name" v-validate="'required'" data-vv-name="super_stockist.name">
									</div>
              					</div>
								  <div class="col-md-6">
              						<div class="form-group" :class="{'has-error':errors.has('super_stockist.name')}">
										<label>Display Name *</label>
										<input type="text" class="form-control" v-model="new_super_stockist.display_name" v-validate="'required'" data-vv-name="super_stockist.display_name">
									</div>
              					</div>
              				</div>
              				<div class="row">
          						<div class="col-md-12">
          							<div class="form-group" :class="{'has-error':errors.has('super_stockist.address')}">
										<label>Address *</label>
										<input type="text" class="form-control" v-model="new_super_stockist.address" v-validate="'max:50'" data-vv-name="super_stockist.address">
										<span class="text-danger" v-show="errors.has('super_stockist.address')">Address can not exceed 50 characters. Please enter remaining address in Address 2.</span>
									</div>
          						</div>
          						<div class="col-md-12">
          							<div class="form-group" :class="{'has-error':errors.has('super_stockist.address2')}">
										<label>Address 2</label>
										<input type="text" class="form-control" v-model="new_super_stockist.address2" v-validate="'max:50'" data-vv-name="super_stockist.address2">
										<span class="text-danger" v-show="errors.has('super_stockist.address2')">Address 2 can not exceed 50 characters.</span>
									</div>
          						</div>
          					</div>
          					<div class="row">
          						<div class="col-md-4">
          							<div class="form-group" :class="{'has-error':errors.has('super_stockist.state')}">
										<label>State *</label>
										<states v-model="new_super_stockist.state" :company="company" v-validate="'required'" data-vv-name="super_stockist.state" v-if="company.id"></states>
									</div>
          						</div>
          						<div class="col-md-4">
          							<div class="form-group">
										<label>City</label>
										<input type="text" class="form-control" v-model="new_super_stockist.city">
									</div>
          						</div>
          						<div class="col-md-4">
          							<div class="form-group">
										<label>Post code</label>
										<input type="text" class="form-control" v-model="new_super_stockist.zipcode">
									</div>
          						</div>
          					</div>
          					
          					<div class="row">
          						<div class="col-md-12">
          							<div class="form-group" :class="{'has-error':errors.has('email')}">
										<label>Email</label>
										<input type="text" class="form-control" v-model="new_super_stockist.email" v-validate="'email'" data-vv-name="email">
										<span class="text-danger" v-show="errors.has('email')">Please enter valid email</span>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('new_super_stockist.phone')}">
										<label>Phone</label>
										<input type="text" class="form-control" v-model="new_super_stockist.phone" v-validate="`phone:${company.region.country.code}|numeric`" data-vv-name="new_super_stockist.phone">
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Fax</label>
										<input type="text" class="form-control" v-model="new_super_stockist.fax">
									</div>
          						</div>
          					</div>
          					<div class="row">
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Territory Code</label>
										<territory-code v-model="new_super_stockist.territory"></territory-code>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('super_stockist.billingLocationCode')}">
										<label>Billing Location Code *</label>
										<billing-location v-model="new_super_stockist.billingLocationCode" :company="company" v-validate="'required'" data-vv-name="super_stockist.billingLocationCode"></billing-location>
									</div>
          						</div>
          					</div>
          					<div class="row">
								<div class="col-md-12">
									<h4>Tax Information</h4>
									<hr>
									<template v-if="taxInformation">
										<h5>Tax</h5>
										<div class="col-md-6">
											<div class="form-group">
												<label>L.S.T. No.</label>
												<input type="text" class="form-control" v-model="new_super_stockist.tax_information.tax.lst">
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label>C.S.T. No.</label>
												<input type="text" class="form-control" v-model="new_super_stockist.tax_information.tax.cst">
											</div>
										</div>
										<div class="clearfix"></div>
									</template>
									<template v-if="gstInformation">
										<h5>GST</h5>
										<div class="col-md-6">
											<div class="form-group">
												<label>GST Customer Type</label>
												<gst-customer-type v-model="new_super_stockist.tax_information.gst.customer_type"></gst-customer-type>
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label>GST Registration Type</label>
												<gst-registration-type v-model="new_super_stockist.tax_information.gst.registration_type"></gst-registration-type>
											</div>
										</div>
										<div class="col-md-12" v-if="new_super_stockist.tax_information.gst.customer_type && (new_super_stockist.tax_information.gst.customer_type.key == 'Registered')">
											<div class="form-group" :class="{'has-error':errors.has('new_super_stockist.tax_information.gst.number')}">
												<label>GST Registration No.</label>
												<input type="text" class="form-control" v-model="new_super_stockist.tax_information.gst.number" v-validate="`required|gstin:${selectedState}`" data-vv-name="new_super_stockist.tax_information.gst.number" :disabled="!new_super_stockist.state">
												<span class="text-danger" v-show="errors.has('new_super_stockist.tax_information.gst.number')">Please enter valid GST No.</span>
											</div>
										</div>
										<div class="clearfix"></div>
									</template>
									<template v-if="panInformation && company.region.country.code!='AE'">
										<h5>Income Tax</h5>
										<div class="col-md-6">
											<div class="form-group">
												<label>P.A.N. Status</label>
												<pan-status v-model="new_super_stockist.tax_information.pan.status"></pan-status>
											</div>
										</div>
										<div class="clearfix"></div>
										<div class="col-md-6">
											<div class="form-group">
												<label>P.A.N. No.</label>
												<input type="text" class="form-control" v-model="new_super_stockist.tax_information.pan.number">
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label>P.A.N. Reference No.</label>
												<input type="text" class="form-control" v-model="new_super_stockist.tax_information.pan.reference_number">
											</div>
										</div>
										<div class="clearfix"></div>
									</template>
									<template v-if="vatInformation">
										<h5>VAT</h5>
										<!--<div class="col-md-6">
											<div class="form-group">
												<label>T.I.N. No.</label>
												<input type="text" class="form-control" v-validate="'max:12'" data-vv-name="super_stockist.tin" v-model="new_super_stockist.tax_information.vat.tin">
												<span class="text-danger" v-show="errors.has('super_stockist.tin')">Can not exceed 12 characters.</span>
											</div>
										</div>#675-->
										<div class="col-md-6">
											<div class="form-group">
												<label>VAT Registration No.</label>
												<input v-validate="'max:15'" data-vv-name="super_stockist.vat_registration_number" type="text" class="form-control" v-model="new_super_stockist.tax_information.vat.registration_number">
												<span class="text-danger" v-show="errors.has('super_stockist.vat_registration_number')">Can not exceed 15 characters.</span>
											</div>
										</div>
										<div class="col-md-12">
											<div class="checkbox"> 
												<label> 
													<input type="checkbox" v-model="new_super_stockist.tax_information.vat.export"> Export or Deemed Export
												</label> 
											</div>
										</div>
										<div class="col-md-12">
											<div class="checkbox"> 
												<label> 
													<input type="checkbox" v-model="new_super_stockist.tax_information.vat.exempted"> VAT Exempted
												</label> 
											</div>
										</div>
										<div class="clearfix"></div>
									</template>
								</div>
          					</div>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-12">
									<h4>Invoicing</h4>
									<hr>
								</div>
          						<div class="col-md-12">
          							<div class="form-group" :class="{'has-error':errors.has('super_stockist.structure')}">
										<label>Structure *</label>
										<tax-types v-model="new_super_stockist.structure" :company="company" v-validate="'required'" data-vv-name="super_stockist.structure"></tax-types>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Copy Sell-to-Addr.to Qte From</label>
										<copy-sell-from v-model="new_super_stockist.copySellFrom"></copy-sell-from>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Gen. Bus.Posting Group</label>
										<general-business-posting-group v-model="new_super_stockist.generalBusinessPostingGroup"></general-business-posting-group>
									</div>
          						</div>
          					</div>

          					<div class="row">
								<div class="col-md-12">
									<h4>Payments</h4>
									<hr>
								</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Application Method</label>
										<payment-application-method v-model="new_super_stockist.paymentApplicationMethod"></payment-application-method>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Payment Terms Code.</label>
										<payment-terms v-model="new_super_stockist.paymentTerms"></payment-terms>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Payment Method Code</label>
										<payment-method v-model="new_super_stockist.paymentMethod"></payment-method>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Cash flow Payment Terms Code</label>
										<cash-flow-payment-terms v-model="new_super_stockist.cashFlowPaymentTerms"></cash-flow-payment-terms>
									</div>
          						</div>
          					</div>

          					<div class="row">
								<div class="col-md-12">
									<h4>Shipping</h4>
									<hr>
								</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Reserve</label>
										<shipping-reserve v-model="new_super_stockist.shippingReserve"></shipping-reserve>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Shipping Advice</label>
										<shipping-advice v-model="new_super_stockist.shippingAdvice"></shipping-advice>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Shipment Method</label>
										<shipping-method v-model="new_super_stockist.shippingMethod"></shipping-method>
									</div>
          						</div>
          					</div>
          					<div class="row">
          						<div class="col-md-12">
									<h4>Credit Details</h4>
									<hr>
								</div>
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('credit_limit')}">
										<label>Credit Limit *</label>
										<input type="text" class="form-control" v-model="new_super_stockist.credit_limit" v-validate="'required'" data-vv-name="credit_limit">
									</div>
          						</div>
          					</div>
          					<div class="row" v-if="!new_super_stockist.id">
								<div class="col-md-12">
									<h4>{{brand}} User</h4>
									<hr>
								</div>
								<div class="col-md-12">
									<p>Email or Phone is mandatory</p>
								</div>
          						<!--<div class="col-md-6">
          							<div class="form-group">
										<label>Username</label>
										<input type="text" class="form-control" v-model="new_super_stockist.user.username" v-validate="'required'" data-vv-name="username">
										<span class="text-danger" v-show="errors.has('username')">Please enter Username.</span>
									</div>
          						</div>-->
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('userEmail')}">
										<label>Email</label>
										<input type="text" class="form-control" v-model="new_super_stockist.user.email" v-validate="{required:new_super_stockist.user.phone?false:true,email:true}" data-vv-name="userEmail">
									<span class="text-danger" v-show="errors.has('userEmail')">Please enter valid email</span>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('userPhone')}">
										<label>Phone</label>
										<input type="text" class="form-control" v-model="new_super_stockist.user.phone" v-validate="{required:new_super_stockist.user.email?false:true,numeric:true,phone:[company.region.country.code]}" data-vv-name="userPhone">
										<span class="text-danger" v-show="errors.has('userPhone')">Please enter valid phone</span>
									</div>
          						</div>
          						<div class="col-md-12">
          							<div class="form-group" :class="{'has-error':errors.has('password')}">
										<label>Password</label>
										<input type="text" class="form-control" v-model="new_super_stockist.user.password" v-validate="'required|min:8'" data-vv-name="password">
										<span class="text-danger" v-show="errors.has('password')">Please enter minimum 8 characters</span>
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
			<h1>Create Super Stockist</h1>
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
	import Country from '../Country'
	import TaxType from '../TaxType'
	import {EventBus} from '../../configs/EventBus'
	import TerritoryCode from '../master/Territory'
	import GeneralBusinessPostingGroup from '../master/GeneralBusinessPostingGroup'
	import PaymentApplicationMethod from '../master/PaymentApplicationMethod'
	import PaymentTerms from '../master/PaymentTerms'
	import PaymentMethod from '../master/PaymentMethod'
	import CashFlowPaymentTerms from '../master/CashFlowPaymentTerms'
	import ShippingReserve from '../master/ShippingReserve'
	import ShippingAdvice from '../master/ShippingAdvice'
	import ShippingMethod from '../master/ShippingMethod'
	import CopySellFrom from '../master/CopySellFrom'
	import TaxTypes from '../companies/TaxTypes'

	import States from '../companies/States'
	import BillingLocation from '../companies/BillingLocations'
	import GstCustomerType from '../tax-information/gst/CustomerType'
	import GstRegistrationType from '../tax-information/gst/RegistrationType'
	import PanStatus from '../tax-information/pan/PanStatus'
	import phoneValidator from '../../validators/phoneNumber.js'
	import GSTINValidator from '../../validators/gstin.js'
	import { Validator } from 'vee-validate';
	Validator.extend('phone',phoneValidator)
	Validator.extend('gstin',GSTINValidator)
	Vue.use(VeeValidate)
	export default{
		mixins : [appMixin],
		components:{
			Country,
			TerritoryCode,
			GeneralBusinessPostingGroup,
			PaymentApplicationMethod,
			PaymentTerms,
			PaymentMethod,
			CashFlowPaymentTerms,
			ShippingReserve,
			ShippingAdvice,
			ShippingMethod,
			CopySellFrom,
			TaxTypes,
			States,
			BillingLocation,
			GstCustomerType,
			GstRegistrationType,
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
				new_super_stockist:{
					id:null,
					name: "",
					address:"",
					address2:"",
					country:null,
					state:null,
					city:null,
					zipcode:"",
					territory:null,
					billingLocationCode:null,
					generalBusinessPostingGroup:null,
					paymentApplicationMethod:null,
					paymentTerms:null,
					paymentMethod:null,
					cashFlowPaymentTerms:null,
					shippingReserve:null,
					shippingAdvice:null,
					shippingMethod:null,
					copySellFrom:null,
					structure:null,
					email:"",
					phone:"",
					fax:"",
					credit_limit:0,
					tax_information:{
						tax:{
							lst:"",
							cst:""
						},
						vat:{
						//	tin:"",
							registration_number:"",
							export:false,
							exempted:false
						},
						gst:{
							customer_type:null,
							registration_type:null,
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
				brand:window.config.brand_short
			}
		},
		mounted(){
			this.company.id = this.$route.params.id
			if(!this.state.user.company)
			{
				Vue.axios.get(`companies/${this.company.id}`)
				.then(response => {
					this.company = Object.assign({},response.data)
				})
			}
			if(this.$route.params.super_stockist_id)
			{
				EventBus.$emit('loader:show')
				Vue.axios.get(`companies/${this.$route.params.id}/super_stockists/${this.$route.params.super_stockist_id}`)
				.then(response => {
					EventBus.$emit('loader:hide')
					this.new_super_stockist = Object.assign({},response.data)
				})
				.catch(error => {
					EventBus.$emit('loader:hide')
				})
			}
		},
		computed:{
			selectedState(){
				if(this.new_super_stockist.state)
					return this.new_super_stockist.state.code
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
			cancel(){
				this.$router.push({name:'companies.super_stockists',params:{id:this.$route.params.id}})
			},
			saveData(){
				let out = {}
				out.name = this.new_super_stockist.name
				out.address = this.new_super_stockist.address
				out.address2 = this.new_super_stockist.address2
				out.state = this.new_super_stockist.state.code
				out.city = this.new_super_stockist.city
				out.zipcode = this.new_super_stockist.zipcode
				out.territory = this.new_super_stockist.territory.key
				out.billingLocationCode = this.new_super_stockist.billingLocationCode.code
				out.generalBusinessPostingGroup = this.new_super_stockist.generalBusinessPostingGroup.key
				out.paymentApplicationMethod = this.new_super_stockist.paymentApplicationMethod.key
				out.paymentTerms = this.new_super_stockist.paymentTerms.key
				out.cashFlowPaymentTerms = this.new_super_stockist.cashFlowPaymentTerms.key
				out.shippingReserve = this.new_super_stockist.shippingReserve.key
				out.shippingAdvice = this.new_super_stockist.shippingAdvice.key
				out.paymentMethod = this.new_super_stockist.paymentMethod.key
				out.shippingMethod = this.new_super_stockist.shippingMethod.key
				out.copySellFrom = this.new_super_stockist.copySellFrom.key
				out.structure = this.new_super_stockist.structure.code
				out.phone = this.new_super_stockist.phone
				out.email = this.new_super_stockist.email
				out.fax = this.new_super_stockist.fax
				out.credit_limit = this.new_super_stockist.credit_limit
				out.display_name = this.new_super_stockist.display_name
				out.tax_information = {}
				if(this.vatInformation)
				{
					out.tax_information.vat = {
					//		tin:this.new_super_stockist.tax_information.vat.tin,
							registration_number:this.new_super_stockist.tax_information.vat.registration_number,
							export:this.new_super_stockist.tax_information.vat.export,
							exempted:this.new_super_stockist.tax_information.vat.exempted
						}
				}
				if(this.gstInformation)
				{
					out.tax_information.gst = {
						customer_type:"",
						registration_type:"",
						number:this.new_super_stockist.tax_information.gst.number
					}
					if(this.new_super_stockist.tax_information.gst.customer_type)
					{
						out.tax_information.gst.customer_type = this.new_super_stockist.tax_information.gst.customer_type.key
					}
					if(this.new_super_stockist.tax_information.gst.registration_type)
					{
						out.tax_information.gst.registration_type = this.new_super_stockist.tax_information.gst.registration_type.key
					}

				}
				if(this.taxInformation)
				{
					out.tax_information.tax = {
						lst:this.new_super_stockist.tax_information.tax.lst,
						cst:this.new_super_stockist.tax_information.tax.cst,
					}
				}
				if(this.panInformation)
				{
					out.tax_information.pan = {
						number:this.new_super_stockist.tax_information.pan.number,
						status:"",
						reference_number:this.new_super_stockist.tax_information.pan.reference_number
					}
					if(this.new_super_stockist.tax_information.pan.status)
					{
						out.tax_information.pan.status = this.new_super_stockist.tax_information.pan.status.key
					}
				}
				if(this.new_super_stockist.user){
					out.user = {
						email:this.new_super_stockist.user.email,
						phone:this.new_super_stockist.user.phone,
						password:this.new_super_stockist.user.password
					}
				}
				return out
			},
			save(){
				let self = this 
				let url = `companies/${this.company.id}/super_stockists`
				let method = 'post'
				let message = 'Super Stockist Created'
				if(this.new_super_stockist.id)
				{
					url = `companies/${this.company.id}/super_stockists/${this.new_super_stockist.id}`
					method = "put"
					message = 'Super Stockist Updated'
				}
				
				this.$validator.validateAll()
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
								self.$router.push({name:'companies.super_stockists',params:{id:self.company.id}})
							})
						})
						.catch(error => {
							if (error.response.data['user.email']) 
							{
        						swal ( "Error" ,  "The user email has already been taken",  "error" )
		    					.then(value =>{
		    						self.new_super_stockist.user.email = null
		    					})
        					}
        					if (error.response.data['user.phone']) 
							{
        						swal ( "Error" ,  "The user phone has already been taken",  "error" )
		    					.then(value =>{
		    						self.new_super_stockist.user.phone = null
		    					})
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