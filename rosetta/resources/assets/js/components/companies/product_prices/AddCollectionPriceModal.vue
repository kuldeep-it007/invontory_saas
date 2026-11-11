<template>
    <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
            <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">
                        <template v-if="company">{{company.name}}</template>
                    </h4>
                    <span class="text-danger animated infinite flash delay-2s">Note: Setting MUP for a Collection will update Pricing for existing Products as well.</span>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Category</label>
                            <category v-model="category"></category>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Collection</label>
                            <collection v-model="collection" :category="category"></collection>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group" :class="errors.has('new_price') ? 'has-error' : ''">
                            <label>MUP</label>
                            <input type="text" class="form-control" v-model="new_price" v-validate="'required|decimal|min_value:0'" data-vv-name="new_price">
                        </div>
                    </div>
                    <div class=col-md-6>
                        <label style="display:block">&nbsp;</label>
                        <button class="btn btn-sm btn-success pull-right" @click="add">Set MUP</button>
                        
                 <!--       <button class="btn btn-sm btn-primary" @click="add">Set MUP</button>-->
                    </div>
                </div>
            </div>
           <!-- <div class="modal-footer">
                <button class="btn btn-sm btn-primary" @click="add">Set RRP</button>
            </div>-->
        </div>
    </div>
   </div>
</template>
<script>
  import Vue from 'vue'
  import {EventBus} from '../../../configs/EventBus'
  import Category from '../../categories/Search'
  import Collection from '../../collections/Search'
    export default {
    props:{
         company:{
            type:Object,
            required:true,
        }
    },
        components:{
            Category,
            Collection,
        },
        data(){
            return {
                category:null,
            //    collection:null,
                collection:{
                 code:null,
                 category_code:null
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
          }
          this.$validator.validateAll()
          .then(value => {
              if(value)
              {
                  EventBus.$emit('loader:show')
                   Vue.axios.post(`companies/${this.company.id}/categories/${this.collection.category_code}/collections/${this.collection.code}/set_mup`,input)
                  .then(response => {
                      EventBus.$emit('loader:hide')
                      swal({
                          title:"MUP set",
                          icon:"success"
                      })
                      this.$emit('collection_price:added',this.new_price)
                      $(this.$refs.modal).modal('hide')
                  })
                  .catch(error => {
                    EventBus.$emit('loader:hide')
                    if(error.response.status == 500)
                    {
                       swal({
                            title:'Oops...',
                            text:"Something went wrong while processing your request. Please contact your Relationship Manager.",
                            icon:'error',
                        })
                        return
                    }
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

