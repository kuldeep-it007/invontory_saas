<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog modal-sm" role="document">
    		<div class="modal-content">
      		<div class="modal-header">
        		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        	</div>
      		<div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Current Tax</label>
                               <input type="text" class="form-control" v-model="product_tax.tax_percent" disabled="disabled">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>New Tax</label>
                            <input type="text" class="form-control" v-model="new_tax" v-validate="'required|decimal|min_value:0|max_value:100'" data-vv-name="new_tax">
                            <span class="text-danger" v-show="errors.has('new_tax')">Please enter valid value.</span>
                        </div>
                    </div>
                </div>
      		</div>
      		<div class="modal-footer">
      			<button class="btn btn-sm btn-primary" @click="update">Set Tax</button>
      		</div>
   		</div>
   	</div>
   </div>
</template>
<script>
  import Vue from 'vue'
  import {EventBus} from '../../../configs/EventBus'
  export default {
    components:{
    },
    data(){
      return {
        product_tax:{},
        new_tax:0
      }
    },
    mounted(){
        let self = this
        $(this.$refs.modal).on('hidden.bs.modal', function (e) {
            self.new_tax = 0
        })
        $(this.$refs.modal).on('show.bs.modal', function (e) {
            Vue.nextTick(function(){
                self.errors.clear()
            })
        })
    },
    methods:{
      update(){
          let input = {          
              tax : this.new_tax,
              productTaxId : this.product_tax.id,
          }
          this.$validator.validateAll()
          .then(value => {
              if(value)
              {
                  EventBus.$emit('loader:show')
                  Vue.axios.put(`regions/${this.product_tax.region_id}/update_product_taxes`,input)
                  .then(response => {
                      EventBus.$emit('loader:hide')
                      swal({
                          title:"Tax updated",
                          icon:"success"
                      })
                      this.$emit('product_tax:updated',this.new_tax)
                      $(this.$refs.modal).modal('hide')
                  })
                  .catch(error => {
                     EventBus.$emit('loader:hide')
                  })
              }
          })
      },
      show(product_tax){
        this.product_tax = Object.assign({},product_tax)
        $(this.$refs.modal).modal('show')
      }
    }
  }
</script>

