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
										<company v-model="company" :region="region"></company>
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
							<div class="row">
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
	                                data-path="invoices"
	                                ref="vuetable"
	                                :css="css.table"
	                                track-by="id"
	                                :per-page="perPage"
	                                @vuetable:pagination-data="onPaginationData"
                            >
								<template slot="customer.name" slot-scope="props">
	                        		{{props.rowData.customer.name}} ({{props.rowData.customer.nav_id}})
								</template>

                                <template slot="order" slot-scope="props">
	                        		<router-link :to="{name:'orders.view',params:{id:props.rowData.order.id}}" v-if="props.rowData.order">{{props.rowData.order.number}}</router-link>
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
	import Vue from 'vue'
	import RegionSelect from '../region/Select'
	import Company from '../companies/Select'
	import {EventBus} from '../../configs/EventBus'
	import AppMixins from '../../vuex-mixin'
	import TableFields from './Fields'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import CustomerType from '../master/CustomerType'
    import CustomerSelect from '../master/CustomerSelect'
	export default{
		mixins:[AppMixins],
		components:{
			Vuetable,
			VuetablePagination,
			RegionSelect,
			Company,
			CustomerSelect,
            CustomerType,
		},
		computed:{
			effectiveCompany(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor.company
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
            	if(this.company)
					return `companies/${this.company.id}/invoices`
            },
            appendParams(){
            	let filters = {}
            	if(this.so)
            	{
            		filters.so = this.so
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
            	return filters
            }
        },
        data(){
			return {
				tableFields:TableFields,
				css:vuetableCssConfig,
				items:[],
				checked:false,
				shippingAddress:null,
				remarks:"",
				perPage:50,
				so:"",
				status:"",
				region:null,
				company:null,
				customer_type:{code:'DISTRIBUTO',name:"Distributor"},
				selectedCustomer:null,
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
					self.$refs.vuetable.hideField(2)	
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
		    		to:((data.page-1) * this.perPage) + data.invoices.length
		    	}
		    	transformed.invoices = data.invoices
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
        		this.so = ""
        		this.status = ""
        		this.customer_type = null
        		this.search()
        	},
        },
        watch:{
            'selectedCustomer':function(){
                this.so = ""
        		this.status = ""
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