<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<h4 class="modal-title">Place Order</h4>
      			</div>
      			<div class="modal-body">
                    <div class="row">
                        <div class="col-md-12" v-if="paymentMethods">
                            <h5>Summary</h5>
                            <table class="table table-condensed table-bordered">
                                <thead>
                                    <tr>
                                        <th>
                                            Method
                                        </th>
                                        <th class="shrink">
                                            Amount
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="paymentMethods.balance">
                                        <td>
                                            Rosetta Balance
                                        </td>
                                        <td>
                                            {{(paymentMethods.balance.amount).toFixed(2)}}
                                        </td>
                                    </tr>
                                    <tr v-if="paymentMethods.online">
                                        <td>
                                            Online Payment
                                        </td>
                                        <td class="shrink">
                                            {{(paymentMethods.online.amount).toFixed(2)}}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th>{{(paymentMethods.orderAmount).toFixed(2)}}</th>
                                    </tr>
                                </tfoot>
                            </table>
                            <!--
                            <template v-if="paymentMethods.paymentMode == 'offline'">
                                <p>You have rosetta balance of {{paymentMethods.rosettaBalance}}. Current order of {{paymentMethods.orderAmount}} will be paid from rosetta balance.</p>
                            </template>
                            <template v-if="paymentMethods.paymentMode == 'online'">
                                <template v-if="paymentMethods.balance">
                                    <p>You have rosetta balance of {{paymentMethods.rosettaBalance}}. {{paymentMethods.balance.amount}} will be paid through rosetta balance</p>
                                </template>
                                <p>You will be paying {{paymentMethods.online.amount}} online.</p>
                            </template>
                            -->
                        </div>
                     </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-default" @click="cancel">Cancel</button>
                    <button class="btn btn-sm btn-default" @click="placeOrderClicked">Place order</button>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
export default {
    data(){
        return {
            paymentMethods:null,
        }
    },
    methods:{
        placeOrderClicked(){
            this.$emit('calculation:place-order',this.paymentMethods)
            this.cancel()
        },
        cancel(){
            $(this.$refs.modal).modal('hide')
            this.paymentMethods = null
        },
        show(payments){
            this.paymentMethods = Object.assign({},payments)
             $(this.$refs.modal).modal('show')
        }
    }
}
</script>

