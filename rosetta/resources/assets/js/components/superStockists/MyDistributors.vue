<template>
	<div v-if="abilities.companies || abilities.viewDistributors">
		<section class="content-header">
			<h1>My Distributors 
				<span class="pull-right">
					<router-link class="btn btn-default btn-sm" :to="{name:'add-distributor'}" v-if="abilities.createDistributors">ADD DISTRIBUTOR</router-link>
				</span>
			</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label>Distributor</label>
										<distributor-select v-model="selectedDistributor" :selected-super-stockist= "effectiveSuperStockist" :company="effectiveCompany" v-if="effectiveCompany"></distributor-select>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label>Distributor ID</label>
										<input type="text" class="form-control" v-model="customer_id">
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
			<div class="row" v-if="!isCustomer">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="col-md-4">
								<div class="form-group">
									<label>Distributor Id</label>
									<input type="text" class="form-control" v-model="customer_id">
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label>Distributor Name</label>
									<input type="text" class="form-control" v-model="customer_name">
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
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<vuetable 
								:fields="tableFields"
								:api-url="apiUrl"
								:append-params="appendParams"
								pagination-path="pagination"
								data-path="distributors"
								ref="vuetable"
								:css="css.table"
								:per-page="perPage"
								@vuetable:pagination-data="onPaginationData">
								<template slot="actions" slot-scope="props" >
					        		<div class="custom-actions">
					        			<router-link class="btn btn-xs btn-info" :to="{name:'my-distributors.edit',params:{distributorid:props.rowData.id}}"><i class="fa fa-pencil-alt"></i></router-link>
					        			<router-link class="btn btn-xs btn-info" :to="{name:'my-distributors.users',params:{distributorid:props.rowData.id}}"><i class="fa fa-user-friends"></i></router-link>
					        		</div>
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
	import appMixin from '../../vuex-mixin.js'
	import MyDistributorListFields from './MyDistributorFields.js'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import {EventBus} from '../../configs/EventBus'
	import DistributorSelect from '../master/DistributorSelect'
	import Vue from 'vue'
	export default{
		mixins:[appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			DistributorSelect,
		},
		data(){
			return {
				css:vuetableCssConfig,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				tableFields:MyDistributorListFields,
				customer_name:"",
				customer_id:"",
				selectedDealer:null,
			}
		},
		computed:{
			apiUrl(){
				return `companies/${this.superStockist.company.id}/super_stockists/${this.superStockist.id}/distributors`
			},
			appendParams(){
            	let filters = {}
           /* 	if(this.customer_name)
            	{
            		filters.customer_name = this.customer_name
            	}*/
            	if(this.customer_id)
            	{
            		filters.customer_id = this.customer_id
            	}
            	if(this.selectedDistributor)
					filters.customer_id = this.selectedDistributor.nav_id
            	return filters
            },
            effectiveCompany(){
                if(this.isCustomer)
                {
                    if(this.superStockist)
                        return this.superStockist.company
                }
            },
            effectiveSuperStockist(){
                if(this.isCustomer)
                {
                    if(this.superStockist)
                        return this.superStockist
                }
                return null
            },
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
		    		to:((data.page-1) * this.perPage) + data.distributors.length
		    	}
		    	transformed.distributors = data.distributors
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
        		this.selectedDistributor = null
        		this.search()
        	},
		}
	}
</script>