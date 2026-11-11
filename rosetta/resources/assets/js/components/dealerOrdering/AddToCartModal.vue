<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button> 
        			<span class="product-category"><template v-if = "item.category">ADD {{item.category.display_name}}</template> &raquo; <template v-if = "item.collection">{{item.collection.display_name}}</template><template v-if="item.colour"> &raquo; {{item.colour.display_name}}</template></span>
      			</div>
      			<div class="modal-body">
                     <template v-if="hasInventory">
                        <div class="alert bg-yellow" style="padding:5px;margin-bottom:5px;">
                        Enter required quantity against each Batch No. and then click on <b>ADD</b> Or <b>BLOCK</b>. Alternatively, select checkbox next to each Batch No and click on <b>Add selected to cart</b> Or <b>Block Selected</b> after entering quantity. To Add/Block all items use <b>Add All</b> Or <b>Block All</b>.</div>
                    </template>
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
                    <div class="row" v-if="hasInventory">
                        <div class="col-md-6">
                            <template v-if="intransit == true">
                                <button class="btn btn-sm btn-primary" :disabled="!selectedItems.length" @click="addToAdvanceCartMultiple">Add selected to cart</button>
                                <button class="btn btn-sm btn-danger" :disabled="!selectedItems.length" @click="blockAdvanceItemsMultiple">Block Selected</button>
                            </template>
                            <template v-if="intransit == false">
                                <button class="btn btn-sm btn-primary" :disabled="!selectedItems.length" @click="addToCartMultiple">Add selected to cart</button>
                                <button class="btn btn-sm btn-danger" :disabled="!selectedItems.length" @click="blockMultiple">Block Selected</button>
                            </template>
                        </div>
                        <div class="col-md-6 text-right">
                            <template v-if="intransit == true">
                                <button class="btn btn-sm btn-primary" @click="addAllToAdvanceCart">Add All</button>
                                <button class="btn btn-sm btn-danger" @click="blockAllAdvanceItems">Block All</button>
                            </template>
                            <template v-if="intransit == false">
                                <button class="btn btn-sm btn-primary" @click="addAll">Add All</button>
                                <button class="btn btn-sm btn-danger" @click="blockAll">Block all</button>
                            </template>
                        </div>
                    </div>
                    <hr>
                    <div v-for="(stocks,stockPoint) in inventory" :key="stockPoint">
                        <h3 style="text-align: center;padding-top: 5px;padding-bottom: 5px;"  class="bg-green">{{getStockPointName(stocks)}}</h3>
                        <div class="box box-solid box-default" v-for="(inv,index) in stocks" :key="getStockKey(inv,stockPoint)">
          					<div class="box-body">
                				<div class="row">
                					<div class="col-md-6">
                						<h1><span><input type="checkbox" v-model="inv.checked" :disabled="disableField(inv)"> BATCH NO:</span>{{inv.lot_no}}</h1>
                					</div>
                					<div class="col-md-6 text-right" v-if="!plankSize">
                						<h1><span>QUANTITY: </span>{{inv.remaining_quantity}} <sup v-if="!inMultiples">{{item.base_unit_of_measure}}</sup> <sup v-if="inMultiples">Box</sup></h1>
                					</div>
                                    <div class="col-md-6 text-right" v-if="plankSize">
                                        <h1><span>QUANTITY: </span>{{inv.remaining_quantity}} <sup v-if="item.category.code != 'CARPETTILE'">Plank/Piece</sup><sup v-if="item.category.code == 'CARPETTILE'">Tile/Piece</sup></h1>
                                    </div>
                				</div>
                				<hr>
                				<div class="row order-details">
                					<div class="col-md-2" v-if="item.price.allow_cl">
                						<switches v-model="inv.cl" theme="bootstrap" color="success"  text-enabled="CL" text-disabled="RL" @input="rlclChanged(inv,index)"></switches>
                					</div>
                					<div :class="[item.price.allow_cl ? 'col-md-6' : 'col-md-8', errors.has(`inv-quantity-${index}`) ? 'has-error' : '']" >
                						<input type="text" class="form-control" v-model="inv.quantity" :disabled="(item.price.allow_cl && !inv.cl)" v-validate="`decimal:2|max_value:${inv.remaining_quantity}`" :data-vv-name="`inv-quantity-${index}`">
                					</div>
                					<template v-if="inv.intransit == 1"> 
                                        <div class="col-md-3">
                                            <button class="btn btn-primary btn-block" @click="addToAdvanceCart(inv,stockPoint,index)" :disabled="disableField(inv)">ADD</button>
                                            <button class="btn btn-danger btn-block" @click="blockAdvanceItem(inv,stockPoint,index)" :disabled="disableField(inv)">BLOCK</button>
                                        </div>
                                    </template>
                                    <template v-if="inv.intransit != 1"> 
                                        <div class="col-md-2">
                                            <button class="btn btn-primary btn-block" @click="addToCart(inv,stockPoint,index)" :disabled="disableField(inv)">ADD</button>
                                        </div>
                    					<div class="col-md-2">
                    						<button class="btn btn-danger btn-block" @click="block(inv,stockPoint,index)" :disabled="disableField(inv)">BLOCK</button>
                    					</div>
                                    </template>
    	              			</div>
                                <div class="row" v-if="inv.intransit == 1">
                                    <div class="col-md-12">
                                        <h1 style="font-size:12px;line-height: 18px;font-weight: 600;"><span>ETA: </span>{{formatDate(inv.eta)}}</h1>
                                    </div>
                                </div>
                			</div>
                        </div>
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
		effectiveCompany(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor.company
                    if(this.dealer)
                        return this.dealer.company
                }
            },
           effectiveDisributor(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor
                }
                return null
            },
            effectiveDealer(){
                if(this.isCustomer)
                {
                    if(this.dealer)
                        return this.dealer
                }
                return null
            },
            orderClass(){
            	if(this.item.category.allow_cl)
            		return 'col-md-4'
            	return 'col-md-8'
            },
            hasInventory(){
                return !_.isEmpty(this.inventory)
            },
            unitRLPrice(){
                return this.item.price.unit.toFixed(2)
            },
            unitCLPrice(){
                let price = this.item.price.unit + (this.item.price.unit * this.item.price.cl_percent / 100)
                return price .toFixed(2)
            },
            unitPrice(){
                return this.item.price.unit.toFixed(2)
            },
            RLPrice(){
                return (this.item.price.unit - (this.item.price.unit * this.item.price.discount / 100)).toFixed(2)
            },
            CLPrice(){
                let price = this.item.price.unit + (this.item.price.unit * this.item.price.cl_percent / 100)
                return (price - (price * this.item.price.discount / 100)).toFixed(2)
            },
            price(){
                return this.item.price.unit - (this.item.price.unit * this.item.price.discount / 100)
            },
            inMultiples(){
                if(_.isEmpty(this.item))
                    return 0
                if(this.item.colour && this.item.colour.in_multiples)
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
                    return parseInt((this.item.colour.in_multiples / this.item.colour.plank_size).toFixed())
                if(this.item.collection.plank_size)
                    return parseInt((this.item.collection.in_multiples / this.item.collection.plank_size).toFixed())
                if(this.item.category.plank_size)
                    return parseInt((this.item.category.in_multiples / this.item.category.plank_size).toFixed())
                return 0
            },
            selectedItems(){
                let self = this
                let selected = []
                _.each(self.inventory,function(element){
                    _.each(element,function(element1){
                        if(element1.checked == true)
                            selected.push(element1) 
                    })
                })
                return selected
            }
		},
		data(){
			return {
				item:{},
				inventory:{},
                stockPoint:null,
                checkAll:false,
                intransit:false,
             //   new_approach:window.config.new_approach,
			}
		},
		methods:{
            formatDate(d)
            {
                return moment(d,'YYYY-MM-DD').format('DD-MM-YYYY')
            },
            getStockKey(inv,stockPoint)
            {
                let key = `${inv.lot_no}_${stockPoint}`
                if(inv.co_location)
                {
                    return `${key}_${inv.co_location.id}`
                }
                return key
            },
            getStockPointName(stocks){
                if(stocks.length)
                    return stocks[0].location_name
            },
            disableField(inv){
                if(!inv.quantity)
                    return true
                if(isNaN(inv.quantity))
                    return true
                if(this.inMultiples && parseFloat(inv.quantity) % 1)
                    return true
                if(this.plankSize && parseFloat(inv.quantity) % 1)
                    return true
                if(parseFloat(inv.quantity) > parseFloat(inv.remaining_quantity))
                    return true
                return false
            },
            toggleAll(){},
            getUnitPrice(item){
                if(item.cl)
                {
                    let price = this.item.price.unit + (this.item.price.unit * this.item.price.cl_percent / 100)
                    return price .toFixed(2)
                }
                else
                {
                    return this.item.price.unit.toFixed(2)
                }
            },
            blockAll(){
                let self = this
                swal({
                    title: 'Block all items?',
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    },
                })
                .then(value => {
                    if(!value)
                        return
                    let input = []
                    _.each(self.inventory,function(element){
                        _.each(element,function(element1){
                            let item = {
                                product_id : self.item.id,
                                batch_no : element1.lot_no,
                                order_quantity : element1.remaining_quantity,
                                distributor_id : self.dealer.distributor_id,//#901
                                super_stockist_id : (element1.location_source=="Super Stockist")?self.dealer.distributor.super_stockist_id:null,
                                stock_point_code : element1.location_code,
                                stock_point_source : element1.location_source
                            }
                            if(element1.co_location)
                            {
                                //item.distributor_id = element1.co_location.id #901
                                item.super_stockist_id = element1.co_location.id
                            }
                            if(self.inMultiples && !self.plankSize)
                            {
                                item.order_quantity = item.order_quantity * self.inMultiples
                            }
                            if(self.plankSize)
                            {
                                item.order_quantity = item.order_quantity * self.plankSize
                            }
                            item.rate_type = 'RL';   

                            item.unit_price = parseFloat(self.getUnitPrice(element));
                            item.discount = self.item.price.discount
                            input.push(item)
                        })
                    })
                    EventBus.$emit('loader:show')
                    let url = `companies/${self.dealer.company.id}/dealers/${self.dealer.id}/block_items`
                    Vue.axios.post(url,{block_items:input})
                    .then(response => {
                        EventBus.$emit('loader:hide')
                        EventBus.$emit('blocked:changed',response.data)
                        EventBus.$emit('blocked:added')//#718
                        swal({
                            title:"All items blocked",
                            icon:"success"
                        })
                        .then(value => {
                            $(self.$refs.modal).modal('hide')
                        })
                    })
                    .catch(error => {
                        if(error.response.status == 412)
                        {
                            swal({
                                    title: "Cooling down!",
                                    text:"You cannot block this item till " + moment.unix(error.response.data.until).format('ddd, MMM Do, YYYY hh:mm A'),
                                    icon:'warning'
                                })
                            .then(value => {
                                EventBus.$emit('loader:hide')
                            })
                        }
                        else
                        {
                            EventBus.$emit('loader:hide')
                        }
                    })
                })
            },
            blockAllAdvanceItems(){
                let self = this
                swal({
                    title: 'Block all items?',
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    },
                })
                .then(value => {
                    if(!value)
                        return
                    let input = []
                    _.each(self.inventory,function(element){
                        _.each(element,function(element1){
                            let item = {
                                product_id : self.item.id,
                                batch_no : element1.lot_no,
                                order_quantity : element1.remaining_quantity,
                                stock_point_code : element1.location_code,
                                stock_point_source : element1.location_source,
                                in_transit : element1.intransit,
                                eta: element1.eta ? moment(element1.eta).format('X') : null,
                                intransit_po: element1.lot_no
                            }
                            if(self.inMultiples && !self.plankSize)
                            {
                                item.order_quantity = item.order_quantity * self.inMultiples
                            }
                            if(self.plankSize)
                            {
                                item.order_quantity = item.order_quantity * self.plankSize
                            }
                            item.rate_type = 'RL';   

                            item.unit_price = parseFloat(self.getUnitPrice(element1));
                            item.discount = self.item.price.discount
                            if(self.item.price.onlineDiscount)
                                item.onlineDiscount = self.item.price.onlineDiscount
                            else
                                item.onlineDiscount = 0
                            input.push(item)
                        })
                    })
                    EventBus.$emit('loader:show')
                    let url = `companies/${self.dealer.company.id}/dealers/${self.dealer.id}/block_advance_items`
                    Vue.axios.post(url,{block_items:input})
                    .then(response => {
                        EventBus.$emit('loader:hide')
                        EventBus.$emit('advance_blocked:changed',response.data)//#717
                        EventBus.$emit('advance_blocked:added')//#717
                        swal({
                            title:"All items blocked",
                            icon:"success"
                        })
                        .then(value => {
                            $(self.$refs.modal).modal('hide')
                        })
                    })
                    .catch(error => {
                        if(error.response.status == 412)
                        {
                            swal({
                                    title: "Cooling down!",
                                    text:"You cannot block this item till " + moment.unix(error.response.data.until).format('ddd, MMM Do, YYYY hh:mm A'),
                                    icon:'warning'
                                })
                            .then(value => {
                                EventBus.$emit('loader:hide')
                            })
                        }
                        else
                        {
                            EventBus.$emit('loader:hide')
                        }
                    })
                })
            },
            addAll(){
                let self = this
                swal({
                    title: 'Add all items to cart?',
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    },
                })
                .then(value => {
                    if(!value)
                        return
                    let input = []
                    _.each(self.inventory,function(element){
                        _.each(element,function(element1){
                            let item = {
                                product_id : self.item.id,
                                batch_no : element1.lot_no,
                                order_quantity : element1.remaining_quantity,
                                distributor_id : self.dealer.distributor_id,
                                super_stockist_id : (element1.location_source=="Super Stockist")?self.dealer.distributor.super_stockist_id:null,
                                stock_point_code : element1.location_code,
                                stock_point_source : element1.location_source
                            }
                            if(element1.co_location)
                            {
                               // item.distributor_id = element1.co_location.id
                                item.super_stockist_id = element1.co_location.id
                            }
                     /*       if(self.inMultiples)
                            {
                                item.order_quantity = item.order_quantity * self.inMultiples
                            }*/
                            if(self.inMultiples && !self.plankSize)
                            {
                                item.order_quantity = item.order_quantity * self.inMultiples
                            }
                            if(self.plankSize)
                            {
                                item.order_quantity = item.order_quantity * self.plankSize
                            }
                            item.rate_type = 'RL';   

                            item.unit_price = parseFloat(self.getUnitPrice(element));
                            item.discount = self.item.price.discount
                            input.push(item)
                        })
                    })
                    EventBus.$emit('loader:show')
                    let url = `companies/${self.dealer.company.id}/dealers/${self.dealer.id}/cart_items`
                    Vue.axios.post(url,{cart_items:input})
                    .then(response => {
                        EventBus.$emit('loader:hide')
                        EventBus.$emit('cart:changed',response.data)
                        EventBus.$emit('cart:added')//#718
                        swal({
                            title:"All items Added to cart",
                            icon:"success"
                        })
                        .then(value => {
                            $(self.$refs.modal).modal('hide')
                        })
                    })
                    .catch(error => {
                        EventBus.$emit('loader:hide')
                    })
                })
            },
            addAllToAdvanceCart (){
                let self = this
                swal({
                    title: 'Add all items to cart?',
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    },
                })
                .then(value => {
                    if(!value)
                        return
                    let input = []
                    _.each(self.inventory,function(element){
                        _.each(element,function(element1){
                            let item = {
                                product_id : self.item.id,
                                batch_no : element1.lot_no,
                                order_quantity : element1.remaining_quantity,
                                stock_point_code : element1.location_code,
                                stock_point_source : element1.location_source,
                                in_transit : element1.intransit,
                                eta: element1.eta ? moment(element1.eta).format('X') : null,
                                intransit_po: element1.lot_no
                            }
                            if(element1.co_location)
                            {
                                item.distributor_id = element1.co_location.id
                            }
                            if(self.inMultiples && !self.plankSize)
                            {
                                item.order_quantity = item.order_quantity * self.inMultiples
                            }
                            if(self.plankSize)
                            {
                                item.order_quantity = item.order_quantity * self.plankSize
                            }
                            item.rate_type = 'RL';   

                            item.unit_price = parseFloat(self.getUnitPrice(element1));
                            item.discount = self.item.price.discount
                            if(self.item.price.onlineDiscount)
                                item.discount = self.item.price.onlineDiscount
                            else
                                item.onlineDiscount = 0
                            input.push(item)
                        })
                    })
                    EventBus.$emit('loader:show')
                    let url = `companies/${self.dealer.company.id}/dealers/${self.dealer.id}/advance_cart_items`
                    Vue.axios.post(url,{advance_items:input})
                    .then(response => {
                        EventBus.$emit('loader:hide')
                        EventBus.$emit('advance_cart:changed',response.data)
                        EventBus.$emit('advance_cart:added')
                        swal({
                            title:"All items Added to Advance Cart",
                            icon:"success"
                        })
                        .then(value => {
                            $(self.$refs.modal).modal('hide')
                        })
                    })
                    .catch(error => {
                        EventBus.$emit('loader:hide')
                    })
                })
            },
            blockMultiple(){
                let self = this
                let itemsValid = true
                _.each(this.selectedItems,function(element){
                    if(!element.quantity)
                    {
                        itemsValid = false
                        return
                    }
                    if(parseFloat(element.quantity) > parseFloat(element.remaining_quantity))
                    {
                        itemsValid = false
                        return
                    }
                })
                if(!itemsValid)
                {
                    swal({
                        title:"Error",
                        text: 'Make sure that all items have valid quantity',
                        icon: 'error'
                    })
                    return
                }
                let input = []
                _.each(this.selectedItems,function(element){
                    let item = {
                        product_id : self.item.id,
                        batch_no : element.lot_no,
                        order_quantity : element.quantity,
                        distributor_id : self.dealer.distributor_id,//#901
                        super_stockist_id : (element.location_source=="Super Stockist")?self.dealer.distributor.super_stockist_id:null,
                        stock_point_code : element.location_code,
                        stock_point_source : element.location_source
                    }
                    if(element.co_location)
                    {
                        //item.distributor_id = element.co_location.id
                        item.super_stockist_id = element.co_location.id
                    }
                 /*   if(self.inMultiples)
                    {
                        item.order_quantity = element.quantity * self.inMultiples
                    }*/
                    if(self.inMultiples && !self.plankSize)
                    {
                        item.order_quantity = item.order_quantity * self.inMultiples
                    }
                    if(self.plankSize)
                    {
                        item.order_quantity = item.order_quantity * self.plankSize
                    }
                    if(element.cl)
                    {
                        item.rate_type = 'CL';
                    }
                    else
                    {  
                        item.rate_type = 'RL';   
                    }
                    item.unit_price = parseFloat(self.getUnitPrice(element));
                    item.discount = self.item.price.discount
                    input.push(item)
                })
                EventBus.$emit('loader:show')
                let url = `companies/${this.dealer.company.id}/dealers/${this.dealer.id}/block_items`
                Vue.axios.post(url,{block_items:input})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    EventBus.$emit('blocked:changed',response.data)
                    EventBus.$emit('blocked:added')//#718
                    _.each(this.selectedItems,function(element){
                        let stockPoint = element.location_code
                        if(element.quantity == element.remaining_quantity)
                        {
                            let index = _.findIndex(self.inventory[stockPoint],{lot_no:element.lot_no})
                            if(index>=0)
                            {
                                self.inventory[stockPoint].splice(index,1)
                            }
                        }
                        else
                        {
                            element.remaining_quantity -= element.quantity
                            if(self.item.price.allow_cl)
                            {
                                element.quantity = element.remaining_quantity.toFixed(2)
                            }
                            else
                            {
                                element.quantity = ""
                            }
                            element.remaining_quantity = element.remaining_quantity.toFixed(2)
                            element.cl = false
                            if(self.inMultiples || self.plankSize)
                            {
                                if(element.remaining_quantity < 1)
                                {
                                    element.quantity = 0
                                    element.remaining_quantity = 0
                                    self.inventory[stockPoint].splice(index,1)
                                }
                                else
                                {
                                   // element.remaining_quantity = Math.floor(element.remaining_quantity) // #739
                                    element.remaining_quantity = parseFloat(parseFloat(element.remaining_quantity).toFixed(2))
                                }
                            }
                        }
                        if(! self.inventory[stockPoint].length)
                        {
                            delete self.inventory[stockPoint]
                        }
                    })
                    swal({
                        title:"Items Blocked",
                        icon:"success"
                    })
                    .then(value => {
                        if(_.isEmpty(self.inventory))
                        {
                            $(self.$refs.modal).modal('hide');
                        }
                        _.each(self.inventory,function(element){
                            _.each(element,function(element1){
                                if(element1.checked == true)
                                    element1.checked = false
                            })
                        })
                    })
                })
                .catch(error => {
                    if(error.response.status == 412)
                    {
                        swal({
                                title: "Cooling down!",
                                    text:"You cannot block this item till " + moment.unix(error.response.data.until).format('ddd, MMM Do, YYYY hh:mm A'),
                                    icon:'warning'
                            })
                        .then(value => {
                            EventBus.$emit('loader:hide')
                        })
                    }
                    else
                    {
                        EventBus.$emit('loader:hide')
                    }
                })
            },
            blockAdvanceItemsMultiple(){
                let self = this
                let itemsValid = true
                _.each(this.selectedItems,function(element){
                    if(!element.quantity)
                    {
                        itemsValid = false
                        return
                    }
                    if(parseFloat(element.quantity) > parseFloat(element.remaining_quantity))
                    {
                        itemsValid = false
                        return
                    }
                })
                if(!itemsValid)
                {
                    swal({
                        title:"Error",
                        text: 'Make sure that all items have valid quantity',
                        icon: 'error'
                    })
                    return
                }
                let input = []
                _.each(this.selectedItems,function(element){
                    let item = {
                        product_id : self.item.id,
                        batch_no : element.lot_no,
                        order_quantity : element.quantity,
                        stock_point_code : element.location_code,
                        stock_point_source : element.location_source,
                        in_transit : element.intransit,
                        eta: element.eta ? moment(element.eta).format('X') : null,
                        intransit_po: element.lot_no
                    }
                    if(self.inMultiples && !self.plankSize)
                    {
                        item.order_quantity = item.order_quantity * self.inMultiples
                    }
                    if(self.plankSize)
                    {
                        item.order_quantity = item.order_quantity * self.plankSize
                    }
                    if(element.cl)
                    {
                        item.rate_type = 'CL';
                    }
                    else
                    {  
                        item.rate_type = 'RL';   
                    }
                    item.unit_price = parseFloat(self.getUnitPrice(element));
                    item.discount = self.item.price.discount
                    if(self.item.price.onlineDiscount)
                        item.onlineDiscount = self.item.price.onlineDiscount
                    else
                        item.onlineDiscount = 0
                    input.push(item)
                })
                EventBus.$emit('loader:show')
                let url = `companies/${this.dealer.company.id}/dealers/${this.dealer.id}/block_advance_items`
                                
                Vue.axios.post(url,{block_items:input})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    EventBus.$emit('advance_blocked:changed',response.data)//#717
                    EventBus.$emit('advance_blocked:added')//#717
                    _.each(this.selectedItems,function(element){
                        let stockPoint = element.location_code
                        if(element.quantity == element.remaining_quantity)
                        {
                            let index = _.findIndex(self.inventory[stockPoint],{lot_no:element.lot_no})
                            if(index>=0)
                            {
                                self.inventory[stockPoint].splice(index,1)
                            }
                        }
                        else
                        {
                            element.remaining_quantity -= element.quantity
                            if(self.item.price.allow_cl)
                            {
                                element.quantity = element.remaining_quantity.toFixed(2)
                            }
                            else
                            {
                                element.quantity = ""
                            }
                            element.remaining_quantity = element.remaining_quantity.toFixed(2)
                            element.cl = false
                            if(self.inMultiples || self.plankSize)
                            {
                                if(element.remaining_quantity < 1)
                                {
                                    element.quantity = 0
                                    element.remaining_quantity = 0
                                    self.inventory[stockPoint].splice(index,1)
                                }
                                else
                                {
                                 //   element.remaining_quantity = Math.floor(element.remaining_quantity) //#739
                                    element.remaining_quantity = parseFloat(parseFloat(element.remaining_quantity).toFixed(2))
                                }
                            }
                        }
                        if(! self.inventory[stockPoint].length)
                        {
                            delete self.inventory[stockPoint]
                        }
                    })
                    swal({
                        title:"Items Blocked",
                        icon:"success"
                    })
                    .then(value => {
                        if(_.isEmpty(self.inventory))
                        {
                            $(self.$refs.modal).modal('hide');
                        }
                        _.each(self.inventory,function(element){
                            _.each(element,function(element1){
                                if(element1.checked == true)
                                    element1.checked = false
                            })
                        })
                    })
                })
                .catch(error => {
                    if(error.response.status == 412)
                    {
                        swal({
                                title: "Cooling down!",
                                text:"You cannot block this item till " + moment.unix(error.response.data.until).format('ddd, MMM Do, YYYY hh:mm A'),
                                icon:'warning'
                            })
                        .then(value => {
                            EventBus.$emit('loader:hide')
                        })
                    }
                    else
                    {
                        EventBus.$emit('loader:hide')
                    }
                })
            },
            block(item,stockPoint,index){
                let self = this
                let input = {
                    product_id : self.item.id,
                    batch_no : item.lot_no,
                    order_quantity : item.quantity,
                    distributor_id : (item.location_source=="Distributor")?item.location_source_id:this.dealer.distributor_id,//#901
                    super_stockist_id : (item.location_source=="Super Stockist")?item.location_source_id:this.dealer.distributor.super_stockist_id,
                    stock_point_code : stockPoint,
                    stock_point_source : item.location_source
                }
                if(item.co_location)
                {
                    //input.distributor_id = item.co_location.id
                    input.super_stockist_id = item.co_location.id//#901
                }
                if(this.inMultiples && !this.plankSize)
                {
                    input.order_quantity = item.quantity * this.inMultiples
                }
                if(this.plankSize)
                {
                    input.order_quantity = item.quantity * this.plankSize
                }
                if(item.cl)
                {
                    input.rate_type = 'CL';
                }
                else
                {
                    input.rate_type = 'RL';   
                }
                input.unit_price = parseFloat(this.getUnitPrice(item));
                input.discount = this.item.price.discount
                EventBus.$emit('loader:show')
                let url = `companies/${this.dealer.company.id}/dealers/${this.dealer.id}/block_items`
                Vue.axios.post(url,{block_items:[input]})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    EventBus.$emit('blocked:changed',response.data)
                    EventBus.$emit('blocked:added')//#718
                    if(item.quantity == item.remaining_quantity)
                    {
                        this.inventory[stockPoint].splice(index,1)
                        if(! this.inventory[stockPoint].length)
                        {
                            delete this.inventory[stockPoint]
                        }
                    }
                    else
                    {   
                        item.remaining_quantity -= item.quantity
                        if(this.item.price.allow_cl)
                        {
                            item.quantity = item.remaining_quantity.toFixed(2)
                        }
                        else
                        {
                            item.quantity = ""
                        }
                        item.remaining_quantity = item.remaining_quantity.toFixed(2)
                        item.cl = false
                        if(this.inMultiples || this.plankSize)
                        {
                            if(item.remaining_quantity < 1)
                            {
                                item.quantity = 0
                                item.remaining_quantity = 0
                                this.inventory[stockPoint].splice(index,1)
                                if(! this.inventory[stockPoint].length)
                                {
                                    delete this.inventory[stockPoint]
                                }
                            }
                            else
                            {
                               // item.remaining_quantity = Math.floor(item.remaining_quantity) // #739
                                item.remaining_quantity = parseFloat(parseFloat(item.remaining_quantity).toFixed(2))
                            }
                        }
                    }
                    swal({
                        title:"Item blocked",
                        icon:"success"
                    })
                    .then(value => {
                        if(_.isEmpty(self.inventory))
                        {
                            $(self.$refs.modal).modal('hide');
                        }
                    })
                })
                .catch(error => {
                    if(error.response.status == 412)
                    {
                        swal({
                                title: "Cooling down!",
                                text:"You cannot block this item till " + moment.unix(error.response.data.until).format('ddd, MMM Do, YYYY hh:mm A'),
                                icon:'warning'
                            })
                        .then(value => {
                            EventBus.$emit('loader:hide')
                        })
                    }
                    else
                    {
                        EventBus.$emit('loader:hide')
                    }
                })
            },
            blockAdvanceItem(item,stockPoint,index){
                let self = this
                let input = {
                    product_id : this.item.id,
                    batch_no : item.lot_no,
                    order_quantity : item.quantity,
                    stock_point_code : stockPoint,
                    stock_point_source : item.location_source,
                    in_transit : item.intransit,
                    eta: item.eta ? moment(item.eta).format('X') : null,
                    intransit_po: item.lot_no
                }
                if(this.inMultiples && !this.plankSize)
                {
                    input.order_quantity = item.quantity * this.inMultiples
                }
                if(this.plankSize)
                {
                    input.order_quantity = item.quantity * this.plankSize
                }
                if(item.cl)
                {
                    input.rate_type = 'CL';
                }
                else
                {
                    input.rate_type = 'RL';   
                }
                input.unit_price = parseFloat(this.getUnitPrice(item));
                input.discount = this.item.price.discount
                if(this.item.price.onlineDiscount)
                    input.onlineDiscount = this.item.price.onlineDiscount
                else
                    input.onlineDiscount = 0
                EventBus.$emit('loader:show')
                let url = `companies/${this.dealer.company.id}/dealers/${this.dealer.id}/block_advance_items`
                Vue.axios.post(url,{block_items:[input]})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    EventBus.$emit('advance_blocked:changed',response.data)//#717
                    EventBus.$emit('advance_blocked:added')//#717
                    if(item.quantity == item.remaining_quantity)
                    {
                        this.inventory[stockPoint].splice(index,1)
                        if(! this.inventory[stockPoint].length)
                        {
                            delete this.inventory[stockPoint]
                        }
                    }
                    else
                    {   
                        item.remaining_quantity -= item.quantity
                        if(this.item.price.allow_cl)
                        {
                            item.quantity = item.remaining_quantity.toFixed(2)
                        }
                        else
                        {
                            item.quantity = ""
                        }
                        item.remaining_quantity = item.remaining_quantity.toFixed(2)
                        item.cl = false
                        if(this.inMultiples || this.plankSize)
                        {
                            if(item.remaining_quantity < 1)
                            {
                                item.quantity = 0
                                item.remaining_quantity = 0
                                this.inventory[stockPoint].splice(index,1)
                                if(! this.inventory[stockPoint].length)
                                {
                                    delete this.inventory[stockPoint]
                                }
                            }
                            else
                            {
                             //   item.remaining_quantity = Math.floor(item.remaining_quantity) //#739
                                item.remaining_quantity = parseFloat(parseFloat(item.remaining_quantity).toFixed(2))
                            }
                        }
                    }
                    swal({
                        title:"Item blocked",
                        icon:"success"
                    })
                    .then(value => {
                        if(_.isEmpty(self.inventory))
                        {
                            $(self.$refs.modal).modal('hide');
                        }
                    })
                })
                .catch(error => {
                    if(error.response.status == 412)
                    {
                        swal({
                                title: "Cooling down!",
                                text:"You cannot block this item till " + moment.unix(error.response.data.until).format('ddd, MMM Do, YYYY hh:mm A'),
                                icon:'warning'
                            })
                        .then(value => {
                            EventBus.$emit('loader:hide')
                        })
                    }
                    else
                    {
                        EventBus.$emit('loader:hide')
                    }
                })
            },
            addToAdvanceCartMultiple(){
                let self = this
                let itemsValid = true
                _.each(this.selectedItems,function(element){
                    if(!element.quantity)
                    {
                        itemsValid = false
                        return
                    }
                })
                if(!itemsValid)
                {
                    swal({
                        title:"Error",
                        text: 'Make sure that all items have valid quantity',
                        icon: 'error'
                    })
                    return
                }
                let input = []
                _.each(this.selectedItems,function(element){
                    let item = {
                        product_id : self.item.id,
                        batch_no : element.lot_no,
                        order_quantity : element.quantity,
                        stock_point_code : element.location_code,
                        stock_point_source : element.location_source,
                        in_transit : element.intransit,
                        eta: element.eta ? moment(element.eta).format('X') : null,
                        intransit_po: element.lot_no
                    }
                    if(element.co_location)
                    {
                        item.distributor_id = element.co_location.id
                    }
                    if(self.inMultiples && !self.plankSize)
                    {
                        item.order_quantity = element.quantity * self.inMultiples
                    }
                    if(self.plankSize)
                    {
                        item.order_quantity = element.quantity * self.plankSize
                    }
                    if(element.cl)
                    {
                        item.rate_type = 'CL';
                    }
                    else
                    {  
                        item.rate_type = 'RL';   
                    }
                    item.unit_price = parseFloat(self.getUnitPrice(element));
                    item.discount = self.item.price.discount
                    if(self.item.price.onlineDiscount)
                        item.onlineDiscount = self.item.price.onlineDiscount
                    else
                        item.onlineDiscount = 0
                    input.push(item)
                })
                EventBus.$emit('loader:show')
                let url = `companies/${self.dealer.company.id}/dealers/${self.dealer.id}/advance_cart_items`
                Vue.axios.post(url,{advance_items:input})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    EventBus.$emit('advance_cart:changed',response.data)
                    EventBus.$emit('advance_cart:added')
                    _.each(this.selectedItems,function(element){
                        let stockPoint = element.location_code
                        if(element.quantity == element.remaining_quantity)
                        {
                            let index = _.findIndex(self.inventory[stockPoint],{lot_no:element.lot_no})
                            if(index>=0)
                            {
                                self.inventory[stockPoint].splice(index,1)
                                if(! self.inventory[stockPoint].length)
                                {
                                    delete self.inventory[stockPoint]
                                }
                            }
                        }
                        else
                        {
                            element.remaining_quantity -= element.quantity
                            if(self.item.price.allow_cl)
                            {
                                element.quantity = element.remaining_quantity.toFixed(2)
                            }
                            else
                            {
                                element.quantity = ""
                            }
                            element.remaining_quantity = element.remaining_quantity.toFixed(2)
                            element.cl = false
                            if(self.inMultiples || self.plankSize)
                            {
                                if(element.remaining_quantity < 1)
                                {
                                    element.quantity = 0
                                    element.remaining_quantity = 0
                                    self.inventory[stockPoint].splice(index,1)
                                    if(! self.inventory[stockPoint].length)
                                    {
                                        delete self.inventory[stockPoint]
                                    }
                                }
                                else
                                {
                                   // element.remaining_quantity = Math.floor(element.remaining_quantity) //#739
                                    element.remaining_quantity = parseFloat(parseFloat(element.remaining_quantity).toFixed(2))
                                }
                            }
                        }
                    })
                    swal({
                        title:"Added to Advance Cart",
                        icon:"success"
                    })
                    .then(value => {
                        if(_.isEmpty(self.inventory))
                        {
                            $(self.$refs.modal).modal('hide');
                        }
                        _.each(this.inventory,function(element){
                            _.each(element,function(element1){
                                if(element1.checked == true)
                                    element1.checked = false
                            })
                        })
                    })
                })
                .catch(error => {
                    EventBus.$emit('loader:hide')
                })
            
            },
            addToCartMultiple(){
                let self = this
                let itemsValid = true
                _.each(this.selectedItems,function(element){
                    if(!element.quantity)
                    {
                        itemsValid = false
                        return
                    }
                })
                if(!itemsValid)
                {
                    swal({
                        title:"Error",
                        text: 'Make sure that all items have valid quantity',
                        icon: 'error'
                    })
                    return
                }
                let input = []
                _.each(this.selectedItems,function(element){
                    let item = {
                        product_id : self.item.id,
                        batch_no : element.lot_no,
                        order_quantity : element.quantity,
                        distributor_id : self.dealer.distributor_id,
                        super_stockist_id : (element.location_source=="Super Stockist")?self.dealer.distributor.super_stockist_id:null,
                        stock_point_code : element.location_code,
                        stock_point_source : element.location_source
                    }
                    if(element.co_location)
                    {
                     //   item.distributor_id = element.co_location.id
                        item.super_stockist_id = element.co_location.id
                    }
                    if(self.inMultiples && !self.plankSize)
                    {
                        item.order_quantity = element.quantity * self.inMultiples
                    }
                    if(self.plankSize)
                    {
                        item.order_quantity = element.quantity * self.plankSize
                    }
                    if(element.cl)
                    {
                        item.rate_type = 'CL';
                    }
                    else
                    {  
                        item.rate_type = 'RL';   
                    }
                    item.unit_price = parseFloat(self.getUnitPrice(element));
                    item.discount = self.item.price.discount
                    input.push(item)
                })
                EventBus.$emit('loader:show')
                let url = `companies/${self.dealer.company.id}/dealers/${self.dealer.id}/cart_items`
                Vue.axios.post(url,{cart_items:input})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    EventBus.$emit('cart:changed',response.data)
                    EventBus.$emit('cart:added')//#718
                    _.each(this.selectedItems,function(element){
                        let stockPoint = element.location_code
                        if(element.quantity == element.remaining_quantity)
                        {
                            let index = _.findIndex(self.inventory[stockPoint],{lot_no:element.lot_no})
                            if(index>=0)
                            {
                                self.inventory[stockPoint].splice(index,1)
                                if(! self.inventory[stockPoint].length)
                                {
                                    delete self.inventory[stockPoint]
                                }
                            }
                        }
                        else
                        {
                            element.remaining_quantity -= element.quantity
                            if(self.item.price.allow_cl)
                            {
                                element.quantity = element.remaining_quantity.toFixed(2)
                            }
                            else
                            {
                                element.quantity = ""
                            }
                            element.remaining_quantity = element.remaining_quantity.toFixed(2)
                            element.cl = false
                            if(self.inMultiples || self.plankSize)
                            {
                                if(element.remaining_quantity < 1)
                                {
                                    element.quantity = 0
                                    element.remaining_quantity = 0
                                    self.inventory[stockPoint].splice(index,1)
                                    if(! self.inventory[stockPoint].length)
                                    {
                                        delete self.inventory[stockPoint]
                                    }
                                }
                                else
                                {
                                   // element.remaining_quantity = Math.floor(element.remaining_quantity) // #739
                                   element.remaining_quantity = parseFloat(parseFloat(element.remaining_quantity).toFixed(2))
                                }
                            }
                        }
                    })
                    swal({
                        title:"Added to cart",
                        icon:"success"
                    })
                    .then(value => {
                        if(_.isEmpty(self.inventory))
                        {
                            $(self.$refs.modal).modal('hide');
                        }
                        _.each(this.inventory,function(element){
                            _.each(element,function(element1){
                                if(element1.checked == true)
                                    element1.checked = false
                            })
                        })
                    })
                })
                .catch(error => {
                    EventBus.$emit('loader:hide')
                })
            },
            addToCart(item,stockPoint,index){
                let self = this
                let selfSSId = (self.dealer.distributor.super_stockist_id && item.location_source=="Super Stockist")?self.dealer.distributor.super_stockist_id:'';
                if(item.location_source_id>0 && item.location_source_id != selfSSId){
                    var superstockistId = item.location_source_id;
                }else if(selfSSId){
                    var superstockistId = selfSSId;
                }else{
                    var superstockistId = '';
                }
                let input = {
                    product_id : self.item.id,
                    batch_no : item.lot_no,
                    order_quantity : item.quantity,
                    distributor_id : self.dealer.distributor_id,
                    stock_point_code : stockPoint,
                    stock_point_source : item.location_source
                }

                if(superstockistId>0 && item.location_source=="Super Stockist")
                {
                    input.super_stockist_id = superstockistId;
                }

                if(superstockistId>0 && item.location_source=="Distributor")
                {
                    input.distributor_id = superstockistId;
                }

                if(item.co_location)
                {
                    //input.super_stockist_id = item.co_location.id
                }
                if(this.inMultiples && !this.plankSize)
                {
                    input.order_quantity = item.quantity * this.inMultiples
                }
                if(this.plankSize)
                {
                    input.order_quantity = item.quantity * this.plankSize
                }
                if(item.cl)
                {
                    input.rate_type = 'CL';
                }
                else
                {
                    input.rate_type = 'RL';   
                }
                input.unit_price = parseFloat(this.getUnitPrice(item));
                input.discount = this.item.price.discount
                EventBus.$emit('loader:show')
                let url = `companies/${this.dealer.company.id}/dealers/${this.dealer.id}/cart_items`
                Vue.axios.post(url,{cart_items:[input]})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    EventBus.$emit('cart:changed',response.data)
                    EventBus.$emit('cart:added')//#718
                    if(item.quantity == item.remaining_quantity)
                    {
                        this.inventory[stockPoint].splice(index,1)
                        if(! this.inventory[stockPoint].length)
                        {
                            delete this.inventory[stockPoint]
                        }
                    }
                    else
                    {   
                        item.remaining_quantity -= item.quantity
                        if(this.item.price.allow_cl)
                        {
                            item.quantity = item.remaining_quantity.toFixed(2)
                        }
                        else
                        {
                            item.quantity = ""
                        }
                        item.remaining_quantity = item.remaining_quantity.toFixed(2)
                        item.cl = false
                        if(this.inMultiples || this.plankSize)
                        {
                            if(item.remaining_quantity < 1)
                            {
                                item.quantity = 0
                                item.remaining_quantity = 0
                                this.inventory[stockPoint].splice(index,1)
                                if(! this.inventory[stockPoint].length)
                                {
                                    delete this.inventory[stockPoint]
                                }
                            }
                            else
                            {
                               // item.remaining_quantity = Math.floor(item.remaining_quantity) // #739
                               item.remaining_quantity = parseFloat(parseFloat(item.remaining_quantity).toFixed(2))
                            }
                        }
                    }
                    swal({
                        title:"Added to cart",
                        icon:"success"
                    })
                    .then(value => {
                        if(_.isEmpty(self.inventory))
                        {
                            $(self.$refs.modal).modal('hide');
                        }
                    })
                })
                .catch(error => {
                    EventBus.$emit('loader:hide')
                })
            },
            addToAdvanceCart(item,stockPoint,index){
                let self = this
                let input = {
                    product_id : this.item.id,
                    batch_no : item.lot_no,
                    order_quantity : item.quantity,
                    stock_point_code : stockPoint,
                    stock_point_source : item.location_source,
                    in_transit : item.intransit,
                    eta: item.eta ? moment(item.eta).format('X') : null,
                    intransit_po: item.lot_no
                }
           /*     if(item.intransit == 1)
                {
                    input.intransit_po = item.lot_no
                }*/
                if(item.co_location)
                {
                    input.distributor_id = item.co_location.id
                }
                if(this.inMultiples && !this.plankSize)
                {
                    input.order_quantity = item.quantity * this.inMultiples
                }
                if(this.plankSize)
                {
                    input.order_quantity = item.quantity * this.plankSize
                }
                if(item.cl)
                {
                    input.rate_type = 'CL';
                }
                else
                {
                    input.rate_type = 'RL';   
                }
                input.unit_price = parseFloat(this.getUnitPrice(item));
                input.discount = this.item.price.discount
                if(this.item.price.onlineDiscount)
                {
                    input.onlineDiscount = this.item.price.onlineDiscount
                }
                else
                {
                    input.onlineDiscount = 0
                }
                EventBus.$emit('loader:show')
                let url = `companies/${this.dealer.company.id}/dealers/${this.dealer.id}/advance_cart_items`
                Vue.axios.post(url,{advance_items:[input]})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    EventBus.$emit('advance_cart:changed',response.data)
                    EventBus.$emit('advance_cart:added')
                    if(item.quantity == item.remaining_quantity)
                    {
                        this.inventory[stockPoint].splice(index,1)
                        if(! this.inventory[stockPoint].length)
                        {
                            delete this.inventory[stockPoint]
                        }
                    }
                    else
                    {   
                        item.remaining_quantity -= item.quantity
                        if(this.item.price.allow_cl)
                        {
                            item.quantity = item.remaining_quantity.toFixed(2)
                        }
                        else
                        {
                            item.quantity = ""
                        }
                        item.remaining_quantity = item.remaining_quantity.toFixed(2)
                        item.cl = false
                        if(this.inMultiples || this.plankSize)
                        {
                            if(item.remaining_quantity < 1)
                            {
                                item.quantity = 0
                                item.remaining_quantity = 0
                                this.inventory[stockPoint].splice(index,1)
                                if(! this.inventory[stockPoint].length)
                                {
                                    delete this.inventory[stockPoint]
                                }
                            }
                            else
                            {
                               // item.remaining_quantity = Math.floor(item.remaining_quantity)//#739 
                                item.remaining_quantity = parseFloat(parseFloat(item.remaining_quantity).toFixed(2))
                            }
                        }
                    }
                    swal({
                        title:"Added to Advance Cart",
                        icon:"success"
                    })
                    .then(value => {
                        if(_.isEmpty(self.inventory))
                        {
                            $(self.$refs.modal).modal('hide');
                        }
                    })
                })
                .catch(error => {
                    EventBus.$emit('loader:hide')
                })
            },
            rlclChanged(inv,index){
                this.errors.remove(`inv-quantity-${index}`)
             //   console.log(inv)
                if(inv.cl)
                {
                    inv.quantity = ""
                }
                else
                {
                    inv.quantity = inv.remaining_quantity
                }
            },
			loadInventoryDetails(){
				let self = this
				EventBus.$emit('loader:show')
                let tempInventory = []
                let url  = ''
                if(this.effectiveDealer)
                {
                if(this.intransit == true)
                    url = `companies/${this.dealer.company.id}/distributors/${this.dealer.distributor_id}/intransit_inventory/${this.item.id}?cust_type=DEALER&dealer=${this.dealer.id}`
                else
                    url = `companies/${this.dealer.company.id}/distributors/${this.dealer.distributor_id}/inventory/${this.item.id}?cust_type=DEALER&dealer=${this.dealer.id}`
                }

                if(this.effectiveDisributor)
                {
                    if(this.intransit == true)
                        url = `companies/${this.effectiveCompany.id}/intransit_inventory/${this.item.id}?cust_type=DISTRIBUTO&distributor=${this.effectiveDisributor.id}`
                    else
                        url = `companies/${this.effectiveCompany.id}/inventory/${this.item.id}?cust_type=DISTRIBUTO&distributor=${this.effectiveDisributor.id}`
                }

                console.log('=============', this.stockPoint);
                if(this.stockPoint)
                {
                    url = url + `&stock_point_id=${this.stockPoint.id}&stock_point_source=${this.stockPoint.source}`
                }
				Vue.axios.get(url)
				.then(response => {
					EventBus.$emit('loader:hide')
					_.each(response.data,function(element){
						let inv = Object.assign({},element)
                        inv.remaining_quantity = parseFloat(parseFloat(inv.remaining_quantity).toFixed(2)) //#739
                        let showBox = true
                        if(self.inMultiples && !self.plankSize)
                        {
                            inv.remaining_quantity = inv.remaining_quantity / self.inMultiples
                            if(inv.remaining_quantity < 1)
                            {
                                inv.remaining_quantity = 0
                                showBox = false
                            }
                            //inv.remaining_quantity = Math.floor(inv.remaining_quantity)  // #739
                            inv.remaining_quantity = parseFloat(parseFloat(inv.remaining_quantity).toFixed(2))  
                        }
                        if(self.plankSize)
                        {
                            inv.remaining_quantity = inv.remaining_quantity / self.plankSize
                            if(inv.remaining_quantity < 1)
                            {
                                inv.remaining_quantity = 0
                                showBox = false
                            }
                         //   inv.remaining_quantity = Math.floor(inv.remaining_quantity)   // #739
                            inv.remaining_quantity = parseFloat(parseFloat(inv.remaining_quantity).toFixed(2))
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
			show(item,stockPoint,intransit){
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
                if(intransit)
                   this.intransit = true
                else
                    this.intransit = false 
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