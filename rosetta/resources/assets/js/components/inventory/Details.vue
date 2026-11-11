<template>
	<div class="details-container">
		<div v-if="details.length">
			<table class="table table-condensed table-striped">
				<thead>
					<tr>
						<th>Batch</th>
						<th>Location</th>
						<th style="text-align: right;">Quantity <sup>{{rowData.base_unit_of_measure}}</sup></th>
						
					</tr>
				</thead>
				<tbody>
					<tr v-for="detail in details" :key="detail.lot_no">
						<td>{{detail.lot_no}}</td>
						<td>{{detail.location_code}}</td>
						<td style="text-align: right;">{{detail.remaining_quantity}}</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div v-else>
			<h4 style="text-align:center;">Fetching details....</h4>
		</div>
	</div>
</template>
<script>
	import Vue from 'vue'
	export default{
		props: {
    		rowData: {
      			type: Object,
      			required: true
    		},
    		rowIndex: {
      			type: Number
    		}
  		},
		mounted(){
			Vue.axios.get(`companies/${this.$route.params.id}/inventory/${this.rowData.id}`)
			.then(response => {
				this.details = response.data
			})
		},
		data(){
			return{
				details:[],
			}
		}
	}
</script>
<style scoped>
.details-container{
	padding:20px;
	border:.5px solid #CCC;
}
</style>