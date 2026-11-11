<template>
	<div>
		<section class="content-header">
			<h1>
				<template v-if="!isCustomer">
					Super Stockist Stock
					<span class="pull-right"><button class="btn btn-sm btn-primary" @click="addStock" v-if="abilities.addStock">ADD</button></span>
				</template>
				<template v-if="isCustomer">
					Stock
				</template> 
				
			</h1>
		</section>
		<section class="content">
			<div class="box box-widget">
				<div class="box-body">
					<div class="row">
						<div class="col-md-3">
							<div class="form-group">
								<category v-model="category"></category>
							</div>
						</div>
						<div class="col-md-3">
							<div class="form-group">
								<collection v-model="collection" :category="category"></collection>
							</div>
						</div>
						<div class="col-md-3">
							<div class="form-group">
								<colour v-model="colour" :collection="collection"></colour>
							</div>
						</div>
						<div class="col-md-3">
							<div class="form-group text-right">
								<button class="btn btn-primary" @click="search"><i class="fa fa-search"></i></button>
								<button class="btn btn-default" @click="clear">Clear</button>
							</div>
						</div>
					</div>
					<!--<div class="row">
						<div class="col-md-3">
							<div class="form-group">
								<quantity v-model="quantity" ref='quantity'></quantity>
							</div>
						</div>
						<div class="col-md-9">
							<div class="form-group text-right">
								<button class="btn btn-primary" @click="search"><i class="fa fa-search"></i></button>
								<button class="btn btn-default" @click="clear">Clear</button>
							</div>
						</div>
					</div>-->
					<hr>
					<div class="row" style="margin-bottom: 5px;">
						<div class="col-md-12 text-right">
							<a href="#" @click.prevent="exportStock"><i class="fas fa-download"></i> Download</a>
							<a v-if="!isCustomer" style="margin-left:10px;" href="#" @click.prevent="importStock"><i class="fas fa-upload"></i> Import</a>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12">
							<div class="table-responsive">
								<vuetable 
					                :fields="tableFields"
					                :append-params="appendParams"
					                :api-url="apiUrl"
					                pagination-path="pagination"
					                data-path="inventory"
					                ref="vuetable"
					                :css="css.table"
					                track-by="id"
					                http-method="post"
					                :per-page="perPage"
					                @vuetable:pagination-data="onPaginationData">
					            >
						            <template slot="inventory" slot-scope="props" >
						                {{props.rowData.quantity}} <sup>{{props.rowData.product.base_unit_of_measure}}</sup>
						            </template>
						            <template slot="blocked" slot-scope="props" >
			                            {{props.rowData.blocked.toFixed(2)}} <sup>{{props.rowData.product.base_unit_of_measure}}</sup>
			                        </template>
						            <template slot="actions" slot-scope="props" v-if="!isCustomer">
			                            <div class="custom-actions">
			                				<a href="#" @click.prevent="adjustStock(props.rowData,props.rowIndex)" v-if="abilities.updateStock"><i class="fa fa-pencil-alt"></i></a>
			                				<a href="#" @click.prevent="deleteStock(props.rowData.id,props.rowIndex)" v-if="abilities.deleteStock"><i class="fa fa-trash-alt"></i></a>                           
			                            </div>
			                        </template>
						        </vuetable>
						    </div>
						</div>
					</div>
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
		     <adjust-stock 
		     	v-if="!isCustomer" 
		     	ref="adjustStock" 
		     	@stock-adjust:set="stockChanged" 
		     	:superStockistId="$route.params.super_stockist_id">
		     </adjust-stock>
		     <add-stock-modal
		     	v-if="!isCustomer"
				ref="addStockModal" 
				@stock-added="onStockAdded"
				:companyId="$route.params.id"
	            :superStockistId="$route.params.super_stockist_id">
			</add-stock-modal>
			<import-stock-modal
		     	v-if="!isCustomer"
				ref="importStockModal" 
				@stock-imported="onStockImported"
	            :superStockistId="$route.params.super_stockist_id">
			</import-stock-modal>
		</section>
	</div>
