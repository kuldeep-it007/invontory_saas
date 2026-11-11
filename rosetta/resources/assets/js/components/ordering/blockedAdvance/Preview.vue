<template>
	<div class="box box-widget cart">
		<div class="box-header with-border">
			<h3 class="box-title">Blocked Advance Items <span class="badge bg-green">{{items.length}}</span></h3>
			<!--<div class="box-tools pull-right">
				<div class="checkbox">
					<label>
						<input type="checkbox" v-model="onlinePayment"> Pay Now!
					</label>
				</div>
			</div>-->
		</div>
		<div class="box-body">
			<template v-if="loaded">
				<ul class="products-list product-list-in-box" v-if="items.length">
					<li class="item" v-for="(item,index) in items" :key="item.id">
						<div class="actions">
							<a href="#" class="text-green" v-if="abilities.deny_add_to_cart == false" @click.prevent="moveToAdvanceCart(item,index)" data-toggle="tooltip" data-original-title="Move to Cart">
								<i class="fa fa-shopping-cart"></i>
							</a>
							<a href="#" class="text-red" @click.prevent="unblock(item,index)" data-toggle="tooltip" data-original-title="Un-block Item"><i class="fa fa-lock-open"></i></a>
						</div>
	  					<div class="product-info">
	    					<a href="javascript:void(0)" class="product-title"><span v-html="productName(item)"></span>
	    						<span class="pull-right label label-warning">
	    							{{item.order_quantity}}<sup>{{item.product.base_unit_of_measure}}</sup>
	    						</span>
	    					</a>
	    					<span class="product-description">
	          					{{item.nav_intransit_po}}
	          					<span class="pull-right">
	          						<vue-countdown :time="(item.expires - now()) * 1000" :interval="1000" @countdownend="expired(index)">
    									<template slot-scope="props">Expires {{props.totalHours }}:{{ props.minutes }}:{{ props.seconds }}</template>
  									</vue-countdown>
	          					</span>
	          				</span>
	          				<span class="order-summary">
	          					<strong>RRP: </strong> {{parseFloat(item.unit_price).toFixed(2)}} | <strong>Discount: </strong> <template v-if="onlinePayment">
											{{parseFloat(item.discount) + parseFloat(item.onlineDiscount)}}
										</template>
										<template v-else>
											{{item.discount}}
										</template>% | <strong>Disc. Price: </strong> {{getDiscountedPrice(item,onlinePayment)}}<span class="tax">(+{{item.tax.tax_type_code}} @ {{parseFloat(item.tax.tax_percent).toFixed(2)}}%)</span> | <strong>Net Amount: </strong> {{getAmount(item,onlinePayment)}} | <strong>Tax Amount: </strong> {{getTaxAmount(item,onlinePayment)}} | <strong>Total Amount: </strong> {{getNetAmount(item,onlinePayment)}} 
	          				</span>
	  					</div>
					</li>
				</ul>
				<h2 class="text-center text-uppercase" v-if="!items.length">No Items Blocked</h2>
			</template>
		</div>
		<div class="overlay" v-if="!loaded">
        	<i class="fa fa-refresh fa-spin"></i>
        </div>
        <div class="box-footer clearfix" v-if="loaded && items.length">
			<h1><span class="pull-left">Total: </span> {{cartTotal}}</h1>

			<router-link :to="{name:'BlockedAdvance'}" class="btn btn-sm btn-info btn-flat pull-right" v-if="isCustomer">CHECKOUT</router-link>
		</div>
	</div>
