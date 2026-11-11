<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog" role="document">
    		<div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <span class="product-category"><template v-if = "item.category">{{item.category.display_name}}</template> &raquo; <template v-if = "item.collection">{{item.collection.display_name}}</template><template v-if="item.colour"> &raquo; {{item.colour.display_name}}</template></span>
                </div>
      			<div class="modal-body">
      				<div class="alert alert-danger text-center text-uppercase" v-if="inMultiples && !plankSize">
                        This product can be ordered in boxes. 1 box contains {{inMultiples}}<sup>{{item.base_unit_of_measure}}</sup>
      				</div>
                    <div class="alert alert-danger text-center text-uppercase" v-if="plankSize && item.category.code != 'CARPETTILE'">
                        This Product can be ordered in planks/pieces. Plank size is {{plankSize}} {{item.base_unit_of_measure}}. 1 box contains {{planksPerBox}} planks/pieces or {{inMultiples}} {{item.base_unit_of_measure}}.
                        </sup>
                    </div>
                    <div class="alert alert-danger text-center text-uppercase" v-if="plankSize && item.category.code == 'CARPETTILE'">
                        This Product can be ordered in tiles/pieces. Tile size is {{plankSize}} {{item.base_unit_of_measure}}. 1 box contains {{planksPerBox}} tiles/pieces or {{inMultiples}} {{item.base_unit_of_measure}}.
                        </sup>
                    </div>
      				<div v-for="(stocks,stockPoint) in inventory" :key="stockPoint">
                        <p><strong>{{stockPoint}}</strong></p>
                        <hr>
                        <table class="table table-condensed table-striped">
                            <thead>
                                <tr>
                                    <th>Batch</th>
                                    <th v-if="!plankSize" style="text-align: right;">Quantity <sup v-if="!inMultiples">{{item.base_unit_of_measure}}</sup> <sup v-if="inMultiples">Box</sup></th>
                                    <th v-if="plankSize" style="text-align: right;">Quantity <sup v-if="!plankSize">{{item.base_unit_of_measure}}</sup><sup v-if="item.category.code != 'CARPETTILE'">Plank/Piece</sup><sup v-if="item.category.code == 'CARPETTILE'">Tile/Piece</sup></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(inv,index) in stocks" :key="`${inv.lot_no}_${stockPoint}`">
                                    <td>{{inv.lot_no}}</td>
                                    <td style="text-align: right;">{{inv.remaining_quantity}}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
      			</div>
    		</div>
  		</div>
	</div>
