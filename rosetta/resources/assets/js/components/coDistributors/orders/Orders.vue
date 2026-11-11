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
										<region-select v-model="region" :allow-empty="true"></region-select>
									</div>
								</div>
								<div class="col-md-3" v-if="this.state.user.company == null">
									<div class="form-group">
										<label>Company</label>
										<company v-model="company" :region="region" :disabled="!region"></company>
									</div>
								</div>
				                <div class="col-md-3">
				                    <div class="form-group" :class="errors.has('customer') ? 'has-error' : ''">
				                    	<label>Distributor</label>
				                        <customer-select v-model="selectedCustomer" :company="company" :customer_type="customer_type" :disabled="!company" :required="true" v-validate="'required'" data-vv-name="customer" ref="customerSelect"></customer-select>
				                    </div>
				                </div>
							</div>
							<div class="row">
								<div class="col-md-3">
									<div class="form-group">
										<label>Order No</label>
										<input type="text" class="form-control" v-model="order_number" :disabled="!company">
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<label>Status</label>
										<order-status v-model="status" :allow-empty="true" :disabled="!company"></order-status>
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
						<div class="box-body">
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
                            >
	                        	<template slot="status" slot-scope="props" >
	                        		{{props.rowData.status}}
								</template> 
								<template slot="customer.name" slot-scope="props">
	                        		{{props.rowData.customer.name}}
								</template>   
								<template slot="actions" slot-scope="props" >
			        				<router-link :to="{name:'co-distributor-order-view',params:{id:props.rowData.id}}">VIEW</router-link>
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
	import RegionSelect from '../../region/Select'
	import Company from '../../companies/Select'
	import {EventBus} from '../../../configs/EventBus'
	import AppMixins from '../../../vuex-mixin'
	import OrderFields from './OrderFields'
	import vuetableCssConfig from '../../../configs/VuetableCssConfig'
	import VuetablePagination from '../../../vendor/vuetable2/VuetablePagination'
	import Vuetable from '../../../vendor/vuetable2/Vuetable'
	import OrderStatus from '../../master/DealerOrderStatus'
	import CustomerType from '../../master/CustomerType'
    import CustomerSelect from '../../master/CustomerSelect'
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
		},
		computed:{
			effectiveCompany(){
                return this.company
            },
            effectiveDistributor(){
                if(this.selectedCustomer)
                {
	                if(this.selectedCustomer.type == "DISTRIBUTO")
	                {
	                	return this.selectedCustomer
	                }
	            }
                return null
            },
            apiUrl(){
            	if(this.effectiveDistributor)
            	{
            		return `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDistributor.id}/co_distributor_blanket_orders`
            	}	
            	if(this.company)
					return `companies/${this.company.id}/co_distributor_blanket_orders`
            },
            appendParams(){
            	let filters = {}
            	if(this.order_number)
            	{
            		filters.order_number = this.order_number
            	}
            	if(this.status)
            	{
            		filters.status = this.status
            	}
            	if(this.selectedCustomer)
            	{
            		filters.selectedCustomerId = this.selectedCustomer.id
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
				order_number:"",
				status:"",
				region:null,
				company:null,
				customer_type:{code:'DISTRIBUTO',name:"Distributor"},
				selectedCustomer:null,
			}
		},
        mounted(){
        	let self = this
			if((!this.isCustomer) && this.state.user.company)
				this.company = 	Object.assign({},this.state.user.company)
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
		    		self.$refs.vuetable.reload()	
		    	})
        		
        	},
        	clear(){
        		this.order_number = ""
        		this.status = ""
        		this.search()
        	},
        },
        watch:{
            'selectedCustomer':function(){
                this.order_number = ""
        		this.status = ""
                this.search()
            },
            'company.id':function(){
                this.selectedCustomer = null
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