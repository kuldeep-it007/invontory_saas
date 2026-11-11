<template>
    <div v-if="abilities.orderProducts">
        <section class="content-header">
            <h1>Order Products</h1>
        </section>
        <section class="content">
            <div class="row">
                <div class="col-md-4">
                    <cart-preview :cart-dealer="this.dealer" :cart-company="this.dealer.company"></cart-preview>
                    <blocked-preview :cart-dealer="this.dealer" :cart-company="this.dealer.company"></blocked-preview>
                    <advance-cart-preview :cart-dealer="this.dealer" :cart-company="this.dealer.company"></advance-cart-preview>
                    <advance-blocked-preview :cart-dealer="this.dealer" :cart-company="this.dealer.company"></advance-blocked-preview>
                </div>
                <div class="col-md-8">
                    <div class="box box-widget">
                        <div class="box-body">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Category</label>
                                    <category-search v-model="category"></category-search>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Collection</label>
                                    <collection-search v-model="collection" :category="category"></collection-search>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Colour</label>
                                    <colour-search v-model="colour" :collection="collection"></colour-search>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group"  :class="{'has-error':errors.has('quantity')}">
                                    <label>Quantity</label>
                                    <quantity v-model="quantity" data-vv-name="quantity" v-validate="'required'"></quantity>
                                    <span class="text-danger" v-show="errors.has('quantity')">Please enter quanity.</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" :class="{'has-error':errors.has('min_price')}">
                                    <label>Min Price</label>
                                    <input type="number" class="form-control" v-model="min_price"  step="0.01" v-validate="{ decimal: 2, min_value: 1}" data-vv-name="min_price" :disabled="!category">
                                   <!-- <span class="text-danger" v-show="errors.has(`min_price`)">Min. Price upto 2 decimal</span>-->
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" :class="{'has-error':errors.has('max_price')}">
                                    <label>Max Price</label>
                                   <input type="number" class="form-control" v-model="max_price" step="0.01" v-validate="{decimal: 2,min_value: min_price}" data-vv-name="max_price" :disabled="!category">
                                <!--    <span class="text-danger" v-show="errors.has(`max_price`)">Max. Price can not be less than Min. Price and upto 2 decimal</span>-->
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label style="display: block;">&nbsp;</label>
                                    <button class="btn btn-info btn-block" :disabled="(category == null) || errors.count()!=0" @click="getDistributorInventory">Search</button>
                                </div>
                            </div>

                            <div class="clearfix"></div>
                            <div class="table-responsive">
                                <vuetable 
                                    :fields="tableFields"
                                    :api-url="apiUrl"
                                    pagination-path=""
                                    data-path="inventory"
                                    ref="vuetable"
                                    :css="css.table"
                                    track-by="id"
                                    :load-on-start="false"
                                    :append-params="appendParams"
                                    @vuetable:load-error="server_error"
                                    detail-row-component="stock-wrapper"
                                    :detail-row-options="detailRowOptions"
                                    @vuetable:loading="loading=true"
                                    @vuetable:loaded="loading=false"
                                >
                                    <template slot="inventory" slot-scope="props" >
                                        {{props.rowData.inventory.toFixed(2)}} <sup>{{props.rowData.base_unit_of_measure}}</sup>
                                        <a href="#" class="text-green" @click.prevent="showStockPoints(props.rowData,'stock-points')">
                                            <span v-if="isStockPointVisible(props.rowData.id,'stock-points')">
                                                <i class="fa fa-eye-slash"></i>
                                            </span>
                                            <span v-else> <i class="fa fa-eye"></i></span>
                                        </a>
                                    </template>
                                    <template slot="blocked" slot-scope="props" >
                                        {{props.rowData.blocked.toFixed(2)}} <sup>{{props.rowData.base_unit_of_measure}}</sup>
                                    </template>
                                    <template slot="in_transit" slot-scope="props" >
                                        {{props.rowData.qty_on_purch_order.toFixed(2)}} <sup>{{props.rowData.base_unit_of_measure}}</sup>
                                        <a href="#" class="text-green" @click.prevent="showStockPoints(props.rowData,'in-transit-stock-points')">
                                            <span v-if="isStockPointVisible(props.rowData.id,'in-transit-stock-points')">
                                                <i class="fa fa-eye-slash"></i>
                                            </span>
                                            <span v-else> <i class="fa fa-eye"></i></span>
                                        </a>
                                    </template>
                                    <template slot="in_transit_blocked" slot-scope="props" >
                                        {{props.rowData.in_transit_blocked.toFixed(2)}} <sup>{{props.rowData.base_unit_of_measure}}</sup>
                                    </template>
                                    <template slot="unit-price" slot-scope="props" >
                                        <template v-if="props.rowData.price.allow_cl">
                                            RL: {{getUnitRLPrice(props.rowData)}}<br>
                                            CL: {{getUnitCLPrice(props.rowData)}}
                                        </template>
                                        <template v-else>
                                            {{getUnitPrice(props.rowData)}}
                                        </template>
                                    </template>
                                    <template slot="price" slot-scope="props" >
                                        <template v-if="props.rowData.price.allow_cl">
                                            RL: {{getRLPrice(props.rowData)}}<br>
                                            CL: {{getCLPrice(props.rowData)}}
                                        </template>
                                        <template v-else>
                                            {{getPrice(props.rowData)}}
                                        </template>
                                    </template>
                                  <!--  <template slot="actions" slot-scope="props" >
                                        <div class="custom-actions" v-if="props.rowData.price.unit > 0">
                                            <button class="btn btn-xs btn-success" @click="addToCart(props.rowData)" v-if="props.rowData.inventory > 0">SELECT</button>
                                        </div>
                                    </template>-->
                                </vuetable>
                            </div>
                            <add-to-cart-modal ref="addToCartModal">
                                
                            </add-to-cart-modal>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <div class="overlay" v-if="loading">
            <i class="fa fa-sync fa-spin"></i>
            <p>Please wait...<br> This operation may take some time</p>
        </div>
    </div>
    <div v-else>
        <page403></page403>
    </div>
