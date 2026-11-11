<template>
    <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Add Payment</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Payment Method</label>
                                <div class="form-group">
                                    <input type="radio" v-model="payment_mode" value="cheque">  Cheque &nbsp;&nbsp;
                                    <input type="radio" v-model="payment_mode" value="cash">  Cash &nbsp;&nbsp;
                                    <input type="radio" v-model="payment_mode" value="credit_card">  Card &nbsp;&nbsp;
                                    <input type="radio" v-model="payment_mode" value="wire_transfer">  Wire Transfer &nbsp;&nbsp;
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="form-group" :class="errors.has('payment_amount') ? 'has-error' : ''">
                                <label>Amount</label>
                                <input type="text" class="form-control" v-model.number="payment_amount" v-validate="{ decimal: 2, max_value: order.remaining_amount}" data-vv-name="payment_amount" :disabled="payment_term == 'full_payment'">
                                <span class="text-danger" v-show="errors.has(`payment_amount`)">Payment Amount can not be more than Order Amount OR Remaining Amount in case partial/advance payments are done.</span>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <div class="form-group">
                                    <input type="radio" v-model="payment_term" value="full_payment">  Full/Balance Payment &nbsp;&nbsp;
                                    <input type="radio" v-model="payment_term" value="partial_payment">  Partial Payment &nbsp;&nbsp;
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
    import VeeValidate from 'vee-validate'
    import {EventBus} from '../../../configs/EventBus'
    Vue.use(VeeValidate)
    export default {
        components:{
        },
    props:['company', 'distributor', 'order'],
        data(){
            return {
                payment_amount:0,
                payment_mode:'cheque',
                payment_term:'full_payment'
            }
        },
    mounted(){
        let self = this
        $(self.$refs.modal).on('shown.bs.modal', function (e) {
            Vue.nextTick(function(){
                self.errors.clear()
            })
            //self.payment_amount = parseFloat(self.order.remaining_amount).toFixed(2)
            self.payment_amount = self.order.remaining_amount
        })
        $(self.$refs.modal).on('hidden.bs.modal', function (e) {
            self.payment_amount = 0  
            self.payment_mode = 'cheque'
            self.payment_term = 'full_payment'
        })
    },
        methods:{
      add(){
        let self = this
            let input = {
                payment_amount : self.payment_amount ? self.payment_amount : 0,
                payment_mode : self.payment_mode ? self.payment_mode : null,
            }
          //  let url = `companies/${self.company.id}/distributors/${self.distributor.id}/sales_orders/${self.order.id}/payments`

            let url = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors/sales_orders/${this.order.id}/payments`

            self.$validator.validateAll()
            .then(value => {
                if(value)
                {
                    EventBus.$emit('loader:show')
                    Vue.axios.post(url,input)
                    .then(response => {
                        EventBus.$emit('loader:hide')
                        swal({
                            title:"Payment added",
                            icon:"success"
                        })
                    this.$emit('payment-added',this.payment_amount)
                    $(self.$refs.modal).modal('hide')
                    })
                    .catch(error => {
                        if(error.response.status == 409)
                        {
                            swal({
                                title: "Error",
                                text:error.response.data.error,
                                icon:'error'
                            })
                            .then(value => {
                                self.payment_amount = 0    
                                self.payment_mode = 'cheque'
                                self.payment_term = 'full_payment'
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
    watch:{
        payment_term(){
            if(this.payment_term == 'full_payment')
            {
                //this.payment_amount = parseFloat(this.order.remaining_amount).toFixed(2)
                this.payment_amount = this.order.remaining_amount
            }
            else
            {
                this.payment_amount =''
            }
        }
    }
    }
</script>

