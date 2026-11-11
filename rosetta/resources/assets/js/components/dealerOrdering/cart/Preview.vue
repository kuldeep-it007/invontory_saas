<template>
	<div class="box box-widget cart">
		<div class="box-header with-border">
				<h3 class="box-title">Shopping Cart</h3>
				<div class="box-tools pull-right">
    			<span class="label label-default">{{items.length}} items</span>
  			</div>
		</div>
		<div class="box-body">
			<template v-if="loaded">
				<ul class="products-list product-list-in-box" v-if="items.length">
					<li class="item" v-for="(item,index) in items" :key="item.id">
						<div class="actions">
							<a href="#" class="text-green" @click.prevent="block(item,index)" data-toggle="tooltip" data-original-title="Block Item">
								<i class="fa fa-lock"></i>
							</a>
							<a href="#" class="text-red" @click.prevent="remove(item,index)" data-toggle="tooltip" data-original-title="Remove from cart"><i class="fa fa-times-circle"></i></a>
						</div>
	  					<div class="product-info">
	    					<a href="javascript:void(0)" class="product-title" ><span v-html="productName(item)"></span> 
	    						<span class="pull-right label label-warning">
	    							{{item.order_quantity}}<sup>{{item.product.base_unit_of_measure}}</sup>
	    						</span>
	    					</a>
	    					<span class="product-description">
	          					{{item.batch_no}}
	          				</span>
	          				<span class="order-summary">
	          					<strong>RRP: </strong> {{parseFloat(item.unit_price).toFixed(2)}} | <strong>Discount: </strong> {{item.discount}}% | <strong>Disc. Price: </strong> {{getDiscountedPrice(item)}} | <strong>Net Amount: </strong> {{getAmount(item)}} 
	          				</span>
	  					</div>
					</li>
				</ul>
				<h2 class="text-center text-uppercase" v-if="!items.length">No Items in cart</h2>
			</template>
		</div>
		<div class="overlay" v-if="!loaded">
              <i class="fa fa-refresh fa-spin"></i>
            </div>
		<div class="box-footer clearfix" v-if="loaded && items.length">
			<h1><span class="pull-left">Total: </span> {{cartTotal}}</h1>

			<router-link :to="{name:'DealerCart'}" class="btn btn-sm btn-info btn-flat pull-right" v-if="isCustomer">CHECKOUT</router-link>
		</div>
	</div>
</template>
<script>
	import Vue from 'vue'
	import {EventBus} from '../../../configs/EventBus'
	import AppMixin from '../../../vuex-mixin'
	export default{
		mixins: [AppMixin],
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
			EventBus.$on('cart:changed',function(items){

				self.items = []
				self.items.push(... items)
			})
			let url = `companies/${this.cartCompany.id}/dealers/${this.cartDealer.id}/cart_items`
			Vue.axios.get(url)
			.then(response => {
				this.items = response.data
				this.loaded = true
			})
			.catch(error => {})
		},
		methods:{
			productName(item){
				let name = `${item.product.category.display_name} &raquo;${item.product.collection.display_name}`
				if(item.product.colour)
				{
					return `${name}&raquo;${item.product.colour.display_name}`
				}
				return name
			},
			remove(item,index){
				let self = this
				swal({
		    		title: 'Delete item from cart?',
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
						let url = `companies/${self.cartCompany.id}/dealers/${self.cartDealer.id}/cart_items`
			            Vue.axios.delete(url,{data:{item_ids:[item.id]}})
			            .then(response => {
			                EventBus.$emit('loader:hide')
			                EventBus.$emit('cart:updated',item)//#718
			                self.items.splice(index,1)
			                swal({
			                    title:"Item removed from cart",
			                    icon:"success"
			                })
			            })
			            .catch(error => {
			                EventBus.$emit('loader:hide')
			            })
					}
				})
				
			},
			block(item,index){
				let self = this
				EventBus.$emit('loader:show')
				let url = `companies/${self.cartCompany.id}/dealers/${self.cartDealer.id}/block_items`
	            Vue.axios.post(url,{block_items:[{cart_id:item.id}]})
	            .then(response => {
	                EventBus.$emit('loader:hide')
	                EventBus.$emit('blocked:changed',response.data)//#718
	                EventBus.$emit('blocked:updated',item)//#718
	                self.items.splice(index,1)
	                swal({
	                    title:"Item blocked",
	                    icon:"success"
	                })
	            })
	            .catch(error => {
	                if(error.response.status == 412)
	                {
	                    swal({
	                            title: "Cooling down!",
                                text:"You cannot block this item till " + moment.unix(error.response.data.until).format('ddd, MMM Do, YYYY hh:mm A'),
                                icon:'warning'
	                        })
	                    .then(value => {
	                        EventBus.$emit('loader:hide')
	                    })
	                }
	                else
	                {
	                	EventBus.$emit('loader:hide')
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