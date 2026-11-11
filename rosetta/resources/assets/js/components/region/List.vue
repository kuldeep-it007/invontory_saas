<template>
	<div class="row">
		<div class="col-md-12">
			<div class="box box-widget">
				<div class="box-body">
					<vuetable 
						:fields="tableFields"
						api-url="regions"
						pagination-path="pagination"
						data-path="regions"
						:per-page="perPage"
						ref="vuetable"
						:css="css.table"
						@vuetable:pagination-data="onPaginationData">
						<template slot="actions" slot-scope="props" >
			        		<div class="custom-actions">
			        			<router-link :to="{name:'regions.edit',params:{id:props.rowData.id}}"><i class="fa fa-pencil-alt"></i></router-link>
			        			<router-link :to="{name:'regions.product_taxes',params:{id:props.rowData.id}}"><i class="fa fa-percent"></i></router-link>
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
	import RegionListColumns from './ListFields.js'
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
				tableFields:RegionListColumns,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				regions:[]
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
		    		to:((data.page-1) * this.perPage) + data.regions.length
		    	}
		    	transformed.regions = data.regions
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