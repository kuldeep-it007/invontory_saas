<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog modal-lg" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 class="modal-title">Add Discount</h4>
      			</div>
      			<div class="modal-body">
      				<div class="row">
      					<div class="col-md-8">
      						<div class="form-group">
      							<label>Dealer</label>
      							<dealer-select v-model="selectedDealer" :selected-distributor= "distributor" :company="company"></dealer-select>
      						</div>
      					</div>
      				</div>
      				<div class="row">
      					<div class="col-md-4">
      						<div class="form-group">
      							<label>Category</label>
      							<category v-model="category"></category>
      						</div>
      					</div>
      					<div class="col-md-4">
      						<div class="form-group">
      							<label>Collection</label>
      							<collection v-model="collection" :category="category"></collection>
      						</div>
      					</div>
      					<div class="col-md-4">
      						<div class="form-group">
      							<label>Colour</label>
      							<colour v-model="colour" :collection="collection" @input="colourSelected"></colour>
      						</div>
      					</div>
      				</div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group" :class="errors.has('discount') ? 'has-error' : ''">
                                <label>Discount</label>
                                <div class="input-group">
                                      <input type="text" class="form-control" v-model="discount" v-validate="{ decimal: 2, max_value: 100}" data-vv-name="discount" @keyup="calculateDiscountedPrice(price, discount.discount)">
                                      <span class="input-group-addon" id="basic-addon2">%</span>
                                    </div>
                            </div>
                        </div>
                        <div class="col-md-4" v-if="price">
                            <div class="form-group" :class="errors.has('discounted_price') ? 'has-error' : ''">
                                <label>Discounted Price</label>
                                <input type="text" class="form-control" v-model="discounted_price" v-validate="{ decimal: 2, max_value: price}" data-vv-name="discounted_price" @keyup="calculateDiscount(price, discounted_price)">
                            </div>
                        </div>
                    </div>
                    <div class="row" v-if="errors.any()">
                        <div class="col-md-12">
                             <p class="text-danger" v-show="errors.has(`discount`)">Discount can not be negative or more than 100%.</p>
                             <p class="text-danger" v-show="errors.has(`discounted_price`)">Discounted Price can not be less than 1 or greater RRP {{price}}.</p>
                        </div>
                    </div>
      			</div>
      			<div class="modal-footer">
      				<button class="btn btn-sm btn-primary" @click="add">ADD</button>
      			</div>
      		</div>
      	</div>
    </div>
</template>
<script>
    import Vue from 'vue'
	import DealerSelect from '../master/DealerSelect'
	import Category from '../categories/Search'
	import Collection from '../collections/Search'
	import Colour from '../colour/Search'
    import {EventBus} from '../../configs/EventBus'
	export default {
		components:{
			DealerSelect,
			Category,
			Collection,
			Colour
		},
        props:['company', 'distributor'],
		data(){
			return {
				selectedDealer:null,
				category:null,
				collection:null,
				colour:null,
				discount:0,
                discounted_price:0,
                price:0,
			}
		},
        mounted(){
            let self = this
            $(this.$refs.modal).on('shown.bs.modal', function (e) {
                Vue.nextTick(function(){
                    self.errors.clear()
                })
            })
            $(this.$refs.modal).on('hidden.bs.modal', function (e) {
                self.selectedDealer = null
                self.category = null    
                self.collection = null
                self.colour = null
                self.discount = 0
                self.discounted_price=0
            })
        },
    	methods:{
        colourSelected(){
            let self = this
            if(this.colour)
            {
                this.price = 0
                let url = `companies/${self.company.id}/categories/${encodeURIComponent(self.category.code)}/collections/${encodeURIComponent(self.collection.code)}/colours/${encodeURIComponent(self.colour.code)}/get_product_price`
                EventBus.$emit('loader:show')
                Vue.axios.get(url)
                .then(response => {
                    EventBus.$emit('loader:hide')
                    this.price = response.data
                    this.discounted_price = this.price
                    this.discount = 0
                }) 
            }
        },
        calculateDiscountedPrice(){
            if(this.price)
                this.discounted_price =  (this.price - (this.price * this.discount /100)).toFixed(2);
        },
        calculateDiscount(){
            if(this.price)
                this.discount = ((this.price - this.discounted_price )/ this.price * 100).toFixed(2);
        },
        add(){
          let input = {
              discount : this.discount ? this.discount : 0,
              distributor_id : this.distributor.id
          }
          if(this.selectedDealer)
          {
              input.customer_id = this.selectedDealer.id
          }
          if(this.category)
          {
              input.category_code = this.category.code
          }
          if(this.collection)
          {
              input.collection_code = this.collection.code
          }
          if(this.colour)
          {
              input.colour_code = this.colour.code
          }
          this.$validator.validateAll()
          .then(value => {
          if(value)
          {
            EventBus.$emit('loader:show')
            Vue.axios.post('customer_discounts',input)
            .then(response => {
                EventBus.$emit('loader:hide')
                swal({
                    title:"Discount added",
                    icon:"success"
                })
                this.$emit('discount-added',response.data)
                $(this.$refs.modal).modal('hide')
            })
            .catch(error => {
            //    EventBus.$emit('loader:show')
              if(error.response.status == 409)
              {
                swal({
                    title: "Error",
                    text:error.response.data.error,
                    icon:'error'
                })
                .then(value => {
                  this.category = null    
                  this.collection = null
                  this.colour = null
                //  $(self.$refs.modal).modal('hide')
                })
              }
            })
          }
        })
      },
			show(){
				$(this.$refs.modal).modal('show')
			}
		},
	}
</script>

