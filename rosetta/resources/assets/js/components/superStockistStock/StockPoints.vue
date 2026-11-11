<template>
	<div>
		<section class="content-header">
			<h1>Stock Point</h1>
		</section>
		<section class="content">
			<div class="box box-widget">
				<div class="box-body">
					<div class="row">
						<div class="col-md-3 form-group">
							<state v-model="state_code" :country="effectiveCompany.region.country"></state>
						</div>
						<div class="col-md-3 form-group">
							<category v-model="category"></category>
						</div>
						<div class="col-md-3 form-group">
							<collection v-model="collection" :category="category"></collection>
						</div>
						<div class="col-md-3 form-group">
							<colour v-model="colour" :collection="collection"></colour>
						</div>

						<div class="col-md-2 form-group">
							<quantity v-model="quantity"></quantity>
						</div>

						<div class="col-md-1">
							<button class="btn btn-default btn-block" @click="search"><i class="fa fa-search"></i></button>
						</div>
					</div>
					<hr>
					<vuetable 
		                :fields="tableFields"
		                :api-url="apiUrl"
		                :append-params="appendParams"
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
		</section>
	</div>
</template>
<script>
	import Vue from 'vue'
	import appMixin from '../../vuex-mixin.js'
    //import CategorySearch from '../categories/Search'
    //import CollectionSearch from '../collections/Search'
    //import ColourSearch from '../colour/Search'
    import InventoryListFields  from './StockListFields.js'
    import Vuetable from '../../vendor/vuetable2/Vuetable'
    import vuetableCssConfig from '../../configs/VuetableCssConfig'
    import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
    import Category from '../categories/Search'
    import Collection from '../collections/Search'
    import Colour from '../colour/Search'
    import Quantity from '../Quantity'
    import State from '../master/State'
    //import Quantity from '../Quantity'
    
	export default{
		mixins : [appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			Category,
			Collection,
			Colour,
			Quantity,
			State,
		},
		data(){
			return {
				css:vuetableCssConfig,
                tableFields:InventoryListFields,
                perPage:15,
                category:null,
                collection:null,
                colour:null,
                quantity:null,
                state_code:null,
			}
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
                else
                	return this.state.user.company
            },
            effectiveDisributor(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor
                }
                return null
            },
            effectiveDealer(){
                if(this.isCustomer)
                {
                    if(this.dealer)
                        return this.dealer
                }
                return null
            },
            apiUrl(){
            	return `companies/${this.effectiveCompany.id}/stock`
            },
            appendParams(){
            	let params = {filters:{}}
            	if(this.category)
            		params.filters.category = this.category.code
            	if(this.collection)
            		params.filters.collection = this.collection.code
            	if(this.colour)
            		params.filters.colour = this.colour.code
            	if(this.quantity)
            		params.filters.quantity = this.quantity
            	if(this.state_code)
            		params.filters.state = this.state_code.code
            	return params
            },
		},
		methods:{
			search(){
				this.$refs.vuetable.refresh()
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