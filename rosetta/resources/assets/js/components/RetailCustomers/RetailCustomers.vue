<template>
	<div>
		<section class="content-header">
			<h1>Retail Customers 
				<span class="pull-right" v-if="this.company">
					<router-link class="btn btn-default btn-sm" :to="{name:'companies.retailCustomers.create',params:{id:this.company.id}}" v-if="abilities.createRetailCustomers"> ADD CUSTOMER</router-link>
				</span>
			</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row" v-if="!isCustomer && this.state.user.company == null">
								<div class="col-md-3">
									<div class="form-group">
										<label>Region</label>
										<region-select v-model="region" :allow-empty="true"></region-select>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<label>Company</label>
										<company v-model="company" :region="region" :disabled="!region"></company>
									</div>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label>Customer Name</label>
									<input type="text" class="form-control" v-model="customer_name" :disabled="!company">
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label>Customer Id</label>
									<input type="text" class="form-control" v-model="customer_id" :disabled="!company">
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
			<div class="row" v-if="company">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<vuetable 
	                                :fields="tableFields"
	                                :api-url="apiUrl"
	                                :append-params="appendParams"
	                                pagination-path="pagination"
	                                data-path="retailCustomers"
	                                ref="vuetable"
	                                :css="css.table"
	                                track-by="id"
	                                :per-page="perPage"
	                                @vuetable:pagination-data="onPaginationData"
                            >
								<template slot="customer.name" slot-scope="props">
	                        		{{props.rowData.customer.name}}
								</template>   
								<template slot="actions" slot-scope="props" >
			        				<router-link class="btn btn-xs btn-info" :to="{name:'companies.retailCustomers.edit',params:{id:props.rowData.company.id,retailCustomerId:props.rowData.id}}"><i class="fa fa-pencil-alt"></i></router-link>
			        				<router-link class="btn btn-xs btn-info" :to="{name:'companies.retailCustomers.addresses',params:{id:props.rowData.company.id,retailCustomerId:props.rowData.id}}" title="manage addresses">
			        					<i class="fa fa-globe"></i>
			        				</router-link>
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
	import RegionSelect from '../region/Select'
	import Company from '../companies/Select'
	import {EventBus} from '../../configs/EventBus'
	import AppMixins from '../../vuex-mixin'
	import RetailCustomerFields from './RetailCustomerFields'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	export default{
		mixins:[AppMixins],
		components:{
			Vuetable,
			VuetablePagination,
			RegionSelect,
			Company,
		},
		computed:{
            apiUrl(){	
            	if(this.company)
					return `companies/${this.company.id}/retail_customers`
            },
            appendParams(){
            	let filters = {}
            	if(this.customer_name)
            	{
            		filters.customer_name = this.customer_name
            	}
            	if(this.customer_id)
            	{
            		filters.customer_id = this.customer_id
            	}
            	return filters
            }
        },
        data(){
			return {
				tableFields:RetailCustomerFields,
				css:vuetableCssConfig,
				checked:false,
				perPage:50,
				customer_name:"",
				customer_id:"",
				region:null,
				company:null,
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
		    		to:((data.page-1) * this.perPage) + data.retailCustomers.length
		    	}
		    	transformed.retailCustomers = data.retailCustomers
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
        		this.customer_name = ""
        		this.customer_id = ""
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