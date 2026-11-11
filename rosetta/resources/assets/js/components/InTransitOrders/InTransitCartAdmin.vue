<template>
	<div>
		<section class="content-header">
			<h1>Advance Cart</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-2" v-if="state.user.company == null">
					<div class="form-group">
						<label>Region</label>
						<region-select v-model="region"></region-select>
					</div>
				</div>
				<div class="col-md-3" v-if="state.user.company == null">
					<div class="form-group">
						<label>Company</label>
						<company v-model="company" :region="region" :disabled="!region"></company>
					</div>
				</div>
				<div class="col-md-3">
                    <div class="form-group">
                        <label>Customer Type</label>
                        <customer-type v-model="customer_type" :disabled="!company"></customer-type>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group" :class="errors.has('customer') ? 'has-error' : ''">
                        <label>
                            <template v-if="customer_type">
                                <template v-if="customer_type.code == 'DISTRIBUTO'">Distributor</template>
                                <template v-if="customer_type.code == 'DEALER'">Dealer</template>
                                <template v-if="customer_type.code == 'SUPER_STOCKIST'">Super Stockist</template>
                            </template>
                            <template v-else>   
                                Customer
                            </template>
                        </label>
                            <customer-select v-model="selectedCustomer" :company="company" :customer_type="customer_type" :disabled="!company" :required="true" v-validate="'required'" data-vv-name="customer"></customer-select>
                    </div>
                </div>
                <div class="col-md-1">
                	<div class="form-group">
                		<label style="display: block;">&nbsp;</label>
						<button class="btn btn-default btn-block" @click="search"><i class="fa fa-search"></i></button>
					</div>
				</div>
			</div>
			<div class="row" v-if="items">
				<div class="col-md-12">
					<div class="alert alert-info">
						<p>Advance Order Quantity & Rates can be changed by the Company without any prior information. Rates are valid till the Date mentioned under <b class="text-red">Discount Validity</b> column</p>
					</div>
				</div>
			</div>
			<div class="row" v-if="items">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="clearfix"></div>
							<div class="table-responsive top-align">
								<vuetable
	                                :fields="tableFields"
	                                api-url="customers_advance_items"
	                                pagination-path="pagination"
	                                data-path="items"
	                                :per-page="perPage"
	                                ref="vuetable"
	                                :css="css.table"
	                                :append-params="appendParams"
	                                @vuetable:pagination-data="onPaginationData"
									:load-on-start="false"
									@vuetable:load-success="itemsLoaded"
									track-by="id"
									@vuetable:checkbox-toggled="checkboxToggled"
	                                @vuetable:checkbox-toggled-all="checkboxToggled"
	                                detail-row-component="invalid-row"
                            	>
                            		<template slot="customer" slot-scope="props" >
						      			<template v-if="props.rowData.customer">
						      				{{props.rowData.customer.name}} ({{props.rowData.customer.nav_id}})
						      			</template>
						      		</template>
                            		<template slot="productName" slot-scope="props">
	                                   <span>{{props.rowData.product.category.display_name}} &raquo; {{props.rowData.product.collection.display_name}}<template v-if="props.rowData.product.colour"> &raquo; {{props.rowData.product.colour.display_name}}</template></span><br>
	                                   <span class="product-category">{{props.rowData.product.category.code}} &raquo; {{props.rowData.product.collection.code}}<template v-if="props.rowData.product.colour"> &raquo; {{props.rowData.product.colour.code}}</template></span>
	                               </template>
	                                <template  slot="order_quantity" slot-scope="props" >
	                                	<div class="form-group">
		                                	<div class="input-group" v-if="abilities.updateAdvanceItems">
		                                		<input type="text" class="form-control input-sm" style="width:90px;text-align: right;" v-model="props.rowData.order_quantity" :data-vv-name="`order_quantity${props.rowData.index}`" :name="`order_quantity${props.rowData.index}`" v-validate="{ decimal: 2, min_value: 1}"></input>
		                                		<span class="input-group-addon" id="basic-addon2">{{props.rowData.product.base_unit_of_measure}}</span>
		                                	</div>
		                                	<div v-if="!(abilities.updateAdvanceItems)">
		                                		{{props.rowData.order_quantity}}<sup>{{props.rowData.product.base_unit_of_measure}}</sup>
		                                	</div>
		                                </div>
                                        <span class="text-danger" v-show="errors.has(`order_quantity${props.rowData.index}`)">Quantity can not be 0 or greater than Original Order Quantity.</span>    
                                    </template>
                                    <template slot="discount" slot-scope="props" >
                                    	<input type="text" class="form-control input-sm" v-if="abilities.updateAdvanceItems" style="width:60px;text-align: right;" v-model="props.rowData.discount"></input>
                                    	<div v-if="!(abilities.updateAdvanceItems)">
		                                	{{props.rowData.discount}}
		                                </div>
                                    </template>
                                    <template slot="discounted_price" slot-scope="props" >
	                                    {{getDiscountedPrice(props.rowData)}}<span class="tax">(+{{props.rowData.tax_type_code}} @ {{parseFloat(props.rowData.tax_percent).toFixed(2)}}%)</span>
	                                </template>
	                                <template slot="amount" slot-scope="props" >
	                                    {{getAmount(props.rowData)}}
	                                </template>
	                                <template slot="tax_amount" slot-scope="props" >
	                                    {{getTaxAmount(props.rowData)}}
	                                </template>
	                                <template slot="net_amount" slot-scope="props" >
	                                    {{getNetAmount(props.rowData)}}
	                                </template>
	                                <template slot="actions" slot-scope="props" >
	                                	<div>
		                                    <a href="#" class="action" v-if="abilities.viewAdvanceMessages" @click.prevent="chat(props.rowData)">
		                                    MESSAGES
		                                	</a>
		                                    <a href="#" class="action text-green" v-if="abilities.updateAdvanceItems" @click.prevent="setETA(props.rowData)">ETA</a>
		                                    <a href="#" class="action" v-if="abilities.updateAdvanceItems" @click.prevent="updateItem(props.rowData)">UPDATE</a>
		                                    <a href="#" class="action text-red" v-if="abilities.deleteAdvanceItems" @click.prevent="deleteItem(props.rowData,props.rowIndex)"><i class="far fa-trash-alt"></i></a>
		                                </div>
	                                </template>
                            	</vuetable>
							</div>
							<button class="btn btn-danger btn-xs" :disabled="!checked" v-if="abilities.deleteAdvanceItems" @click="deleteItems">DELETE</button>
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
			<chat-modal
                ref="chatModal"
                :item="selectedItem"
                :effective-company="company"
                :effective-distributor="effectiveDistributor"
                :effective-super-stockist="effectiveSuperStockist"
                :effective-dealer="effectiveDealer">
            </chat-modal>
            <eta-modal
                ref="etaModal"
                @eta-set="onEtaSet"
                :item="selectedItem"
                :effective-company="company"
                :effective-distributor="effectiveDistributor"
                :effective-super-stockist="effectiveSuperStockist"
                :effective-dealer="effectiveDealer">
            </eta-modal>
		</section>
	</div>
