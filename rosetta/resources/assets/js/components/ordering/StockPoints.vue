<template>
	<div>
		<h4 style="text-align:center;font-style:italic"><b>Available Stock</b></h4>
		<table class="table table-condensed table-bordered" style="width: 90%;">
			<thead>
				<tr>
					<th>Stock point</th>
					<th>Stock</th>
				<!--	<th>Intransit</th>-->
					<th>Blocked</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(rec,index) in rowData.stock_points" :key="`${rec.source}-${rec.id}`" v-if="(rec.inventory + rec.blocked) > 0">
					<td>{{rec.name}}</td>
					<td class="text-right shrink">{{rec.inventory.toFixed(2)}}<sup>{{rowData.base_unit_of_measure}}</sup></td>
					<!--<td class="text-right shrink">{{rec.intransit.toFixed(2)}}<sup>{{rowData.base_unit_of_measure}}</sup></td>-->
					<td class="text-right shrink">{{rec.blocked.toFixed(2)}}<sup>{{rowData.base_unit_of_measure}}</sup></td>
					<td class="text-right shrink"><button class="btn btn-xs btn-success" @click="select(rec)" v-if="rowData.price.unit > 0">SELECT</button></td>
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
    		},
    		options: {
    			type: Object,
  			}
  		},
  		methods:{
  			select(rec){
  				EventBus.$emit('stock-points:load',{item:this.rowData,stock_point:rec})
  			}
  		}
	}
</script>
