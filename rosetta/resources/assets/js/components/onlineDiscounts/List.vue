<template>
	<div>
        <div class="row">
			<div class="col-md-12">
				<a class="pull-right"  href="#" @click.prevent="reload"><i class="fas fa-sync"></i></a>
				<a class="pull-right" :class="hasFilters? 'text-green':''" href="#" @click.prevent="filters=!filters"><i class="fa fa-filter"></i></a>
			</div>
		</div>
		<div v-show="filters">
            <div class="box box-widget">
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Category</label>
                                <category v-model="category"></category>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Collection</label>
                                <collection v-model="collection" :category="category"></collection>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Colour</label>
                                <colour v-model="colour" :collection="collection"></colour>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label style="display: block;">&nbsp;</label>
                                <button class="btn btn-info btn-block" @click="search"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label style="display: block;">&nbsp;</label>
                                <button class="btn btn-info btn-block" @click="clear">Clear</button>
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
							data-path="discounts"
							:per-page="perPage"
							ref="vuetable"
							:css="css.table"
							@vuetable:pagination-data="onPaginationData"
							:append-params="appendParams"
                            @vuetable:loading="loading=true"
                            @vuetable:loaded="loading=false"
							>
							<template slot="actions" slot-scope="props" >
				        		<div class="custom-actions">
				        			<a href="#" @click.prevent="editDiscount(props.rowData,props.rowIndex)" v-if="abilities.addOnlineDiscount">
										<i class="fa fa-pencil-alt"></i>
									</a>
				        			<a href="#" @click.prevent="deleteDiscount(props.rowData,props.rowIndex)" v-if="abilities.deleteOnlineDiscount">
										<i class="fa fa-times"></i>
									</a>
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
                    <div class="overlay" v-if="loading">
                        <i class="fa fa-sync-alt fa-spin"></i>
                    </div>
				</div>
			</div>
		</div>
	</div>
</template>
<script>
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import DiscountListColumns from './ListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Vue from 'vue'
	import Category from '../categories/Search'
	import Collection from '../collections/Search'
	import Colour from '../colour/Search'
	import appMixin from '../../vuex-mixin'
	import {EventBus} from '../../configs/EventBus'
	export default{
		mixins:[appMixin],
        props:{
            company:{
                type: Object,
                required:true,
            }
        },
		components:{
			Vuetable,
			VuetablePagination,
			Category,
			Collection,
			Colour,
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:DiscountListColumns,
				perPage:100,
				category:null,
				collection:null,
				colour:null,
                filters:false,
                loading:false,
			}
		},
		computed:{
            apiUrl(){
                return `companies/${this.company.id}/online_discounts`
            },
			appendParams(){
				let params = {}
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
			reload(){
				this.$refs.vuetable.reload()
			},
			updateDiscount(discount,index)
			{
				this.$refs.vuetable.tableData.splice(index,1,discount)
			},
			editDiscount(discount,index){
				this.$emit('online-discount:edit',{discount:discount,index:index})
			},
			discountEdited(discount,index){
				this.$refs.vuetable.tableData.splice(index,1,discount)
			},
			deleteDiscount(discount,index){
				swal({
                    title: "Delete Discount?",
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    }
                })
                .then(value=> {
                	if(value)
                	{
                		EventBus.$emit('loader:show')
                		Vue.axios.delete(`companies/${this.company.id}/online_discounts/${discount.id}`)
                		.then(response => {
                			swal({
                    			title: "Discount deleted.",
                    			icon:'success',
                    		})
                    		.then(value =>{
                    			this.$refs.vuetable.tableData.splice(index,1)		
                    		})
                		})
                		.catch(error => {
                			swal({
                    			title: "Unable to delete discount.",
                    			icon:'error',
                    		})
                		})
                	}
                })
			},
            clear(){
                this.colour = null
                this.collection = null
                this.category = null
                this.search()
            },
			search(){
				this.$refs.vuetable.reload()
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
		    		to:((data.page-1) * this.perPage) + data.discounts.length
		    	}
		    	transformed.discounts = data.discounts
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