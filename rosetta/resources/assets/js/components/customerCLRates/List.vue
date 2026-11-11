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
						<div class="col-md-3">
							<div class="form-group">
								<label>Customer Type</label>
								<customer-type v-model="customer_type" :allow-empty="true"></customer-type>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>
									<template v-if="customer_type">
										<template v-if="customer_type.code == 'DISTRIBUTO'">Co Distributor</template>
										<template v-if="customer_type.code == 'DEALER'">Dealer</template>
									</template>
									<template v-else>
										Customer
									</template>
								</label>
								<customer-select v-model="selectedCustomer" :use_distributor="distributor" :customer_type="customer_type"></customer-select>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-3">
							<div class="form-group">
								<label>Category</label>
								<category v-model="category" :only-cl="true"></category>
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
							data-path="rates"
							:per-page="perPage"
							ref="vuetable"
							:css="css.table"
							@vuetable:pagination-data="onPaginationData"
							:append-params="appendParams"
							>
							<template slot="actions" slot-scope="props" >
				        		<div class="custom-actions">
				        			<a href="#" @click.prevent="deleteCLRate(props.rowData,props.rowIndex)"><i class="fa fa-times"></i></a>
				        		</div>
				      		</template>
				      		<template slot="customer-type" slot-scope="props" >
				      				<span v-if="props.rowData.customer_type == 'DISTRIBUTO'">Distributor</span>
				        			<span v-if="props.rowData.customer_type == 'DEALER'">DEALER</span>
				        		
				      		</template>
				      		<template slot="customer" slot-scope="props" >
				      			<template v-if="props.rowData.customer">
				      				{{props.rowData.customer.name}} ({{props.rowData.customer.nav_id}})
				      			</template>
				      			<template v-else>
				      				<span v-if="props.rowData.customer_type == 'DISTRIBUTO'" class="label label-info">ALL CO DISTRIBUTORS</span>
				        			<span v-if="props.rowData.customer_type == 'DEALER'" class="label label-info">ALL DEALERS</span>
				      			</template>
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
	</div>
</template>
<script>
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import DiscountListColumns from './ListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import CustomerType from '../master/CustomerType'
	import CustomerSelect from '../master/DistributorCustomerSelect'
	import Category from '../categories/Search'
	import Collection from '../collections/Search'
	import Colour from '../colour/Search'
	import appMixin from '../../vuex-mixin.js'
	export default{
		mixins:[appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			CustomerType,
			CustomerSelect,
			Category,
			Collection,
			Colour,
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:DiscountListColumns,
				perPage:100,
				customer_type:null,
				selectedCustomer:null,
				category:null,
				collection:null,
				colour:null,
				filters:false,
			}
		},
		computed:{
			apiUrl(){
				return `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/cl_rates`
			},
			appendParams(){
				let params = {}
				if(this.region)
					params.region = this.region.id
				
				if(this.customer_type)
					params.customer_type = this.customer_type.code
				if(this.category)
					params.category = this.category.code
				if(this.collection)
					params.collection = this.collection.code
				if(this.colour)
					params.colour = this.colour.code
				if(this.selectedCustomer)
					params.customer_id = this.selectedCustomer.id
				return params
			},
			hasFilters(){
				return !_.isEmpty(this.appendParams)
			}

			
		},
		methods:{
			search(){
				this.$refs.vuetable.reload()
			},
			deleteCLRate(rate,index){
				swal({
                    title: "Delete CL Rate?",
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
                		Vue.axios.delete(`companies/${this.distributor.company.id}/distributors/${this.distributor.id}/cl_rates/${rate.id}`)
                		.then(response => {
                			EventBus.$emit('loader:hide')
                			swal({
                    			title: "CL Rate deleted.",
                    			icon:'success',
                    		})
                    		.then(value =>{
                    			this.$refs.vuetable.tableData.splice(index,1)		
                    		})
                		})
                		.catch(error => {
                			EventBus.$emit('loader:show')
                		})
                	}
                })
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
		    		to:((data.page-1) * this.perPage) + data.rates.length
		    	}
		    	transformed.rates = data.rates
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