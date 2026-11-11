<template>
	<div>
		<section class="content-header">
			<h1>Depot Orders</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
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
										<label>Tenant</label>
										<stockpoint-tenants
											:stock-point="stock_point"
											v-model="tenant"
										>
										</stockpoint-tenants>
									</div>
								</div>
							</div>
							<div class="row">
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
								<template slot="dealer_name" slot-scope="props">
                            		{{props.rowData.dealer_name}}
									<template v-if="props.rowData.source == 'APP' && props.rowData.on_behalf != 'Dealer'">
	                        			 <span class="badge bg-green pull-right">Self</span>
	                        		</template> 
								</template>   
	                        	<template slot="status" slot-scope="props" >
	                        		{{props.rowData.status}}
								</template> 
								<template slot="customer.name" slot-scope="props">
									<template v-if="props.rowData.customer">
	                        			{{props.rowData.customer.name}}
	                        		</template> 
								</template>   
								<template slot="actions" slot-scope="props" >
			        				<router-link :to="{name:'DepotOrders.view',params:{id:props.rowData.id},query:{tenant:tenant.name}}" v-if="tenant">VIEW</router-link>
			        				<router-link :to="{name:'DepotOrders.view',params:{id:props.rowData.id}}" v-else>VIEW</router-link>
								</template>

								<template slot="paid_amount" slot-scope="props" >
                                    {{props.rowData.paid_amount}}
                                    <a href="#" class="text-green" @click.prevent="showPayments(props.rowData)">
                                        <span v-if="isPaymentVisible(props.rowData.id)">
                                            <i class="fa fa-eye-slash pull-right"></i>
                                        </span>
                                        <span v-else> <i class="fa fa-eye pull-right"></i></span>
                                    </a>
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
		</section>
	</div>
</template>
<script>
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import AppMixins from '../../vuex-mixin'
	import OrderFields from './OrderFields'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import OrderStatus from '../master/OrderStatus'
	import StockPoint from '../stockpoints/Select'
    import Payments from  './Payments'//#766
	import StockpointTenants from './StockpointTenants.vue'
    Vue.component('Payments',Payments)
	export default{
		mixins:[AppMixins],
		components:{
			Vuetable,
			VuetablePagination,
			StockPoint,
			OrderStatus,
			StockpointTenants
		},
		computed:{
            apiUrl(){
            	if(this.tenant)
            	{
            		return `companies/${this.company.id}/inter_depot_orders`
            		//return this.tenant.url + `/api/companies/${this.company.id}/depot_orders`
            	}
            	if(this.company)
					return `companies/${this.company.id}/current_depot_orders`
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
            		filters.stock_point_code = this.stock_point.code
            	}
            	if(this.tenant)//#762
            	{
            		filters.tenant = this.tenant.name
            	}
            	if(this.status)
            	{
            		filters.status = this.status
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
				company:null,
				page:1,
				tenant:null,
				tenant_url:null
			}
		},
        mounted(){
        	let self = this
			if((!this.isCustomer) && this.state.user.company)
				this.company = 	Object.assign({},this.state.user.company)
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
        		self.search()
        	},
        	isPaymentVisible(id){
                return this.$refs.vuetable.isVisibleDetailRow(id)
            },
            showPayments(row){
                this.$refs.vuetable.toggleDetailRow(row.id)
            },
        },
        watch:{
        	stock_point:function(n,o){ 
        		if(!this.tenant)
        		{
        			let self = this
			    	Vue.nextTick(function(){
			    		self.$refs.vuetable.refresh()
			    	})
        		}
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