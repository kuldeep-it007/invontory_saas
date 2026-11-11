<template>
	<div>
		<h4 style="text-align:center;font-style:italic"><b>In-transit Stock</b></h4>
		<table class="table table-condensed table-bordered" style="width: 90%;">
			<thead>
				<tr>
					<th>Stock point</th>
					<th>In-transit</th>
					<th>Blocked</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
				<template v-for="(rec,index) in rowData.stock_points">
				<tr :key="`${rec.source}-${rec.id}`" v-if="rec.intransit > 0">
					<td>{{rec.name}}</td>
					<td class="text-right shrink">{{rec.intransit.toFixed(2)}}<sup>{{rowData.base_unit_of_measure}}</sup></td>
					<td class="text-right shrink">{{rec.intransit_blocked.toFixed(2)}}<sup>{{rowData.base_unit_of_measure}}</sup></td>
					<td class="text-right shrink"><button class="btn btn-xs btn-success" @click="select(rec)" v-if="rowData.price.unit > 0">SELECT</button></td>
				</tr>
				</template>
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
  				EventBus.$emit('intransit-stock-points:load',{item:this.rowData,stock_point:rec})
  			}
  		}
	}
</script>
