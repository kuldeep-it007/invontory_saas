<template>
	<div class="box box-widget cart">
		<div class="box-header with-border">
				<h3 class="box-title">Blocked Items</h3>
				<div class="box-tools pull-right">
    			<span class="label label-default">{{items.length}} items</span>
  			</div>
		</div>
		<div class="box-body">
			<template v-if="loaded">
				<ul class="products-list product-list-in-box" v-if="items.length">
					<li class="item" v-for="(item,index) in items" :key="item.id">
						<div class="actions">
							<a href="#" class="text-green" @click.prevent="moveToCart(item,index)" data-toggle="tooltip" data-original-title="Move to Cart">
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
	          					{{item.batch_no}}
	          					<span class="pull-right">
	          						<vue-countdown :time="(item.expires - now()) * 1000" :interval="1000" @countdownend="expired(index)">
    									<template slot-scope="props">Expires {{props.totalHours }}:{{ props.minutes }}:{{ props.seconds }}</template>
  									</vue-countdown>
	          					</span>
	          				</span>
	          				<span class="order-summary">
	          					<strong>RRP: </strong> {{parseFloat(item.unit_price).toFixed(2)}} | <strong>Discount: </strong> {{item.discount}}% | <strong>Disc. Price: </strong> {{getDiscountedPrice(item)}} | <strong>Net Amount: </strong> {{getAmount(item)}} 
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

			<router-link :to="{name:'dealer-blocked-items'}" class="btn btn-sm btn-info btn-flat pull-right" v-if="isCustomer">CHECKOUT</router-link>
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
				loaded:false
			}
		},
		computed:{
			cartTotal(){
				let sum = 0
				let self = this
				_.each(this.items,function(element){
					sum += parseFloat(self.getAmount(element))
				})
				return sum.toFixed(2)
			}
		},
		mounted(){
			let self = this
			EventBus.$on('blocked:changed',function(items){
				self.items = []
				self.items.push(... items)
			})
			let url = `companies/${this.cartCompany.id}/dealers/${this.cartDealer.id}/blocked_items`
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
						let url = `companies/${this.cartCompany.id}/dealers/${self.cartDealer.id}/unBlock_items`
		                Vue.axios.post(url,{blocked_Ids:[item.id]})
		                .then(response => {
		                    EventBus.$emit('loader:hide')
		                    EventBus.$emit('blocked:updated',item)//#718
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
			moveToCart(item,index){
				let input = {
                    blocked_id:item.id
                }
                EventBus.$emit('loader:show')
                let url = `companies/${this.cartCompany.id}/dealers/${this.cartDealer.id}/cart_items`
                Vue.axios.post(url,{cart_items:[input]})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    EventBus.$emit('cart:changed',response.data)//#718
                    EventBus.$emit('cart:updated',item)//#718
                    this.items.splice(index,1)
                    swal({
                        title:"Moved to cart",
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

</style>