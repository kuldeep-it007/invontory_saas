<template>
	<div class="row">
		<div class="col-md-12">
			<h4>Inventory</h4>
			<div class="box box-widget">
				<div class="box-body">
					<vuetable 
						:fields="tableFields"
						:api-url="apiUrl"
						pagination-path=""
						data-path="inventory"
						:per-page="perPage"
						ref="vuetable"
						:css="css.table"
						track-by="id"
						detail-row-component="inventory-details"
					>
						<template slot="inventory" slot-scope="props" >
							{{props.rowData.inventory}} <sup>{{props.rowData.base_unit_of_measure}}</sup>
						</template>
						<template slot="actions" slot-scope="props" >
			        		<div class="custom-actions">
			        			<a href="#" @click.prevent="showDetails(props.rowData)"><i class="fa fa-eye"></i></a>
			        		</div>
			      		</template>
					</vuetable>
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
	import InventoryDetails from './Details'
	Vue.component('inventory-details', InventoryDetails)
	export default{
		props:{
			company:{
				type:Object,
				required:true
			}
		},
		components:{
			Vuetable,
			VuetablePagination,
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:CompanyListColumns,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				companies:[],
			}
		},
		computed:{
			apiUrl(){
				return `companies/${this.company.id}/inventory`
			}
		},
		methods:{
			showDetails(row){
				this.$refs.vuetable.toggleDetailRow(row.id)
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
		    		to:((data.page-1) * this.perPage) + data.inventory.length
		    	}
		    	transformed.inventory = data.inventory
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