</template>
<script>
	import Vue from 'vue'
	import appMixin from '../../vuex-mixin.js'
	import {EventBus} from '../../configs/EventBus'
	import Switches from 'vue-switches'
    import VeeValidate from 'vee-validate'
    Vue.use(VeeValidate)
	export default{
		mixins:[appMixin],
		components:{
			Switches,
		},
		computed:{
            hasInventory(){
                return !_.isEmpty(this.inventory)
            },
            orderClass(){
            	if(this.item.category.allow_cl)
            		return 'col-md-4'
            	return 'col-md-8'
            },
            inMultiples(){
                if(_.isEmpty(this.item))
                    return 0
                if(this.item.colour.in_multiples && this.item.colour)
                    return this.item.colour.in_multiples
                if(this.item.collection.in_multiples)
                    return this.item.collection.in_multiples
                if(this.item.category.in_multiples)
                    return this.item.category.in_multiples
                return 0
            },
            plankSize(){
                if(_.isEmpty(this.item))
                    return 0
                if(this.item.colour.plank_size && this.item.colour)
                    return this.item.colour.plank_size
                if(this.item.collection.plank_size)
                    return this.item.collection.plank_size
                if(this.item.category.plank_size)
                    return this.item.category.plank_size
                return 0
            },
            planksPerBox(){
            	if(_.isEmpty(this.item))
                    return 0
                if(this.item.colour.plank_size && this.item.colour)
                    return parseInt(this.item.colour.in_multiples / this.item.colour.plank_size)
                if(this.item.collection.plank_size)
                    return parseInt(this.item.collection.in_multiples / this.item.collection.plank_size)
                if(this.item.category.plank_size)
                    return parseInt(this.item.category.in_multiples / this.item.category.plank_size)
                return 0
            }
		},
		data(){
			return {
				item:{

				},
				inventory:{},
                stockPoint:null,
                checkAll:false,
          //      new_approach:window.config.new_approach,
			}
		},
		methods:{
            disableField(inv){
                if(!inv.quantity)
                    return true
                if(isNaN(inv.quantity))
                    return true
                if(inv.quantity % 1)
                    return true
                if(inv.quantity > inv.remaining_quantity)
                    return true
                return false
            },
            getUnitPrice(item){
                if(item.cl)
                {
                    let price = this.item.price.unit + (this.item.price.unit * this.item.category.cl_percent / 100)
                    return price .toFixed(2)
                }
                else
                {
                    return this.item.price.unit.toFixed(2)
                }
            },
            validateQuantity(){

            },
			loadInventoryDetails(){
				let self = this
                let tempInventory = []
				EventBus.$emit('loader:show')
                let url = `companies/${this.state.user.company.id}/inventory/${this.item.id}`
                if(this.stockPoint)
                {
                    url = url + '?stock_point_id=' + this.stockPoint.id
                }
				Vue.axios.get(url)
				.then(response => {
					EventBus.$emit('loader:hide')
					_.each(response.data,function(element){
				/*		let inv = Object.assign({},element)
                        inv.remaining_quantity = (inv.remaining_quantity).toFixed(2)
						//self.inventory.push(inv)
                        tempInventory.push(inv)

*/
						let inv = Object.assign({},element)
                        let showBox = true
                        if(self.inMultiples && !self.plankSize)
                        {
                            inv.remaining_quantity = inv.remaining_quantity / self.inMultiples
                            if(inv.remaining_quantity < 1)
                            {
                                inv.remaining_quantity = 0
                                showBox = false
                            }
                            inv.remaining_quantity = Math.floor(inv.remaining_quantity)   
                        }
                        if(self.plankSize)
                        {
                            inv.remaining_quantity = inv.remaining_quantity / self.plankSize
                            if(inv.remaining_quantity < 1)
                            {
                                inv.remaining_quantity = 0
                                showBox = false
                            }
                            inv.remaining_quantity = Math.floor(inv.remaining_quantity)   
                        }
						if(self.item.price.allow_cl)
						{
							inv.cl = false
                            inv.checked = false
                            inv.remaining_quantity = inv.remaining_quantity
							inv.quantity = (inv.remaining_quantity).toFixed(2)
						}
                        if(!self.inMultiples)
                            inv.remaining_quantity = (parseFloat(inv.remaining_quantity)).toFixed(2)
                        if(!self.plankSize)
                            inv.remaining_quantity = (parseFloat(inv.remaining_quantity)).toFixed(2)
                        if(showBox)
                        tempInventory.push(inv)
					})
					this.inventory = Object.assign(_.groupBy(tempInventory,'location_code'))
				})
			},
			show(item,stockPoint){
				let self = this
				this.item = Object.assign({},item)
                if(stockPoint)
                {
                    this.stockPoint = Object.assign({},stockPoint)
                }
                else
                {
                    this.stockPoint = null
                }
				Vue.nextTick(function(){
					$(self.$refs.modal).modal('show')	
				})	
			},
		},
		mounted(){
			let self= this
			$(this.$refs.modal).on('shown.bs.modal', function (e) {
  				self.loadInventoryDetails()
			})
            $(this.$refs.modal).on('hidden.bs.modal', function (e) {
                self.inventory = []
            })
		}
	}

</script>
<style scoped>
	h1{
		font-size:16px;
		margin:0;
		color: #777;
	}
	h1 span{
		color:#555;
	}
	hr{
		margin-top: 5px;
		margin-bottom: 10px;
	}
	
	.order-details .col-md-2,.order-details .col-md-8,.order-details .col-md-6{
		padding-left: 5px;
		padding-right: 5px;
	}
	.order-details .col-md-2:first-child,.order-details .col-md-8:first-child,.order-details .col-md-6:first-child{
		padding-left: 15px;
	}
	.order-details .col-md-2:last-child,.order-details .col-md-8:last-child,.order-details .col-md-6:last-child{
		padding-right:15px;
	}
    .product-category {
        display: block;
        color: #888;
        font-size: 14px;
    }
</style>