<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static" v-if="abilities.updateStock">
  		<div class="modal-dialog" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 class="modal-title">Adjust stock</h4>
      			</div>
      			<div class="modal-body">
      				<div class="row" v-if="validInventory">
      					<div class="col-md-12">
		      				<table class="table table-condensed table-bordered">
		      					<thead>
		      						<tr>
		      							<th>ID</th>
		      							<th>Category</th>
		      							<th>Collection</th>
		      							<th>Colour</th>
		      							<th>Batch No</th>
		      							<th>Stock</th>
		      						</tr>
		      					</thead>
		      					<tbody>
		      						<tr>
		      							<td>{{inventory.product.nav_id}}</td>
		      							<td>{{inventory.product.category.display_name}}</td>
		      							<td>{{inventory.product.collection.display_name}}</td>
		      							<td>
		      								<template v-if="inventory.product.colour">
		      									{{inventory.product.colour.display_name}}
		      								</template>
		      								<template v-else>
		      									&nbsp;
		      								</template>
		      							</td>
		      							<td>{{inventory.batch_no}}</td>
		      							<td>{{inventory.quantity}}<sup>{{inventory.product.base_unit_of_measure}}</sup></td>
		      						</tr>
		      					</tbody>
		      				</table>
		      			</div>
		      		</div>
      				<div class="row" v-if="validInventory">
	      				<div class="col-md-6">
	      					<div class="form-group" :class="errors.has('quantity') ?'has-error' : '' ">
		      					<div class="input-group">
		      						<div class="input-group-btn">
		        						<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-html="btnHtml"></button>
		        						<ul class="dropdown-menu">
		          							<li><a href="#" @click.prevent="operation = '+'"><i class="fa fa-plus"></i></a></li>
		          							<li><a href="#" @click.prevent="operation = '-'"><i class="fa fa-minus"></i></a></li>
		          							<li><a href="#" @click.prevent="operation = '='"><i class="fa fa-equals"></i></a></li>
		        						</ul>
		      						</div>
		      						<input type="text" class="form-control" aria-label="..." v-model.number="quantity"  v-validate="'required|decimal'" data-vv-name="quantity">
		      						<span class="input-group-addon" id="sizing-addon2">{{inventory.product.base_unit_of_measure}}</span>
		    					</div>
		    				</div>
	      				</div>
	      				<div class="col-md-2">
	      					<button class="btn btn-default btn-block" @click="setStock">SET</button>
	      				</div>
	      			</div>
	      			<div class="row" v-if="quantityMessage">
	      				<div class="col-md-12" v-if="quantity" v-html="quantityMessage">
	      				</div>
	      			</div>
      			</div>
      		</div>
      	</div>
    </div>
</template>
<script>
	import Vue from 'vue'
	import appMixin from '../../vuex-mixin.js'
	import VeeValidate from 'vee-validate'
	import {EventBus} from '../../configs/EventBus'
	Vue.use(VeeValidate)
	export default{
		mixins : [appMixin],
		props:['superStockistId'],
		data(){
			return {
				inventory:{},
				operation:"+",
				quantity:0,
				index:-1,
			}
		},
		computed:{
			validInventory(){
				return !_.isEmpty(this.inventory)
			},
			quantityMessage(){
				if(this.quantity == "")
				{
					return '<span class="text-red">Enter valid quantity</span>'
				}
				if(isNaN(this.quantity))
				{
					return '<span class="text-red">Enter valid quantity</span>'
				}
				if(_.isEmpty(this.inventory))
				{
					return ""
				}
				if(this.operation == '=')
				{
					return `Stock will be set to ${this.quantity}<sup>${this.inventory.product.base_unit_of_measure}</sup>`
				}
				if(this.operation == '+')
				{
					return `${this.quantity}<sup>${this.inventory.product.base_unit_of_measure}</sup> will be <span class="text-green">added</span> to stock. Resulting stock will be ${this.inventory.quantity + this.quantity}<sup>${this.inventory.product.base_unit_of_measure}</sup>`
				}
				if(this.operation == '-')
				{
					return `${this.quantity}<sup>${this.inventory.product.base_unit_of_measure}</sup> will be <span class="text-red">deducted</span> from stock. Resulting stock will be ${this.inventory.quantity - this.quantity}<sup>${this.inventory.product.base_unit_of_measure}</sup>`
				}
				return ""
			},
			btnHtml(){
				if(this.operation == '+')
				{
					return '<i class="fa fa-plus"></i>'
				}
				if(this.operation == '-')
				{
					return '<i class="fa fa-minus"></i>'
				}

				if(this.operation == '=')
				{
					return '<i class="fa fa-equals"></i>'
				}
			}
		},
		mounted(){
			let self = this
			$(this.$refs.modal).on('hidden.bs.modal', function (e) {
                self.operation = '+'
                self.quantity = 0
            })
		},
		methods:{
			setStock(){
				let self = this
				self.$validator.validateAll()
				.then(value=> {
					if(value){
						EventBus.$emit('loader:show')
						Vue.axios.post(`super_stockists/${self.superStockistId}/inventory/${self.inventory.id}/adjust`,{quantity:self.quantity,operation:self.operation})
						.then(response =>{
							EventBus.$emit('loader:hide')
							self.$emit('stock-adjust:set',{quantity:response.data.quantity,index:self.index})
							$(self.$refs.modal).modal('hide')
						})
					}
				})
				
			},
			show(inventory,index){
				this.inventory = Object.assign({},inventory)
				this.index = index
				$(this.$refs.modal).modal('show',index)
			}
		}
	}
</script>