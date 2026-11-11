<template>
	<div>
		<section class="content-header">
				<h1>My blocked items</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div>
								<div class="col-lg-3 col-xs-6">
          							<div class="small-box bg-aqua">
            							<div class="inner">
              								<h3>{{items.length}}</h3>
              								<p>Blocked items</p>
            							</div>
            							<div class="icon">
              								<i class="fa fa-lock"></i>
            							</div>
          							</div>
        						</div>
        						<div class="col-lg-3 col-xs-6">
          							<div class="small-box bg-aqua">
            							<div class="inner">
              								<h3>{{cartTotal(onlinePayment)}}</h3>
              								<p>Blocked total</p>
            							</div>
            							<div class="icon">
              								<i class="fa fa-lock"></i>
            							</div>
          							</div>
        						</div>
        						<div class="col-lg-3 col-xs-6">
          							<div class="small-box bg-green">
            							<div class="inner">
              								<h3>{{selectedItems.length}}</h3>
              								<p>Selected items</p>
            							</div>
            							<div class="icon">
              								<i class="fa fa-lock"></i>
            							</div>
          							</div>
        						</div>
        						
        						<div class="col-lg-3 col-xs-6">
          							<div class="small-box bg-green">
            							<div class="inner">
              								<h3>{{selectedTotal(onlinePayment)}}</h3>
              								<p>Selected Total</p>
            							</div>
            							<div class="icon">
              								<i class="fa fa-lock"></i>
            							</div>
          							</div>
        						</div>
							</div>
							<div class="clearfix"></div>
							<template v-if="customer.paymentTypes.online">
								<div style="margin-bottom:5px;">
									<div class="alert bg-yellow" style="padding:5px;margin-bottom:5px;">
										<template v-if="customer.paymentTypes.offline">
											<div class="checkbox" style="margin-top:5px;margin-bottom:5px;">
												<i class="far fa-credit-card animated flash infinite slow"></i>
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
											<div class="alert bg-yellow" style="padding:5px;margin-bottom:5px;">
												<template v-if="!selectedItems.length">
													<i class="far fa-credit-card animated flash infinite slow"></i> <b style="font-style:italic;">Pay Now!</b> You are saving more by paying online. Select items to see exact saving.
												</template>
												<template v-else>
													<i class="far fa-credit-card animated flash infinite slow"></i> <b style="font-style:italic;">Pay Now!</b> You will save <b>{{(selectedTotal() - selectedTotal(true)).toFixed(2)}}</b> by paying now.
												</template>
											</div>
										</template>
									</div>
								</div>
							</template>
							<template v-else>
								<template v-if="items.length != 0">
									<template v-if="!selectedItems.length">
										<div class="alert bg-yellow" style="padding:5px;margin-bottom:5px;">
										Select items for which you wish to place order.</div>
									</template>
								</template>
							</template>
							<div class="table-responsive">
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
                            		<template slot="product" slot-scope="props" >
	                                    {{props.rowData.product.category.display_name}} &raquo; {{props.rowData.product.collection.display_name}}<template v-if="props.rowData.product.colour"> &raquo; {{props.rowData.product.colour.display_name}}</template>
	                                </template>
	                            	<template slot="quantity" slot-scope="props" >
	                                    {{props.rowData.order_quantity}}<sup>{{props.rowData.product.base_unit_of_measure}}</sup> <span class="label label-default pull-right">{{props.rowData.rate_type}}</span>
	                                </template>
									<template slot="discount" slot-scope="props" >
										<template v-if="onlinePayment">
											{{parseFloat(props.rowData.discount) + parseFloat(props.rowData.onlineDiscount)}}
										</template>
										<template v-else>
											{{props.rowData.discount}}
										</template>
	                                </template>
	                                <template slot="discounted-price" slot-scope="props" >
										<template v-if="onlinePayment">
											{{getDiscountedPrice(props.rowData,true)}}
										</template>
										<template v-else>
											{{getDiscountedPrice(props.rowData)}}
										</template>
										<span class="tax">(+{{props.rowData.tax.tax_type_code}} @ {{parseFloat(props.rowData.tax.tax_percent).toFixed(2)}}%)</span>
	                                </template>
	                                <template slot="amount" slot-scope="props" >
	                                    <template v-if="onlinePayment">
											{{getAmount(props.rowData,true)}}
										</template>
										<template v-else>
											{{getAmount(props.rowData)}}
										</template>
	                                </template>
	                                <template slot="tax_amount" slot-scope="props" >
	                                    <template v-if="onlinePayment">
											{{getTaxAmount(props.rowData,true)}}
										</template>
										<template v-else>
											{{getTaxAmount(props.rowData)}}
										</template>
	                                </template>
	                                <template slot="net_amount" slot-scope="props" >
	                                    <template v-if="onlinePayment">
											{{getNetAmount(props.rowData,true)}}
										</template>
										<template v-else>
											{{getNetAmount(props.rowData)}}
										</template>
	                                </template>
	                                <template slot="actions" slot-scope="props" >
	                                    <a href="#" @click.prevent="deleteItem(props.rowData,props.rowIndex)"><i class="fa fa-times"></i></a>
	                                </template>
                            	</vuetable>
							</div>
							<button class="btn btn-danger btn-xs pull-right" :disabled="!checked" @click="deleteItems">DELETE</button>
							<template v-if="customer.paymentTypes.online">
								<div style="margin-bottom:5px;">
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
											<i class="far fa-credit-card animated flash infinite slow text-green"></i> <b style="font-style:italic;">Pay Now!</b> You are saving more by paying online. Select items to see exact saving.
										</template>
										<template v-else>
											<i class="far fa-credit-card animated flash infinite slow text-green"></i> <b style="font-style:italic;">Pay Now!</b> You will save <b>{{(selectedTotal() - selectedTotal(true)).toFixed(2)}}</b> by paying now.
										</template>
									</template>
								</div>
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
								<address-select :company="effectiveCompany" :distributor="effectiveDisributor" :superStockist="effectiveSuperStockist" :dealer="effectiveDealer" v-model="shippingAddress" :allow-customer-address="true"></address-select>
								<!--#737 added allow-customer-address-->
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
								<button class="btn btn-sm btn-success" @click="placeOrderClicked" :disabled="!selectedItems.length || remarks.length >=231">PLACE ORDER</button><!--#824-->
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>
		<calculation ref="calculation" @calculation:place-order="calculationPlaceOrderClicked"></calculation>
	</div>
