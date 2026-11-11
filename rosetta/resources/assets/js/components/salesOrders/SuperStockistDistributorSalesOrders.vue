<template>
	<div>
		<section class="content-header">
			<h1>Distributors Orders</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label>Dealer</label>
										<distributor-select v-model="selectedDistributor" :selected-super-stockist= "superStockist" :company="superStockist.company" :with-orders="true" ></distributor-select><!--#750-->
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<label>Order Date</label>
										<input type="date" class="form-control" v-model="date">
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-3">
									<div class="form-group">
										<label>SO No.</label>
										<input type="text" class="form-control" v-model="number">
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<label>Status</label>
										<order-status v-model="status" :allow-empty="true"></order-status>
									</div>
								</div>
								<div class="col-md-1">
									<div class="form-group">
										<label style="display: block;">&nbsp;</label>
										<button class="btn btn-default" @click="search">Search</button>
									</div>
								</div>
								<div class="col-md-1">
									<div class="form-group">
										<label style="display: block;">&nbsp;</label>
										<button class="btn btn-default" @click="clear">Clear</button>
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
						<div class="box-body">
							<vuetable 
	                                :fields="tableFields"
	                                :api-url="apiUrl"
	                                :append-params="appendParams"
	                                pagination-path="pagination"
	                                data-path="salesOrders"
	                                ref="vuetable"
	                                :css="css.table"
	                                track-by="id"
	                                :per-page="perPage"
	                                @vuetable:pagination-data="onPaginationData"
                            >
	                        	<template slot="status" slot-scope="props" >
	                        		{{props.rowData.status}}
								</template> 
								<template slot="customer.stock_points" slot-scope="props">
	                        		{{ props.rowData.stock_points ? props.rowData.stock_points.join(', ') : '' }}
								</template>
								<template slot="customer.name" slot-scope="props">
	                        		{{props.rowData.customer.name}}
									<!-- <template v-if="props.rowData.customer.type == 'Distributor'">
	                        			 	<span class="badge bg-blue pull-right pull-right" style="padding: 5px 15px;">DIST</span>
									</template>
									<template v-if="props.rowData.customer.type == 'superStockist'">
	                        			 	<span class="badge bg-green pull-right pull-right" style="padding: 5px 15px;">SS</span>
									</template> -->
								</template>   
								<template slot="actions" slot-scope="props" >
			        				<router-link :to="{name:'superstockistSalesOrders.view',params:{id:props.rowData.id}}">VIEW</router-link>
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
	import OrderStatus from '../master/DealerOrderStatus'
    import DistributorSelect from '../master/DistributorSelect'
	export default{
		mixins:[AppMixins],
		components:{
			Vuetable,
			VuetablePagination,
			OrderStatus,
            DistributorSelect,
		},
		computed:{
            effectiveCompany(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor.company
                }
            },
            effectiveDisributor(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor
                }
                return null
            },
			apiUrl(){
            	let url = `companies/${this.superStockist.company.id}/superstockists/${this.superStockist.id}/sales_orders`;
				return url;
            },
            appendParams(){
            	let filters = {
					is_dist : true
				}
            	if(this.number)
            	{
            		filters.number = this.number.replace('SO', 'PO')
            	}
            	if(this.status)
            	{
            		filters.status = this.status
            	}
            	if(this.selectedDistributor)
            	{
					filters.customer_id = this.selectedDistributor.id
            	}
            	if(this.date)
            	{
            	//	filters.date = moment(this.date).unix()
            	//	filters.date = this.date
            		filters.end_date = moment(this.date).endOf('day').unix();
            		filters.start_date = moment(this.date).startOf('day').unix();
            	}

				if(this.$route.query.is_ss=='true'){
					filters.is_ss = true;
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
				number:"",
				status:"",
				selectedDistributor:null,
				date:""
			}
		},
        mounted(){
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
		    		to:((data.page-1) * this.perPage) + data.salesOrders.length
		    	}
		    	transformed.salesOrders = data.salesOrders
		    	return transformed
		    },
		    
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    },
		    search(){
		    	let self = this
		    	Vue.nextTick(function(){
		    		self.$refs.vuetable.reload()	
		    	})
        		
        	},
        	clear(){
        		this.number = ""
        		this.status = ""
        		this.date = ""
        		this.selectedDistributor = null
        		this.search()
        	},
        },
        watch:{
            'selectedDistributor':function(){
                this.number = ""
        		this.status = ""
        		this.date = ""
                this.search()
            },
            '$route.query':function(){
                this.search()
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