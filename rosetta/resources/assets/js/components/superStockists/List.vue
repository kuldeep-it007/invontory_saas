<template>
	<div>
		<!--<div class="row">
			<div class="col-md-12">
				<div class="box box-widget">
					<div class="box-body">
						<div class="col-md-4">
							<div class="form-group">
								<label>Super Stockist Id</label>
								<input type="text" class="form-control" v-model="super_stockist_id">
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label>Super Stockist Name</label>
								<input type="text" class="form-control" v-model="super_stockist_name">
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
		</div>-->
		<div class="row">
			<div class="col-md-12">
				<div class="box box-widget">
					<div class="box-body">
						<vuetable 
							:fields="tableFields"
							:api-url="apiUrl"
							:append-params="appendParams"
							pagination-path="pagination"
							data-path="super_stockists"
							ref="vuetable"
							:css="css.table"
							:per-page="perPage"
							@vuetable:pagination-data="onPaginationData">
							<template slot="actions" slot-scope="props" >
				        		<div class="custom-actions">
				        	<!--		<router-link class="btn btn-xs btn-info" :to="{name:'companies.super_stockists.edit',params:{id:$route.params.id,super_stockist_id:props.rowData.id}}"><i class="fa fa-pencil-alt"></i></router-link>-->
						        	<router-link class="btn btn-xs btn-info" :to="{name:'companies.super_stockists.distributors',params:{id:$route.params.id,super_stockist_id:props.rowData.id}}" title="view distributors">
					        				<i class="fa fa-users"></i>
					        		</router-link>
									<!-- {name:'companies.super_stockists.edit',params:{id:props.rowData.id}} -->
									<router-link class="btn btn-xs btn-info" :to="{name:'companies.super_stockists.edit',params:{id:$route.params.id, super_stockist_id:props.rowData.id}}"><i class="fa fa-pencil-alt"></i></router-link>
				        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.super_stockists.product_attributes',params:{id:$route.params.id,super_stockist_id:props.rowData.id}}"><i class="fa fa-parking"></i></router-link>
				        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.super_stockists.users',params:{id:$route.params.id,super_stockist_id:props.rowData.id}}"><i class="fa fa-user-friends"></i></router-link>
				        			<router-link class="btn btn-xs btn-success" :to="{name:'companies.super_stockists.stock',params:{id:$route.params.id,super_stockist_id:props.rowData.id}}"><i class="fa fa-box-open"></i></router-link>
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
	</div>
</template>
<script>
	import RegionSelect from '../region/Select'
	import Company from '../companies/Select'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import SuperStockistListColumns from './ListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import {EventBus} from '../../configs/EventBus'
	import AppMixins from '../../vuex-mixin'
	import Vue from 'vue'
	export default{
		mixins:[AppMixins],
		components:{
			Vuetable,
			VuetablePagination,
			RegionSelect,
			Company,
		},
		mounted(){
        	let self = this
			if((!self.isCustomer) && self.state.user.company)
				self.company = 	Object.assign({},self.state.user.company)
        },
		computed:{
            appendParams(){
            	let filters = {}
            	if(this.super_stockist_name)
            	{
            		filters.super_stockist_name = this.super_stockist_name
            	}
            	if(this.super_stockist_id)
            	{
            		filters.super_stockist_id = this.super_stockist_id
            	}
            	return filters
            }
        },
		data(){
			return {
				super_stockist_name:"",
				super_stockist_id:"",
				region:null,
				company:null,
				css:vuetableCssConfig,
				tableFields:SuperStockistListColumns,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				companies:[],
				apiUrl:`companies/${this.$route.params.id}/super_stockists`,
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
		    		to:((data.page-1) * this.perPage) + data.super_stockists.length
		    	}
		    	transformed.super_stockists = data.super_stockists
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
        		this.super_stockist_name = ""
        		this.super_stockist_id = ""
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
	.vuetable-th-id{
		width: 1%;
		white-space: nowrap;	
	}
</style>