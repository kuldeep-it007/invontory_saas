<template>
	<div v-if="abilities.manageBlockedItems">
		<section class="content-header">
			<h1>Blocked Items</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row" v-if="!isCustomer && this.state.user.company == null">
								<div class="col-md-3">
									<div class="form-group">
										<label>Region</label>
										<region-select v-model="region"></region-select>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<label>Company</label>
										<company v-model="company" :region="region" :disabled="!region"></company>
									</div>
								</div>
								<div class="col-md-3">
				                    <div class="form-group">
				                        <label>Customer Type</label>
				                        <customer-type ref="customerType" v-model="customer_type" :disabled="!company" :allow-all="true"></customer-type>
				                    </div>
				                </div>
				                <div class="col-md-3"  v-if="customer_type.code != 'ALL'">
				                    <div class="form-group" :class="errors.has('customer') ? 'has-error' : ''">
				                        <label>
				                            <template v-if="customer_type">
				                                 <template v-if="customer_type.code == 'DISTRIBUTO'">Distributor</template>
				                                <template v-if="customer_type.code == 'DEALER'">Dealer</template>
				                            </template>
				                            <template v-else>   
				                                Customer
				                            </template>
				                        </label>
				                            <customer-select v-model="selectedCustomer" :company="company" :customer_type="customer_type" :disabled="!company" :required="true" v-validate="'required'" data-vv-name="customer" ref="customerSelect"></customer-select>
				                    </div>
				                </div>
				            </div>
							<div class="row">
								<div class="col-md-3">
									<div class="form-group">
										<label>Category</label>
										<category v-model="category" ref="category"></category>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<label>Collection</label>
										<collection v-model="collection" :category="category" ref="collection"></collection>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<label>Colour</label>
										<colour v-model="colour" :collection="collection" ref="colour"></colour>
									</div>
								</div>
					            <div class="col-md-1">
									<div class="form-group">
										<label style="display: block;">&nbsp;</label>
										<button class="btn btn-default" :disabled="!company" @click="search">Search</button>
									</div>
								</div>
								<div class="col-md-1">
									<div class="form-group">
										<label style="display: block;">&nbsp;</label>
										<button class="btn btn-default" :disabled="!company" @click="clear">Clear</button>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12 table-responsive">
									<vuetable 
										:fields="tableFields"
										:api-url="apiUrl"
										:append-params="appendParams"
										pagination-path="pagination"
										data-path="items"
										:per-page="perPage"
										ref="vuetable"
										:css="css.table"
										@vuetable:pagination-data="onPaginationData"
										:load-on-start="false"
										>
										<template slot="actions" slot-scope="props" >
							        		<div class="custom-actions">
							        			<a href="#" @click.prevent="deleteDiscount(props.rowData,props.rowIndex)"><i class="fa fa-times"></i></a>
							        		</div>
							      		</template>
							      		<template slot="category" slot-scope="props" >
							      			<template v-if="props.rowData.product.category.code">
							      				{{props.rowData.product.category.display_name}}<br><span class="product-category">{{props.rowData.product.category.code}}</span>
							      			</template>
							      		</template>
							      		<template slot="collection" slot-scope="props" >
							      			<template v-if="props.rowData.product.collection.code">
							      				{{props.rowData.product.collection.display_name}}<br><span class="product-category">{{props.rowData.product.collection.code}}</span>
							      			</template>
							      		</template>
							      		<template slot="colour" slot-scope="props" >
							      			<template v-if="props.rowData.product.colour &&props.rowData.product.colour.code">
							      				{{props.rowData.product.colour.display_name}}<br><span class="product-category">{{props.rowData.product.colour.code}}</span>
							      			</template>
							      		</template>
							      		<template slot="customer" slot-scope="props" >
							      			<template v-if="props.rowData.customer">
							      				{{props.rowData.customer.name}} ({{props.rowData.customer.nav_id}})
							      			</template>
							      			<template v-if="!isCustomer && state.user.company == null">
							      				<span class="text-red">{{props.rowData.comment}}</span>
							      			</template>
							      		</template>
							      		<template v-if= "!isCustomer && props.rowData.customer" slot="distributor" slot-scope="props" >
							      			<template v-if="props.rowData.customer.distributor_name">
							      				{{props.rowData.customer.distributor_name}} ({{props.rowData.customer.distributor_nav_id}})
							      			</template>
							      			<template v-if="!isCustomer && state.user.company == null">
							      				<span class="text-red">{{props.rowData.comment}}</span>
							      			</template>
							      		</template>
							      		<template slot="quantity" slot-scope="props" >
                                        	{{props.rowData.order_quantity}} <sup>{{props.rowData.product.base_unit_of_measure}}</sup> <span class="label label-default pull-right">{{props.rowData.rate_type}}</span>
                                    	</template>
                                    	<template slot="expires" slot-scope="props" >
                                        	<vue-countdown :time="(props.rowData.expires - now()) * 1000" :interval="1000">
    											<template slot-scope="props">Expires {{props.totalHours }}:{{ props.minutes }}:{{ props.seconds }}</template>
  											</vue-countdown>
                                    	</template>
                                    	<template slot="actions" slot-scope="props" >
                                    		<template v-if="abilities.unBlockCompanyStock">
		                                        <div class="custom-actions">
		                                        	<a href="#" @click.prevent="unblock(props.rowData,props.rowIndex)"><i class="fa fa-lock-open"></i></a>
		                                        </div>
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
				</div>
			</div>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import RegionSelect from '../region/Select'
	import Company from '../companies/Select'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import BlockedListColumns from './ListFields.js'
	import VueCountdown from '@xkeshi/vue-countdown'
	import {EventBus} from '../../configs/EventBus'
	import Vue from 'vue'
	import CustomerType from '../master/CustomerType'
    import CustomerSelect from '../master/CustomerSelect'
    import Category from '../categories/Search'
	import Collection from '../collections/Search'
	import Colour from '../colour/Search'
	export default{
		mixins : [appMixin],
		components:{
			RegionSelect,
			Company,
			VuetablePagination,
			Vuetable,
			VueCountdown,
			CustomerSelect,
            CustomerType,
            Category,
			Collection,
			Colour,
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
                return this.company
            },
            effectiveDistributor(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor
                }
                if(this.selectedCustomer)
                {
	                if(this.selectedCustomer.type == "DISTRIBUTO")
	                {
	                	return this.selectedCustomer
	                }
	            }
                return null
            },
            effectiveDealer(){
                if(this.isCustomer)
                {
                    if(this.dealer)
                        return this.dealer
                }
                if(this.selectedCustomer)
                {
	                if(this.selectedCustomer.type == "DEALER")
	                {
	                	return this.selectedCustomer
	                }
	            }
                return null
            },
			apiUrl(){
				if(this.effectiveDistributor)
            	{
            		return `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDistributor.id}/blocked_items`
            	}
            	if(this.effectiveDealer)
            	{
            		return `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/blocked_items`
            	}	
            	if(this.company)
					return `companies/${this.company.id}/blocked_items`
			},
			appendParams(){
            	let filters = {}
            	if(this.customer_type)
            	{
            		filters.selectedCustomerTypeCode = this.customer_type.code
            	}
            	if(this.selectedCustomer)
            	{
            		filters.selectedCustomerType = this.selectedCustomer.type
            		filters.selectedCustomerId = this.selectedCustomer.id
            	}
            	if(this.category)
					filters.category = this.category.code
				if(this.collection)
					filters.collection = this.collection.code
				if(this.colour)
					filters.colour = this.colour.code
            	return filters
            }
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:BlockedListColumns,
				perPage:100,
				region:null,
				company:null,
				sideBarWasOpen:false,
				customer_type:{code:'DISTRIBUTO',name:"Distributor"},
				selectedCustomer:null,
				category:null,
				collection:null,
				colour:null,
			}
		},
		mounted(){
			if((!this.isCustomer) && this.state.user.company)
				this.company = 	Object.assign({},this.state.user.company)
			if(this.isCustomer)
			{
				this.company = Object.assign({},this.customer.company)
			}
			Vue.nextTick(function(){
                if(!$('body').hasClass('sidebar-collapse'))
                {
                    self.sideBarWasOpen = true
                    $('[data-toggle="push-menu"]').pushMenu('toggle')
                }
            })
		},
		destroyed(){
            let self = this
            if(this.sideBarWasOpen)
            {
                Vue.nextTick(function(){
                	if($('body').hasClass('sidebar-collapse'))
                	{
                    	$('[data-toggle="push-menu"]').pushMenu('toggle')
                	}
            	})
            }
        },
		methods:{
			unblock(item,index){
				let self = this
				let url = ""
				if(item.customer)
				{
					if(item.customer.type == 'SUPERSTOCKIST')
					{
						url = `companies/${this.company.id}/super_stockists/${item.customer.id}/unBlock_items`
					}
					else if(item.customer.type == 'DISTRIBUTO')
					{
						url = `companies/${this.company.id}/distributors/${item.customer.id}/unBlock_items`
					}
					else if(item.customer.type == 'DEALER')
					{
						url = `companies/${this.company.id}/dealers/${item.customer.id}/unBlock_items`
					}
				}
				else//When item is blocked on one Tenant, the blockable type is Company on all other tenants
				{
					url = `companies/${this.company.id}/unblock_acrossTenants`
				}

				console.log('URL',url);
				swal({
		    		title: 'Unblock item?',
		    		icon:'warning',
  					buttons: {
    					cancel: true,
    					confirm: true,
  					},
				})
				.then(value => {
					if(value)
					{
						EventBus.$emit('loader:show')
		                Vue.axios.post(url,{blocked_Ids:[item.id]})
		                .then(response => {
		                    EventBus.$emit('loader:hide')
		                    //let tableData = [...]
		                    self.$refs.vuetable.tableData.splice(index,1)
		                    swal({
		                        title:"Item unblocked",
		                        icon:"success"
		                    })
		                })
		                .catch(error => {
		                    EventBus.$emit('loader:hide')
		                })
					}
				})
				
			},
			now(){
				return moment().format('X')
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
		    		to:((data.page-1) * this.perPage) + data.items.length
		    	}
		    	transformed.items = data.items
		    	return transformed
		    },
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    },
		    search(){
		    	let self = this
		    	Vue.nextTick(function(){
		    		self.$refs.vuetable.reload()	
		    	})
        	},
        	clear(){
        		let self = this
        		self.$refs.customerType.remove()
        		self.$refs.category.categoryRemoved()
        		self.$refs.collection.collectionRemoved()
        		self.$refs.colour.colourRemoved()
        		self.search()
        	},
		},
		watch:{
			'selectedCustomer':function(){
                this.search()
            },
            'customer_type.code':function(){
            	let self = this
                this.selectedCustomer = null
                Vue.nextTick(() => {
            		if(self.customer_type.code != 'ALL')
            		{
                		self.$refs.customerSelect.loadCustomers()	
            		}
                })
            //    this.search()

            },
            'company.id':function(){
                this.selectedCustomer = null
            },
            'region.id':function(){
                this.company = null
            }
        }
	}
</script>
<style scoped>
.product-category {
    display: block;
    color: #888;
    font-size: 11px;
}
</style>