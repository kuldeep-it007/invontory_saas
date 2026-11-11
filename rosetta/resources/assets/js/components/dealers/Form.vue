<template>
	<div v-if="abilities.createDealers">
		<section class="content-header">
			<h1>
				<template v-if="new_dealer.id">Edit </template>
				<template v-else>Create </template> Customer 
				<span class="pull-right">
					<button class="btn btn-primary btn-sm" v-if="new_dealer.dealer_type != ''" @click="save">SAVE</button> 
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
	          					<div class="row" >
	          						<div class="col-md-12">
	              						<div class="form-group" :class="{'has-error':errors.has('dealer.name')}">
											<label>Name *</label>
											<input type="text" class="form-control" v-model="new_dealer.name" v-validate="'required'" data-vv-name="dealer.name">
										</div>
	              					</div>
	              					<div class="col-md-12">
					                    <div class="form-group" :class="errors.has('selectedDistributor') ? 'has-error' : ''">
						                    <label>Distributor</label>
						                     <customer-select v-model="selectedDistributor" :company="company" :customer_type="customer_type" :disabled="!company" ref="customerSelect"></customer-select>
						                </div>
					                </div><!--//#827-->
	              					<div class="col-md-12">
	              						<div class="form-group">
											<label>Contact Person</label>
											<input type="text" class="form-control" v-model="contact" :disabled="new_dealer.contact">
										</div>
	              					</div>
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
	          					
	          					<div class="row">
	          						<div class="col-md-12">
	          							<div class="form-group" :class="{'has-error':errors.has('email')}">
											<label>Email</label>
											<input type="text" class="form-control" v-model="new_dealer.email" v-validate="'email'" data-vv-name="email" data-vv-as="User Email">
											<span class="text-danger" v-show="errors.has('email')">Please enter valid email</span>
										</div>
	          						</div>
	          						<div class="col-md-6">
	          							<div class="form-group" :class="{'has-error':errors.has('new_dealer.phone')}">
											<label>Phone</label>
											<input type="text" class="form-control" v-model="new_dealer.phone" v-validate="`phone:${company.region.country.code}|numeric`" data-vv-name="new_dealer.phone">
										</div>
	          						</div>
	          						<div class="col-md-6">
	          							<div class="form-group">
											<label>Fax</label>
											<input type="text" class="form-control" v-model="new_dealer.fax">
										</div>
	          						</div>
	          					</div>
	          					<div class="row">
	          						<div class="col-md-6">
	          							<div class="form-group">
											<label>Territory Code</label>
											<territory-code v-model="new_dealer.territory"></territory-code>
										</div>
	          						</div>
	          						<div class="col-md-6">
	          							<div class="form-group" :class="{'has-error':errors.has('dealer.billingLocationCode')}">
											<label>Billing Location Code *</label>
											<billing-location v-model="new_dealer.billingLocationCode" :company="company" v-validate="'required'" data-vv-name="dealer.billingLocationCode"></billing-location>
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
													<input type="text" class="form-control" v-model="new_dealer.tax_information.tax.lst">
												</div>
											</div>
											<div class="col-md-6">
												<div class="form-group">
													<label>C.S.T. No.</label>
													<input type="text" class="form-control" v-model="new_dealer.tax_information.tax.cst">
												</div>
											</div>
											<div class="clearfix"></div>
										</template>
										<template v-if="gstInformation">
											<h5>GST</h5>
							
											<div class="col-md-6">
												<div class="form-group">
													<label>GST Customer Type</label>
													<gst-customer-type v-model="new_dealer.tax_information.gst.customer_type" :disabled="new_dealer.dealer_type == 1" @input="gstCustomerTypeChanged">Registered</gst-customer-type>
												</div>
											</div>
								
											
											<div class="col-md-6"  v-if="new_dealer.tax_information.gst.customer_type && (new_dealer.tax_information.gst.customer_type.key == 'Registered')">
												<div class="form-group">
													<label>GST Registration Type</label>
													<gst-registration-type v-model="new_dealer.tax_information.gst.registration_type"></gst-registration-type>
												</div>
											</div>
											<div class="col-md-12" v-if="new_dealer.tax_information.gst.customer_type && (new_dealer.tax_information.gst.customer_type.key == 'Registered')">
												<div class="form-group" :class="{'has-error':errors.has('new_dealer.tax_information.gst.number')}">
													<label>GST Registration No.</label>
													<input type="text" class="form-control" v-model="new_dealer.tax_information.gst.number" v-validate="`required|gstin:${selectedState}`" data-vv-name="new_dealer.tax_information.gst.number" :disabled="!new_dealer.state">
													<span class="text-danger" v-show="errors.has('new_dealer.tax_information.gst.number')">Please enter valid GST No.</span>
												</div>
											</div>
											<div class="clearfix"></div>
										</template>
										<template v-if="panInformation && company.region.country.code!='AE'">
											<h5>Income Tax</h5>
											<div class="col-md-6">
												<div class="form-group">
													<label>P.A.N. Status</label>
													<pan-status v-model="new_dealer.tax_information.pan.status"></pan-status>
												</div>
											</div>
											<div class="clearfix"></div>
											<div class="col-md-6">
												<div class="form-group">
													<label>P.A.N. No.</label>
													<input type="text" class="form-control" v-model="new_dealer.tax_information.pan.number">
												</div>
											</div>
											<div class="col-md-6">
												<div class="form-group">
													<label>P.A.N. Reference No.4</label>
													<input type="text" class="form-control" v-model="new_dealer.tax_information.pan.reference_number">
												</div>
											</div>
											<div class="clearfix"></div>

											<div class="col-md-6">
												<div class="form-group"
													:class="{ 'has-error': errors.has('dealer.sales_manager_id') }">
													<label>Sales Manager</label>
													<sales-manager v-model="new_dealer.sales_manager" :company="company"
														 data-vv-name="dealer.sales_manager"
														v-if="company.id"></sales-manager>
												</div>
											</div>

											<div class="col-md-6">
												<div class="form-group"
													:class="{ 'has-error': errors.has('dealer.sales_manager_id') }">
													<label>Priority</label>
													<select class="form-control" name="priority" v-model="new_dealer.priority">
														<option value="">Select Priority</option>
														<option value="Low" :selected="new_dealer.priority == 'Low'">Low</option>
														<option value="Medium" :selected="new_dealer.priority == 'Medium'">Medium</option>
														<option value="High" :selected="new_dealer.priority == 'High'">High</option>
													</select>
											</div>
											</div>
										</template>
										<template v-if="vatInformation">
											<h5>VAT</h5>
										<!--	<div class="col-md-6">
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
											<div class="col-md-12">
												<div class="checkbox"> 
													<label> 
														<input type="checkbox" v-model="new_dealer.tax_information.vat.export"> Export or Deemed Export
													</label> 
												</div>
											</div>
											<div class="col-md-12">
												<div class="checkbox"> 
													<label> 
														<input type="checkbox" v-model="new_dealer.tax_information.vat.exempted"> VAT Exempted
													</label> 
												</div>
											</div>
											<div class="clearfix"></div>
										</template>
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
									<h4>Invoicing</h4>
									<hr>
								</div>
          						<div class="col-md-12">
          							<div class="form-group" :class="{'has-error':errors.has('dealer.structure')}">
										<label>Structure *</label>
										<tax-types v-model="new_dealer.structure" :company="company" v-validate="'required'" data-vv-name="dealer.structure"></tax-types>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Copy Sell-to-Addr.to Qte From</label>
										<copy-sell-from v-model="new_dealer.copySellFrom"></copy-sell-from>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Gen. Bus.Posting Group</label>
										<general-business-posting-group v-model="new_dealer.generalBusinessPostingGroup"></general-business-posting-group>
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
										<payment-application-method v-model="new_dealer.paymentApplicationMethod"></payment-application-method>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Payment Terms Code.</label>
										<payment-terms v-model="new_dealer.paymentTerms"></payment-terms>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Payment Method Code</label>
										<payment-method v-model="new_dealer.paymentMethod"></payment-method>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Cash flow Payment Terms Code</label>
										<cash-flow-payment-terms v-model="new_dealer.cashFlowPaymentTerms"></cash-flow-payment-terms>
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
										<shipping-reserve v-model="new_dealer.shippingReserve"></shipping-reserve>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Shipping Advice</label>
										<shipping-advice v-model="new_dealer.shippingAdvice"></shipping-advice>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group">
										<label>Shipment Method</label>
										<shipping-method v-model="new_dealer.shippingMethod"></shipping-method>
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
										<input type="text" class="form-control" v-model="new_dealer.credit_limit" v-validate="'required'" data-vv-name="credit_limit">
									</div>
          						</div>
          						<div class="col-md-6" v-if="this.$route.params.distributorid">
          							<div class="form-group">
										<label>Overdue Balance</label>
										<input type="text" class="form-control" v-model="new_dealer.overdue_balance">
									</div>
          						</div>
          					</div>
          					<div class="row">
          						<div class="col-md-12">
									<h4>User Interaction</h4>
									<hr>
								</div>
          						<div class="col-md-12">
          							<div class="row">
          								<div class="col-md-6">
		                                	<div class="form-group">
				                               	<div class="checkbox"> 
													<label> 
														<input type="checkbox" v-model="new_dealer.app_explained"> App Explained
													</label> 
												</div>
												<div class="checkbox"> 
													<label> 
														<input type="checkbox" v-model="new_dealer.updated_folder_given"> Updated folder sets given
													</label> 
												</div>
											</div>
										</div>

										<div class="col-md-6">
		          							<div class="form-group">
												<label>Area</label>
												<input type="text" class="form-control" v-model="new_dealer.area">
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
		                                	<div class="form-group">
												<div class="form-group" :class="{'has-error':errors.has('dealer.notes')}">
													<label>Notes</label>
													<textarea class="form-control" v-model="new_dealer.notes" v-validate="'max:500'" data-vv-name="dealer.notes" rows="8"></textarea>
										 			<span class="text-danger" v-show="errors.has('new_dealer.notes')">Notification Text can not exceed 200 characters.</span>
												</div>
				                            </div>
				                        </div>
				                    </div>
          						</div>
          					</div>
          					<div class="row" v-if="!new_dealer.id">
								<div class="col-md-12">
									<h4>{{brand}} User</h4>
									<hr>
								</div>
								<div class="col-md-12">
									<p>Email or Phone is mandatory</p>
								</div>
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('userEmail')}">
										<label>Email</label>
										<input type="text" class="form-control" v-model="new_dealer.user.email" v-validate="{required:new_dealer.user.phone?false:true,email:true}" data-vv-name="userEmail">
									<span class="text-danger" v-show="errors.has('userEmail')">Please enter valid email</span>
									</div>
          						</div>
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('userPhone')}">
										<label>Phone</label>
										<input type="text" class="form-control" v-model="new_dealer.user.phone" v-validate="{required:new_dealer.user.email?false:true,numeric:true,phone:[company.region.country.code]}" data-vv-name="userPhone">
										<span class="text-danger" v-show="errors.has('userPhone')">Please enter valid phone</span>
									</div>
          						</div>
          						
          						<!--
          						<div class="col-md-6">
          							<div class="form-group" :class="{'has-error':errors.has('userEmail')}">
										<label>Email</label>
										<input type="text" class="form-control" v-model="new_dealer.user.email" v-validate="'email'" data-vv-name="userEmail">
									<span class="text-danger" v-show="errors.has('userEmail')">Please enter valid email</span>
									</div>
          						</div>
          						<div class="col-md-3">
          							<div class="form-group" :class="{'has-error':errors.has('userPhone')}">
										<label>Phone</label>
										<input type="text" class="form-control" v-model="new_dealer.user.phone" v-validate="'phone|numeric'" data-vv-name="userPhone">
										<span class="text-danger" v-show="errors.has('userPhone')">Please enter valid phone</span>
									</div>
          						</div>
          					-->
          						<div class="col-md-12">
          							<div class="form-group" :class="{'has-error':errors.has('password')}">
										<label>Password</label>
										<input type="text" class="form-control" v-model="new_dealer.user.password" v-validate="'required|min:8'" data-vv-name="password">
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
			<h1>Create Dealer</h1>
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
	import SalesManager from '../SalesManager/SalesManager'
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
			SalesManager,
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
					overdue_balance:0,
					dealer_type:"",
					app_explained:false,
					updated_folder_given:false,
					notes:"",
					area:"",
					tax_information:{
						tax:{
							lst:"",
							cst:""
						},
						vat:{
							//tin:"",
							registration_number:"",
							export:false,
							exempted:false
						},
						gst:{
							customer_type:{
								key:"Registered",
								value:"Registered",
							},//#780
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
					},
					sales_manager:null
				},
				brand:window.config.brand_short,
				customer_type:{code:'DISTRIBUTO',name:"Distributor"},
				selectedDistributor:null,//#827
			}
		},
		mounted(){
			let self = this
			this.company = Object.assign({},{
					id:this.$route.params.id,
					region:{
						tax_information:[],
						country:{
							code:""
						}
					}
				})//this.$route.params.id
			//if(!this.state.user.company)
			//{
				Vue.axios.get(`companies/${this.company.id}`)
				.then(response => {
					this.company = Object.assign({},response.data)
					if(!this.$route.params.dealerid)
					{
						let d = Object.assign({},this.new_dealer)
						if(this.new_dealer.customer_type == 1)
						{
							d.tax_information.gst.customer_type = {
									key:"Registered",
									value:"Registered",
								}
							d.tax_information.gst.registration_type = {
								key:"GSTIN",
								value:"GSTIN",
							}
							this.new_dealer = Object.assign({},d)
						}
						else
						{
								d.tax_information.gst.customer_type = {
									key:"UnRegistered",
									value:"UnRegistered",
								}
							d.tax_information.gst.registration_type = null
							this.new_dealer = Object.assign({},d)
						}
					}
				})
		//	}
			if(this.$route.params.distributorid && this.$route.params.dealerid)
			{
				EventBus.$emit('loader:show')
				Vue.axios.get(`companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}/dealers/${this.$route.params.dealerid}`)
				.then(response => {
					EventBus.$emit('loader:hide')
					//this.new_dealer = Object.assign({},response.data)
					let d = Object.assign({},response.data)
					if(d.tax_information.gst)
					{
						/*d.tax_information.gst.customer_type = {
												key:"f",
												value:"Registered",
											}*/
					}//#780


					this.new_dealer = Object.assign({},d)
					this.contact = this.new_dealer.contact
					this.selectedDistributor = Object.assign({},this.new_dealer.distributor)//#827
					setTimeout(function(){
						self.$refs.customerSelect.loadCustomers()	
					},300)
				})
				.catch(error => {
					EventBus.$emit('loader:hide')
				})
			}
			else if(this.$route.params.dealerid)
			{
				let url = `companies/${this.$route.params.id}/dealers/${this.$route.params.dealerid}`
				EventBus.$emit('loader:show')
				Vue.axios.get(url)
				.then(response => {
					EventBus.$emit('loader:hide')
					let d = Object.assign({},response.data)
				//	d.app_explained = response.data.app_explained ? 'Y' : 'N'
				//	d.updated_folder_given =response.data.updated_folder_given ? 'Y' : 'N'
					
				/*	if(d.tax_information.gst)
					{
						d.tax_information.gst.customer_type = {
												key:"Registered",
												value:"Registered",
											}
					}//#780*/
					this.new_dealer = Object.assign({},d)
					this.selectedDistributor = Object.assign({},this.new_dealer.distributor)//#827
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
				if(!this.new_dealer.tax_information.gst)
					return
				if(a == 1)
				{
					if(!this.new_dealer.tax_information.gst.number)
					{
						d.tax_information.gst.customer_type = {
							key:"Registered",
							value:"Registered",
						}
						d.tax_information.gst.registration_type = {
							key:"GSTIN",
							value:"GSTIN",
						}
						this.new_dealer = Object.assign({},d)
					}
				}
				else
				{
					if(!this.new_dealer.tax_information.gst.number)
					{
						d.tax_information.gst.customer_type = {
							key:"Unregistered",
							value:"Unregistered",
						}
						d.tax_information.gst.registration_type = null
						this.new_dealer = Object.assign({},d)
					}
				}
				/*if(a == 1)
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
						//	d.tax_information.gst.number = null
							d.tax_information.gst.customer_type = {
													key:"Unregistered",
													value:"Unregistered",
												}
						}
					}
					this.new_dealer = Object.assign({},d)
				}*/
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
				if(this.$route.params.distributorid != null)
					this.$router.push({name:'companies.distributors.dealers',params:{id:this.company.id,distributorid:this.$route.params.distributorid}})
				else
					this.$router.push({name:'companies.dealers',params:{id:this.$route.params.id}})
			},
			saveData(){
				let out = {}
				out.dealer_type = this.new_dealer.dealer_type ? this.new_dealer.dealer_type : 1,
				out.name = this.new_dealer.name
				out.contact = this.contact
				out.address = this.new_dealer.address
				out.address2 = this.new_dealer.address2
				out.state = this.new_dealer.state.code
				out.city = this.new_dealer.city
				out.zipcode = this.new_dealer.zipcode
				out.territory = this.new_dealer.territory.key
				out.billingLocationCode = this.new_dealer.billingLocationCode.code
				out.generalBusinessPostingGroup = this.new_dealer.generalBusinessPostingGroup.key
				out.paymentApplicationMethod = this.new_dealer.paymentApplicationMethod.key
				out.paymentTerms = this.new_dealer.paymentTerms.key
				out.cashFlowPaymentTerms = this.new_dealer.cashFlowPaymentTerms.key
				out.shippingReserve = this.new_dealer.shippingReserve.key
				out.shippingAdvice = this.new_dealer.shippingAdvice.key
				out.paymentMethod = this.new_dealer.paymentMethod.key
				out.shippingMethod = this.new_dealer.shippingMethod.key
				out.copySellFrom = this.new_dealer.copySellFrom.key
				out.structure = this.new_dealer.structure.code
				out.phone = this.new_dealer.phone
				out.email = this.new_dealer.email
				out.fax = this.new_dealer.fax
				out.credit_limit = this.new_dealer.credit_limit
				out.overdue_balance = this.new_dealer.overdue_balance
				out.area = this.new_dealer.area
				out.notes = this.new_dealer.notes
				out.app_explained = this.new_dealer.app_explained ? 'Y' : 'N'
				out.updated_folder_given = this.new_dealer.updated_folder_given ? 'Y' : 'N'
				out.sales_manager_id = this.new_dealer.sales_manager.id
				out.priority = this.new_dealer.priority

				if(this.selectedDistributor)//#827
				{
					out.newDistributorId = this.selectedDistributor.id
					out.newDistributorNavId = this.selectedDistributor.nav_id
				}
				else
				{
					out.newDistributorId = null
					out.newDistributorNavId = null
				}
				//out.distributor = {id:this.selectedDistributor.id}
				out.tax_information = {}
				if(this.vatInformation)
				{
					out.tax_information.vat = {
						//	tin:this.new_dealer.tax_information.vat.tin,
							registration_number:this.new_dealer.tax_information.vat.registration_number,
							export:this.new_dealer.tax_information.vat.export,
							exempted:this.new_dealer.tax_information.vat.exempted
						}
				}
				if(this.gstInformation)
				{
					out.tax_information.gst = {
						customer_type:"",
						registration_type:"",
						number:this.new_dealer.tax_information.gst.number
					}
					if(this.new_dealer.tax_information.gst.customer_type)
					{
						out.tax_information.gst.customer_type = this.new_dealer.tax_information.gst.customer_type.key
					}
					if(this.new_dealer.tax_information.gst.registration_type)
					{
						out.tax_information.gst.registration_type = this.new_dealer.tax_information.gst.registration_type.key
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
				if(this.new_dealer.user){
					out.user = {
						email:this.new_dealer.user.email,
						phone:this.new_dealer.user.phone,
						password:this.new_dealer.user.password
					}
				}
				return out
			},
			save(){
				let self = this 
				let url = `companies/${self.company.id}/dealers`
				let method = 'post'
				let message = 'Dealer Created'
				if(self.new_dealer.id)
				{
					url = `companies/${self.company.id}/dealers/${self.new_dealer.id}`
					method = "put"
					message = 'Dealer Updated'
				}
				if(self.$route.params.distributorid != null && self.new_dealer.id != null)
				{
					url = `companies/${self.company.id}/distributors/${self.$route.params.distributorid}/dealers/${self.new_dealer.id}`
					method = "put"
					message = 'Dealer Updated'
				}
				if(self.$route.params.distributorid != null && self.new_dealer.id == null)
				{
					url = `companies/${self.company.id}/distributors/${self.$route.params.distributorid}/dealers`
				}
			//	console.log(url)
			//	return
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
								if(self.$route.params.distributorid != null)
									self.$router.push({name:'companies.distributors.dealers',params:{id:self.company.id,distributorid:self.$route.params.distributorid}})
								else
									self.$router.push({name:'companies.dealers',params:{id:self.company.id}})
							})
						})
						.catch(error => {
							if (error.response.data['user.email']) 
							{
        						return swal ( "Error" ,  "The user email has already been taken",  "error" )
		    					.then(value =>{
		    						self.new_dealer.user.email = null
		    					})
        					}
        					if (error.response.data['user.phone']) 
							{
        						return  swal ( "Error" ,  "The user phone has already been taken",  "error" )
		    					.then(value =>{
		    						self.new_dealer.user.phone = null
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
		},
	}
</script>