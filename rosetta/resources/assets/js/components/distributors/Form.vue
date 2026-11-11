<template>
	<div v-if="abilities.createDistributors">
		<section class="content-header">
			<h1>
				<template v-if="new_distributor.id">Edit </template>
				<template v-else>Create </template> Distributor 
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
              						<div class="form-group" :class="{'has-error':errors.has('distributor.name')}">
										<label>Name *</label>
										<input type="text" class="form-control" v-model="new_distributor.name" v-validate="'required'" data-vv-name="distributor.name">
									</div>
              					</div>
								  <div class="col-md-6">
              						<div class="form-group" :class="{'has-error':errors.has('super_stockist.name')}">
										<label>Display Name *</label>
										<input type="text" class="form-control" v-model="new_distributor.display_name" v-validate="'required'" data-vv-name="new_distributor.display_name">
									</div>
              					</div>
              					<div class="col-md-12" v-if="!state.superStockist">
				                    <div class="form-group" :class="errors.has('selectedSuperStockist') ? 'has-error' : ''">
					                    <label>Super Stockist</label>
					                     <customer-select v-model="selectedSuperStockist" :company="company" :customer_type="customer_type" :disabled="!company" ref="customerSelect"></customer-select>
					                </div>
				                </div><!--//#894-->
              				</div>
              				<div class="row">
          						<div class="col-md-12">
          							<div class="form-group" :class="{'has-error':errors.has('distributor.address')}">
										<label>Address *</label>
										<input type="text" class="form-control" v-model="new_distributor.address" v-validate="'max:50'" data-vv-name="distributor.address">
										<span class="text-danger" v-show="errors.has('distributor.address')">Address can not exceed 50 characters. Please enter remaining address in Address 2.</span>
									</div>
          						</div>
          						<div class="col-md-12">
          							<div class="form-group" :class="{'has-error':errors.has('distributor.address2')}">
										<label>Address 2</label>
										<input type="text" class="form-control" v-model="new_distributor.address2" v-validate="'max:50'" data-vv-name="distributor.address2">
										<span class="text-danger" v-show="errors.has('distributor.address2')">Address 2 can not exceed 50 characters.</span>
									</div>
          						</div>
          					</div>
          					<div class="row">
          						<div class="col-md-4">
          							<div class="form-group" :class="{'has-error':errors.has('distributor.state')}">
										<label>State *</label>
										<states v-model="new_distributor.state" :company="company" v-validate="'required'" data-vv-name="distributor.state" v-if="company.id"></states>
									</div>
          						</div>
          						<div class="col-md-4">
          							<div class="form-group">
										<label>City</label>
										<input type="text" class="form-control" v-model="new_distributor.city">
									</div>
          						</div>
          						<div class="col-md-4">
          							<div class="form-group">
										<label>Post code</label>
										<input type="text" class="form-control" v-model="new_distributor.zipcode">
									</div>
          						</div>
          					</div>
          					
          					<div class="row">
          						<div class="col-md-12">
          							<div class="form-group" :class="{'has-error':errors.has('email')}">
										<label>Email</label>
										<input type="text" class="form-control" v-model="new_distributor.email" v-validate="'email'" data-vv-name="email">
										<span class="text-danger" v-show="errors.has('email')">Please enter valid email</span>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('new_distributor.phone')}">
										<label>Phone</label>
										<input type="text" class="form-control" v-model="new_distributor.phone" v-validate="`phone:${company.region.country.code}|numeric`" data-vv-name="new_distributor.phone">
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Fax</label>
										<input type="text" class="form-control" v-model="new_distributor.fax">
									</div>
          						</div>
          					</div>
          					<div class="row">
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Territory Code</label>
										<territory-code v-model="new_distributor.territory"></territory-code>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('distributor.billingLocationCode')}">
										<label>Billing Location Code *</label>
										<billing-location v-model="new_distributor.billingLocationCode" :company="company" v-validate="'required'" data-vv-name="distributor.billingLocationCode"></billing-location>
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
												<input type="text" class="form-control" v-model="new_distributor.tax_information.tax.lst">
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label>C.S.T. No.</label>
												<input type="text" class="form-control" v-model="new_distributor.tax_information.tax.cst">
											</div>
										</div>
										<div class="clearfix"></div>
									</template>
									<template v-if="gstInformation">
										<h5>GST</h5>
										<div class="col-md-6">
											<div class="form-group">
												<label>GST Customer Type</label>
												<gst-customer-type v-model="new_distributor.tax_information.gst.customer_type"></gst-customer-type>
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label>GST Registration Type</label>
												<gst-registration-type v-model="new_distributor.tax_information.gst.registration_type"></gst-registration-type>
											</div>
										</div>
										<div class="col-md-12" v-if="new_distributor.tax_information.gst.customer_type && (new_distributor.tax_information.gst.customer_type.key == 'Registered')">
											<div class="form-group" :class="{'has-error':errors.has('new_distributor.tax_information.gst.number')}">
												<label>GST Registration No.</label>
												<input type="text" class="form-control" v-model="new_distributor.tax_information.gst.number" v-validate="`required|gstin:${selectedState}`" data-vv-name="new_distributor.tax_information.gst.number" :disabled="!new_distributor.state">
												<span class="text-danger" v-show="errors.has('new_distributor.tax_information.gst.number')">Please enter valid GST No.</span>
											</div>
										</div>
										<div class="clearfix"></div>
									</template>
									<template v-if="panInformation && company.region.country.code!='AE'">
										<h5>Income Tax</h5>
										<div class="col-md-6">
											<div class="form-group">
												<label>P.A.N. Status</label>
												<pan-status v-model="new_distributor.tax_information.pan.status"></pan-status>
											</div>
										</div>
										<div class="clearfix"></div>
										<div class="col-md-6">
											<div class="form-group">
												<label>P.A.N. No.</label>
												<input type="text" class="form-control" v-model="new_distributor.tax_information.pan.number">
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label>P.A.N. Reference No.</label>
												<input type="text" class="form-control" v-model="new_distributor.tax_information.pan.reference_number">
											</div>
										</div>
										<div class="clearfix"></div>
									</template>
									<template v-if="vatInformation">
										<h5>VAT</h5>
										<!--<div class="col-md-6">
											<div class="form-group">
												<label>T.I.N. No.</label>
												<input type="text" class="form-control" v-validate="'max:12'" data-vv-name="distributor.tin" v-model="new_distributor.tax_information.vat.tin">
												<span class="text-danger" v-show="errors.has('distributor.tin')">Can not exceed 12 characters.</span>
											</div>
										</div>#675-->
										<div class="col-md-6">
											<div class="form-group">
												<label>VAT Registration No.</label>
												<input v-validate="'max:15'" data-vv-name="distributor.vat_registration_number" type="text" class="form-control" v-model="new_distributor.tax_information.vat.registration_number">
												<span class="text-danger" v-show="errors.has('distributor.vat_registration_number')">Can not exceed 15 characters.</span>
											</div>
										</div>
										<div class="col-md-12">
											<div class="checkbox"> 
												<label> 
													<input type="checkbox" v-model="new_distributor.tax_information.vat.export"> Export or Deemed Export
												</label> 
											</div>
										</div>
										<div class="col-md-12">
											<div class="checkbox"> 
												<label> 
													<input type="checkbox" v-model="new_distributor.tax_information.vat.exempted"> VAT Exempted
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
          							<div class="form-group" :class="{'has-error':errors.has('distributor.structure')}">
										<label>Structure *</label>
										<tax-types v-model="new_distributor.structure" :company="company" v-validate="'required'" data-vv-name="distributor.structure"></tax-types>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Copy Sell-to-Addr.to Qte From</label>
										<copy-sell-from v-model="new_distributor.copySellFrom"></copy-sell-from>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Gen. Bus.Posting Group</label>
										<general-business-posting-group v-model="new_distributor.generalBusinessPostingGroup"></general-business-posting-group>
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
										<payment-application-method v-model="new_distributor.paymentApplicationMethod"></payment-application-method>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Payment Terms Code.</label>
										<payment-terms v-model="new_distributor.paymentTerms"></payment-terms>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Payment Method Code</label>
										<payment-method v-model="new_distributor.paymentMethod"></payment-method>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Cash flow Payment Terms Code</label>
										<cash-flow-payment-terms v-model="new_distributor.cashFlowPaymentTerms"></cash-flow-payment-terms>
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
										<shipping-reserve v-model="new_distributor.shippingReserve"></shipping-reserve>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Shipping Advice</label>
										<shipping-advice v-model="new_distributor.shippingAdvice"></shipping-advice>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Shipment Method</label>
										<shipping-method v-model="new_distributor.shippingMethod"></shipping-method>
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
										<input type="text" class="form-control" v-model="new_distributor.credit_limit" v-validate="'required'" data-vv-name="credit_limit">
									</div>
          						</div>
          					</div>
          					<div class="row" v-if="!new_distributor.id">
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
										<input type="text" class="form-control" v-model="new_distributor.user.username" v-validate="'required'" data-vv-name="username">
										<span class="text-danger" v-show="errors.has('username')">Please enter Username.</span>
									</div>
          						</div>-->
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('userEmail')}">
										<label>Email</label>
										<input type="text" class="form-control" v-model="new_distributor.user.email" v-validate="{required:new_distributor.user.phone?false:true,email:true}" data-vv-name="userEmail">
									<span class="text-danger" v-show="errors.has('userEmail')">Please enter valid email</span>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('userPhone')}">
										<label>Phone</label>
										<input type="text" class="form-control" v-model="new_distributor.user.phone" v-validate="{required:new_distributor.user.email?false:true,numeric:true,phone:[company.region.country.code]}" data-vv-name="userPhone">
										<span class="text-danger" v-show="errors.has('userPhone')">Please enter valid phone</span>
									</div>
          						</div>
          						<div class="col-md-12">
          							<div class="form-group" :class="{'has-error':errors.has('password')}">
										<label>Password</label>
										<input type="text" class="form-control" v-model="new_distributor.user.password" v-validate="'required|min:8'" data-vv-name="password">
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
			<h1>Create Distributor</h1>
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
	import CustomerSelect from '../master/CustomerSelect'//#827
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
			CustomerSelect,
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
				new_distributor:{
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
				brand:window.config.brand_short,
				customer_type:{code:'SUPER_STOCKIST',name:"SuperStockist"},
				selectedSuperStockist:null,//#894
			}
		},
		mounted(){
			if(!this.$route.params.id)
			{
				if(this.state.superStockist)
				{
					this.company.id = this.state.superStockist.company.id
				}
			}
			else
			{
				this.company.id = this.$route.params.id
			}
			if(!this.state.user.company)
			{
				Vue.axios.get(`companies/${this.company.id}`)
				.then(response => {
					this.company = Object.assign({},response.data)
				})
			}
			if((this.$route.params.super_stockist_id && this.$route.params.distributorid) || this.state.superStockist)
			{
				EventBus.$emit('loader:show')
				let sid = null
				let cid = null
				if(this.$route.params.super_stockist_id && this.$route.params.distributorid)
				{
					sid = this.$route.params.super_stockist_id
					cid = this.$route.params.id
				}
				if(this.state.superStockist)
				{
					sid = this.state.superStockist.id
					cid = this.state.superStockist.company.id
				}
				Vue.axios.get(`companies/${cid}/super_stockists/${sid}/distributors/${this.$route.params.distributorid}`)
				.then(response => {
					EventBus.$emit('loader:hide')
					let d = Object.assign({},response.data)
					this.new_distributor = Object.assign({},d)
					this.contact = this.new_distributor.contact
					this.selectedSuperStockist = Object.assign({},this.new_distributor.superStockist)//#827
					setTimeout(function(){
						self.$refs.customerSelect.loadCustomers()	
					},300)
				})
				.catch(error => {
					EventBus.$emit('loader:hide')
				})
			}
			else if(this.$route.params.distributorid)
			{
				EventBus.$emit('loader:show')
				Vue.axios.get(`companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}`)
				.then(response => {
					EventBus.$emit('loader:hide')
					this.new_distributor = Object.assign({},response.data)
					this.selectedSuperStockist = Object.assign({},this.new_distributor.superStockist)//#827
					setTimeout(function(){
						self.$refs.customerSelect.loadCustomers()	
					},300)
				})
				.catch(error => {
					EventBus.$emit('loader:hide')
				})
			}
		},
		computed:{
			selectedState(){
				if(this.new_distributor.state)
					return this.new_distributor.state.code
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
				if(this.$route.params.super_stockist_id != null)
					this.$router.push({name:'companies.super_stockists.distributors',params:{id:this.company.id,super_stockist_id:this.$route.params.super_stockist_id}})
				else if(this.state.superStockist)
					this.$router.push({name:'my-distributors'})
				else
					this.$router.push({name:'companies.distributors',params:{id:this.$route.params.id}})
			},
			saveData(){
				let out = {}
				out.name = this.new_distributor.name
				out.address = this.new_distributor.address
				out.address2 = this.new_distributor.address2
				out.state = this.new_distributor.state.code
				out.city = this.new_distributor.city
				out.zipcode = this.new_distributor.zipcode
				out.territory = this.new_distributor.territory.key
				out.billingLocationCode = this.new_distributor.billingLocationCode.code
				out.generalBusinessPostingGroup = this.new_distributor.generalBusinessPostingGroup.key
				out.paymentApplicationMethod = this.new_distributor.paymentApplicationMethod.key
				out.paymentTerms = this.new_distributor.paymentTerms.key
				out.cashFlowPaymentTerms = this.new_distributor.cashFlowPaymentTerms.key
				out.shippingReserve = this.new_distributor.shippingReserve.key
				out.shippingAdvice = this.new_distributor.shippingAdvice.key
				out.paymentMethod = this.new_distributor.paymentMethod.key
				out.shippingMethod = this.new_distributor.shippingMethod.key
				out.copySellFrom = this.new_distributor.copySellFrom.key
				out.structure = this.new_distributor.structure.code
				out.phone = this.new_distributor.phone
				out.email = this.new_distributor.email
				out.fax = this.new_distributor.fax
				out.credit_limit = this.new_distributor.credit_limit
				out.display_name = this.new_distributor.display_name
				if(this.selectedSuperStockist)//#827
				{
					out.newSuperStockistId = this.selectedSuperStockist.id
					out.newSuperStockistNavId = this.selectedSuperStockist.nav_id
				}
				else
				{
					out.newSuperStockistId = null
					out.newSuperStockistNavId = null
				}
				out.tax_information = {}
				if(this.vatInformation)
				{
					out.tax_information.vat = {
					//		tin:this.new_distributor.tax_information.vat.tin,
							registration_number:this.new_distributor.tax_information.vat.registration_number,
							export:this.new_distributor.tax_information.vat.export,
							exempted:this.new_distributor.tax_information.vat.exempted
						}
				}
				if(this.gstInformation)
				{
					out.tax_information.gst = {
						customer_type:"",
						registration_type:"",
						number:this.new_distributor.tax_information.gst.number
					}
					if(this.new_distributor.tax_information.gst.customer_type)
					{
						out.tax_information.gst.customer_type = this.new_distributor.tax_information.gst.customer_type.key
					}
					if(this.new_distributor.tax_information.gst.registration_type)
					{
						out.tax_information.gst.registration_type = this.new_distributor.tax_information.gst.registration_type.key
					}

				}
				if(this.taxInformation)
				{
					out.tax_information.tax = {
						lst:this.new_distributor.tax_information.tax.lst,
						cst:this.new_distributor.tax_information.tax.cst,
					}
				}
				if(this.panInformation)
				{
					out.tax_information.pan = {
						number:this.new_distributor.tax_information.pan.number,
						status:"",
						reference_number:this.new_distributor.tax_information.pan.reference_number
					}
					if(this.new_distributor.tax_information.pan.status)
					{
						out.tax_information.pan.status = this.new_distributor.tax_information.pan.status.key
					}
				}
				if(this.new_distributor.user){
					out.user = {
						email:this.new_distributor.user.email,
						phone:this.new_distributor.user.phone,
						password:this.new_distributor.user.password
					}
				}
				return out
			},
			save(){
				let self = this 
				let url = `companies/${self.company.id}/distributors`
				let method = 'post'
				let message = 'Distributor Created'
				if(self.state.superStockist)
				{
					if(self.new_distributor.id != null)
					{
						method = 'put'
						url =  `companies/${self.state.superStockist.company.id}/super_stockists/${self.state.superStockist.id}/distributors/${self.new_distributor.id}`
						message = 'Distributor updated'
					}
					else
					{
						url =  `companies/${self.state.superStockist.company.id}/super_stockists/${self.state.superStockist.id}/distributors`
						message = 'Distributor created'
					}
					
				}
				if(self.$route.params.super_stockist_id != null && self.new_distributor.id != null)
				{
					url = `companies/${self.company.id}/super_stockists/${self.$route.params.super_stockist_id}/distributors/${self.new_distributor.id}`
					method = "put"
					message = 'Distributor Updated'
				}
				if(self.new_distributor.id && ! self.state.superStockist)
				{
					url = `companies/${self.company.id}/distributors/${self.new_distributor.id}`
					method = "put"
					message = 'Distributor Updated'
				}
				if(self.$route.params.super_stockist_id != null && self.new_distributor.id == null)
				{
					url = `companies/${self.company.id}/super_stockists/${self.$route.params.super_stockist_id}/distributors`
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
								//if(this.superStockist)
								//self.$router.push({name:'companies.super_stockists.distributors'})
								if(self.$route.params.super_stockist_id != null)
									self.$router.push({name:'companies.super_stockists.distributors',params:{id:self.company.id,super_stockist_id:self.$route.params.super_stockist_id}})
								else if(self.state.superStockist)
									self.$router.push({name:'my-distributors'})
								else
									self.$router.push({name:'companies.distributors',params:{id:self.company.id}})
							})
						})
						.catch(error => {
							if (error.response.data['user.email']) 
							{
        						swal ( "Error" ,  "The user email has already been taken",  "error" )
		    					.then(value =>{
		    						self.new_distributor.user.email = null
		    					})
        					}
        					if (error.response.data['user.phone']) 
							{
        						swal ( "Error" ,  "The user phone has already been taken",  "error" )
		    					.then(value =>{
		    						self.new_distributor.user.phone = null
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