</template>
<script>
	import Vue from 'vue'
	import {EventBus} from '../../../configs/EventBus'
	import AppMixins from '../../../vuex-mixin'
	import BlockedFields from './BlockedFields'
	import vuetableCssConfig from '../../../configs/VuetableCssConfig'
	import Vuetable from '../../../vendor/vuetable2/Vuetable'
	import AddressSelect from '../../addresses/Select'
	import InvalidRow from './InvalidRow'
	import VeeValidate from 'vee-validate'
	import Calculation from '../Calculation'
    Vue.use(VeeValidate)
	Vue.component('invalid-row', InvalidRow)
	export default {
		mixins: [AppMixins],
		components:{
			Vuetable,
			AddressSelect,
			Calculation,
		},
		computed:{
			effectiveCompany(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor.company
                    if(this.dealer)
                        return this.dealer.company
                    if(this.superStockist)
                        return this.superStockist.company
                }
            },
            effectiveSuperStockist(){
                if(this.isCustomer)
                {
                    if(this.superStockist)
                        return this.superStockist
                }
                return null
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
            	if(this.effectiveDisributor)
            	{
            		return `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDisributor.id}/blocked_items`
            	}
            	if(this.effectiveSuperStockist)
            	{
            		return `companies/${this.effectiveCompany.id}/super_stockists/${this.effectiveSuperStockist.id}/blocked_items`
            	}
            	if(this.effectiveDealer)
            	{
            		return `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/blocked_items`
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
				tableFields:BlockedFields,
				css:vuetableCssConfig,
				items:[],
				checked:false,
				shippingAddress:null,
				remarks:"",
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
			})
			.catch(error => {})
			if(this.customer.paymentTypes.online && !this.customer.paymentTypes.offline)
			{
				this.onlinePayment = true
			}
		},
		methods:{
			cartTotal(online){
            	let self = this
            	let sum = _.reduce(this.items, function(memo, element){ return memo + parseFloat(self.getNetAmount(element,online)) }, 0);
            	return sum.toFixed(2)
			},
			
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
					purchase_lines: [],
					shipping_address: Object.assign({},this.shippingAddress),
					order_from: 'blocked',
					online: this.onlinePayment
				}
				order.shipping_address.state = this.shippingAddress.state.code
				_.each(this.selectedItems,function (element){
					let item = {
						item_id : element.id,
						product_id: element.product.id,
						rate_type: element.rate_type,
						quantity: element.order_quantity,
						batch_no: element.batch_no,
						unit_price: element.unit_price,
						discount: element.discount,	
						onlineDiscount: element.onlineDiscount,
						stock_point_id: element.stock_point_id,
						distributor_id: element.distributor_id,
						super_stockist_id: element.super_stockist_id,
						amount: self.getAmount(element)
					}
					order.purchase_lines.push(item)
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
                if(this.effectiveDisributor)
                {
                    url = `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDisributor.id}/purchase_orders`
                }
                if(this.effectiveSuperStockist)
                {
                    url = `companies/${this.effectiveCompany.id}/super_stockists/${this.effectiveSuperStockist.id}/purchase_orders`
                }
                if(this.effectiveDealer)
                {
                    url = `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/purchase_orders`
                }
				Vue.axios.post(url,order)
				.then(response => {
					EventBus.$emit('loader:hide')
					if(this.onlinePayment && response.status == 202)
					{
						return window.location.href = response.data.url
					}
					let deletedItems= [...self.$refs.vuetable.selectedTo]
					let invalidItems = _.pluck(response.data.invalid,'item_id')
				//	console.log(deletedItems,invalidItems,)
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
					if(!response.data.po)
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
		    					buttons: ['Review Cart','View PO']
							})
							.then(value => {
								if(value)
								{
									self.$router.push({name:'orders.view',params:{id:response.data.po.id}})
								}
							})
							return
						}
						else
						{
							self.$router.push({name:'orders.view',params:{id:response.data.po.id}})
						}
					}
				})
				.catch(error => {
                    if(error.response.status == 412)
                    {
                        swal({
		    				title: error.response.data.error_text,
		    				icon:'error',
						})
						return
                    }
                    if(error.response.status == 500)
                    {
                       swal({
                       		title:'Oops...',
                       		text:"Something went wrong while processing your request. Please contact your Relationship Manager.",
		    				icon:'error',
						})
						return
                    }
                })
			},
			placeOrderClicked(){
				let self = this
				swal({
		    		title: `Place order for ${this.selectedItems.length} items?`,
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
						return;
					}
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
				if(this.effectiveDisributor)
				{
					url = `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDisributor.id}/unBlock_items`
				}
				if(this.effectiveSuperStockist)
				{
					url = `companies/${this.effectiveCompany.id}/super_stockists/${this.effectiveSuperStockist.id}/unBlock_items`
				}
				if(this.effectiveDealer)
				{
					url = `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/unBlock_items`
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
						Vue.axios.post(url,{blocked_Ids:self.$refs.vuetable.selectedTo})
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
			deleteItem(item,index){
				let self = this;
				let url = ""
				if(this.effectiveDisributor)
				{
					url = `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDisributor.id}/unBlock_items`
				}
				if(this.effectiveSuperStockist)
				{
					url = `companies/${this.effectiveCompany.id}/super_stockists/${this.effectiveSuperStockist.id}/unBlock_items`
				}
				if(this.effectiveDealer)
				{
					url = `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/unBlock_items`
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
						Vue.axios.post(url,{blocked_Ids:[item.id]})
						.then(response => {
							EventBus.$emit('loader:hide')
							self.items.splice(index,1)
						})
					}
				})
			},
			getDiscountedPrice(item,online){
				let discount = item.discount
                if(online)
					discount += parseFloat(item.onlineDiscount)
				return parseFloat(item.unit_price - (item.unit_price * discount /100)).toFixed(2)
			},
			getAmount(item,online){
				return parseFloat(this.getDiscountedPrice(item,online) * item.order_quantity).toFixed(2)
			},
			getTaxAmount(item,online){
				return parseFloat(((this.getDiscountedPrice(item,online) * item.tax.tax_percent)/100 * item.order_quantity).toFixed(2))
			},
			getNetAmount(item,online){
				return (parseFloat(this.getAmount(item,online)) + parseFloat(this.getTaxAmount(item,online))).toFixed(2)
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
.tax {
    display: block;
    color: #888;
    font-size: 11px;
}
textarea{
	resize: none;
}
</style>