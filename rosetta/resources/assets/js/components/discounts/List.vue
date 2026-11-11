<template>
	<div>
		<section class="content-header">
			<div class="row">
				<div class="col-md-12">
					<a class="pull-right" :class="hasFilters? 'text-green':''" href="#" @click.prevent="filters=!filters"><i class="fa fa-filter"></i></a>
				</div>
			</div>
		</section>
		<section class="content">
			<div v-show="filters">
				<div class="row">
					<div class="col-md-3">
						<div class="form-group">
							<label>Region</label>
							<region-select v-model="region" :allow-empty="true"></region-select>
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group">
							<label>State</label>
							<state-select v-model="selectedState" :country="region.country" v-if="region"></state-select>
							<template v-else>
								<div class="clearfix"></div>
								<div style="line-height: 34px;">SELECT REGION FIRST</div>
							</template>
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group">
							<label>City</label>
							<input class="form-control" type="text" v-model="city" :disabled="!selectedState">
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group">
							<label>Post Code</label>
							<input class="form-control" type="text" v-model="post_code" :disabled="!city">
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-3">
						<div class="form-group">
							<label>Customer Type</label>
							<customer-type v-model="customer_type" :allow-empty="true"></customer-type>
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group">
							<label>Company</label>
							<company v-model="company" :region="region" :disabled="(!customer_type) || (!region)"></company>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label>
								<template v-if="customer_type">
									<template v-if="customer_type.code == 'DISTRIBUTO'">Distributor</template>
									<template v-if="customer_type.code == 'DEALER'">Dealer</template>
								</template>
								<template v-else>
									Customer
								</template>
							</label>
							<customer-select v-model="selectedCustomer" :company="company" :customer_type="customer_type" :disabled="!company"></customer-select>
						</div>
					</div>
				</div>
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
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<vuetable 
								:fields="tableFields"
								api-url="discounts"
								pagination-path="pagination"
								data-path="discounts"
								:per-page="perPage"
								ref="vuetable"
								:css="css.table"
								@vuetable:pagination-data="onPaginationData"
								:append-params="appendParams"
								>
								<template slot="actions" slot-scope="props" >
					        		<div class="custom-actions">
					        			<a href="#" @click.prevent="updateDiscount(props.rowData,props.rowIndex)"><i class="fa fa-pencil-alt"></i></a> &nbsp;
					        			<a href="#" @click.prevent="deleteDiscount(props.rowData,props.rowIndex)"><i class="fa fa-times"></i></a>
					        		</div>
					      		</template>
					      		<template slot="customer-type" slot-scope="props" >
					        		<span v-if="props.rowData.customer_type == 'DISTRIBUTO'">Distributor</span>
					        		<span v-if="props.rowData.customer_type == 'DEALER'">DEALER</span>
									<span v-if="props.rowData.customer_type == 'SUPER_STOCKIST_DISTRIBUTOR'">Distributor under Superstockist</span>
									<span v-if="props.rowData.customer_type == 'SUPER_STOCKIST_DEALER'">Dealer under Distributor under Superstokist</span>
									<span v-if="props.rowData.customer_type == 'SUPER_STOCKIST'">Super Stokist</span>
					      		</template>
					      		<template slot="customer" slot-scope="props" >
					      			<template v-if="props.rowData.customer">
					      				{{props.rowData.customer.name}} ({{props.rowData.customer.nav_id}})
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
			<update-discount-modal
                ref="updateDiscountModal"
                @discount-updated="onDiscountUpdated">
            </update-discount-modal>
        </section>
	</div>
</template>
<script>
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import DiscountListColumns from './ListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import RegionSelect from '../region/Select'
	import StateSelect from '../master/State'
	import CustomerType from '../master/CustomerType'
	import Company from '../companies/Select'
	import CustomerSelect from '../master/CustomerSelect'
	import Category from '../categories/Search'
	import Collection from '../collections/Search'
	import Colour from '../colour/Search'
	import appMixin from '../../vuex-mixin.js'
	import UpdateDiscountModal from './UpdateDiscountModal'
	export default{
		mixins:[appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			RegionSelect,
			StateSelect,
			CustomerType,
			Company,
			CustomerSelect,
			Category,
			Collection,
			Colour,
			UpdateDiscountModal
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:DiscountListColumns,
				perPage:100,
				region:null,
				selectedState:null,
				city:null,
				post_code:null,
				customer_type:null,
				company:null,
				selectedCustomer:null,
				category:null,
				collection:null,
				colour:null,
				filters:false,
				selectedDiscount:null,
				price:null,
			}
		},
		computed:{
			appendParams(){
				let params = {}
				if(this.region)
					params.region = this.region.id
				if(this.selectedState)
					params.selectedState = this.selectedState.code
				if(this.city)
					params.city = this.city
				if(this.post_code)
					params.post_code = this.post_code
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
			reload(){
				this.$refs.vuetable.refresh()
			},
			search(){
				this.$refs.vuetable.reload()
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
                		Vue.axios.delete(`discounts/${discount.id}`)
                		.then(response => {
                			EventBus.$emit('loader:hide')
                			swal({
                    			title: "Discount deleted.",
                    			icon:'success',
                    		})
                    		.then(value =>{
                    			this.$refs.vuetable.tableData.splice(index,1)		
                    		})
                		})
                		.catch(error => {
                			EventBus.$emit('loader:hide')
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
		    		to:((data.page-1) * this.perPage) + data.discounts.length
		    	}
		    	transformed.discounts = data.discounts
		    	return transformed
		    },
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    },
		    updateDiscount(discount, index){
		    	console.log(discount)
		    	let self = this
		    	self.selectedDiscount = discount
		    	if(discount.colour)
		    	{
		    		if(discount.customer)
		    		{
		    			let url = `companies/${discount.customer.company.id}/categories/${encodeURIComponent(discount.category.code)}/collections/${encodeURIComponent(discount.collection.code)}/colours/${encodeURIComponent(discount.colour.code)}/get_product_price`
		    			EventBus.$emit('loader:show')
		    		    Vue.axios.get(url)
	                    .then(response => {
	                    	EventBus.$emit('loader:hide')
	                    	self.$refs.updateDiscountModal.show(discount, response.data)
	                    })   
	                }
	                else if(discount.region_company_id != null)
	                {
	                	let url = `companies/${discount.region_company_id}/categories/${encodeURIComponent(discount.category.code)}/collections/${encodeURIComponent(discount.collection.code)}/colours/${encodeURIComponent(discount.colour.code)}/get_product_price`
		    			EventBus.$emit('loader:show')
		    		    Vue.axios.get(url)
	                    .then(response => {
	                    	EventBus.$emit('loader:hide')
	                    	self.$refs.updateDiscountModal.show(discount, response.data)
	                    }) 
	                }
	                else
	                {
	                	self.$refs.updateDiscountModal.show(discount)
	                }
		    	}
		    	else
                	self.$refs.updateDiscountModal.show(discount)
            },//#860
            onDiscountUpdated(discount){
                this.selectedDiscount.discount = discount.discount
            },
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