</template>
<script>
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import AppMixins from '../../vuex-mixin'
	import AdvanceCartFieldsAdmin from './InTransitCartFieldsAdmin'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VeeValidate from 'vee-validate'
	import chatModal from '../AdvanceOrders/chatModal'
	import etaModal from '../AdvanceOrders/etaModal'
	import RegionSelect from '../region/Select'
	import Company from '../companies/Select'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import CustomerType from '../master/CustomerType'
    import CustomerSelect from '../master/CustomerSelect'
    import VueCountdown from '@xkeshi/vue-countdown'
    Vue.use(VeeValidate)
	export default {
		mixins: [AppMixins],
		components:{
			Vuetable,
			chatModal,
			VuetablePagination,
			etaModal,
			RegionSelect,
			Company,
			CustomerSelect,
            CustomerType,
            VueCountdown
		},
		computed:{
            selectedItems(){
            	let self = this
            	return _.filter(self.items,function(element){
            		return _.contains(self.$refs.vuetable.selectedTo,element.id)
            	})
            },
            appendParams(){
				let params = {}
				if(this.customer_type)
				{
					params.customer_type = this.customer_type.code
				}
				if(this.selectedCustomer)
				{
					params.customer_id = this.selectedCustomer.id
				}
				return params
			},
		},
		data(){
			return {
				tableFields:AdvanceCartFieldsAdmin,
				css:vuetableCssConfig,
				items:[],
				checked:false,
				selectedItem:null,
				region:null,
				company:null,
				effectiveDistributor:null,
				effectiveDealer:null,
				selectedCustomer:null,
                customer_type:{code:'DISTRIBUTO',name:"Distributor"},
                perPage:100,
                sideBarWasOpen:false,
			}
		},
		mounted(){
			let self = this
			if(this.state.user.company)
			{
				this.company = 	Object.assign({},this.state.user.company)
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
			search(){
				this.$refs.vuetable.selectedTo = []
				this.$refs.vuetable.refresh()
			},
			itemsLoaded(response){
				let self = this
				if(self.$route.query.item)
                {
                    let item = _.findWhere(response.data.items,{id:parseInt(self.$route.query.item)})
                    if(item)
                    {
                        self.chat(item)
                    }
                }
			},
			chat(item){
                this.selectedItem = item
                if(item.customer.type == 'DISTRIBUTO')
					this.effectiveDistributor = item.customer
				if(item.customer.type == 'SUPER_STOCKIST')
					this.effectiveSuperStockist = item.customer
				if(item.customer.type == 'DEALER')
					this.effectiveDealer = item.customer
                this.$refs.chatModal.show()
            },
            setETA(item){
            	this.selectedItem = item
            	if(item.customer.type == 'DISTRIBUTO')
					this.effectiveDistributor = item.customer
				if(item.customer.type == 'SUPER_STOCKIST')
					this.effectiveSuperStockist = item.customer
				if(item.customer.type == 'DEALER')
					this.effectiveDealer = item.customer
                this.$refs.etaModal.show(item)
            },
            onEtaSet(item){
                this.selectedItem.eta = item.eta
            },
			getDiscountedPrice(item){
				return parseFloat(item.unit_price - (item.unit_price * item.discount /100)).toFixed(2)
			},
			getAmount(item){
				return parseFloat(this.getDiscountedPrice(item) * item.order_quantity).toFixed(2)
			},
			getTaxAmount(item){
				return parseFloat(((this.getDiscountedPrice(item) * item.tax_percent)/100 * item.order_quantity).toFixed(2))
			},
			getNetAmount(item){
				return (parseFloat(this.getAmount(item)) + parseFloat(this.getTaxAmount(item))).toFixed(2)
			},
			addProduct(){
                this.$refs.advanceProductModal.show()
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

		    	_.each(transformed.items,function(element){
	                element.original_quantity = (element.order_quantity).toFixed(2)
	            })
		    	return transformed
		    },
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    },
		    now(){
				return moment().format('X')
			},
			updateItem(item){
				this.selectedItem = item
				if(this.selectedItem.order_quantity == null || this.selectedItem.order_quantity == '')
				{
					swal({
                        title:"Item Quantity cannot be blank",
                        icon:"warning"
                    })
                    return
				}
                let input = {
                	order_quantity : this.selectedItem.order_quantity,
                	discount : this.selectedItem.discount,
                	discounted_unit_price : this.getDiscountedPrice(this.selectedItem),
                	total_price : this.getAmount(this.selectedItem),
                	tax_amount : this.getTaxAmount(this.selectedItem),
                	net_amount : this.getNetAmount(this.selectedItem)
                }
                let url = ""
                if(this.selectedItem.customer.type == 'DISTRIBUTO')
                    url = `companies/${this.selectedItem.company_id}/distributors/${this.selectedItem.customer.id}/advance_items/${this.selectedItem.id}`
                else if(this.selectedItem.customer.type == 'DEALER')
                    url = `companies/${this.selectedItem.company_id}/dealers/${this.selectedItem.customer.id}/advance_items/${this.selectedItem.id}`
                else if(this.selectedItem.customer.type == 'SUPER_STOCKIST')
                    url = `companies/${this.selectedItem.company_id}/super_stockists/${this.selectedItem.customer.id}/advance_items/${this.selectedItem.id}`
          
                Vue.axios.put(url, input)
                .then(response => {
                    swal({
                        title:"Item updated!",
                        icon:"success"
                    })
                    this.selectedItem.original_quantity = this.selectedItem.order_quantity
                    this.selectedItem.discounted_price = this.getDiscountedPrice(this.selectedItem)
                    this.selectedItem.amount = this.getAmount(this.selectedItem)
                    this.selectedItem.total_price = this.getAmount(this.selectedItem)
                    this.selectedItem.net_amount = this.getAmount(this.selectedItem)
                })
            },
            deleteItem(item,index){
				let self = this;
				self.selectedItem = item
				let url = ""
				if(self.selectedItem.customer.type == 'DISTRIBUTO')
                    url = `companies/${self.selectedItem.company_id}/distributors/${self.selectedItem.customer.id}/delete_advance_items`
                if(self.selectedItem.customer.type == 'SUPER_STOCKIST')
                    url = `companies/${self.selectedItem.company_id}/super_stockists/${self.selectedItem.customer.id}/delete_advance_items`
				if(self.selectedItem.customer.type == 'DEALER')
                    url = `companies/${self.selectedItem.company_id}/dealers/${self.selectedItem.customer.id}/delete_advance_items`
				swal({
		    		title: `Delete  ${item.product.name}`,
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
						Vue.axios.post(url,{item_Ids:[self.selectedItem.id]})
						.then(response => {
							//this.items.splice(index,1)
							self.$refs.vuetable.tableData.splice(index,1)
							EventBus.$emit('loader:hide')
						})
					}
				})
			},
			deleteItems(){
				let self = this
				let url = ""
				if(!self.selectedCustomer)//changed approach so mostly not in use, need to verify
				{
					if(self.customer_type.code == 'DISTRIBUTO')
						url = `companies/${self.company.id}/distributors/delete_advance_items`
					if(self.customer_type.code == 'DEALER')
						url = `companies/${self.company.id}/dealers/delete_advance_items`
				}
				else
				{
					if(self.customer_type.code == 'DISTRIBUTO')
						url = `companies/${self.company.id}/distributors/${self.selectedCustomer.id}/delete_advance_items`
					if(self.customer_type.code == 'SUPER_STOCKIST')
						url = `companies/${self.company.id}/super_stockists/${self.selectedCustomer.id}/delete_advance_items`
					if(self.customer_type.code == 'DEALER')
						url = `companies/${self.company.id}/dealers/${self.selectedCustomer.id}/delete_advance_items`
				}
				
			
				swal({
		    		title: `Delete  ${self.$refs.vuetable.selectedTo.length} items?`,
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
						Vue.axios.post(url,{item_Ids:self.$refs.vuetable.selectedTo})
						.then(response => {
							
							EventBus.$emit('loader:hide')
							let deletedItems= [...self.$refs.vuetable.selectedTo]
							let items =  _.reject(self.$refs.vuetable.tableData,function(element){
								return _.contains(deletedItems,element.id)
							})
							self.items = items
							//self.$refs.vuetable.setData(self.items)
							self.$refs.vuetable.reload()
							self.checked = false
						})
					}
				})
			},
			checkboxToggled(){
				this.checked = this.$refs.vuetable.selectedTo.length != 0
			},
		},
		watch:{
            'customer_type.code':function(){
                this.selectedCustomer = null
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
.address{
	padding-bottom: 5px;
}
.address h1 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #666;
    font-size: 20px;
    font-weight: bold;
}
.address h2 {
    margin-top: 0;
    font-size: 18px;
}
.address p {
    margin-bottom: 5px;
}
textarea{
	resize: none;
}
a.action{
	margin-left: 5px;
}
a.action:first-child{
	margin-left: 0;
}
.product-category {
    display: block;
    color: #888;
    font-size: 11px;
}
.product-title {
    display: block;
}
.actions a.action{
	font-size: 11px;
}
.tax {
    display: block;
    color: #888;
    font-size: 11px;
}
</style>