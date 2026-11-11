<template>
	<div v-if="abilities.createRetailCustomers">
		<section class="content-header">
			<h1>
				<template v-if="new_retail_customer.id">Edit </template>
				<template v-else>Create </template> Retail Customer 
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
              					<div class="col-md-12">
              						<div class="form-group" :class="{'has-error':errors.has('retail_customer.name')}">
										<label>Name *</label>
										<input type="text" class="form-control" v-model="new_retail_customer.name" v-validate="'required'" data-vv-name="retail_customer.name">
									</div>
              					</div>
              				</div>
          					<div class="row">
          						<div class="col-md-12">
          							<div class="form-group" :class="{'has-error':errors.has('retail_customer.address')}">
										<label>Address *</label>
										<input type="text" class="form-control" v-model="new_retail_customer.address" v-validate="'required'" data-vv-name="retail_customer.address"">
									</div>
          						</div>
          						<div class="col-md-12">
          							<div class="form-group">
										<label>Address 2</label>
										<input type="text" class="form-control" v-model="new_retail_customer.address2">
									</div>
          						</div>
          					</div>
          					<div class="row">
          						<div class="col-md-4">
          							<div class="form-group" :class="{'has-error':errors.has('retail_customer.state')}">
										<label>State *</label>
										<states v-model="new_retail_customer.state" :company="company" v-validate="'required'" data-vv-name="retail_customer.state" v-if="company.id"></states>
									</div>
          						</div>
          						<div class="col-md-4">
          							<div class="form-group" :class="{'has-error':errors.has('retail_customer.city')}">
										<label>City *</label>
										<input type="text" class="form-control" v-model="new_retail_customer.city" v-validate="'required'" data-vv-name="retail_customer.city">
									</div>
          						</div>
          						<div class="col-md-4">
          							<div class="form-group" :class="{'has-error':errors.has('retail_customer.zipcode')}">
										<label>Post code *</label>
										<input type="text" class="form-control" v-model="new_retail_customer.zipcode" v-validate="'required'" data-vv-name="retail_customer.zipcode">
									</div>
          						</div>
          					</div>
          					
          					<div class="row">
          						<div class="col-md-12">
          							<div class="form-group">
										<label>Email</label>
										<input type="text" class="form-control" v-model="new_retail_customer.email">
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Phone</label>
										<input type="text" class="form-control" v-model="new_retail_customer.phone">
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Fax</label>
										<input type="text" class="form-control" v-model="new_retail_customer.fax">
									</div>
          						</div>
          					</div>
          					<div class="row">
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Territory Code</label>
										<territory-code v-model="new_retail_customer.territory"></territory-code>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('retail_customer.billingLocationCode')}">
										<label>Billing Location Code *</label>
										<billing-location v-model="new_retail_customer.billingLocationCode" :company="company" v-validate="'required'" data-vv-name="retail_customer.billingLocationCode"></billing-location>
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
												<input type="text" class="form-control" v-model="new_retail_customer.tax_information.tax.lst">
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label>C.S.T. No.</label>
												<input type="text" class="form-control" v-model="new_retail_customer.tax_information.tax.cst">
											</div>
										</div>
										<div class="clearfix"></div>
									</template>
									<template v-if="gstInformation">
										<h5>GST</h5>
										<div class="col-md-6">
											<div class="form-group">
												<label>GST Customer Type</label>
												<gst-customer-type v-model="new_retail_customer.tax_information.gst.customer_type"></gst-customer-type>
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label>GST Registration Type</label>
												<gst-registration-type v-model="new_retail_customer.tax_information.gst.registration_type"></gst-registration-type>
											</div>
										</div>
										<div class="col-md-12">
											<div class="form-group">
												<label>GST Registration No.</label>
												<input type="text" class="form-control" v-model="new_retail_customer.tax_information.gst.number">
											</div>
										</div>
										<div class="clearfix"></div>
									</template>
									<template v-if="panInformation">
										<h5>Income Tax</h5>
										<div class="col-md-6">
											<div class="form-group">
												<label>P.A.N. Status</label>
												<pan-status v-model="new_retail_customer.tax_information.pan.status"></pan-status>
											</div>
										</div>
										<div class="clearfix"></div>
										<div class="col-md-6">
											<div class="form-group">
												<label>P.A.N. No.</label>
												<input type="text" class="form-control" v-model="new_retail_customer.tax_information.pan.number">
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label>P.A.N. Reference No.</label>
												<input type="text" class="form-control" v-model="new_retail_customer.tax_information.pan.reference_number">
											</div>
										</div>
										<div class="clearfix"></div>
									</template>
									<template v-if="vatInformation">
										<h5>VAT</h5>
										<div class="col-md-6">
											<div class="form-group">
												<label>T.I.N. No.</label>
												<input type="text" class="form-control" v-model="new_retail_customer.tax_information.vat.tin">
											</div>
										</div>
										<div class="col-md-12">
											<div class="checkbox"> 
												<label> 
													<input type="checkbox" v-model="new_retail_customer.tax_information.vat.export"> Export or Deemed Export
												</label> 
											</div>
										</div>
										<div class="col-md-12">
											<div class="checkbox"> 
												<label> 
													<input type="checkbox" v-model="new_retail_customer.tax_information.vat.exempted"> VAT Exempted
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
          							<div class="form-group" :class="{'has-error':errors.has('retail_customer.structure')}">
										<label>Structure *</label>
										<tax-types v-model="new_retail_customer.structure" :company="company" v-validate="'required'" data-vv-name="retail_customer.structure"></tax-types>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Copy Sell-to-Addr.to Qte From</label>
										<copy-sell-from v-model="new_retail_customer.copySellFrom"></copy-sell-from>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Gen. Bus.Posting Group</label>
										<general-business-posting-group v-model="new_retail_customer.generalBusinessPostingGroup"></general-business-posting-group>
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
										<payment-application-method v-model="new_retail_customer.paymentApplicationMethod"></payment-application-method>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Payment Terms Code.</label>
										<payment-terms v-model="new_retail_customer.paymentTerms"></payment-terms>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Payment Method Code</label>
										<payment-method v-model="new_retail_customer.paymentMethod"></payment-method>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Cash flow Payment Terms Code</label>
										<cash-flow-payment-terms v-model="new_retail_customer.cashFlowPaymentTerms"></cash-flow-payment-terms>
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
										<shipping-reserve v-model="new_retail_customer.shippingReserve"></shipping-reserve>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Shipping Advice</label>
										<shipping-advice v-model="new_retail_customer.shippingAdvice"></shipping-advice>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Shipment Method</label>
										<shipping-method v-model="new_retail_customer.shippingMethod"></shipping-method>
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
			<h1>Create Retail Customer</h1>
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
						tax_information:[]
					}
				},
				new_retail_customer:{
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
					tax_information:{
						tax:{
							lst:"",
							cst:""
						},
						vat:{
							tin:"",
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
				}
			}
		},
		mounted(){
			if((!this.isCustomer) && this.state.user.company)
				this.company = 	Object.assign({},this.state.user.company)
			else
			{	
				this.company.id = this.$route.params.id
				Vue.axios.get(`companies/${this.company.id}`)
				.then(response => {
					this.company = Object.assign({},response.data)
				})
			}
			if(this.$route.params.retailCustomerId)
			{
				EventBus.$emit('loader:show')
				Vue.axios.get(`companies/${this.$route.params.id}/retailCustomers/${this.$route.params.retailCustomerId}`)
				.then(response => {
					EventBus.$emit('loader:hide')
					this.new_retail_customer = Object.assign({},response.data)
				})
				.catch(error => {
					EventBus.$emit('loader:hide')
				})
			}
		},
		computed:{
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
				this.$router.push({name:'companies.RetailCustomers',params:{id:this.company.id}})
				//this.$router.push({name:'companies.RetailCustomers',params:{id:this.$route.params.id}})
			},
			saveData(){
				let out = {}
				out.name = this.new_retail_customer.name
				out.address = this.new_retail_customer.address
				out.address2 = this.new_retail_customer.address2
				out.state = this.new_retail_customer.state.code
				out.city = this.new_retail_customer.city
				out.zipcode = this.new_retail_customer.zipcode
				out.territory = this.new_retail_customer.territory.key
				out.billingLocationCode = this.new_retail_customer.billingLocationCode.code
				out.generalBusinessPostingGroup = this.new_retail_customer.generalBusinessPostingGroup.key
				out.paymentApplicationMethod = this.new_retail_customer.paymentApplicationMethod.key
				out.paymentTerms = this.new_retail_customer.paymentTerms.key
				out.cashFlowPaymentTerms = this.new_retail_customer.cashFlowPaymentTerms.key
				out.shippingReserve = this.new_retail_customer.shippingReserve.key
				out.shippingAdvice = this.new_retail_customer.shippingAdvice.key
				out.paymentMethod = this.new_retail_customer.paymentMethod.key
				out.shippingMethod = this.new_retail_customer.shippingMethod.key
				out.copySellFrom = this.new_retail_customer.copySellFrom.key
				out.structure = this.new_retail_customer.structure.code
				out.phone = this.new_retail_customer.phone
				out.email = this.new_retail_customer.email
				out.fax = this.new_retail_customer.fax
				out.tax_information = {}
				if(this.vatInformation)
				{
					out.tax_information.vat = {
							tin:this.new_retail_customer.tax_information.vat.tin,
							export:this.new_retail_customer.tax_information.vat.export,
							exempted:this.new_retail_customer.tax_information.vat.exempted
						}
				}
				if(this.gstInformation)
				{
					out.tax_information.gst = {
						customer_type:"",
						registration_type:"",
						number:this.new_retail_customer.tax_information.gst.number
					}
					if(this.new_retail_customer.tax_information.gst.customer_type)
					{
						out.tax_information.gst.customer_type = this.new_retail_customer.tax_information.gst.customer_type.key
					}
					if(this.new_retail_customer.tax_information.gst.registration_type)
					{
						out.tax_information.gst.registration_type = this.new_retail_customer.tax_information.gst.registration_type.key
					}

				}
				if(this.taxInformation)
				{
					out.tax_information.tax = {
						lst:this.new_retail_customer.tax_information.tax.lst,
						cst:this.new_retail_customer.tax_information.tax.cst,
					}
				}
				if(this.panInformation)
				{
					out.tax_information.pan = {
						number:this.new_retail_customer.tax_information.pan.number,
						status:"",
						reference_number:this.new_retail_customer.tax_information.pan.reference_number
					}
					if(this.new_retail_customer.tax_information.pan.status)
					{
						out.tax_information.pan.status = this.new_retail_customer.tax_information.pan.status.key
					}
				}
				return out
			},
			save(){
				let self = this 
				let url = `companies/${this.company.id}/retailCustomers`
				let method = 'post'
				let message = 'Retail Customer Created'
				if(this.new_retail_customer.id)
				{
					url = `companies/${this.company.id}/retailCustomers/${this.new_retail_customer.id}`
					method = "put"
					message = 'Retail Customer Updated'
				}
				
				this.$validator.validateAll()
				.then(result => {
					if(result)
					{
						EventBus.$emit('loader:show')
						Vue.axios[method](url,this.saveData())
						.then(response => {
							EventBus.$emit('loader:hide')
							swal({
								text:message,
								icon:"success"
							})
							.then(value=>{
								self.$router.push({name:'companies.RetailCustomers',params:{id:self.company.id}})
							})
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