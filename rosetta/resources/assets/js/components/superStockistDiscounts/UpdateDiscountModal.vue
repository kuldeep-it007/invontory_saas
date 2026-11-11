<template>
    <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"> &times; </span></button>
                    <h4 v-if="discount" class="modal-title">
                        <span class="product-category">
                            <template v-if="discount.category">{{discount.category.code}}</template> 
                            <template v-if="discount.collection"> &raquo; {{discount.collection.code}}</template>
                            <template v-if="discount.colour"> &raquo; {{discount.colour.code}}</template>
                        </span>
                    </h4>
                </div>
                <div class="modal-body" v-if="discount">
                    <div class="row">
                        <div class="col-md-12">
                            <table class="table table-condensed table-bordered">
                                <thead>
                                    <tr>
                                        <th>Distributor</th>
                                        <th>Category</th>
                                        <th>Collection</th>
                                        <th>Colour</th>
                                        <th v-if="price">RRP</th>
                                        <th>Discount %</th>
                                        <th v-if="price">Discounted Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <template v-if="discount.customer">
                                                {{discount.customer.name}} ({{discount.customer.nav_id}})
                                            </template>
                                            <template v-else>
                                                <span class="label label-info text-uppercase">
                                                    All Distributors
                                                </span>
                                            </template>
                                        </td>
                                        <td>
                                            <template v-if="discount.category">{{discount.category.display_name}}</template>
                                            <template v-else>
                                                <span class="label label-info text-uppercase">All Categories</span>
                                            </template>
                                        </td>
                                        <td>
                                            <template v-if="discount.collection">{{discount.collection.display_name}}</template>
                                            <template v-else>
                                                <span class="label label-info text-uppercase">All Collections</span>
                                            </template>
                                        </td>
                                        <td>
                                            <template v-if="discount.colour">
                                                {{discount.colour.display_name}}
                                            </template>
                                            <template v-else>
                                                <span class="label label-info text-uppercase">All Colours</span>
                                            </template>
                                        </td>
                                        <td v-if="price">
                                           <template>{{price}}</template>
                                        </td>
                                        <td :class="[errors.has(`discount`)? 'has-error' : '']">
                                           <input type="text" class="form-control" v-model="discount.discount" v-validate="{decimal: 2, min_value: 0, max_value: 100}" name="discount" data-vv-name="discount" @keyup="calculateDiscountedPrice(price, discount.discount)">
                                          
                                        </td>
                                        <td v-if="price" :class="[errors.has(`discounted_price`)? 'has-error' : '']">
                                           <input type="text" class="form-control" v-model="discounted_price" v-validate="{decimal: 2, min_value: 1, max_value: price}" name="discounted_price" data-vv-name="discounted_price" @keyup="calculateDiscount(price, discounted_price)">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-if="errors.any()">
                                 <p class="text-danger" v-show="errors.has(`discount`)">Discount can not be negative or more than 100%.</p>
                                 <p class="text-danger" v-show="errors.has(`discounted_price`)">Discounted Price can not be less than 1 or greater RRP.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-default pull-right" :disabled="errors.has('discount')" @click="update(discount)">UPDATE</button>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    import Vue from 'vue'
    import appMixin from '../../vuex-mixin.js'
    import {EventBus} from '../../configs/EventBus'
    import VeeValidate from 'vee-validate'
    Vue.use(VeeValidate)
    export default {
        mixins : [appMixin],
        data(){
            return {
                loaded:false,
                discount:null,
                price:null,
                discounted_price:null
            }
        },
        mounted(){
            let self = this
            $(this.$refs.modal).on('shown.bs.modal', function (e) {
                Vue.nextTick(function(){
                    self.errors.clear()
                })
            })
            $(this.$refs.modal).on('hidden.bs.modal', function (e) {
                self.loaded = false
                self.discount = null
                self.price = null
                self.discounted_price=null
            })
        },
        computed:{
        },
        methods:{
            calculateDiscountedPrice(price, discount){
                this.discounted_price =  (price - (price * discount /100)).toFixed(2);
            },
            calculateDiscount(price, discounted_price){
                this.discount.discount = ((price - discounted_price )/ price * 100).toFixed(2);
            },
            show(discount, price){
                let self = this
                self.discount = Object.assign({},discount)
                self.price = price
                if(price)
                {
                    self.calculateDiscountedPrice(price,discount.discount)
                }
                $(self.$refs.modal).modal('show')
            },
            update(discount){
                let self = this
                self.$validator.validateAll()
                .then(value => {
                    if(value)
                    {
                        let input = {
                            discount: self.discount.discount ? self.discount.discount : 0
                        }
                        let url = `ss_discounts/${self.discount.id}`

                        EventBus.$emit('loader:show')
                        Vue.axios.put(url, input)
                        .then(response => {
                            swal({
                                title:"Discount updated!",
                                icon:"success"
                            })
                            .then(value => {
                                self.$emit('discount-updated',response.data)
                                $(self.$refs.modal).modal('hide')
                            }) 
                        })
                    }
                    else
                    {
                        swal({
                            title: "Error",
                            text:"Please enter valid Discount upto max 2 decimals.",
                            icon:'error'
                        })
                    }
                })
            },
        }
    }
</script>
<style scoped>
.product-category {
    display: block;
    color: #888;
    font-size: 14px;
}
</style>