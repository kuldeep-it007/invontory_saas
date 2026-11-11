<template>
	<div  v-if="abilities.manageCoDistributorOrders">
		<section class="content-header">
			<h1>Sales Orders <small>Orders placed by my co-distributors</small></h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label>Co Distributor</label>
										<co-distributor v-model="selectedCoDistributor" ref="coDistributors" :customer_type="customerType" :use_distributor="effectiveDisributor"></co-distributor>
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
	                                data-path="orders"
	                                ref="vuetable"
	                                :css="css.table"
	                                track-by="id"
	                                :per-page="perPage"
	                                @vuetable:pagination-data="onPaginationData"
	                                @vuetable:loading="loadingData"
									@vuetable:loaded="dataLoaded"
                            >
	                        	<template slot="status" slot-scope="props" >
	                        		{{props.rowData.status}}
								</template> 
								<template slot="customer.name" slot-scope="props">
	                        		{{props.rowData.customer.name}}
								</template>   
								<template slot="actions" slot-scope="props" >
			        				<router-link :to="{name:'co-distributor-so-view',params:{id:props.rowData.id}}">VIEW</router-link>
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
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../../vuex-mixin.js'
	import Vuetable from '../../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../../vendor/vuetable2/VuetablePagination'
	import vuetableCssConfig from '../../../configs/VuetableCssConfig'
	import {EventBus} from '../../../configs/EventBus'
	import Vue from 'vue'
	import OrderFields from './SalesOrdersFields'
	import OrderStatus from '../../master/DealerOrderStatus'
  	import CoDistributor from '../../master/DistributorCustomerSelect'
	export default{
		mixins:[appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			OrderStatus,
			CoDistributor
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
				selectedCoDistributor:null,
				date:"",
				customerType:{
					code:"DISTRIBUTO",
					name:"Distributor"
				},
			}
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
			appendParams(){
				let filters = {}
				if(this.number)
            	{
            		filters.number = this.number.replace('SO', 'PO')
            	}
            	if(this.status)
            	{
            		filters.status = this.status
            	}
            	if(this.selectedCoDistributor)
            	{
					filters.customer_id = this.selectedCoDistributor.id
            	}
            	if(this.date)
            	{
            		filters.end_date = moment(this.date).endOf('day').unix();
            		filters.start_date = moment(this.date).startOf('day').unix();
            	}
            	return filters
			},
			apiUrl(){
				let url = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors/orders?type=2`
				return url
			}
		},
		mounted(){
			let self = this
			Vue.nextTick(function(){
				self.$refs.coDistributors.loadCustomers()	
			})
		},
		methods:{
			dataLoaded(){
				EventBus.$emit('loader:hide')
			},
			loadingData(){
				EventBus.$emit('loader:show')
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
		    		to:((data.page-1) * this.perPage) + data.orders.length
		    	}
		    	transformed.orders = data.orders
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
        		this.selectedCoDistributor = null
        		this.search()
        	},
		},
	}
</script>