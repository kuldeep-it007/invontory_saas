<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog modal-sm" role="document">
    		<div class="modal-content">
      		<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 class="modal-title">{{product.name}} : {{product.nav_id}}</h4>
      		</div>
      		<div class="modal-body">
      			<div class="row">
   					<div class="col-md-12">
   						<div class="form-group">
                            <label>Current MUP</label>
						       <input type="text" class="form-control" v-model="product.price.unit" disabled="disabled">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group" :class="errors.has('new_price') ? 'has-error' : ''">
                            <label>New MUP</label>
                            <input type="text" class="form-control" v-model="new_price" v-validate="'required|decimal|min_value:0'" data-vv-name="new_price">
   						</div>
   					</div>
      			</div>
      		</div>
      		<div class="modal-footer">
      			<button class="btn btn-sm btn-primary" @click="add">Set MUP</button>
      		</div>
   		</div>
   	</div>
   </div>
</template>
<script>
  import Vue from 'vue'
  import {EventBus} from '../../../configs/EventBus'
	export default {
    props:{
        company:{
            type:Object,
            required:true,
        }
    },
		components:{
		},
		data(){
			return {
        product:{
         price:{},
        },
        new_price:0
			}
		},
    mounted(){
        let self = this
        $(this.$refs.modal).on('hidden.bs.modal', function (e) {
            self.new_price = 0
        })
        $(this.$refs.modal).on('show.bs.modal', function (e) {
            Vue.nextTick(function(){
                self.errors.clear()
            })
        })
    },
		methods:{
      add(){
          let input = {          
              price : this.new_price,
              productId : this.product.id,
          }
          this.$validator.validateAll()
          .then(value => {
              if(value)
              {
                  EventBus.$emit('loader:show')
                  Vue.axios.post(`companies/${this.company.id}/product_prices`,input)
                  .then(response => {
                      EventBus.$emit('loader:hide')
                      swal({
                          title:"MUP updated",
                          icon:"success"
                      })
                      this.$emit('product_price:added',this.new_price)
                      $(this.$refs.modal).modal('hide')
                  })
                  .catch(error => {
                     EventBus.$emit('loader:hide')
                  })
              }
          })
      },
			show(product){
        this.product = Object.assign({},product)
				$(this.$refs.modal).modal('show')
			}
		}
	}
</script>

