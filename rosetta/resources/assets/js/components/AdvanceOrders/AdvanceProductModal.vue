<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog modal-lg" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 class="modal-title">Select Products</h4>
      			</div>
      			<div class="modal-body">
                    <div class="row">
          				<div class="col-md-3">
                            <div class="form-group">
                                <label>Category</label>
                                <category-search v-model="category"></category-search>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Collection</label>
                                <collection-search v-model="collection" :category="category"></collection-search>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Colour</label>
                                <colour-search v-model="colour" :collection="collection"></colour-search>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label style="display: block;">&nbsp;</label>
                                <button class="btn btn-info btn-block" :disabled="(!(category) || !(collection))" @click="getProducts">Search</button>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 table-responsive">
                            <table class="table table-bordered table-striped table-condensed">
                                <thead>
                                    <tr>
                                       <!-- <th>Category</th>
                                        <th>Collection</th>
                                        <th>colour</th>-->
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>RRP</th>
                                        <th>Discount %</th>
                                        <th>Disc. RRP</th>
                                        <th>Net Amount</th>
                                        <th>Tax Amount</th>
                                        <th>Total Amount</th>
                                        <th>&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(product,index) in products" :key="product.id">
                                       <!-- <td>{{product.category.display_name}}</td>
                                        <td>{{product.collection.display_name}}</td>
                                        <td>
                                            <template v-if="product.colour">
                                                {{product.colour.display_name}}
                                            </template>
                                        </td>-->
                                        <td>
                                            {{product.category.display_name}} &raquo; {{product.collection.display_name}}
                                            <template v-if="product.colour"> &raquo; {{product.colour.display_name}}</template>
                                        </td>
                                        <td>
                                            <input type="text" v-model.number="product.quantity">
                                        </td>
                                        <td>{{product.price.unit}}</td>
                                        <td>{{product.price.discount}}</td>
                                        <td>{{getDiscountedPrice(product)}}<span class="tax">(+{{product.price.tax_type_code}} @ {{parseFloat(product.price.tax_percent).toFixed(2)}}%)</span></td>
                                        <td>{{getAmount(product)}}</td>
                                        <td>{{getTaxAmount(product)}}</td>
                                        <td>{{getNetAmount(product)}}</td>
                                        <td>
                                            <button class="btn btn-block btn-primary" :disabled="product.quantity <= 0" @click="addToAdvanceCart(product,index)">ADD</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
      			</div>
                <div class="modal-footer">
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
    import CategorySearch from '../categories/Search'
    import CollectionSearch from '../collections/Search'
    import ColourSearch from '../colour/Search'
    import ProductListFields  from './ProductListFields.js'
    import vuetableCssConfig from '../../configs/VuetableCssConfig'
    import Vuetable from '../../vendor/vuetable2/Vuetable'
    Vue.use(VeeValidate)
    export default {
        mixins : [appMixin],
        props:['effectiveCompany', 'effectiveDistributor', 'effectiveSuperStockist', 'effectiveDealer'],
        components: {
            Switches,
            CategorySearch,
            CollectionSearch,
            ColourSearch,
            Vuetable,
        },
        data(){
                return {
                    category   : null,
                    collection : null,
                    colour     : null,
                    unit_price : null,
                    discount   : null,
                    discounted_unit_price : null,
                    total_price : null,
                    tableFields:ProductListFields,
                    css:vuetableCssConfig,
                    products:[]
                }
        },
        mounted(){
            let self = this
            $(this.$refs.modal).on('show.bs.modal', function (e) {
                Vue.nextTick(function(){
                    self.errors.clear()
                })
            })
            $(this.$refs.modal).on('hidden.bs.modal', function (e) {
                self.products = []
                self.category = null    
                self.collection = null
                self.colour = null
            })
        },
        computed:{
        },
        methods:{
            /*show(company){
                let self = this
                this.company = Object.assign({},company)
                $(self.$refs.modal).modal('show')
            },*/
            show(){
                let self = this
                $(self.$refs.modal).modal('show')
            },
            getDiscountedPrice(product){
                return parseFloat(product.price.unit - (product.price.unit * product.price.discount /100)).toFixed(2)
            },
            getAmount(product){
                return parseFloat(this.getDiscountedPrice(product) * product.quantity).toFixed(2)
            },
            getTaxAmount(product){
                return parseFloat(((this.getDiscountedPrice(product) * product.price.tax_percent)/100 * product.quantity).toFixed(2))
            },
            getNetAmount(product){
                return (parseFloat(this.getAmount(product)) + parseFloat(this.getTaxAmount(product))).toFixed(2)
            },
            getProducts(){
                let self  = this
                let url = 'getProducts'
                self.products = []
                let params = {}
                if(self.category)
                {
                    params.category = self.category.code
                }
                if(self.collection)
                {
                     params.collection = self.collection.code
                }
                if(self.colour)
                {
                    params.colour = self.colour.code
                }
                if(self.effectiveCompany)
                {
                    params.company = self.effectiveCompany.id
                }
                
                if(self.effectiveDistributor)
                {
                    params.customerId = self.effectiveDistributor.id
                    params.cust_type = 'DISTRIBUTO'
                }
                if(self.effectiveSuperStockist)
                {
                    params.customerId = self.effectiveSuperStockist.id
                    params.cust_type = 'SUPER_STOCKIST'
                }
                if(self.effectiveDealer)
                {
                    params.customerId = self.effectiveDealer.id
                    params.cust_type = 'DEALER'
                }

                Vue.axios.get(url, {params:params})
                .then(response => {
                    _.each(response.data,function(element){
                        self.products.push(Object.assign(element,{quantity:0})) 
                    })
                })
            },
            addToAdvanceCart(product,index){
                let self = this
                self.$validator.validateAll()
                .then(value => {
                    if(value)
                    {
                        let input = {
                            advance_items:[]
                        }

                        let item = {
                            product_id: product.id,
                            order_quantity: product.quantity,
                            unit_price: product.price.unit,
                            discount: product.price.discount,
                            discounted_unit_price : self.getDiscountedPrice(product),
                            total_price : self.getAmount(product),
                            tax_type_code : product.price.tax_type_code,
                            tax_percent : product.price.tax_percent,
                            tax_amount : self.getTaxAmount(product),
                            net_amount : self.getNetAmount(product),
                        }
                        input.advance_items.push(item)
                        let url = ""
                        if(self.effectiveDistributor)
                        {
                            url = `companies/${self.effectiveCompany.id}/distributors/${self.effectiveDistributor.id}/advance_items`
                        }
                        if(self.effectiveSuperStockist)
                        {
                            url = `companies/${self.effectiveCompany.id}/super_stockists/${self.effectiveSuperStockist.id}/advance_items`
                        }
                        if(self.effectiveDealer)    
                        {
                            url = `companies/${self.effectiveCompany.id}/dealers/${self.effectiveDealer.id}/advance_items`
                        }
                        Vue.axios.post(url,input)
                        .then(response => {
                            if(!response.data)
                            {
                                swal({
                                    title: "Unable to add to Advance Cart. Prease try again.",
                                    icon:'error',
                                })
                                return
                            }
                            else
                            {
                               swal({
                                    title:"Added to cart",
                                    icon:"success"
                                })
                                self.$emit('item-added', response.data)
                                product.quantity=0
                            }
                        })
                    }
                })
            },
        },
    }
</script>
<style scoped>
    .tax {
        display: block;
        color: #888;
        font-size: 11px;
    }
	h1{
		font-size:16px;
		margin:0;
		color: #777;
	}
	h1 span{
		color:#555;
	}
</style>