</template>
<script>
	import Vue from 'vue'
	import appMixin from '../../vuex-mixin.js'
    import InventoryListFields  from './InventoryListFields.js'
    import Vuetable from '../../vendor/vuetable2/Vuetable'
    import vuetableCssConfig from '../../configs/VuetableCssConfig'
    import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
    import AdjustStock from './AdjustModal'
    import AddStockModal from './AddStockModal'
    import ImportStockModal from './ImportStockModal'
    import Category from '../categories/Search'
    import Collection from '../collections/Search'
    import Colour from '../colour/Search'
   // import Quantity from '../Quantity'
    import { saveAs } from 'file-saver'
    import {EventBus} from '../../configs/EventBus'
    //import Quantity from '../Quantity'
    
	export default{
		mixins : [appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			AdjustStock,
			AddStockModal,
			ImportStockModal,
			Category,
			Collection,
			Colour,
			//Quantity
		},
		data(){
			return {
				css:vuetableCssConfig,
                tableFields:InventoryListFields,
                perPage:15,
                category:null,
                collection:null,
                colour:null,
             //   quantity:'>0'
			}
		},
		computed:{
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
            apiUrl(){
            	if(this.effectiveSuperStockist)
            		return `super_stockists/${this.effectiveSuperStockist.id}/inventory`
            	if(!this.isCustomer)
            		return `super_stockists/${this.$route.params.super_stockist_id}/inventory`
            },
            appendParams(){
            	let params = {filters:{}}
            	if(this.category)
            		params.filters.category = this.category.code
            	if(this.collection)
            		params.filters.collection = this.collection.code
            	if(this.colour)
            		params.filters.colour = this.colour.code
           // 	if(this.quantity)
            //		params.filters.quantity = this.quantity
            	return params
            }
		},
		methods:{
			addStock(){
				this.$refs.addStockModal.show()
			},
			deleteStock(stockId,index){
	          	this.$validator.validateAll()
	          	.then(value => {
	          		if(!value)
                        return;
		            let url = `companies/${this.$route.params.id}/super_stockists/${this.$route.params.super_stockist_id}/delete_stock/${stockId}`
		            EventBus.$emit('loader:show')
		            Vue.axios.delete(url)
		            .then(response => {
		                swal({
	                        title:"Stock deleted!",
	                        icon:"success"
	                    })
	                    .then(value => {
	                    	this.$refs.vuetable.tableData.splice(index,1)
	                    	//this.$refs.vuetable.refresh()
	                    }) 
		            })
		            .catch(error => {
		              	if(error.response.status == 409)
		              	{
		                	swal({
			                    title: "Error",
			                    text:error.response.data.error,
			                    icon:'error'
			                })
		              	}
		            })
	          	})
      		},
			onStockAdded(stock){
        		this.$refs.vuetable.reload()
            },
            onStockImported(){
        		this.$refs.vuetable.reload()
            },
            clear(){
            	let self = this
        		this.category = null
        		this.collection = null
        		this.colour = null
        		this.search()
        	},
        	importStock(){
				this.$refs.importStockModal.show()
			},
            exportStock(){
            	let params = {filters:{}}
            	if(this.category)
            		params.filters.category = this.category.code
            	if(this.collection)
            		params.filters.collection = this.collection.code
            	if(this.colour)
            		params.filters.colour = this.colour.code
            	/*if(this.quantity)
            		params.filters.quantity = this.quantity
*/
            	let url = ''
            	if(!this.isCustomer)
            		url = `super_stockists/${this.$route.params.super_stockist_id}/export_stock`
            	else
            		url = `super_stockists/${this.effectiveSuperStockist.id}/export_stock`

            	EventBus.$emit('loader:show')
                Vue.axios.post(url,{params:params},{responseType:'blob'})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    let disposition = response.headers['content-disposition']
                 //   console.log(disposition)
					let filename = decodeURI(disposition.match(/filename="(.*)"/)[1])
				//	console.log(filename)
					saveAs(response.data, filename);

                })
                .catch(error => {})
            },
			search(){
				this.$refs.vuetable.refresh()
			},
			stockChanged(data){
				let d = Object.assign({},this.$refs.vuetable.tableData[data.index])
				console.log(data.index,this.$refs.vuetable.tableData,d)
				d.quantity = data.quantity
				this.$refs.vuetable.tableData.splice(data.index,1,d)
			},
			adjustStock(inventory,index){
				this.$refs.adjustStock.show(inventory,index)
			},
			onPaginationData (tablePagination) {
		      this.$refs.pagination.setPaginationData(tablePagination)
		    },
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
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
		    }
		}
	}
</script>