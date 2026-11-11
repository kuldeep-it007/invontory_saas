<template>
    <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Payment Type</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-group">
                                    <input type="radio" v-model="payment_type" value="1"> Offline Payment<br/>
                                    <input type="radio" v-model="payment_type" value="2">  Online Payment<br/>
                                    <input type="radio" v-model="payment_type" value="3">  Both 
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-primary" @click="save">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
  import Vue from 'vue'
    import VeeValidate from 'vee-validate'
    import {EventBus} from '../../configs/EventBus'
    Vue.use(VeeValidate)
    export default {
        components:{},
        data(){
            return {
                payment_type:'',
                distributor:null,
              }
        },
        mounted(){
            let self = this
            $(self.$refs.modal).on('hidden.bs.modal', function (e) {
                 Vue.nextTick(function(){
                    self.errors.clear()
                })
            })
        },
        methods:{
            save(){
                let self = this
                let input = {
                    payment_type : self.payment_type ? self.payment_type : 1,
                }
                if(self.distributor.company)
                {
                    let url = `companies/${self.distributor.company.id}/distributors/${self.distributor.id}/payment_type`
                    self.$validator.validateAll()
                    .then(value => {
                        if(value)
                        {
                            EventBus.$emit('loader:show')
                            Vue.axios.put(url,input)
                            .then(response => {
                                EventBus.$emit('loader:hide')
                                swal({
                                    title:"Payment Type set",
                                    icon:"success"
                                })
                            self.$emit('payment-type-set',response.data)
                            $(self.$refs.modal).modal('hide')
                            })
                            .catch(error => {})
                        }
                    })
                }
            },
            show(distributor){
                let self = this
                self.distributor = Object.assign({},distributor)
                if(distributor.paymentTypes)
                {
                    if(distributor.paymentTypes.online == true && distributor.paymentTypes.offline == true) 
                        self.payment_type = '3'
                    else if(distributor.paymentTypes.online == true && distributor.paymentTypes.offline == false)
                        self.payment_type = '2'
                    else
                        self.payment_type = '1'
                }
                $(this.$refs.modal).modal('show')
            }
        },
    }
</script>

