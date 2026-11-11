<template>
	<div v-if="region && abilities.viewTaxes">
		<section class="content-header">
			<h1>{{region.name}}<span class="pull-right"><button class="btn btn-sm btn-primary" @click="addTax" v-if="abilities.addTaxes">Add Tax</button></span></h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<vuetable 
								:fields="tableFields"
								:api-url="apiUrl"
								pagination-path="pagination"
								data-path="taxes"
								:per-page="perPage"
								ref="vuetable"
								:css="css.table"
								@vuetable:pagination-data="onPaginationData"
								>
								<template slot="actions" slot-scope="props">
					        		<div class="custom-actions">
					        			<span class="pull-right">
					        				<button class="btn btn-xs btn-primary" @click="showUpdateTaxModal(props.rowData,props.rowIndex)" v-if="abilities.addTaxes">Update</button>
					        				<button class="btn btn-xs btn-primary" @click="deleteTax(props.rowData.id,props.rowIndex)" v-if="abilities.deleteTaxes">Delete</button>
					        			</span>
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
		<update-tax ref="updateModal" @product_tax:updated="ProductTaxUpdated"></update-tax>
		<add-tax ref="addModal" @product_tax:added="ProductTaxAdded"></add-tax>
	</div>
</template>
<script>
	import appMixin from '../../../vuex-mixin.js'
	import Vuetable from '../../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../../vendor/vuetable2/VuetablePagination'
	import vuetableCssConfig from '../../../configs/VuetableCssConfig'
	import ListColumns from './ListFields.js'
	import UpdateTax from './Modal'
	import AddTax from './addModal'
	import {EventBus} from '../../../configs/EventBus'
	import Vue from 'vue'
	export default{
		mixins : [appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			AddTax,
			UpdateTax
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:ListColumns,
				perPage:20,
				region:null,
				selectedProductTax:null,
			}
		},
		computed:{
			apiUrl(){
				return `regions/${this.$route.params.id}/product_taxes`
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
		    		to:((data.page-1) * this.perPage) + data.taxes.length
		    	}
		    	transformed.taxes = data.taxes
		    	return transformed
		    },
		    
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    },
		    showUpdateTaxModal(product_tax,index){
				this.selectedProductTax = product_tax
				this.$refs.updateModal.show(product_tax)
			},
			ProductTaxUpdated(tax){
				this.selectedProductTax.tax_percent = tax
			},
			ProductTaxAdded(tax){
				this.$refs.vuetable.reload()
			},
			addTax(){
				this.$refs.addModal.show()
			},
			deleteTax(taxId, index){
				let self = this
				swal({
                    title: "Delete Tax?",
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    }
                })
                .then(value => {
                    if(value !== null)
                    {
						let url = `regions/${this.$route.params.id}/delete_product_taxes/${taxId}`
						EventBus.$emit('loader:show')
                    	Vue.axios.delete(url)
                    	.then(response => {
                    		self.$refs.vuetable.tableData.splice(index,1)
                    		EventBus.$emit('loader:hide')
              			})
                    	.catch(error=>{
                    		EventBus.$emit('loader:hide')
                    	})
                    }
                })
                .catch()
			},
		},
		mounted(){
			let self = this
			if(this.$route.params.id)
			{
				Vue.axios.get(`regions/${this.$route.params.id}`)
				.then(response => {
					self.region = Object.assign({},response.data)
				})
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