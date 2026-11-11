<template>
	<div v-if="abilities.viewCompanyStock">
		<section class="content-header">
			<h1>View Stock</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
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
                            <div class="col-md-4">
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
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" :class="{'has-error':errors.has('max_price')}">
                                    <label>Max Price</label>
                                   <input type="number" class="form-control" v-model="max_price" step="0.01" v-validate="{decimal: 2,min_value: min_price}" data-vv-name="max_price" :disabled="!category">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label style="display: block;">&nbsp;</label>
                                    <button class="btn btn-info btn-block" :disabled="(category == null) || errors.count()!=0" @click="getInventory">Search</button>
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
                                    detail-row-component="stock-point-inventory"
                                    :load-on-start="false"
                                    :append-params="appendParams"
                                >
                                    <template slot="category" slot-scope="props" >
                                        <template v-if="props.rowData.category.code">
                                            {{props.rowData.category.display_name}}<br><span class="product-category">{{props.rowData.category.code}}</span>
                                        </template>
                                    </template>
                                    <template slot="collection" slot-scope="props" >
                                        <template v-if="props.rowData.collection.code">
                                            {{props.rowData.collection.display_name}}<br><span class="product-category">{{props.rowData.collection.code}}</span>
                                        </template>
                                    </template>
                                    <template slot="colour" slot-scope="props" >
                                        <template v-if="props.rowData.colour.code">
                                            {{props.rowData.colour.display_name}}<br><span class="product-category">{{props.rowData.colour.code}}</span>
                                        </template>
                                    </template>
                                    <template slot="inventory" slot-scope="props" >
                                        {{props.rowData.inventory.toFixed(2)}} <sup>{{props.rowData.base_unit_of_measure}}</sup>
                                         <a href="#" class="text-green" @click.prevent="showStockPoints(props.rowData)">
                                            <span v-if="isStockPointVisible(props.rowData.id)">
                                                <i class="fa fa-eye-slash"></i>
                                            </span>
                                            <span v-else> <i class="fa fa-eye"></i></span>
                                        </a>
                                    </template>
                                    <template slot="blocked" slot-scope="props" >
                                        {{props.rowData.blocked.toFixed(2)}} <sup>{{props.rowData.base_unit_of_measure}}</sup>
                                    </template>
                                    <template slot="unit-price" slot-scope="props" >
                                        <template>
                                            {{getUnitPrice(props.rowData)}}
                                            <br><span class="tax">{{props.rowData.price.tax_percent}}% {{props.rowData.price.tax_type_code}}</span>
                                        </template>
                                    </template>
                                    <template slot="actions" slot-scope="props" >
                                        <div class="custom-actions">
                                            <button class="btn btn-xs btn-success" v-if="props.rowData.inventory > 0" @click="showDetails(props.rowData)">DETAILS</button>
                                        </div>
                                    </template>
                                </vuetable>
                            </div>
                            <batch-details-modal ref="BatchDetailsModal"></batch-details-modal>
						</div>
					</div>
				</div>
			</div>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
    import Vue from 'vue'
	import appMixin from '../../vuex-mixin.js'
    import CategorySearch from '../categories/Search'
    import CollectionSearch from '../collections/Search'
    import ColourSearch from '../colour/Search'
    import Vuetable from '../../vendor/vuetable2/Vuetable'
    import vuetableCssConfig from '../../configs/VuetableCssConfig'
    import BatchDetailsModal from './BatchDetailsModal'
    import Quantity from '../Quantity'
    import VeeValidate from 'vee-validate'
    import InventoryListFields  from './InventoryListFields.js'
    import StockPointInventory from  './StockPoints'
    import {EventBus} from '../../configs/EventBus'
    Vue.component('stock-point-inventory',StockPointInventory)
    Vue.use(VeeValidate)
	export default{
		mixins : [appMixin],
		components:{
            CategorySearch,
            CollectionSearch,
            ColourSearch,
            Vuetable,
            Quantity,
            BatchDetailsModal
		},
        data(){
            return {
                category:null,
                collection:null,
                colour:null,
                quantity:">1",
                sideBarWasOpen:false,
                css:vuetableCssConfig,
                tableFields:InventoryListFields,
                min_price:"1",
                max_price:"",
            }
        },
        mounted(){
            let self = this
            EventBus.$on('stock-points:load',function(data){
                self.showDetails(data.item, data.stock_point)

            })
            Vue.nextTick(function(){
                if(!$('body').hasClass('sidebar-collapse'))
                {
                    self.sideBarWasOpen = true
                    $('[data-toggle="push-menu"]').pushMenu('toggle')
                }
            })
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
            apiUrl(){
                return `companies/${this.state.user.company.id}/inventory`
            },
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
                return params
            }
        },
        methods:{
            isStockPointVisible(id){
                return this.$refs.vuetable.isVisibleDetailRow(id)
            },
            showStockPoints(row){
                this.$refs.vuetable.toggleDetailRow(row.id)
            },
            getUnitPrice(product){
                return product.price.unit.toFixed(2)
            },
            getInventory(){
                if(this.quantity != null)
                    this.$refs.vuetable.reload()
            },
            showDetails(item,stockpoint){
                this.$refs.BatchDetailsModal.show(item,stockpoint)
            },
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
    .product-category {
    display: block;
    color: #888;
    font-size: 11px;
    }
    .tax {
    display: block;
    color: #888;
    font-size: 11px;
    }
</style>