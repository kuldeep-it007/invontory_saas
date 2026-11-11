<template>
	<div class="row">
		<div class="col-md-12">
			<div class="box box-widget">
				<div class="box-body table-responsive">
					<vuetable 
						:fields="tableFields"
						api-url="companies"
						pagination-path="pagination"
						data-path="companies"
						:per-page="perPage"
						ref="vuetable"
						:css="css.table"
						@vuetable:pagination-data="onPaginationData">
						<template slot="actions" slot-scope="props" >
			        		<div class="custom-actions">
			        			<router-link class="btn btn-xs btn-info" :to="{name:'company',params:{id:props.rowData.id}}" title="view company">
			        				<i class="fa fa-eye"></i>
			        			</router-link>
			        			<router-link class="btn btn-xs btn-primary" :to="{name:'companies.super_stockists',params:{id:props.rowData.id}}" title="view super stockists">
			        				<i class="fas fa-users-cog"></i>
			        			</router-link>
			        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.distributors',params:{id:props.rowData.id}}" title="view distributors">
			        				<i class="fa fa-users"></i>
			        			</router-link>
			        			<router-link class="btn btn-xs btn-warning" :to="{name:'companies.dealers',params:{id:props.rowData.id}}" title="view dealers">
			        				<i class="fa fa-users"></i>
			        			</router-link>
			        			<router-link class="btn btn-xs btn-success" :to="{name:'companies.codistributors.list',params:{id:props.rowData.id}}" title="manage co-distributors">
			        				<i class="fa fa-users"></i>
			        			</router-link>
			        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.edit',params:{id:props.rowData.id}}"><i class="fa fa-pencil-alt"></i></router-link>
			        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.product_attributes',params:{id:props.rowData.id}}"><i class="fa fa-parking"></i></router-link>
								<router-link class="btn btn-xs btn-info" :to="{name:'companies.replenishreport',params:{id:props.rowData.id}}"><i class="fa fa-box-open"></i></router-link>
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
	import CompanyListColumns from './ListFields.js'
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
				tableFields:CompanyListColumns,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				companies:[]
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
		    		to:((data.page-1) * this.perPage) + data.companies.length
		    	}
		    	transformed.companies = data.companies
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