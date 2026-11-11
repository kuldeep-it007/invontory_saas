<template>
	<div v-if="abilities.viewInvoices">
		<section class="content-header">
			<h1>Invoices</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-3" v-if="this.state.user.company == null">
									<div class="form-group">
										<label>Region</label>
										<region-select v-model="region"></region-select>
									</div>
								</div>
								<div class="col-md-4" v-if="this.state.user.company == null">
									<div class="form-group">
										<label>Company</label>
										<company v-model="company" :region="region" :disabled="!region"></company>
									</div>
								</div>
								<div class="col-md-3" v-if="company">
									<div class="form-group">
										<label>Stock Point</label>
										<stockpoint v-model="stockpoint" :region="region" :company="company" :disabled="!company"></stockpoint>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-2" v-if="stockpoint">
									<div class="form-group">
										<div class="radio">
	  										<label>
	    										<input type="radio" v-model="printed" value="1">
	    										Printed
	  										</label>
										</div>
									</div>
								</div>
								<div class="col-md-4" v-if="stockpoint">
									<div class="form-group">
										<div class="radio">
	  										<label>
	    										<input type="radio" v-model="printed" value="0">
	    										Not Printed
	  										</label>
										</div>
									</div>
								</div>
								<!--
								<div class="col-md-1">
									<div class="form-group">
										<label style="display: block;">&nbsp;</label>
										<button class="btn btn-default" :disabled="!company" @click="search">Search</button>
									</div>
								</div>
								-->
							</div>
							
							<div class="row" v-if="stockpoint">
								<div class="col-md-12">
									<div class="box box-widget">
										<div class="box-body">
											<vuetable 
					                                :fields="tableFields"
					                                :api-url="apiUrl"
					                                pagination-path="pagination"
					                                data-path="invoices"
					                                ref="vuetable"
					                                :css="css.table"
					                                track-by="id"
					                                :per-page="perPage"
					                                @vuetable:pagination-data="onPaginationData"
				                            >  
											<template slot="actions" slot-scope="props" >
												 <a href="#" v-if="new_approach == 1" @click.prevent="print(props.rowData)"><i class="fa fa-print" aria-hidden="true"></i></a>
												&nbsp;
												 <a href="#" v-if="new_approach == 1 && printed == 0" @click.prevent="mark_printed(props.rowData)"><i class="fas fa-truck" aria-hidden="true"></i></a>	
											</template>
				                            </vuetable>
										</div>
										<div class="box-footer">
											<div style="text-align:right;">
												<vuetable-pagination ref="pagination"
									        		:css="css.pagination"
									        		@vuetable-pagination:change-page="onChangePage"
									      		></vuetable-pagination>
											</div>
										</div>
									</div>
								</div>
							</div>
							
						</div>
					</div>
				</div>
			</div>
		</section>
		<div ref="printElement" class="visible-print purchase-order">
			<div v-if="order">
				<div style="position:absolute;width:250px;right: 0;top:20px;">
					<img src="/images/rosetta.png" style="width:250px;">
				</div>
				<table style="width: 50%;">
					<tr>
						<td>
							<h1 style="font-size: 18px;">{{order.customer.distributor.name}}</h1><br>
							<p><b>Office: </b>{{order.customer.distributor.address}} {{order.customer.distributor.address2}} {{order.customer.distributor.state.code}} {{order.customer.distributor.zipcode}}</p>
							<p><b>Contact No: </b>{{order.customer.distributor.phone}}</p>
							<p><b>Email: </b>{{order.customer.distributor.email}}</p>
						</td>
					</tr>
				</table>
				<br>
				<table class="table table-bordered">
					<tr>
						<!--<td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
							<b>GSTIN No {{order.customer.distributor.tax_information.gst.number}}</b>
						</td>-->
						<td v-if="order.customer.company.region.country_code == 'IN'" colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
							<b>GSTIN No {{order.customer.distributor.tax_information.gst.number}}</b>
						</td>
						<td v-if="order.customer.company.region.country_code == 'AE'" colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
							<b>TIN No {{order.customer.distributor.tax_information.vat.number}}</b>
						</td>
					    <td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
							<b>PAN No. {{order.customer.distributor.tax_information.pan.number}}</b>
						</td>
					</tr>
					<tr>
						<td colspan="10" style="width: 50%;border-color: #000 !important;padding-left: 4px;" class="text-center"><b>PROFORMA INVOICE</b></td>
					</tr>
					<tr>
						<td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
							<p><b>Proforma Invoice To: </b></p>
							<p><b>{{this.order.customer.name}}</b></p>
							<p>{{this.order.customer.address}} {{this.order.customer.address2}} {{this.order.customer.state.code}}</p>
							<p v-if="order.customer.company.region.country_code == 'IN'"><b>GSTIN/UniqID {{this.order.customer.tax_information.gst.number}}</b></p>
							<p v-if="order.customer.company.region.country_code == 'AE'"><b>TIN/UniqID {{this.order.customer.tax_information.vat.number}}</b></p>

						</td>
						<td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
							<p><b>Proforma Invoice No: </b>{{this.order.ros_invoice_number}}</p>
							<p><b>Sales Order No: </b>{{orderNumber}}</p>
							<p><b>Purchase Order No: </b>{{this.order.number}}</p>
							<p><b>Order Date: </b>{{orderDate}}</p>
							<p><b>Payment Terms: </b> {{this.order.customer.paymentTerms.value}}</p>
							
						</td>
					</tr>
					<tr>
						<td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
							<p><b>Ship To: </b></p>
							<p><b>{{this.order.customer.name}}</b></p>
							<p>{{this.order.address.address}} {{this.order.address.address2}} {{this.order.address.state_code}}</p>
							<p v-if="order.customer.company.region.country_code == 'IN'"><b>GSTIN/UniqID {{this.order.customer.tax_information.gst.number}}</b></p>
							<p v-if="order.customer.company.region.country_code == 'AE'"><b>TIN/UniqID {{this.order.customer.tax_information.vat.number}}</b></p>

						</td>
						<td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
							<p><b>Ship To Contact: </b>{{this.order.customer.contact}} | {{this.order.customer.phone}}</p>
						</td>
					</tr>
					<tr>
						<td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;vertical-align:top;">
							<p><b>Remarks: </b>{{this.order.remarks}}</p>
						</td>
						<td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
							<p><b>Transporter Name: </b></p>
							<p><b>Vehicle No.: </b></p>
							<p><b>Transp. Method: </b></p>
							<br>
							<p><b>LR /RR No.: </b></p>
							<p><b>LR /RR Date.: </b></p>
							<br>
						</td>
					</tr>
				<tr>
						<td style="border-color: #000 !important;padding-left: 4px;">
						<b>Sr No</b>
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							<b>PRODUCT DESCRIPTION</b>
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							<b>STOCK LOCATION</b>
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							<b>STATUS</b>
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							<b>QTY</b>
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							<b>RATE</b>
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							<b>UOM</b>
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							<b>NET AMOUNT</b>
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							<b v-if="order.customer.company.region.country_code == 'AE'">VAT (%)</b>
							<b v-if="order.customer.company.region.country_code == 'IN'">GST (%)</b>
						</td>
					<!--	<td style="border-color: #000 !important;padding-left: 4px;" v-if="order.customer.company.region.country_code == 'AE'">
							<b >VAT (%)</b>
						</td>-->
						<td style="border-color: #000 !important;padding-left: 4px;">
							<b>TOTAL AMOUNT</b>
						</td>
					</tr>
					<tr v-for="(line,index) in this.order.purchase_lines" :key="line.id">
						<td style="border-color: #000 !important;padding-left: 4px;">
						<b>{{index+1}}</b>
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							{{line.product.category.display_name}} {{line.product.collection.display_name}} <template v-if="line.product.colour"> {{line.product.colour.display_name}}</template>
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							{{line.stock_point.name}} 
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							{{lineStatus(line)}} 
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							{{line.quantity}} 
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							{{discountedPrice(line)}}
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							{{line.product.base_unit_of_measure}}
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
						{{calculateAmount(line)}}
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							{{line.tax_percent}}
						</td>
						<td style="border-color: #000 !important;padding-left: 4px;">
							{{line.net_amount}}
						</td>
					</tr>

					<tr>
						<td colspan="5" style="border-color: #000 !important;padding-left: 4px;"><b>Net Total</b></td>
						<td colspan="5" style="border-color: #000 !important;padding-left: 4px;" class="text-right"><b>{{orderNetAmount.toFixed(2)}}</b></td>
					</tr>
				</table>
				<table>
					<tr>
						<td style="border-color: #000 !important;padding-left: 4px;">
							<h1 style="font-size: 18px;">
								<b>AMOUNT IN WORDS: **** 
									<span style="font-weight:bold;text-transform: uppercase;">{{ orderNetAmount.toFixed(2) | toWords}}</span> {{order.customer.company.region.currency_major}} 
									<span v-if="orderAmountDecimal != 0" style="font-weight:bold;text-transform: uppercase;">
										AND {{orderAmountDecimal | toWords}} {{order.customer.company.region.currency_minor}} 
									</span> ONLY
								</b>
							</h1>
						</td>
					</tr>
				</table>
				<div style="position: absolute;bottom: 0;text-align: right;width:100%;">{{this.order.nav_invoice_number}}</div>
			</div>
        </div>
	</div>
