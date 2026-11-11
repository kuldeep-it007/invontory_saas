<template>
	<div>
		<section class="content-header">
			<h1>Orders</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row" v-if="!isCustomer">
								<div class="col-md-3" v-if="this.state.user.company == null">
									<div class="form-group">
										<label>Region</label>
										<region-select v-model="region"></region-select>
									</div>
								</div>
								<div class="col-md-3" v-if="this.state.user.company == null">
									<div class="form-group">
										<label>Company</label>
										<company v-model="company" :region="region" :disabled="!region"></company>
									</div>
								</div>
								<div class="col-md-3">
				                    <div class="form-group">
				                        <label>Customer Type</label>
				                        <customer-type ref="customerType" v-model="customer_type" :disabled="!company" :allow-all="true"></customer-type>
				                    </div>
				                </div>
				                <div class="col-md-3"  v-if="customer_type.code != 'ALL'">
				                    <div class="form-group" :class="errors.has('customer') ? 'has-error' : ''">
				                        <label>
				                            <template v-if="customer_type">
				                                <template v-if="customer_type.code == 'DISTRIBUTO'">Distributor</template>
				                                <template v-if="customer_type.code == 'DEALER'">Dealer</template>
				                                <template v-if="customer_type.code == 'SUPER_STOCKIST'">Super Stockist</template>
				                            </template>
				                            <template v-else>   
				                                Customer
				                            </template>
				                        </label>
				                            <customer-select v-model="selectedCustomer" :company="company" :customer_type="customer_type" :disabled="!company" :required="true" v-validate="'required'" data-vv-name="customer" ref="customerSelect"></customer-select>
				                    </div>
				                </div>
							</div>
							<div class="row"><!--#762-->
								<div class="col-md-3" v-if="company">
									<div class="form-group">
										<label>Stock Point</label>
										<stock-point
										:company="company" 
										v-model="stock_point" 
										:disabled="!company" 
										ref="stockPoint"></stock-point>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<label>SO NO</label>
										<input type="text" class="form-control" v-model="so" :disabled="!company">
									</div>
								</div>
								<template v-if="!isCustomer">
									<div class="col-md-3">
										<div class="form-group">
											<label>SI NO</label>
											<input type="text" class="form-control" v-model="si" :disabled="!company">
										</div>
									</div>
								</template>
								<div class="col-md-3">
									<div class="form-group">
										<label>Status</label>
										<order-status ref="order_status" v-model="status" :allow-empty="true" :disabled="!company"></order-status>
									</div>
								</div>
							</div>
							<div class="row"><!--#839-->
								<template v-if="!isCustomer">
									<div class="col-md-3">
										<div class="form-group">
											<label>Dealer Order</label>
											<dealer-select v-model="selectedDealer" :selected-distributor= "selectedCustomer" :company="company" v-if="selectedCustomer"></dealer-select>
										</div>
									</div>
								</template>
								<div class="col-md-2">
									<div class="form-group">
										<label>From Date</label>
										<input type="date" class="form-control" v-model="from_date" :disabled="!company">
									</div>
								</div>
								<div class="col-md-2">
									<div class="form-group">
										<label>To Date</label>
										<input type="date" class="form-control" v-model="to_date" :disabled="!company">
									</div>
								</div>
								<div class="col-md-1">
									<div class="form-group">
										<label style="display: block;">&nbsp;</label>
										<button class="btn btn-default" :disabled="!company" @click="search">Search</button>
									</div>
								</div>
								<div class="col-md-1">
									<div class="form-group">
										<label style="display: block;">&nbsp;</label>
										<button class="btn btn-default" :disabled="!company" @click="clear">Clear</button>
									</div>
								</div>
								<div class="col-md-6 text-right">
									<div class="form-group">
										<template v-if="from_date && to_date">
											<label style="display: block;">&nbsp;</label>
											<a href="#" @click.prevent="exportPOs"><i class="fas fa-download"></i> Export POs</a>
										</template>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row" v-if="company">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body table-responsive">
							<vuetable 
	                                :fields="tableFields"
	                                :api-url="apiUrl"
	                                :append-params="appendParams"
	                                pagination-path="pagination"
	                                data-path="purchaseOrders"
	                                ref="vuetable"
	                                :css="css.table"
	                                track-by="id"
	                                :per-page="perPage"
	                                @vuetable:pagination-data="onPaginationData"
	                                detail-row-component="Payments"
	                                :page="page"
                            >
                            	<template slot="number" slot-scope="props">
                            		{{props.rowData.number}}
									<template v-if="props.rowData.source == 'APP'">
	                        			 <span class="badge bg-green pull-right">A</span>
	                        		</template> 
								</template>
								<template slot="dist_name" slot-scope="props">                            		 
									<template v-if="props.rowData.dealer_type == 'Distributor'">
										{{(props.rowData.distributor_name)?props.rowData.distributor_name:props.rowData.dealer_name}}
	                        		</template>
								</template> 
								<template slot="dealer_name" slot-scope="props">
									<template v-if="props.rowData.dealer_name != ''">
										<template v-if="props.rowData.dealer_type == 'Dealer'">
											{{ props.rowData.dealer_name }}
										</template>
										<template v-else-if="props.rowData.distributor_name!=props.rowData.dealer_name">
											{{ props.rowData.dealer_name }}
										</template>
	                        		</template>                  		
									<template v-if="props.rowData.source == 'APP' && (props.rowData.on_behalf != 'Dealer' && props.rowData.dealer_type != 'Distributor')">
	                        			 <span class="badge bg-green pull-right">Self</span>
	                        		</template> 
	                        		<template v-if="props.rowData.dealer_type == 'Retail Customer'">
	                        			 <span class="badge bg-blue pull-right">RC</span>
	                        		</template>
	                        		<template v-if="props.rowData.dealer_type == 'Dealer'">
	                        			 <span class="badge bg-blue pull-right pull-right">DLR</span>
	                        		</template>
									<!-- <template v-if="props.rowData.dealer_type == 'Distributor'">
	                        			 <span class="badge bg-blue pull-right pull-right">DIST</span>
	                        		</template>  -->
									<template v-if="props.rowData.dealer_type == 'Superstockist'">
	                        			 <!-- <span class="badge bg-blue pull-right pull-right">SS</span> -->
	                        		</template>
								</template> 
	                        	<template slot="status" slot-scope="props" >
	                        		{{props.rowData.status}}
								</template> 
								<template slot="customer.name" slot-scope="props">
									<template v-if="props.rowData.customer">
	                        			{{props.rowData.customer.name}}
										<template v-if="props.rowData.customer.type == 'Distributor'">
	                        			 	<span class="badge bg-blue pull-right pull-right" style="padding: 5px 15px;">DIST</span>
	                        			</template>
										<template v-if="props.rowData.customer.type == 'superStockist'">
	                        			 	<span class="badge bg-green pull-right pull-right"  style="padding: 5px 20px;">SS</span>
	                        			</template>
										<template v-if="props.rowData.customer.type == 'Dealer'">
	                        			 	<span class="badge bg-yellow pull-right pull-right" style="padding: 5px 15px;">DLR</span>
	                        			</template>
	                        		</template> 
								</template>   
								<template slot="actions" slot-scope="props" >
			        				<router-link :to="{name:'orders.view',params:{id:props.rowData.id}}">VIEW</router-link>
								</template>

								<!-- <template slot="paid_amount" slot-scope="props" >
                                    {{props.rowData.paid_amount}}
                                    <a href="#" class="text-green" @click.prevent="showPayments(props.rowData)">
                                        <span v-if="isPaymentVisible(props.rowData.id)">
                                            <i class="fa fa-eye-slash pull-right"></i>
                                        </span>
                                        <span v-else> <i class="fa fa-eye pull-right"></i></span>
                                    </a>
                                </template> -->

							<!--	<template slot="actions" slot-scope="props" >
				        		<div class="custom-actions">
				        			<router-link class="btn btn-xs btn-info" :to="{name:'orders.view',params:{id:props.rowData.id}}" title="View Order">
				        				<i class="fa fa-eye"></i>
				        			</router-link>
				        			<button class="btn btn-xs btn-info" title="Refresh Payment" @click="checkPayment"><i class="fa fa-money-bill"></i></button>
				        		</div>
				      		</template>-->

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
		</section>
	</div>
