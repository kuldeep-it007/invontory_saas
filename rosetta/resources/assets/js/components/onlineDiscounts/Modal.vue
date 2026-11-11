<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog modal-lg" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 class="modal-title">Add Online Discount</h4>
      			</div>
      			<div class="modal-body">
      				<div class="row">
      					<div class="col-md-3">
      						<div class="form-group">
      							<label>Category</label>
      							<category v-model="category" :disabled="editDiscount"></category>
      						</div>
      					</div>
      					<div class="col-md-3">
      						<div class="form-group">
      							<label>Collection</label>
      							<collection v-model="collection" :category="category" :disabled="editDiscount"></collection>
      						</div>
      					</div>
      					<div class="col-md-3">
      						<div class="form-group">
      							<label>Colour</label>
      							<colour v-model="colour" :collection="collection" :disabled="editDiscount"  @input="colourSelected"></colour>
      						</div>
      					</div>
      				</div>

                    <div class="row">
                        <div class="col-md-3" v-if="colour">
                            <div class="form-group">
                                <label>RRP</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" v-model="price">
                                    <span class="input-group-addon" id="basic-addon2"></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group" :class="errors.has('discount') ? 'has-error' : ''">
                                <label>Discount</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" v-model="discount" v-validate="{ decimal: 2, max_value: 100}" data-vv-name="discount" @keyup="calculateDiscountedPrice(price, discount.discount)">
                                    <span class="input-group-addon" id="basic-addon2">%</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3" v-if="price">
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
      				<button class="btn btn-sm btn-primary" @click="add"><template v-if="editDiscount">UPDATE</template><template v-else>ADD</template></button>
      			</div>
      		</div>
      	</div>
    </div>
</template>
<script>
    import Vue from 'vue'
	import Category from '../categories/Search'
	import Collection from '../collections/Search'
	import Colour from '../colour/Search'
    import {EventBus} from '../../configs/EventBus'
	export default {
        props:{
            company:{
                type: Object,
                required:true
            }
        },
		components:{
			Category,
			Collection,
			Colour
		},
		data(){
			return {
				category:null,
				collection:null,
				colour:null,
                discount:0,
                editDiscount:null,
                index:-1,
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
                self.category = null    
                self.collection = null
                self.colour = null
                self.discount = 0
                self.discounted_price=0
                self.price=0
            })
        },
		methods:{
            addRequest(input){
                EventBus.$emit('loader:show')
                Vue.axios.post(`companies/${this.company.id}/online_discounts`,input)
                .then(response => {
                    swal({
                        title:"Discount added",
                        text: "Reload list to see new discount",
                        icon:"success"
                    })
                    this.$emit('online-discount:added',response.data)
                    $(this.$refs.modal).modal('hide')
                })
                .catch(error => {
                    if(error.response.status == 409)
                    {
                        return swal({
                            title:"Error",
                            text:"Online discount already exists",
                            icon:"error"
                        })
                    }
                    swal({
                        title:"Error",
                        text:"Unable to add discount",
                        icon:"error"
                    })
                })
            },
            updateRequest(input){
                EventBus.$emit('loader:show')
                Vue.axios.put(`companies/${this.company.id}/online_discounts/${this.editDiscount.id}`,input)
                .then(response => {
                    swal({
                        title:"Discount Updated",
                        icon:"success"
                    })
                    this.$emit('online-discount:updated',{discount:response.data,index:this.index})
                    $(this.$refs.modal).modal('hide')
                })
                .catch(error => {
                    if(error.response.status == 409)
                    {
                        return swal({
                            title:"Error",
                            text:"Online discount already exists",
                            icon:"error"
                        })
                    }
                    swal({
                        title:"Error",
                        text:"Unable to update discount",
                        icon:"error"
                    })
                })
            },
            add(){
                let input = {
                    discount: this.discount
                }
                if(this.category)
                {
                    input.category = this.category.code
                }
                if(this.collection)
                {
                    input.collection = this.collection.code
                }
                if(this.colour)
                {
                    input.colour = this.colour.code
                }
                this.$validator.validateAll()
                .then(value => {
                    if(value)
                    {
                        if(this.editDiscount)
                        {
                            this.updateRequest(input)
                        }
                        else
                        {
                            this.addRequest(input)
                        }
                    }
                })
            },
			show(discount,index){
                if(discount)
                {
                    this.editDiscount = Object.assign({},discount)
                    if(this.editDiscount.category)
                        this.category = Object.assign({},this.editDiscount.category)
                    if(this.editDiscount.collection)
                        this.collection = Object.assign({},this.editDiscount.collection)
                    if(this.editDiscount.colour)
                    {
                        this.colour = Object.assign({},this.editDiscount.colour)
                        this.colourSelected()
                    }
                    this.discount = this.editDiscount.discount
                    this.index = index
                }
                else
                {
                    this.editDiscount = null
                    this.category = null
                    this.collection = null
                    this.colour = null
                    this.index = -1
                    this.discount = 0
                }
				$(this.$refs.modal).modal('show')
			},
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
                  //      this.discount = 0
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
		}
	}
</script>