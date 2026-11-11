<template>
	<div class="alert alert-danger">
		Unable to place order for {{rowData.product.category.code}}&raquo;{{rowData.product.collection.code}}<template v-if="rowData.product.colour">&raquo;{{rowData.product.colour.code}}</template> ({{rowData.batch_no}}) for quantity {{rowData.order_quantity}}<sup>{{rowData.product.base_unit_of_measure}}</sup>. This may happen due to changes in stock.
	</div>
</template>
<script>
	export default{
		props:{
			rowData: {
        		type: Object,
        		required: true
      		},
      		rowIndex: {
        		type: Number
      		}
		}
	}
</script>
<style scoped>
.alert{
	margin-bottom: 0;
	padding-top: 5px;
    padding-bottom: 5px;
}
</style>