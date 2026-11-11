<template>
	<div>
		<div class="row">
			<div class="col-md-12">
				<a class="pull-right" :class="hasFilters? 'text-green':''" href="#" @click.prevent="filters=!filters"><i class="fa fa-filter"></i></a>
			</div>
		</div>
		<div v-show="filters">
			<div class="box box-widget">
				<div class="box-body">
					<div class="row">
						<div class="col-md-2">
							<input type="text" class="form-control" v-model="nav_id" placeholder="NAV ID">
						</div>
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
						<div class="col-md-2">
							<div class="form-group">
								<colour v-model="colour" :collection="collection"></colour>
							</div>
						</div>
						<div class="col-md-2">
			                <div class="form-group">
			                    <button class="btn btn-sm btn-primary" @click="search"><i class="fa fa-search"></i></button>
			                    <button class="btn btn-sm btn-primary" @click="clear">Clear</button>
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
							pagination-path="pagination"
							data-path="products"
							:per-page="perPage"
							ref="vuetable"
							:css="css.table"
							@vuetable:pagination-data="onPaginationData"
							:append-params="appendParams"
							>
							<template slot="actions" slot-scope="props" >
				        		<div class="custom-actions">
				        			<span class="pull-right"><button class="btn btn-xs btn-primary" @click="showAddPriceModal(props.rowData,props.rowIndex)" v-if="abilities.addProductPrices">Set MUP</button></span>
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
		<add-price :company="company" ref="addModal" @product_price:added="ProductPriceAdded"></add-price>
	</div>
</template>
<script>
	import appMixin from '../../../vuex-mixin.js'
	import Vuetable from '../../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../../vendor/vuetable2/VuetablePagination'
	import ListColumns from './ListFields.js'
	import vuetableCssConfig from '../../../configs/VuetableCssConfig'
	import Vue from 'vue'
	import {EventBus} from '../../../configs/EventBus'
	import Category from '../../categories/Search'
	import Collection from '../../collections/Search'
	import Colour from '../../colour/Search'
	import AddPrice from './Modal'
	export default{
		props:{
			company:{
				type:Object,
				required:true
			}
		},
		mixins : [appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			Category,
			Collection,
			Colour,
			AddPrice,
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:ListColumns,
				perPage:100,
				region:null,
				category:null,
				collection:null,
				colour:null,
				nav_id:null,
				filters:false,
				selectedProduct:null,
			}
		},
		computed:{
			apiUrl(){
				return `companies/${this.company.id}/product_prices`
			},
			appendParams(){
				let params = {}
				if(this.region)
					params.region = this.region.id
				if(this.nav_id)
					params.nav_id = this.nav_id
				if(this.category)
					params.category = this.category.code
				if(this.collection)
					params.collection = this.collection.code
				if(this.colour)
					params.colour = this.colour.code
				return params
			},
			hasFilters(){
				return !_.isEmpty(this.appendParams)
			}

			
		},
		methods:{
			showAddPriceModal(product,index){
				this.selectedProduct = product
				this.$refs.addModal.show(product)
			},
			ProductPriceAdded(mup){
				this.selectedProduct.price.unit = mup
			},
			search(){
				let self = this
		    	Vue.nextTick(function(){
		    		self.$refs.vuetable.reload()	
		    	})
				//this.$refs.vuetable.reload()
			},
			clear(){
        		this.category = null
        		this.collection = null
        		this.colour = null
        		this.nav_id = null
        		this.search()
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
		    		to:((data.page-1) * this.perPage) + data.products.length
		    	}
		    	transformed.products = data.products
		    	return transformed
		    },
		    onChangePage (page) {
		      	this.$refs.vuetable.changePage(page)
		    },
		    reload(){
		    	this.$refs.vuetable.reload()
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