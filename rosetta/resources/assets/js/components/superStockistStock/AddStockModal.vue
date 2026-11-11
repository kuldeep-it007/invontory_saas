<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static" v-if="abilities.addStock">
  		<div class="modal-dialog modal-md" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 class="modal-title">Add Stock</h4>
      			</div>
      			<div class="modal-body">
      				<div class="row">
      					<div class="col-md-4">
      						<div class="form-group" :class="errors.has('category') ? 'has-error' : ''">
      							<label>Category *</label>
      							<category v-model="category" v-validate="'required'" data-vv-name="category"></category>
      						</div>
      					</div>
      					<div class="col-md-4">
      						<div class="form-group" :class="errors.has('collection') ? 'has-error' : ''">
      							<label>Collection *</label>
      							<collection v-model="collection" :category="category"  v-validate="'required'" data-vv-name="collection"></collection>
      						</div>
      					</div>
      					<div class="col-md-4">
      						<div class="form-group">
      							<label>Colour</label>
      							<colour v-model="colour" :collection="collection"></colour>
      						</div>
      					</div>
              </div>
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group" :class="errors.has('batch_no') ? 'has-error' : ''">
                    <label>Batch No *</label>
                    <input type="text" class="form-control" v-model="batch_no" v-validate="'required'" data-vv-name="batch_no"></input>
                  </div>
                </div>
      					<div class="col-md-4">
      						<div class="form-group" :class="errors.has('stock') ? 'has-error' : ''">
      							<label>Stock *</label>
      							<div class="input-group">
  									<input type="text" class="form-control" v-model.number="stock" v-validate="'required|decimal'" data-vv-name="stock">
                                    <span class="input-group-addon" id="sizing-addon2">SFT</span>
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
  import appMixin from '../../vuex-mixin.js'
	import Category from '../categories/Search'
	import Collection from '../collections/Search'
	import Colour from '../colour/Search'
    import VeeValidate from 'vee-validate'
    import {EventBus} from '../../configs/EventBus'
    Vue.use(VeeValidate)
	export default {
    mixins : [appMixin],
		components:{
			Category,
			Collection,
			Colour
		},
    props:['companyId', 'superStockistId'],
		data(){
			return {
				category:null,
				collection:null,
				colour:null,
				stock:null,
        batch_no:null
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
            self.stock = null
            self.batch_no = null
        })
    },
		methods:{
      add(){

          this.$validator.validateAll()
          .then(value => {
          if(value)
          {
            let input = {
              stock : this.stock ? this.stock : 0,
              super_stockist_id : this.superStockistId,
              category_code : this.category.code,
              collection_code : this.collection.code,
              batch_no : this.batch_no
            }
            if(this.colour)
            {
                input.colour_code = this.colour.code
            }
            let url = `companies/${this.companyId}/super_stockists/${this.superStockistId}/add_stock`
            EventBus.$emit('loader:show')
            Vue.axios.post(url,input)
            .then(response => {
                EventBus.$emit('loader:hide')
                swal({
                    title:"Stock added",
                    icon:"success"
                })
                this.$emit('stock-added',response.data)
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
                  this.batch_no = null
                  this.stock = 0
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

