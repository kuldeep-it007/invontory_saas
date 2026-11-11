<template>
	<div>
		<div class="row">
			<div class="col-md-12">
				<a class="pull-right" :class="hasFilters? 'text-green':''" href="#" @click.prevent="filters=!filters"><i class="fa fa-filter"></i></a>
			</div>
		</div>
		<div class="box box-widget" v-show="filters">
			<div class="box-body">
					<div class="row">
					<div class="col-md-6">
						<div class="form-group">
							<label>Co Distributor</label>
							<co-distributor v-model="selectedCoDistributor" ref="coDistributors" :customer_type="customerType" :use_distributor="UseDistributor"></co-distributor>
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
		</div>
		<div class="box box-widget">
			<div class="box-body">
				<div class="row">
					<div class="col-md-12 table-responsive">
						<vuetable 
							:fields="tableFields"
							api-url="co_distributor_discounts"
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
				        			<a href="#" @click.prevent="updateDiscount(props.rowData,props.rowIndex)" v-if="abilities.addCoDistributorDiscounts">
				        				<i class="fa fa-pencil-alt"></i>
				        			</a> &nbsp;
				        			<a href="#" @click.prevent="deleteDiscount(props.rowData,props.rowIndex)"  v-if="abilities.deleteCoDistributorDiscounts">
				        				<i class="fa fa-times"></i>
				        			</a>
				        		</div>
				      		</template>
				      		<template slot="dealer" slot-scope="props" >
				      			<template v-if="props.rowData.customer">
				      				{{props.rowData.customer.name}} ({{props.rowData.customer.nav_id}})
				      			</template>
				      			<template v-else>
				      				<span class="label label-info text-uppercase">
				      					All Distributors
				      				</span>
				      			</template>
				      		</template>
						</vuetable>
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
		 <update-discount-modal
            ref="updateDiscountModal"
            @discount-updated="onDiscountUpdated">
        </update-discount-modal>
	</div>
</template>
<script>
	import Vuetable from '../../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../../vendor/vuetable2/VuetablePagination'
	import DiscountListColumns from './ListFields.js'
	import vuetableCssConfig from '../../../configs/VuetableCssConfig'
	import Vue from 'vue'
	import {EventBus} from '../../../configs/EventBus'
	//import DealerSelect from '../../master/DealerSelect'
	import Category from '../../categories/Search'
	import Collection from '../../collections/Search'
	import Colour from '../../colour/Search'
	import appMixin from '../../../vuex-mixin.js'
	import UpdateDiscountModal from './UpdateDiscountModal'
	import CoDistributor from '../../master/DistributorCustomerSelect'
	export default{
		mixins:[appMixin],
		props:{
			UseDistributor:{
				type:Object,
				required:true
			},
			company:{
				type:Object,
				required:true
			}
		},
		components:{
			Vuetable,
			VuetablePagination,
			//DealerSelect,
			Category,
			Collection,
			Colour,
			UpdateDiscountModal,
			CoDistributor
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
				selectedDiscount:null,
				price:null,
				customerType:{
					code:"DISTRIBUTO",
					name:"Distributor"
				},
				selectedCoDistributor:null,
			}
		},
		computed:{
			appendParams(){
				let params = {}
				params.distributor_id = this.UseDistributor.id
				if(this.category)
					params.category = this.category.code
				if(this.collection)
					params.collection = this.collection.code
				if(this.colour)
					params.colour = this.colour.code
				if(this.selectedCoDistributor)
					params.customer_id = this.selectedCoDistributor.id
				return params
			},
			hasFilters(){
				return !_.isEmpty(this.appendParams)
			},
			effectiveCompany(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor.company
                }
            },
            effectiveDisributor(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor
                }
                return null
            },
		},
		mounted(){
			let self = this
			if(this.abilities.deleteCoDistributorDiscounts || this.abilities.addCoDistributorDiscounts)
			{
				this.$refs.vuetable.showField(5)
			}
			else
			{
				this.$refs.vuetable.hideField(5)	
			}
			this.UseDistributor.company = this.company
			Vue.nextTick(function(){
				self.$refs.coDistributors.loadCustomers()	
			})
			

		},
		methods:{
			search(){
				this.$refs.vuetable.reload()
			},
			reload(){
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
                		Vue.axios.delete(`co_distributor_discounts/${discount.id}`)
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
		    		to:((data.page-1) * this.perPage) + data.discounts.length
		    	}
		    	transformed.discounts = data.discounts
		    	return transformed
		    },
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    },
		    updateDiscount(discount, index){
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
	                else if(self.UseDistributor)
	                {
	                	let url = `companies/${self.UseDistributor.company.id}/categories/${encodeURIComponent(discount.category.code)}/collections/${encodeURIComponent(discount.collection.code)}/colours/${encodeURIComponent(discount.colour.code)}/get_product_price`
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
            },
            onDiscountUpdated(discount){
                this.selectedDiscount.discount = discount.discount
            },
		},
		watch:{
			UseDistributor(){
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