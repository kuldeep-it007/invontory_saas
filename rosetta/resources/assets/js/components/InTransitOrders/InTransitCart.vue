<template>
	<div>
		<section class="content-header">
			<h1>Advance Cart
				<!--<span class="pull-right">
					<router-link :to="{name:'AdvanceCartProducts'}" v-if="abilities.createAdvanceOrders">
						<button class="btn btn-sm btn-primary" v-if="abilities.createAdvanceOrders">Add To Cart</button>
					</router-link>
				</span>-->
				<!--<template v-if="!effectiveDealer">
				 <span class="pull-right"><button class="btn btn-sm btn-primary" @click="addProduct()" v-if="abilities.createAdvanceOrders" :disabled="!isCustomer && !selectedCustomer">Add To Cart</button></span>
				</template>--><!--#873-->
			</h1>
		</section>
		<section class="content">
			<div class="row" v-if="items">
				<div class="col-md-12">
					<div class="alert alert-info">
						<p>Advance Order Quantity & Rates can be changed by the Company without any prior information. Rates are valid till the Date mentioned under <b class="text-red">Discount Validity</b> column</p>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="clearfix"></div>
							<div class="table-responsive top-align">
								<vuetable 
	                                :fields="tableFields"
	                                :data="items"
	                                :api-mode="false"
	                                pagination-path=""
	                                data-path=""
	                                ref="vuetable"
	                                :css="css.table"
	                                track-by="id"
	                                @vuetable:checkbox-toggled="checkboxToggled"
	                                @vuetable:checkbox-toggled-all="checkboxToggled"
	                                detail-row-component="invalid-row"
                            	>
                            		<template slot="productName" slot-scope="props">
	                                   {{props.rowData.product.category.display_name}} &raquo; {{props.rowData.product.collection.display_name}}<template v-if="props.rowData.product.colour"> &raquo; {{props.rowData.product.colour.display_name}}</template>
	                               </template>
	                            	<template slot="quantity" slot-scope="props" >
	                                    {{props.rowData.order_quantity}}<sup>{{props.rowData.product.base_unit_of_measure}}</sup>
	                                </template>
	                                <template slot="discounted-price" slot-scope="props" >
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
	                                    <a href="#" class="action text-red" @click.prevent="deleteItem(props.rowData,props.rowIndex)"><i class="fa fa-trash-alt"></i></a>
	                                    <a href="#" class="action" @click.prevent="chat(props.rowData)"><i class="fa fa-comments" aria-hidden="true"></i></a>
	                                </div>
	                                </template>
	                                <!--<template slot="expires" slot-scope="props" >
                                    	<vue-countdown :time="(props.rowData.expires - now()) * 1000" :interval="1000">
											<template slot-scope="props">Expires {{props.totalHours }}:{{ props.minutes }}:{{ props.seconds }}</template>
										</vue-countdown>
                                	</template>
                                	<template slot-scope="props">{{props.rowData.expires|format_date_unix}}</template>-->
                            	</vuetable>
							</div>
							<button class="btn btn-danger btn-xs" :disabled="!checked" @click="deleteItems">DELETE</button>
							<template v-if="customer.paymentTypes.online">
								<template v-if="customer.paymentTypes.offline">
									<div class="checkbox" style="margin-top:5px;margin-bottom:5px;">
										<i class="far fa-credit-card animated flash infinite slow text-green"></i>
										<label>
											<input type="checkbox" v-model="onlinePayment"> <b style="font-style:italic;">Pay Now!</b>
										<template v-if="!selectedItems.length">
											Pay online and save more. Select items to see exact savings.
										</template>
										<template v-else>
											You will save <b>{{(selectedTotal() - selectedTotal(true)).toFixed(2)}}</b> by paying now.
										</template>
										</label>
									</div>
								</template>
								<template v-else>
									<template v-if="!selectedItems.length">
										<b style="font-style:italic;">Pay Now!</b> You are saving more by paying online. Select items to see exact saving.
									</template>
									<template v-else>
										<b style="font-style:italic;">Pay Now!</b> You will save <b>{{(selectedTotal() - selectedTotal(true)).toFixed(2)}}</b> by paying now.
									</template>
								</template>
							</template>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="col-md-6">
								<label>Shipping Address</label>
								<address-select :company="effectiveCompany" :distributor="effectiveDistributor" :superStockist="effectiveSuperStockist" :dealer="effectiveDealer" v-model="shippingAddress"></address-select>
								<div class="box box-default box-solid" v-if="shippingAddress" style="margin-top:5px;">
		            				<div class="box-body address">
		              					<h1>{{shippingAddress.name}}</h1>
										<h2>{{shippingAddress.contact}}</h2>
										<p>{{shippingAddress.address}}</p>
										<p>{{shippingAddress.address_2}}</p>
										<p>{{shippingAddress.city}} {{shippingAddress.state.name}} {{shippingAddress.post_code}}</p>
										<p>{{shippingAddress.country_region_code}}</p>
										<p><abbr title="Phone">P:</abbr> {{shippingAddress.phone_no}}</p>
		            				</div>
		          				</div>
							</div>
							<div class="col-md-6">
								<div class="form-group" :class="{'has-error':errors.has('remarks')}">
									<label>Special Request</label>
									<textarea class="form-control" style="height: 250px" v-model="remarks" v-validate="'max:230'" data-vv-name="remarks"></textarea>
									<span class="text-danger" v-show="errors.has('remarks')">Special Request can not exceed 230 characters.</span>
								</div>
							</div>
							<div class="clearfix"></div>
							<div class="col-md-12">
								<button class="btn btn-sm btn-success" @click="placeOrderClicked" :disabled="!selectedItems.length || remarks.length >=231">PLACE ORDER</button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<chat-modal
                ref="chatModal"
                :item="selectedItem"
                :effective-company="effectiveCompany"
                :effective-distributor="effectiveDistributor"
                :effective-super-stockist="effectiveSuperStockist"
                :effective-dealer="effectiveDealer">
            </chat-modal>
            <eta-modal
                ref="etaModal"
                @eta-set="onEtaSet"
                :item="selectedItem"
                :effective-company="effectiveCompany"
                :effective-distributor="effectiveDistributor"
                :effective-super-stockist="effectiveSuperStockist"
                :effective-dealer="effectiveDealer">
            </eta-modal>
            <advance-product-modal 
                ref="advanceProductModal"
                @item-added="onItemAdded"
                :effective-company="effectiveCompany"
                :effective-distributor="effectiveDistributor"
                :effective-super-stockist="effectiveSuperStockist"
                :effective-dealer="effectiveDealer">
            </advance-product-modal>
            <calculation 
            	ref="calculation" 
            	@calculation:place-order="calculationPlaceOrderClicked">
            </calculation>
		</section>
	</div>