</template>
<script>
	import Vue from 'vue'
	import RegionSelect from '../region/Select'
	import Company from '../companies/Select'
	import {EventBus} from '../../configs/EventBus'
	import AppMixins from '../../vuex-mixin'
	import TableFields from './Fields'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import Stockpoint  from '../stockpoints/Select'
	import { Printd } from 'printd'
	import converter from 'number-to-words'
	export default{
		mixins:[AppMixins],
		components:{
			RegionSelect,
			Company,
			Vuetable,
			VuetablePagination,
			Stockpoint
		},
		computed:{
            apiUrl(){
            	if(this.company)
				{
					let url = `companies/${this.company.id}/invoices?printed=${this.printed}`
					if(this.stockpoint)
						url = `${url}&stock_point_id=${this.stockpoint.id}`
					return url
				}
			},
			orderNumber(){
                if(!this.order.number)
                    return ""
                return this.order.number.replace('PO', 'SO')
            },
			orderDate(){
                if(!this.order.created_at)
                    return ""
                return moment.unix(this.order.created_at).format('DD MMM YYYY')
            },
			orderNetAmount(){
                if(!this.order)
                    return 0
                return _.reduce(this.order.purchase_lines,function(memo,e){return parseFloat(e.net_amount)+memo},0)
            },
			orderAmountDecimal(){
                if(!this.order)
                    return 0
                return (this.orderNetAmount + "" ).replace(/\d+\./,'').padEnd(2,'0')
            },
        },
        data(){
			return {
				region:null,
				company:null,
				tableFields:TableFields,
				css:vuetableCssConfig,
				perPage:50,
				items:[],
				stockpoint:null,
				printed:"0",
				new_approach:window.config.new_approach,
				order:null,
				selectedCustomer:null,
                customer_type:null,
			}
		},
		filters: 
        {
            toWords: function (value) 
            {
                if (!value) return ''
                return converter.toWords(value);
            }
        },
        mounted(){
			let self = this
			self.printed=0
	/*		window.onafterprint = function(event) {
				console.log(event)
			};*/
        },
        methods:{
        	search(){
				let self = this
		    	Vue.nextTick(function(){
					self.$refs.vuetable.reload()	
		    	})
        	},
			lineStatus(item){
                if(item.quantity == item.shipped_quantity)
                    return 'Shipped'; 
                return 'Pending'; 
            },
			discountedPrice(item){
                return (item.unit_price - (item.unit_price * item.discount /100)).toFixed(2);
            },
			calculateAmount(item){
                return (item.quantity * item.discounted_price).toFixed(2)
            },
			print(row)
			{
				let self = this
                let url = `companies/${self.company.id}/invoices/${row.Dealer_BO_Id}`
				EventBus.$emit('loader:show')
				Vue.axios.get(url)
				.then(response => {
					EventBus.$emit('loader:hide')
					let order = Object.assign({},response.data.po)
					_.each(order.purchase_lines,function(element){
						element.original_quantity = (element.quantity).toFixed(2)
						element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
					})
					self.order = Object.assign({},order)
					this.selectedCustomer = Object.assign({}, response.data.po.customer)
					this.customer_type = response.data.po.customer_type

					let d = new Printd()
					let bootstrapCss = window.config.api_url.replace('api/','')+'vendors/bootstrap/css/bootstrap.min.css'
					Vue.nextTick(function(){
						setTimeout(() => {
							d.print(self.$refs.printElement,[bootstrapCss,`
									table{
												font-family: "system-ui",Arial;
									font-size:14px;
											}
									`]);
							})
						}, 200);
		//				self.search();
					})
					
				.catch(error => {
			//		console.log(error)
				})
			},
			mark_printed(row){
				
                let self = this
                let url = `companies/${self.company.id}/invoices/${row.Distributor_BO_Id}/mark_printed`

            	let input = {
					dealer_bo_id:row.Dealer_BO_Id,
					dealer_po_id:row.Dealer_order_id,
					distributor_bo_id:row.Distributor_BO_Id,
					distributor_nav_so_number:row.so_numbers[0],
				}
				EventBus.$emit('loader:show')
				Vue.axios.put(url,input)
				.then(response => {
					EventBus.$emit('loader:hide')
						self.search();
					})	
				.catch(error => {
					console.log(error)
				})
            },
        	onPaginationData (tablePagination) {
		      this.$refs.pagination.setPaginationData(tablePagination)
		    },
		    transform: function(data){
		    	var transformed = {}
		    	transformed.pagination = {

		    		total:data.total,
		    		per_page :this.perPage,
		    		current_page: data.page,
		    		last_page: Math.ceil(data.total / this.perPage),
		    		from: ((data.page-1) * this.perPage) +1,
		    		to:((data.page-1) * this.perPage) + data.invoices.length
		    	}
		    	transformed.invoices = data.invoices
		    	return transformed
		    },
		    
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    },
        },
        watch:{
			'region.id':function(n,o){
				this.company  = null
				this.stockpoint = null
			},
            'company.id':function(n,o){
				this.stockpoint = null
            },
			'stockpoint.id':function(n,o){
				this.printed = 0
            },
        }
	}
</script>
<style>
	.has-actions{
		width: 1%;
		white-space: nowrap;
	}
</style>