<template>
	<div class="box box-widget cart">
		<div class="box-header with-border">
				<h3 class="box-title">Advance Cart <span class="badge bg-green">{{items.length}}</span></h3>
		</div>
		<div class="box-body">
			<template v-if="loaded">
				<ul class="products-list product-list-in-box" v-if="items.length">
					<li class="item" v-for="(item,index) in items" :key="item.id">
						<div class="actions">
							<a href="#" class="text-green" v-if="abilities.deny_block == false" @click.prevent="block(item,index)" data-toggle="tooltip" data-original-title="Block Item">
								<i class="fa fa-lock"></i>
							</a>
							<a href="#" class="text-red" @click.prevent="remove(item,index)" data-toggle="tooltip" data-original-title="Remove from advance cart"><i class="fa fa-times-circle"></i></a>
						</div>
	  					<div class="product-info">
	    					<a href="javascript:void(0)" class="product-title" ><span v-html="productName(item)"></span> 
	    						<span class="pull-right label label-warning">
	    							{{item.order_quantity}}<sup>{{item.product.base_unit_of_measure}}</sup>
	    						</span>
	    					</a>
	    					<span class="product-description">
	          					{{item.nav_intransit_po}}
	          				</span>
	          				<span class="order-summary">
	          					<strong>RRP: </strong> {{parseFloat(item.unit_price).toFixed(2)}} | <strong>Discount: </strong> <template v-if="onlinePayment">
											{{parseFloat(item.discount) + parseFloat(item.onlineDiscount)}}
										</template>
										<template v-else>
											{{item.discount}}
										</template>% | <strong>Disc. Price: </strong> {{item.discounted_price}}<span class="tax">(+{{item.tax_type_code}} @ {{parseFloat(item.tax_percent).toFixed(2)}}%)</span>| <strong>Net Amount: </strong> {{item.total_price}} | <strong>Tax Amount: </strong> {{item.tax_amount}} | <strong>Total Amount: </strong> {{item.net_amount}} 
	          				</span>
	  					</div>
					</li>
				</ul>
				<h2 class="text-center text-uppercase" v-if="!items.length">No Items in advance cart</h2>
			</template>
		</div>
		<div class="overlay" v-if="!loaded">
              <i class="fa fa-refresh fa-spin"></i>
            </div>
		<div class="box-footer clearfix" v-if="loaded && items.length">
			<h1><span class="pull-left">Total: </span> {{advanceCartTotal}}</h1>

			<router-link :to="{name:'InTransitCart'}" class="btn btn-sm btn-info btn-flat pull-right" v-if="isCustomer">CHECKOUT</router-link>
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
			advanceCartTotal(){
				let sum = 0
				let self = this
				_.each(this.items,function(element){
					sum += element.net_amount
				})
				return sum
			}
		},
		mounted(){
			let self = this
			if(this.customer.paymentTypes.online && !this.customer.paymentTypes.offline)
			{
				this.onlinePayment = true
			}
			EventBus.$on('advance_cart:changed',function(items){

				self.items = []
				self.items.push(... items)
			})
			let url = ""
            if(this.cartSuperStockist)
            {
                url = `companies/${this.cartCompany.id}/super_stockists/${this.cartSuperStockist.id}/advance_items`
            }
            if(this.cartDistributor)
            {
                url = `companies/${this.cartCompany.id}/distributors/${this.cartDistributor.id}/advance_items`
            }
            if(this.cartDealer)
            {
                url = `companies/${this.cartCompany.id}/dealers/${this.cartDealer.id}/advance_items`
            }
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
		    		title: 'Delete item from advance cart?',
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
		                    url = `companies/${self.cartCompany.id}/super_stockists/${self.cartSuperStockist.id}/delete_advance_items`
		                }
		                if(self.cartDistributor)
		                {
		                    url = `companies/${self.cartCompany.id}/distributors/${self.cartDistributor.id}/delete_advance_items`
		                }
		                if(self.cartDealer)
		                {
		                    url = `companies/${self.cartCompany.id}/dealers/${self.cartDealer.id}/delete_advance_items`
		                }
			            Vue.axios.post(url,{item_Ids:[item.id]})
			            .then(response => {
			                EventBus.$emit('loader:hide')
			                EventBus.$emit('advance_cart:updated',item)//#717
			                self.items.splice(index,1)
			                swal({
			                    title:"Item removed from advance cart",
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
				let url = ""
                if(self.cartSuperStockist)
                {
                    url = `companies/${self.cartCompany.id}/super_stockists/${self.cartSuperStockist.id}/block_advance_items`
                }
                if(self.cartDistributor)
                {
                    url = `companies/${self.cartCompany.id}/distributors/${self.cartDistributor.id}/block_advance_items`
                }
                if(self.cartDealer)
                {
                    url = `companies/${self.cartCompany.id}/dealers/${self.cartDealer.id}/block_advance_items`
                }
	            Vue.axios.post(url,{block_items:[{cart_id:item.id}]})
	            .then(response => {
	                EventBus.$emit('loader:hide')
	                EventBus.$emit('advance_blocked:changed',response.data)//#717
	                EventBus.$emit('advance_blocked:updated',item)//#717
	                self.items.splice(index,1)
	                swal({
	                    title:"Advance Item blocked",
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