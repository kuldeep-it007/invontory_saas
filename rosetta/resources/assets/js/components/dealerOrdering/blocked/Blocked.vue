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
              								<h3>{{cartTotal}}</h3>
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
              								<h3>{{selectedTotal}}</h3>
              								<p>Selected Total</p>
            							</div>
            							<div class="icon">
              								<i class="fa fa-lock"></i>
            							</div>
          							</div>
        						</div>
							</div>
							<div class="clearfix"></div>
							<template>
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
	                            	<template slot="quantity" slot-scope="props" >
	                                    {{props.rowData.order_quantity}}<sup>{{props.rowData.product.base_unit_of_measure}}</sup> <span class="label label-default pull-right">{{props.rowData.rate_type}}</span>
	                                </template>
	                                <template slot="discounted-price" slot-scope="props" >
	                                    {{getDiscountedPrice(props.rowData)}}
	                                </template>
	                                <template slot="amount" slot-scope="props" >
	                                    {{getAmount(props.rowData)}}
	                                </template>
	                                <template slot="actions" slot-scope="props" >
	                                    <a href="#" @click.prevent="deleteItem(props.rowData,props.rowIndex)"><i class="fa fa-times"></i></a>
	                                </template>
                            	</vuetable>
							</div>
							<button class="btn btn-danger btn-xs" :disabled="!checked" @click="deleteItems">DELETE</button>
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
								<address-select 
									:company="this.dealer.company" 
									:dealer="this.dealer" 
									v-model="shippingAddress"
									:allow-customer-address="true"
								></address-select>
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
									<textarea class="form-control" style="height: 240px" v-model="remarks" v-validate="'max:230'" data-vv-name="remarks"></textarea>
									 <span class="text-danger" v-show="errors.has('remarks')">Special Request can not exceed 230 characters.</span>
								</div>
							</div>
							<div class="clearfix"></div>
							<div class="col-md-6">
								<label>Customer details for Warranty</label>
								<customer-select :company="this.dealer.company" :dealer="this.dealer" v-model="selectedCustomer"></customer-select>

								<div class="box box-default box-solid" v-if="selectedCustomer" style="margin-top:5px;">
		            				<div class="box-body address">
		              					<h1>{{selectedCustomer.name}}</h1>
										<p><abbr title="Phone">P:</abbr> {{selectedCustomer.phone_no}}</p>
		            				</div>
		          				</div>
							</div>
							<div class="col-md-6">
								<label>Installer details</label>
								<installer-select :company="this.dealer.company" :dealer="this.dealer" v-model="selectedInstaller"></installer-select>
								<div class="box box-default box-solid" v-if="selectedInstaller" style="margin-top:5px;">
		            				<div class="box-body address">
		              					<h1>{{selectedInstaller.name}}</h1>
										<p><abbr title="Phone">P:</abbr> {{selectedInstaller.phone_no}}</p>
		            				</div>
		          				</div>
							</div>
							<div class="clearfix"></div>
							<div class="col-md-12" style="margin-top:10px;">
								<button class="btn btn-sm btn-success" @click="placeOrderClicked" :disabled="!selectedItems.length || remarks.length >=231">PLACE ORDER</button>
								<span> Note: Please enter customer details to avail of Company Warranty Certificate</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>
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
	import CustomerSelect from '../../customers/Select'
	import InstallerSelect from '../../Engineers/Select'
	import InvalidRow from './InvalidRow'
	import VeeValidate from 'vee-validate'
    Vue.use(VeeValidate)
	Vue.component('invalid-row', InvalidRow)
	export default {
		mixins: [AppMixins],
		components:{
			Vuetable,
			AddressSelect,
			CustomerSelect,
			InstallerSelect
		},
		computed:{
            apiUrl(){
            		return `companies/${this.dealer.company.id}/dealers/${this.dealer.id}/blocked_items`
            },
            selectedItems(){
            	let self = this
            	return _.filter(self.items,function(element){
            		return _.contains(self.$refs.vuetable.selectedTo,element.id)
            	})
            },
            cartTotal(){
            	let self = this
            	let sum = _.reduce(this.items, function(memo, element){ return memo + parseFloat(self.getAmount(element)) }, 0);
            	return sum.toFixed(2)
            },
            selectedTotal(){
            	let self = this
            	let sum = _.reduce(this.selectedItems, function(memo, element){ return memo + parseFloat(self.getAmount(element)) }, 0);
            	return sum.toFixed(2)
            }
		},
		data(){
			return {
				tableFields:BlockedFields,
				css:vuetableCssConfig,
				items:[],
				checked:false,
				shippingAddress:null,
				selectedCustomer:null,
				selectedInstaller:null,
				remarks:"",
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
		},
		methods:{
			placeOrder(){
				let self = this
				let shippingAddress = null
				shippingAddress = Object.assign({},this.shippingAddress)
				shippingAddress.code = null
				let order = {
					shipping_method: 'AS AGREED',
					special_request: this.remarks,
					purchase_lines: [],
					shipping_address: shippingAddress,
					order_from: 'blocked',
				}
				if(this.selectedCustomer)
					order.end_customer_id = this.selectedCustomer.id
				if(this.selectedInstaller)
					order.installer_id = this.selectedInstaller.id
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
						stock_point_id: element.stock_point_id,	
						distributor_id: element.distributor_id,
						super_stockist_id: element.super_stockist_id,
						amount: self.getAmount(element)
					}
					order.purchase_lines.push(item)
				})
				EventBus.$emit('loader:show')
				let url = `companies/${this.dealer.company.id}/dealers/${this.dealer.id}/purchase_orders`
				Vue.axios.post(url,order)
				.then(response => {
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
									self.$router.push({name:'DealerOrders.view',params:{id:response.data.po.id}})
								}
							})
							return
						}
						else
						{
							self.$router.push({name:'DealerOrders.view',params:{id:response.data.po.id}})
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
					self.placeOrder()
				})
			},
			checkboxToggled(){
				this.checked = this.$refs.vuetable.selectedTo.length != 0
			},
			deleteItems(){
				let self = this
				let url = `companies/${this.dealer.company.id}/dealers/${this.dealer.id}/unBlock_items`
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
				let url = `companies/${this.dealer.company.id}/dealers/${this.dealer.id}/unBlock_items`
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
			getDiscountedPrice(item){
				return parseFloat(item.unit_price - (item.unit_price * item.discount /100)).toFixed(2)
			},
			getAmount(item){
				return parseFloat(this.getDiscountedPrice(item) * item.order_quantity).toFixed(2)
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
textarea{
	resize: none;
}
</style>