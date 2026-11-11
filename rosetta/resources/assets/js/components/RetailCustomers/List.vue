<template>
	<div class="row">
		<div class="col-md-12">
			<div class="box box-widget">
				<div class="box-body">
					<vuetable 
						:fields="tableFields"
						:api-url="apiUrl"
						pagination-path="pagination"
						data-path="retailCustomers"
						ref="vuetable"
						:css="css.table"
						:per-page="perPage"
						@vuetable:pagination-data="onPaginationData">
						<template slot="actions" slot-scope="props" >
			        		<div class="custom-actions">
			        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.retailCustomers.addresses',params:{id:$route.params.id,retailCustomerId:props.rowData.id}}" title="manage addresses">
			        				<i class="fa fa-globe"></i>
			        			</router-link>
			        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.retailCustomers.edit',params:{id:$route.params.id,retailCustomerId:props.rowData.id}}"><i class="fa fa-pencil"></i></router-link>
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
</template>
<script>
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import RetailCustomerListColumns from './ListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Vue from 'vue'
	export default{
		components:{
			Vuetable,
			VuetablePagination,
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:RetailCustomerListColumns,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				companies:[],
				apiUrl:`companies/${this.$route.params.id}/retailCustomers`
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
		    		to:((data.page-1) * this.perPage) + data.retailCustomers.length
		    	}
		    	transformed.retailCustomers = data.retailCustomers
		    	return transformed
		    },
		    
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    }
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