</template>
<script>
	import Vue from 'vue'
	import {EventBus} from '../../../configs/EventBus'
	import AppMixin from '../../../vuex-mixin'
	import VueCountdown from '@xkeshi/vue-countdown'
	export default{
		mixins: [AppMixin],
		components:{
			VueCountdown
		},
		props:{
			cartSuperStockist:{
				type:Object,
				required:false
			},
			cartDistributor:{
				type:Object,
				required:false
			},
			cartDealer:{
				type:Object,
				required:false
			},
			cartCompany:{
				type:Object,
				required:true
			}
		},
		data(){
			return {
				items:[],
				loaded:false,
				onlinePayment:false,
			}
		},
		computed:{
			cartTotal(){
				let sum = 0
				let self = this
				_.each(this.items,function(element){
					sum += parseFloat(self.getNetAmount(element,self.onlinePayment))
				})
				return sum.toFixed(2)
			}
		},
		mounted(){
			let self = this
			if(this.customer.paymentTypes.online && !this.customer.paymentTypes.offline)
			{
				this.onlinePayment = true
			}
			EventBus.$on('advance_blocked:changed',function(items){
				self.items = []
				self.items.push(... items)
			})
			let url = ""
			if(this.cartSuperStockist)
            {
                url = `companies/${this.cartCompany.id}/super_stockists/${this.cartSuperStockist.id}/blocked_advance_items`
            }
            if(this.cartDistributor)
            {
                url = `companies/${this.cartCompany.id}/distributors/${this.cartDistributor.id}/blocked_advance_items`
            }
            if(this.cartDealer)
            {
                url = `companies/${this.cartCompany.id}/dealers/${this.cartDealer.id}/blocked_advance_items`
            }
			Vue.axios.get(url)
			.then(response => {
				this.items = response.data
				this.loaded = true
			})
			.catch(error => {})
		},
		methods:{
			now(){
				return moment().format('X')
			},
			unblock(item,index){
				let self = this
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
						let url = ""
			            if(self.cartSuperStockist)
			            {
			                url = `companies/${this.cartCompany.id}/super_stockists/${self.cartSuperStockist.id}/unBlock_advance_items`
			            }
			            if(self.cartDistributor)
			            {
			                url = `companies/${this.cartCompany.id}/distributors/${self.cartDistributor.id}/unBlock_advance_items`
			            }
			            if(self.cartDealer)
			            {
			                url = `companies/${this.cartCompany.id}/dealers/${self.cartDealer.id}/unBlock_advance_items`
			            }
		                Vue.axios.post(url,{blocked_Ids:[item.id]})
		                .then(response => {
		                    EventBus.$emit('loader:hide')
		                    EventBus.$emit('advance_blocked:updated',item)//#717
		                    self.items.splice(index,1)
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
			moveToAdvanceCart(item,index){
				let input = {
                    blocked_id:item.id
                }
                EventBus.$emit('loader:show')
                let url = ""
	            if(this.cartSuperStockist)
	            {
	                url = `companies/${this.cartCompany.id}/super_stockists/${this.cartSuperStockist.id}/advance_cart_items`
	            }
	            if(this.cartDistributor)
	            {
	                url = `companies/${this.cartCompany.id}/distributors/${this.cartDistributor.id}/advance_cart_items`
	            }
	            if(this.cartDealer)
	            {
	                url = `companies/${this.cartCompany.id}/dealers/${this.cartDealer.id}/advance_cart_items`
	            }
                Vue.axios.post(url,{advance_items:[input]})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    EventBus.$emit('advance_cart:changed',response.data)//#717
                    EventBus.$emit('advance_cart:updated',item)//#717
                    this.items.splice(index,1)
                    swal({
                        title:"Moved to advance cart",
                        icon:"success"
                    })
                })
                .catch(error => {
                    EventBus.$emit('loader:hide')
                })
			},
			productName(item){
				let name = `${item.product.category.display_name} &raquo;${item.product.collection.display_name}`
				if(item.product.colour)
				{
					return `${name}&raquo;${item.product.colour.display_name}`
				}
				return name
			},
			expired(index){
				this.items.splice(index,1)
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
    .product-info{
	   margin-left: 0;
    }
    .cart .box-body{
	   max-height: 250px;
	   min-height: 100px;
	   overflow-y: auto;
    }
    .blocked .box-body{
	   max-height: 250px;
	   overflow-y: auto;
    }
    .box-footer h1{
    	display: inline-block;
        margin: 0;
    	font-size: 20px;
    	line-height: 30px;
    	color:#444;
    }
    .box-footer h1 span{
    	color:#777;
    }
    .actions{
    	text-align: right;
    }
    .tax {
    display: block;
    color: #888;
    font-size: 11px;
   	}
</style>