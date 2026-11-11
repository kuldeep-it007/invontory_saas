<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog modal-lg" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 v-if="line" class="modal-title">Returns : 
        				<span>{{line.product.category.display_name}} &raquo; {{line.product.collection.display_name}}<template v-if="line.product.colour"> &raquo; {{line.product.colour.display_name}}
                        </template>
                            (Batch: {{line.batch_no}})
                        </span>
        			</h4>
      			</div>
      			<div class="modal-body">
      				<div class="row" v-if="line && line.returnItems.length">
      					<div class="col-md-12">
		      				<table class="table table-condensed table-bordered">
		      					<thead>
		      						<tr>
		      							<th>Returned Quantity</th>
		      							<th>Status</th>
		      							<th>Authorised By</th>
		      							<th>Authorised Date</th>
		      							<th>Reject Reason</th>
		      						</tr>
		      					</thead>
		      					<tbody>
                                    <tr v-for="(returnItem,index) in line.returnItems" :key="returnItem.id">
		      							<td>{{returnItem.quantity}}<sup>{{returnItem.product.base_unit_of_measure}}</sup></td>
		      							<td>{{returnItem.status}}</td>
		      							<td>{{returnItem.authorised_by}}</td>
		      							<td>
		      								<template v-if="returnItem.authorisation_date">
		      									{{returnItem.authorisation_date|moment}}		
		      								</template>
		      								<template v-else>
		      									&nbsp;
		      								</template>
		      							</td>
		      							<td>{{returnItem.reject_reason}}</td>
		      						</tr>
		      					</tbody>
		      				</table>
		      			</div>
		      		</div>
      				<div class="row" v-if="line && line.return_allowed && (line.return_approved + line.return_pending) < line.quantity">
	      				<div class="col-md-6">
	      					<div class="form-group" :class="errors.has('quantity') ? 'has-error' : '' ">
		      					<div class="input-group">
		      						<input type="text" class="form-control" aria-label="..." v-model.number="quantity"  v-validate="`required|decimal|max_value:${maxQuantity}`" data-vv-name="quantity">
		      						<span class="input-group-addon" id="sizing-addon2">{{line.product.base_unit_of_measure}}</span>
		    					</div>
		    				</div>
	      				</div>
	      				<div class="col-md-2">
	      					<button class="btn btn-default btn-block" :disabled="errors.has('quantity') || quantity <= 0" @click="returnStock">RETURN</button>
	      				</div>
	      			</div>
	      			<div class="row" v-if="line && !line.return_allowed">
	      				<div class="col-md-12">
	      					<span class="text-red">Return is allowed within 90 days of Order placement.</span>
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
	import VeeValidate from 'vee-validate'
	import {EventBus} from '../../../configs/EventBus'
	import moment from 'moment'
    Vue.prototype.moment = moment
	Vue.use(VeeValidate)
	export default{
		props:['line', 'companyId', 'distributorId', 'dealerId'],
		data(){
			return {
				quantity:''
			}
		},
		 filters: {
            moment: function (date) {
                return moment.unix(parseInt(date)).format('MMMM Do YYYY, h:mm:ss a');
             }
        },
		computed:{
			maxQuantity(){
				return this.line.quantity - (this.line.return_approved + this.line.return_pending)
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
				if(_.isEmpty(this.line))
				{
					return ""
				}
				if(this.quantity > this.line.quantity || this.quantity > (this.line.quantity - (this.line.return_approved + this.line.return_pending)))
				{
					return `<span class="text-red">Quantity to be returned can not exceed Shipped Quantity</span> (${this.line.quantity}<sup>${this.line.product.base_unit_of_measure}</sup>)`
				}
				return ""
			},
		},
		mounted(){
			let self = this
			$(this.$refs.modal).on('hidden.bs.modal', function (e) {
                self.quantity = 0
            })
		},
		methods:{
			moment: function () {
  	            return moment();
  	        },
			returnStock(){
				let self = this
				self.$validator.validateAll()
				.then(value=> {
					if(value){
						EventBus.$emit('loader:show')
						Vue.axios.post(`distributors/${self.distributorId}/return_items`,{customerId:self.dealerId,lineId:self.line.id,quantity:self.quantity})
						.then(response =>{
							EventBus.$emit('loader:hide')
						//	self.$emit('stock-returned',{quantity:self.quantity})
						    self.line.return_requested += self.quantity
						    self.line.return_pending += self.quantity
						    self.quantity = 0
							self.$emit('stock-returned',response.data)
						//	$(self.$refs.modal).modal('hide')
						})
					}
				})
			},
			show(line){
			//	this.line = Object.assign({},line)
				$(this.$refs.modal).modal('show')
			}
		}
	}
</script>