<template>
	<div>
		<table class="table table-condensed table-bordered">
			<thead>
				<tr>
					<th>Stock point</th>
					<th>Stock</th>
					<th>Blocked</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(rec,index) in rowData.stock_points" :key="`${rec.source}-${rec.id}`">
					<td>{{rec.name}}</td>
					<td class="text-right shrink">{{rec.inventory.toFixed(2)}}<sup>{{rowData.base_unit_of_measure}}</sup></td>
					<td class="text-right shrink">{{rec.blocked.toFixed(2)}}<sup>{{rowData.base_unit_of_measure}}</sup></td>
					<td class="text-right shrink"><button class="btn btn-xs btn-success" @click="select(rec)">DETAILS</button></td>
				</tr>
			</tbody>
		</table>
	</div>
</template>
<script>
	import {EventBus} from '../../configs/EventBus'
	export default {
		props: {
    		rowData: {
      			type: Object,
      			required: true
    		},
    		rowIndex: {
      			type: Number
    		}
  		},
  		methods:{
  			select(rec){
  				EventBus.$emit('stock-points:load',{item:this.rowData,stock_point:rec})
  			}
  		}
	}
</script>