</template>
<script>
    import Vue from 'vue'
    import {EventBus} from '../../configs/EventBus'
    import appMixin from '../../vuex-mixin.js'
    import CategorySearch from '../categories/Search'
    import CollectionSearch from '../collections/Search'
    import ColourSearch from '../colour/Search'
    import Vuetable from '../../vendor/vuetable2/Vuetable'
    import vuetableCssConfig from '../../configs/VuetableCssConfig'
    import InventoryListFields  from './InventoryListFields.js'
    import AddToCartModal from './AddToCartModal'
    import CartPreview from './cart/Preview'
    import BlockedPreview from './blocked/Preview'
    import AdvanceCartPreview from './advance/Preview'
    import AdvanceBlockedPreview from './blockedAdvance/Preview'
    import Quantity from '../Quantity'
 //   import StockPointInventory from  './StockPoints'
    import VeeValidate from 'vee-validate'
 //   Vue.component('stock-point-inventory',StockPointInventory)
    import StockWrapper from './StockWrapper'
    Vue.component('stock-wrapper',StockWrapper)
    Vue.use(VeeValidate)
    export default{
        mixins : [appMixin],
        components:{
            CategorySearch,
            CollectionSearch,
            ColourSearch,
            Vuetable,
            AddToCartModal,
            CartPreview,
            BlockedPreview,
            AdvanceCartPreview,
            AdvanceBlockedPreview,
            Quantity
        },
        data(){
            return {
                category:null,
                collection:null,
                colour:null,
                quantity:">=1",//#754 changed filter to >= from >
                sideBarWasOpen:false,
                css:vuetableCssConfig,
                tableFields:InventoryListFields,
                loading:false,
                min_price:"1",
                max_price:"",
                detailRowOptions:{
                    type:'stock-points'
                }
            }
        },
        mounted(){
            let self = this

            EventBus.$on('stock-points:load',function(data){
                self.addToCart(data.item, data.stock_point)

            })
            EventBus.$on('intransit-stock-points:load',function(data){
                self.addToCart(data.item, data.stock_point,true)

            })
            Vue.nextTick(function(){
                if(!$('body').hasClass('sidebar-collapse'))
                {
                    self.sideBarWasOpen = true
                    $('[data-toggle="push-menu"]').pushMenu('toggle')
                }
            })
        /*    EventBus.$on('cart:changed',function(items){
                self.$refs.vuetable.reload()
            })
            EventBus.$on('blocked:changed',function(items){
                self.$refs.vuetable.reload()
            })*/
            //#718
            EventBus.$on('cart:added',function(){
                self.$refs.vuetable.reload()
            })
            EventBus.$on('advance_cart:added',function(){
                self.$refs.vuetable.reload()
            })
            EventBus.$on('blocked:added',function(){
                self.$refs.vuetable.reload()
            })
            EventBus.$on('advance_blocked:added',function(){
                self.$refs.vuetable.reload()
            })
            EventBus.$on('cart:updated',function(item){
                if(self.category)
                {
                    if(self.category.code == item.product.category.code)
                    {
                        if(self.collection)
                        {
                            if(self.collection.code == item.product.collection.code)
                            {
                                if(self.colour)
                                {
                                    if(self.colour.code == item.product.colour.code)
                                        self.$refs.vuetable.reload()
                                }
                                else
                                {
                                    self.$refs.vuetable.reload()
                                }
                            }
                        }
                        else
                        {
                            self.$refs.vuetable.reload()
                        }
                    }
                }
            })
            EventBus.$on('blocked:updated',function(item){
                if(self.category)
                {
                    if(self.category.code == item.product.category.code)
                    {
                        if(self.collection)
                        {
                            if(self.collection.code == item.product.collection.code)
                            {
                                if(self.colour)
                                {
                                    if(self.colour.code == item.product.colour.code)
                                        self.$refs.vuetable.reload()
                                }
                                else
                                {
                                    self.$refs.vuetable.reload()
                                }
                            }
                        }
                        else
                        {
                            self.$refs.vuetable.reload()
                        }
                    }
                }
            })//End of #718
            EventBus.$on('advance_blocked:updated',function(item){
                if(self.category)
                {
                    if(self.category.code == item.product.category.code)
                    {
                        if(self.collection)
                        {
                            if(self.collection.code == item.product.collection.code)
                            {
                                if(self.colour)
                                {
                                    if(self.colour.code == item.product.colour.code)
                                        self.$refs.vuetable.reload()
                                }
                                else
                                {
                                    self.$refs.vuetable.reload()
                                }
                            }
                        }
                        else
                        {
                            self.$refs.vuetable.reload()
                        }
                    }
                }
            })//End of #717
            EventBus.$on('advance_cart:updated',function(item){
                if(self.category)
                {
                    if(self.category.code == item.product.category.code)
                    {
                        if(self.collection)
                        {
                            if(self.collection.code == item.product.collection.code)
                            {
                                if(self.colour)
                                {
                                    if(self.colour.code == item.product.colour.code)
                                        self.$refs.vuetable.reload()
                                }
                                else
                                {
                                    self.$refs.vuetable.reload()
                                }
                            }
                        }
                        else
                        {
                            self.$refs.vuetable.reload()
                        }
                    }
                }
            })//#810
        },
        destroyed(){
            let self = this
            if(this.sideBarWasOpen)
            {
                Vue.nextTick(function(){
                if($('body').hasClass('sidebar-collapse'))
                {
                    $('[data-toggle="push-menu"]').pushMenu('toggle')
                }
            })
            }
        },
        computed:{
            appendParams(){
                let params = {}
                if(this.category)
                {
                    params.category = encodeURIComponent(this.category.code)
                }
                if(this.collection)
                {
                     params.collection = encodeURIComponent(this.collection.code)
                }
                if(this.colour)
                {
                    params.colour = encodeURIComponent(this.colour.code)
                }
                params.quantity = encodeURIComponent(this.quantity)
                params.min_price = this.min_price ? this.min_price : 1
                params.max_price = this.max_price
                if(this.isCustomer)
                {
                    if(this.dealer)
                    {
                        params.dealer = this.dealer.id
                        params.cust_type = 'DEALER'
                    }
                }
                return params
            },
            apiUrl(){
                return `companies/${this.dealer.company.id}/distributors/${this.dealer.distributor_id}/inventory`
            },
        /*    effectiveCompany(){
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
            }*/
        },
        methods:{
            isStockPointVisible(id){
                return this.$refs.vuetable.isVisibleDetailRow(id)
            },
            showStockPoints(row,type){
                if(this.$refs.vuetable.isVisibleDetailRow(row.id))
                {
                    if(type == this.detailRowOptions.type)
                    {
                        this.$refs.vuetable.toggleDetailRow(row.id)    
                    }
                    else
                    {
                        this.detailRowOptions = Object.assign({},{type:type})
                    }
                }
                else
                {
                    this.detailRowOptions = Object.assign({},{type:type})
                    this.$refs.vuetable.toggleDetailRow(row.id)
                }
                
            },
            showInTransitStockPoints(row){
                this.$refs.vuetable.toggleDetailRow(row.id)
            },
            getUnitRLPrice(product){
                return product.price.unit.toFixed(2)
            },
            getUnitCLPrice(product){
                let price = product.price.unit + (product.price.unit * product.price.cl_percent / 100)
                return price.toFixed(2)
            },
            getUnitPrice(product){
                return product.price.unit.toFixed(2)
            },
            getRLPrice(product){
                return (product.price.unit - (product.price.unit * product.price.discount / 100)).toFixed(2)
            },
            getCLPrice(product){
                let price = product.price.unit + (product.price.unit * product.price.cl_percent / 100)
                return (price - (price * product.price.discount / 100)).toFixed(2)
            },
            getPrice(product){
                return (product.price.unit - (product.price.unit * product.price.discount / 100)).toFixed(2)
            },
            addToCart(item,stockpoint,intransit){
                this.$refs.addToCartModal.show(item,stockpoint,intransit)
            },
            getDistributorInventory(){
                if(this.quantity != null)
                    this.$refs.vuetable.reload()
            },
            server_error(error){
                if(error.response.status >= 500)
                {
                    swal({
                        title: "Error",
                        text: "We are unable to process your request, please try again later.",
                        icon:'error'
                    })
                }
            }
        },
        watch:{
            'category.code':function(){
                this.collection = null
            },
            'collection.code':function(){
                this.colour = null
            }
        }
    }
</script>
<style scoped>
    .product-info{
       margin-left: 0;
    }
    .cart .box-body{
       max-height: 250px;
       overflow-y: auto;
    }
    .blocked .box-body{
       max-height: 250px;
       overflow-y: auto;
       min-height: 100px;
    }

</style>
<style>
    .has-actions .btn{
        text-transform: uppercase;
    }
    .has-actions{
        width: 1%;
        white-space: nowrap;
    }
    .vuetable-th-id{
        width: 1%;
        white-space: nowrap;    
    }
    .overlay{
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.7);
    z-index: 1000;
}
.overlay>.fa, .overlay-wrapper .overlay>.fa {
    position: absolute;
    top: 50%;
    left: 50%;
    margin-left: -15px;
    margin-top: -15px;
    color: #000;
    font-size: 30px;
}
.overlay>p{
position: absolute;
    top: calc(50% + 45px);
    left: 50%;
    margin-left: -150px;
    margin-top: -15px;
    color: #000;
    width: 300px;
    text-align: center;
    background: #FFF;
    padding: 10px;
    border: 1px solid #CCC;
}
</style>