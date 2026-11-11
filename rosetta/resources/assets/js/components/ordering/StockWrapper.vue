<template>
	<component :is="options.type" :row-data="rowData" :row-index="rowIndex"></component>
</template>
<script>
	import StockPoints from './StockPoints'
	import InTransitStockPoints from './InTransitStockPoints'
	export default{
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
  		components:{
  			StockPoints,
  			InTransitStockPoints
  		},
  		data(){
  			return {
  				stockComponent:'in-transit-stock-points'
  			}
  		},
  		watch:{
  			options(n,o){
  				this.stockComponent = this.options.type
  			}
  		}
	}
</script>