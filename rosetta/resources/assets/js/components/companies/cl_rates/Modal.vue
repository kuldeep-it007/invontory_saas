<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog modal-lg" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 class="modal-title">Add CL Rate</h4>
      			</div>
      			<div class="modal-body">
      				<div class="row">
      					<div class="col-md-3">
      						<div class="form-group">
      							<label>Customer Type</label>
      							<customer-type v-model="customer_type"></customer-type>
      						</div>
      					</div>
      					<div class="col-md-6">
      						<div class="form-group">
      							<label>
      								<template v-if="customer_type">
      									<template v-if="customer_type.code == 'DISTRIBUTO'">Distributor</template>
      									<template v-if="customer_type.code == 'DEALER'">Dealer</template>
      								</template>
      								<template v-else>
      									CUstomer
      								</template>
      							</label>
      							<customer-select v-model="customer" :company="company" :customer_type="customer_type" :disabled="!company"></customer-select>
      						</div>
      					</div>
      				</div>
      				<div class="row">
      					<div class="col-md-3">
      						<div class="form-group">
      							<label>Category</label>
      							<category v-model="category" :only-cl="true"></category>
      						</div>
      					</div>
      					<div class="col-md-3">
      						<div class="form-group">
      							<label>Collection</label>
      							<collection v-model="collection" :category="category"></collection>
      						</div>
      					</div>
      					<div class="col-md-3">
      						<div class="form-group">
      							<label>Colour</label>
      							<colour v-model="colour" :collection="collection"></colour>
      						</div>
      					</div>
      					<div class="col-md-3">
      						<div class="form-group" :class="errors.has('rate') ? 'has-error' : ''">
      							<label>CL Rate</label>
      							<div class="input-group">
  									<input type="text" class="form-control" v-model="rate" v-validate="'required|decimal'" data-vv-name="discount">
  									<span class="input-group-addon" id="basic-addon2">%</span>
								</div>
      						</div>
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
	import CustomerType from '../../master/CustomerType'
	import CustomerSelect from '../../master/CustomerSelect'
	import Category from '../../categories/Search'
	import Collection from '../../collections/Search'
	import Colour from '../../colour/Search'
    import VeeValidate from 'vee-validate'
    import {EventBus} from '../../../configs/EventBus'
    Vue.use(VeeValidate)
	export default {
        props:{
            company:{
                type:Object,
                required:true,
            }
        },
		components:{
			CustomerType,
			CustomerSelect,
			Category,
			Collection,
			Colour
		},
		data(){
			return {
				customer_type:null,
				customer:null,
				category:null,
				collection:null,
				colour:null,
				rate:0
			}
		},
		methods:{
            add(){
                let input = {
                    
                    rate : this.rate,
                    customer_type: this.customer_type.code
                }
                if(this.customer)
                {
                    input.customer_id = this.customer.id
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
                        EventBus.$emit('loader:show')
                        Vue.axios.post(`companies/${this.company.id}/cl_rates`,input)
                        .then(response => {
                            EventBus.$emit('loader:hide')
                            swal({
                                title:"Rate added",
                                icon:"success"
                            })
                            this.$emit('cl_rate:added',response.data)
                            $(this.$refs.modal).modal('hide')
                        })
                        .catch(error => {
                            swal({
                                title:"CL Rate for selected parametes aleady exists",
                                icon:"error"
                            })
                            EventBus.$emit('loader:hide')
                        })
                    }
                })
                

            },
			show(){
				$(this.$refs.modal).modal('show')
			}
		}
	}
</script>

