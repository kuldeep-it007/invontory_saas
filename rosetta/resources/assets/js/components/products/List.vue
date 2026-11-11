<template>
	<div class="row">
		<div class="col-md-12">
			<div class="box box-widget">
				<div class="box-body">
					<div class="row">
						<div class="col-md-2">
							<input type="text" class="form-control" v-model="nav_id" placeholder="NAV ID">
						</div>
						<div class="col-md-3">
							<category v-model="category"></category>
						</div>
						<div class="col-md-3">
							<collection v-model="collection" :category="category"></collection>
						</div>
						<div class="col-md-3">
							<colour v-model="colour" :collection="collection"></colour>
						</div>
						<div class="col-md-1">
							<button class="btn btn-default btn-block" @click="search"><i class="fa fa-search"></i></button>
						</div>
						<div class="clearfix"></div>
						<hr>
						
					</div>
					<vuetable 
						:fields="tableFields"
						api-url="products"
						pagination-path="pagination"
						data-path="products"
						ref="vuetable"
						:css="css.table"
						:per-page="perPage"
						:append-params="appendParams"
						@vuetable:pagination-data="onPaginationData">
						<template slot="category" slot-scope="props" >
			      			<template v-if="props.rowData.category.code">
			      				{{props.rowData.category.display_name}}
			      				<template v-if="state.user.company">
			      					<br><span class="product-category">{{props.rowData.category.code}}</span>
			      				</template>
			      			</template>
			      		</template>
			      		<template slot="collection" slot-scope="props" >
			      			<template v-if="props.rowData.collection.code">
			      				{{props.rowData.collection.display_name}}
			      				<template v-if="state.user.company">
			      					<br><span class="product-category">{{props.rowData.collection.code}}</span>
			      				</template>
			      			</template>
			      		</template>
			      		<template slot="colour" slot-scope="props" >
			      			<template v-if="props.rowData.colour">
			      				{{props.rowData.colour.display_name}}
			      				<template v-if="state.user.company">
			      					<br><span class="product-category">{{props.rowData.colour.code}}</span>
			      				</template>
			      			</template>
			      		</template>
						<template slot="status" slot-scope="props" >
			      			<template v-if="props.rowData.status == 1">
			      				<span class="label label-success" style="border-radius: 50%; cursor: pointer;" @click.prevent="toggleStatus(props.rowData.id)" title="Active">&nbsp;</span>			      				
			      			</template>
							<template v-else>
			      				<span class="label label-danger" style="border-radius: 50%; cursor: pointer;" @click.prevent="toggleStatus(props.rowData.id)" title="Inactive">&nbsp;</span>
							</template>										
			      		</template>
						<template slot="actions" slot-scope="props" >
	                        <div class="custom-actions">
	                        	<router-link :to="{name:'products.edit',params:{id:props.rowData.id}}"><i class="fa fa-pencil-alt"></i></router-link>
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
	import ProductListColumns from './ListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Vue from 'vue'
	import appMixin from '../../vuex-mixin.js'
	import Category from '../categories/Search'
	import Collection from '../collections/Search'
	import Colour from '../colour/Search'
	import {EventBus} from '../../configs/EventBus.js'
	export default{
		mixins:[appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			Category,
			Collection,
			Colour
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:ProductListColumns,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				products:[],
				category:null,
				collection:null,
				colour:null,
				nav_id:null
			}
		},
		mounted(){
			if(!this.abilities.manageProducts)
			{
				this.$refs.vuetable.hideField(6)
			}
		},
		computed:{
			appendParams(){
				let params = {}
				if(this.nav_id)
				{
					params.nav_id = this.nav_id
				}
				if(this.category)
				{
					params.category = this.category.code
				}
				if(this.collection)
				{
					params.collection = this.collection.code
				}
				if(this.colour)
				{
					params.colour = this.colour.code
				}
				return params
			}
		},
		methods:{
			search(){
				this.$refs.vuetable.refresh()
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
			toggleStatus(id){
				let self = this
				EventBus.$emit('loader:show');
				self.$http.put('products/'+id+'/toggle-status').then(response=>{
					EventBus.$emit('loader:hide');
					self.$refs.vuetable.reload()
				}).catch(error=>{
					self.handleError(error)
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
	.product-category {
	    display: block;
	    color: #888;
	    font-size: 11px;
	}
</style>