</template>
<script>
	import Vue from 'vue'
	import RegionSelect from '../../region/Select'
	import Company from '../../companies/Select'
	import {EventBus} from '../../../configs/EventBus'
	import AppMixins from '../../../vuex-mixin'
	import OrderFields from './OrderFields'
	import vuetableCssConfig from '../../../configs/VuetableCssConfig'
	import VuetablePagination from '../../../vendor/vuetable2/VuetablePagination'
	import Vuetable from '../../../vendor/vuetable2/Vuetable'
	import OrderStatus from '../../master/OrderStatus'
	import CustomerType from '../../master/CustomerType'
    import CustomerSelect from '../../master/CustomerSelect'
    import DealerSelect from '../../master/DealerSelect'
    import StockPoint from '../../stockpoints/Select'
    import Payments from  '../Payments'//#766
    import { saveAs } from 'file-saver'
    Vue.component('Payments',Payments)
	export default{
		mixins:[AppMixins],
		components:{
			Vuetable,
			VuetablePagination,
			OrderStatus,
			RegionSelect,
			Company,
			CustomerSelect,
            CustomerType,
            StockPoint,
            DealerSelect
		},
		computed:{
			effectiveCompany(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor.company
                    if(this.superStockist)
                        return this.superStockist.company
                    if(this.dealer)
                        return this.dealer.company
                }
                return this.company
            },
            effectiveDistributor(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor
                }
                if(this.selectedCustomer)
                {
	                if(this.selectedCustomer.type == "DISTRIBUTO")
	                {
	                	return this.selectedCustomer
	                }
	            }
                return null
            },
            effectiveSuperStockist(){
                if(this.isCustomer)
                {
                    if(this.superStockist)
                        return this.superStockist
                }
                if(this.selectedCustomer)
                {
	                if(this.selectedCustomer.type == "SUPER_STOCKIST")
	                {
	                	return this.selectedCustomer
	                }
	            }
                return null
            },
            effectiveDealer(){
                if(this.isCustomer)
                {
                    if(this.dealer)
                        return this.dealer
                }
                if(this.selectedCustomer)
                {
	                if(this.selectedCustomer.type == "DEALER")
	                {
	                	return this.selectedCustomer
	                }
	            }
                return null
            },
            apiUrl(){
            	if(this.effectiveDistributor)
            	{
            		return `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDistributor.id}/blanket_orders`
            	}
            	if(this.effectiveDealer)
            	{
            		return `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/blanket_orders`
            	}	
            	if(this.effectiveSuperStockist)
            	{
            		return `companies/${this.effectiveCompany.id}/super_stockists/${this.effectiveSuperStockist.id}/blanket_orders`
            	}
            	if(this.company)
					return `companies/${this.company.id}/blanket_orders`
            },
            appendParams(){
            	let filters = {}
            	if(this.so)
            	{
            		filters.so = this.so
            	}
            	if(this.si)
            	{
            		filters.si = this.si
            	}
            	if(this.stock_point)//#762
            	{
            		filters.stock_point_id = this.stock_point.id
            	}
            	if(this.status)
            	{
            		filters.status = this.status
            	}
            	if(this.customer_type)
            	{
            		filters.selectedCustomerTypeCode = this.customer_type.code
            	}
            	if(this.selectedCustomer)
            	{
            		filters.selectedCustomerType = this.selectedCustomer.type
            		filters.selectedCustomerId = this.selectedCustomer.id
            	}
            	if(this.from_date)
            	{
            		filters.start_date = moment(this.from_date).startOf('day').unix();
            	}
            	if(this.to_date)
            	{
            		filters.end_date = moment(this.to_date).endOf('day').unix();
            	}
            	if(this.selectedDealer)
            	{
					filters.dealer_id = this.selectedDealer.id
            	}
            	return filters
            }
        },
        data(){
			return {
				tableFields:OrderFields,
				css:vuetableCssConfig,
				items:[],
				checked:false,
				shippingAddress:null,
				remarks:"",
				perPage:50,
				so:"",
				si:"",
				stock_point:null,
				status:"",
				region:null,
				company:null,
				customer_type:{code:'DISTRIBUTO',name:"Distributor"},
				selectedCustomer:null,
				page:1,
				from_date:"",
				to_date:"",
				selectedDealer:null,
			}
		},
        mounted(){
        	//{id}/{customer_type}/{customer_id}/purchase_orders
        	let self = this
			if((!this.isCustomer) && this.state.user.company)
				this.company = 	Object.assign({},this.state.user.company)
			if(this.isCustomer)
			{
				this.company = Object.assign({},this.customer.company)
			}
			if(this.isCustomer)
			{
				Vue.nextTick(function(){
				//	self.$refs.vuetable.hideField(3)//#765
					self.$refs.vuetable.hideField(4)
				})
			}
			Vue.nextTick(function(){
                if(!$('body').hasClass('sidebar-collapse'))
                {
                    self.sideBarWasOpen = true
                    $('[data-toggle="push-menu"]').pushMenu('toggle')
                }
            })
            EventBus.$on('payments:changed',function(data){
                let rec = _.find(self.$refs.vuetable.tableData,{
                	id:data.id
                })
                rec.paid_amount= _.reduce(data.payments, function(memo,e){
                	return memo + parseFloat(e.paid_amount)
                },0)
                rec.si_numbers = _.map(data.payments, function(e){
                	return e.si_number
                })
            })//#766 refresh Paid Amount & SI Numbers on row
        },
        destroyed(){
            let self = this
            if(this.sideBarWasOpen)
            {
                Vue.nextTick(function(){
                if($('body').hasClass('sidebar-collapse'))
                {
                    $('[data-toggle="push-menu"]').pushMenu('toggle')
                }
            })
            }
        },
        methods:{
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
		    		to:((data.page-1) * this.perPage) + data.purchaseOrders.length
		    	}
		    	transformed.purchaseOrders = data.purchaseOrders
		    	return transformed
		    },
		    
		    onChangePage (page) {
		      	this.$refs.vuetable.changePage(page)
		    },
		    search(){
		    	let self = this
		    	
		    	Vue.nextTick(function(){
		    		self.$refs.vuetable.refresh()
		    	})
        		
        	},
        	clear(){
        		let self = this
        		self.so = ""
        		self.si = ""
        		self.stock_point = null
        		this.status = null
        		self.from_date = ""
        		self.to_date = ""
        		if(!this.isCustomer)
        		{
        			this.customer_type = Object.assign({},{code:'ALL',name:'All'})
        			this.selectedCustomer = null
        		}
        		//self.$refs.customerType.remove()
        		//self.$refs.order_status.remove()
        		self.search()
        	},
        	isPaymentVisible(id){
                return this.$refs.vuetable.isVisibleDetailRow(id)
            },
            showPayments(row){
                this.$refs.vuetable.toggleDetailRow(row.id)
            },
            exportPOs(){
            	let params = {}
            	if(this.from_date)
            		params.start_date = moment(this.from_date).startOf('day').unix()
            	if(this.to_date)
            		params.end_date = moment(this.to_date).endOf('day').unix()
            	if(this.so)
            		params.so = this.so
            	if(this.si)
            		params.si = this.si
            	if(this.stock_point)
 					params.stock_point_id = this.stock_point.id
            	if(this.status)
            		params.status = this.status
            	if(this.customer_type)
					params.selectedCustomerTypeCode = this.customer_type.code
            	if(this.selectedCustomer)
            	{
            		params.selectedCustomerType = this.selectedCustomer.type
            		params.selectedCustomerId = this.selectedCustomer.id
            	}
            	let	url = `companies/${this.company.id}/export_orders`
            	EventBus.$emit('loader:show')
                Vue.axios.post(url,params,{responseType:'blob'})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    let disposition = response.headers['content-disposition']
					let filename = decodeURI(disposition.match(/filename=(.*)/)[1])
					saveAs(response.data, filename);
                })
                .catch(error => {})
            },//#814
        },
        watch:{
            'selectedCustomer':function(){
                this.so = ""
                this.si = ""
                this.stock_point = null
        		this.status = ""
        		this.from_date = ""
        		this.to_date = ""
        		this.selectedDealer = null
                this.search()
            },
            'customer_type.code':function(){
            	let self = this
                this.selectedCustomer = null
                Vue.nextTick(() => {
            		if(self.customer_type.code != 'ALL')
            		{
                		self.$refs.customerSelect.loadCustomers()	
            		}
                })
            },
            'company.id':function(){
                this.selectedCustomer = null
            },
            'region.id':function(){
                this.company = null
            }
        }
	}
</script>
<style>
	.has-actions{
		width: 1%;
		white-space: nowrap;
	}
</style>