</template>
<script>
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import AppMixins from '../../vuex-mixin'
	import AdvanceCartFields from './InTransitCartFields'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import AddressSelect from '../addresses/Select'
	import chatModal from '../AdvanceOrders/chatModal'
	import etaModal from '../AdvanceOrders/etaModal'
	import VueCountdown from '@xkeshi/vue-countdown'
	import advanceProductModal from '../AdvanceOrders/AdvanceProductModal'
	import Calculation from '../dealerOrdering/Calculation'
	export default {
		mixins: [AppMixins],
		components:{
			Vuetable,
			AddressSelect,
			chatModal,
			etaModal,
			advanceProductModal,
			VueCountdown,
			Calculation,
		},
		computed:{
			effectiveCompany(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor.company
                    if(this.superStockist)
                        return this.superStockist.company
                    if(this.dealer)
                        return this.dealer.company
                }
            },
            effectiveDistributor(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor
                }
                return null
            },
            effectiveSuperStockist(){
                if(this.isCustomer)
                {
                    if(this.superStockist)
                        return this.superStockist
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
            	if(this.effectiveDistributor)
            	{
            		return `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDistributor.id}/advance_items`
            	}
            	if(this.effectiveSuperStockist)
            	{
            		return `companies/${this.effectiveCompany.id}/super_stockists/${this.effectiveSuperStockist.id}/advance_items`
            	}
            	if(this.effectiveDealer)
            	{
            		return `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/advance_items`
            	}
            },
            selectedItems(){
            	let self = this
            	return _.filter(self.items,function(element){
            		return _.contains(self.$refs.vuetable.selectedTo,element.id)
            	})
            }
		},
		data(){
			return {
				tableFields:AdvanceCartFields,
				css:vuetableCssConfig,
				items:[],
				checked:false,
				shippingAddress:null,
				remarks:"",
				selectedItem:null,
				onlinePayment:false,
			}
		},
		mounted(){
			EventBus.$emit('loader:show')
			Vue.axios.get(this.apiUrl)
			.then(response => {
				let self = this
				EventBus.$emit('loader:hide')
				_.each(response.data,function(element){
					let item = Object.assign({},element)
					item.selected = false
					self.items.push(item)
				})
                if(self.$route.query.item)
                {
                    let item = _.findWhere(self.items,{id:parseInt(self.$route.query.item)})
                    if(item)
                    {
                        self.chat(item)
                    }
                }
			})
			.catch(error => {})
			if(this.customer.paymentTypes.online && !this.customer.paymentTypes.offline)
			{
				this.onlinePayment = true
			}
		},
		methods:{
			selectedTotal(online){
            	let self = this
            	let sum = _.reduce(this.selectedItems, function(memo, element){ return memo + parseFloat(self.getNetAmount(element,online)) }, 0);
            	return sum.toFixed(2)
            },
			placeOrder(online,amount){
				let self = this
				let order = {
					shipping_method: 'AS AGREED',
					special_request: this.remarks,
					advance_lines: [],
					shipping_address: Object.assign({},this.shippingAddress),
					order_from: 'cart',
					online: this.onlinePayment
				}
				order.shipping_address.state = this.shippingAddress.state.code
				_.each(this.selectedItems,function (element){
					let item = {
						item_id : element.id,
						product_id: element.product.id,
						rate_type: element.rate_type,
						quantity: element.order_quantity,
						unit_price: element.unit_price,
						discount: element.discount,
						onlineDiscount: element.onlineDiscount,
						stock_point_id: element.stock_point_id,
						eta: element.eta ? moment.unix(element.eta).format('X') : null,
						tax_amount : self.getTaxAmount(element),
                		net_amount : self.getNetAmount(element),
                		amount : self.getAmount(element),
                		tax_type_code : element.tax_type_code,
                		tax_percent : element.tax_percent,
                		stock_point_id: element.stock_point_id,
                		batch_no: element.nav_intransit_po,
					}
					order.advance_lines.push(item)
				})
				if(online)
				{
					order.online = true
					order.onlineAmount = amount
				}
				else
				{
					order.online = false
				}
				EventBus.$emit('loader:show')
				let url = ""
                if(this.effectiveDistributor)
                {
                    url = `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDistributor.id}/intransit_orders`
                }
                if(this.effectiveSuperStockist)
            	{
            		url = `companies/${this.effectiveCompany.id}/super_stockists/${this.effectiveSuperStockist.id}/intransit_orders`
            	}
                if(this.effectiveDealer)
                {
                    url = `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/intransit_orders`
                }
				Vue.axios.post(url,order)
				.then(response => {
					EventBus.$emit('loader:hide')
					if(this.onlinePayment && response.status == 202)
					{
						return window.location.href = response.data.url
					}
					EventBus.$emit('loader:hide')
					let deletedItems= [...self.$refs.vuetable.selectedTo]
					let invalidItems = _.pluck(response.data.invalid,'item_id')
					let itemsToDelete = _.difference(deletedItems,invalidItems)
					let items =  _.reject(self.items,function(element){
						return _.contains(itemsToDelete,element.id)
					})
					self.items = items
					self.$refs.vuetable.setData(self.items)
					self.checked = false
					_.each(invalidItems,function(element){
						self.$refs.vuetable.showDetailRow(element)
					})
					if(!response.data.ao)
					{
						swal({
		    				title: "Unable to place order. Please review cart.",
		    				icon:'error',
						})
						return
					}
					else
					{
						if(response.data.invalid.length)
						{
							swal({
		    					title: `We were unable to place order for ${response.data.invalid.length} items. Order for remaining items has been placed.`,
		    					icon:'warning',
		    					buttons: ['Review Cart','View AO']
							})
							.then(value => {
								if(value)
								{
									self.$router.push({name:'InTransitorder.view',params:{id:response.data.ao.id}})
								}
							})
							return
						}
						else
						{
							self.$router.push({name:'InTransitorder.view',params:{id:response.data.ao.id}})
						}
					}
				})
				.catch(error => {
	                if(error.response.status == 412)
	                {
	                    swal({
	                            title: "Error",
	                            text:error.response.data.error_text,
	                            icon:'error'
	                        })
	                    .then(value => {
	                        self.$router.push({name:'InTransitorder.view',params:{id:response.data.ao.id}})
	                    })
	                }
	            })
			},
			placeOrderClicked(){
				let self = this
				swal({
		    		title: `Note:  Discount selected can not be altered later. Do you want to continue placing order for ${this.selectedItems.length} items?`,
		    		icon:'warning',
  					buttons: {
    					cancel: true,
    					confirm: true,
  					},
				})
				.then(value=>{
					if(!value)
						return
					if(!self.onlinePayment)
					{
						self.placeOrder()
						return
					}
					//check payment methods
					self.checkPaymentMethods()
				})
			},
			calculationPlaceOrderClicked(calculation){
				if(calculation.paymentMode == 'offline')
				{
					this.placeOrder()
				}
				else
				{
					this.placeOrder(true,calculation.online.amount)
				}
			},
			checkPaymentMethods(){
				EventBus.$emit('loader:show')
				let url = ""
                if(this.effectiveDisributor)
                {
                    url = `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDisributor.id}/calculate_payment`
                }
                if(this.effectiveSuperStockist)
            	{
            		url = `companies/${this.effectiveCompany.id}/super_stockists/${this.effectiveSuperStockist.id}/calculate_payment`
            	}
                if(this.effectiveDealer)
                {
                    url = `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/calculate_payment`
                }
				Vue.axios.post(url,{
					online: true,
					amount: this.selectedTotal(this.onlinePayment)
				})
				.then(response => {
					EventBus.$emit('loader:hide')
					this.$refs.calculation.show(response.data)
				})
			},
			checkboxToggled(){
				this.checked = this.$refs.vuetable.selectedTo.length != 0
			},
			deleteItems(){
				let self = this
				let url = ""
				if(this.effectiveDistributor)
				{
					url = `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDistributor.id}/delete_advance_items`
				}
				if(this.effectiveSuperStockist)
            	{
            		url = `companies/${this.effectiveCompany.id}/super_stockists/${this.effectiveSuperStockist.id}/delete_advance_items`
            	}
				if(this.effectiveDealer)
				{
					url = `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/delete_advance_items`
				}
				swal({
		    		title: `Delete  ${this.$refs.vuetable.selectedTo.length} items?`,
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
					//	Vue.axios.delete(self.apiUrl,{data:{item_ids:self.$refs.vuetable.selectedTo}})
						Vue.axios.post(url,{item_Ids:self.$refs.vuetable.selectedTo})
						.then(response => {
							
							EventBus.$emit('loader:hide')
							let deletedItems= [...self.$refs.vuetable.selectedTo]
							let items =  _.reject(self.items,function(element){
								return _.contains(deletedItems,element.id)
							})
							self.items = items
							self.$refs.vuetable.setData(self.items)
							self.checked = false
						})
					}
				})
			},
			chat(item){
                this.selectedItem = item
                this.$refs.chatModal.show()
            },
            setETA(item){
            	this.selectedItem = item
                this.$refs.etaModal.show(item)
            },
            onEtaSet(item){
                this.selectedItem.eta = item.eta
            },
			deleteItem(item,index){
				let self = this;
				let url = ""
				if(self.effectiveDistributor)
				{
					url = `companies/${self.effectiveCompany.id}/distributors/${self.effectiveDistributor.id}/delete_advance_items`
				}
				if(this.effectiveSuperStockist)
            	{
            		url = `companies/${this.effectiveCompany.id}/super_stockists/${this.effectiveSuperStockist.id}/delete_advance_items`
            	}
				if(self.effectiveDealer)
				{
					url = `companies/${self.effectiveCompany.id}/dealers/${self.effectiveDealer.id}/delete_advance_items`
				}
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
						Vue.axios.post(url,{item_Ids:[item.id]})
						.then(response => {
							self.items.splice(index,1)
							EventBus.$emit('loader:hide')
						})
					}
				})
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
            onItemAdded(items){
            	let self = this
               self.items.splice(0,self.items.length)
               _.each(items,function(element){
					let item = Object.assign({},element)
					item.selected = false
					self.items.push(item)
				})
            	//this.$refs.vuetable.setData(items)
            },
            now(){
				return moment().format('X')
			},
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

    /* text-align: center; */
}
.address h2 {
    margin-top: 0;
    /* margin-bottom: 0; */
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