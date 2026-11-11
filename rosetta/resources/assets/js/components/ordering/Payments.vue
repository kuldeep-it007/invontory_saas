<template>
	<div class="row">
		<div class="col-md-6 pull-right">
			<table class="table table-condensed table-bordered">
				<thead>
					<tr>
						<th>Stock Point</th>
						<th>SI No.</th>
						<th>Total Amount</th>
						<th>Paid Amount</th>
						<th>Remaining Amount</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="!loaded">
						<td colspan="3" class="text-center">
								<i class="fa fa-sync fa-spin"></i> Please wait...
						</td>
					</tr>
					<tr v-if="loaded && !payments.length">
						<td colspan="3" class="text-center">
								No payments found
						</td>
					</tr>
					<tr v-for="(rec,index) in payments" :key="`${rec.stock_point_name}`">
						<td class="text-left shrink">{{rec.stock_point_name}}</td>
						<td class="text-left shrink">{{rec.si_number}}</td>
						<td class="text-left shrink">{{rec.amount}}</td>
						<td class="text-left shrink">{{rec.paid_amount}}</td>
						<td class="text-left shrink">
							<template v-if="!rec.si_number">{{rec.amount}}</template>
							<template v-if="rec.si_number">{{rec.remaining_amount}}</template>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>
<script>
	import {EventBus} from '../../configs/EventBus'
	import Vue from 'vue'
	import AppMixins from '../../vuex-mixin'
	export default {
		mixins:[AppMixins],
		props: {
    		rowData: {
      			type: Object,
      			required: true
    		},
    		rowIndex: {
      			type: Number
    		}
  		},
		data(){
			return {
				loaded:false,
				payments:[],
				company:null,
			}
		},
		mounted(){
			// call api 
			if(this.isCustomer)//Superadmin
			{
				this.company = Object.assign({},this.customer.company)
			}
			else//DUC 
			{
				this.company = this.rowData.customer.company
			}
			this.getPaymentDetails()
		},
  		methods:{
  			getPaymentDetails(){
  				let url = ""
  			/*	if(this.rowData.customer_type == 'DISTRIBUTO')
                    url = `companies/${this.rowData.customer.company.id}/distributors/${this.rowData.customer.id}/blanket_orders/${this.rowData.id}/payments`
                if(this.rowData.customer_type == 'DEALER')
                    url = `companies/${this.rowData.customer.company.id}/dealers/${this.rowData.customer.id}/blanket_orders/${this.rowData.id}/payments`*/
                url = `companies/${this.company.id}/blanket_orders/${this.rowData.id}/payments`
				Vue.axios.get(url)
				    .then(response => {
						this.loaded = true
						this.payments = response.data

						EventBus.$emit('payments:changed',{
							id: this.rowData.id,
							payments: response.data
						})
				 })
			}
  		}
	}
</script>
