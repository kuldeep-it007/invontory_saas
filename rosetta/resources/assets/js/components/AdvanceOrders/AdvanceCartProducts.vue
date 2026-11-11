<template>
    <div v-if="abilities.createAdvanceOrders">
        <section class="content-header">
            <h1>Selected Products
            <span class="pull-right"><button class="btn btn-sm btn-primary" @click="addProduct(company)" v-if="abilities.createAdvanceOrders" :disabled="!isCustomer && !selectedCustomer">Select Products</button></span>
            </h1>
        </section>
        <section class="content">
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-widget">
                        <div class="box-body">
                            <div class="row" >
                                <div class="col-md-3" v-if="!isCustomer && this.state.user.company == null">
                                    <div class="form-group">
                                        <label>Region</label>
                                        <region-select v-model="region" :allow-empty="true"></region-select>
                                    </div>
                                </div>
                                <div class="col-md-3" v-if="!isCustomer && this.state.user.company == null">
                                    <div class="form-group">
                                        <label>Company</label>
                                        <company v-model="company" :region="region" :disabled="!region"></company>
                                    </div>
                                </div>
                                <div class="col-md-3" v-if="!isCustomer">
                                    <div class="form-group">
                                        <label>Customer Type</label>
                                        <customer-type v-model="customer_type" :disabled="!company"></customer-type>
                                    </div>
                                </div>
                                <div class="col-md-3" v-if="!isCustomer">
                                    <div class="form-group" :class="errors.has('customer') ? 'has-error' : ''">
                                        <label>
                                            <template v-if="customer_type">
                                                <template v-if="customer_type.code == 'DISTRIBUTO'">Distributor</template>
                                                <template v-if="customer_type.code == 'DEALER'">Dealer</template>
                                            </template>
                                            <template v-else>   
                                                Customer
                                            </template>
                                        </label>
                                            <customer-select v-model="selectedCustomer" :company="company" :customer_type="customer_type" :disabled="!company" :required="true" v-validate="'required'" data-vv-name="customer"></customer-select>
                                    </div>
                                </div>
                            </div>
                            <table class="table table-condensed table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Collection</th>
                                        <th>colour</th>
                                        <th>RRP</th>
                                        <th>Discount %</th>
                                        <th>Disc. RRP</th>
                                        <th>Total Price</th>
                                        <th style="text-align: right;">Quantity</th>
                                        <th>&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item,index) in items">
                                        <td>{{item.category_code}}</td>
                                        <td>{{item.collection_code}}</td>
                                        <td>{{item.colour_code}}</td>
                                        <td>{{item.unit_price}}</td>
                                        <td>{{item.discount}}</td>
                                        <td>{{item.discounted_unit_price}}</td>
                                        <td>{{item.total_price}}</td>
                                        <td style="text-align: right;">{{item.quantity}}</td>
                                        <td class="shrink"><a href="#" @click.prevent="removeProduct(index)"><i class="fa fa-times"></i></a></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <span class="pull-right"><button class="btn btn-sm btn-primary" v-if="abilities.createAdvanceOrders" @click="addToAdvanceCart" :disabled="!items.length">Add To Advance Cart</button></span>
                </div>
            </div>
            <advance-product-modal 
                ref="advanceProductModal"
                @product-added="onProductAdded"
                v-if="abilities.createAdvanceOrders"
                :effective-company="effectiveCompany"
                :effective-distributor="effectiveDistributor"
                :effective-dealer="effectiveDealer">
            </advance-product-modal>
        </section>
    </div>
    <div v-else>
        <page403></page403>
    </div>
</template>
<script>
    import Vue from 'vue'
    import VeeValidate from 'vee-validate'
    import appMixin from '../../vuex-mixin.js'
    import advanceProductModal from './AdvanceProductModal'
    import RegionSelect from '../region/Select'
    import Company from '../companies/Select'
    import CustomerType from '../master/CustomerType'
    import CustomerSelect from '../master/CustomerSelect'
    Vue.use(VeeValidate)
    export default{
        mixins : [appMixin],
        components:{
            advanceProductModal,
            RegionSelect,
            Company,
            CustomerSelect,
            CustomerType,
        },
        data(){
            return {
                region:null,
                company:null,
                items:[],
                selectedCustomer:null,
                customer_type:{code:'DISTRIBUTO',name:"Distributor"},
            }
        },
        mounted(){
            let self = this
            if((!this.isCustomer) && this.state.user.company)
                this.company =  Object.assign({},this.state.user.company)
            if(this.isCustomer)
            {
                this.company = Object.assign({},this.customer.company)
            }
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
                return this.company
            },
            effectiveDistributor(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor
                }
                if(this.customer_type.code == "DISTRIBUTO")
                    return this.selectedCustomer
                else
                    return null
            },
            effectiveDealer(){
                if(this.isCustomer)
                {
                    if(this.dealer)
                        return this.dealer
                }
                if(this.customer_type.code == "DEALER")
                    return this.selectedCustomer
                else
                    return null
            },
        },
        methods:{
            addProduct(company){
                this.$refs.advanceProductModal.show(company)
            },
            onProductAdded(item){
               this.items.push(item)
            },
            addToAdvanceCart(){
                let self = this
                this.$validator.validateAll()
                .then(value => {
                    if(value)
                    {
                        let input = {
                            advance_items:[]
                        }

                        _.each(self.items,function (element){
                            let item = {
                                product_id: element.product_id,
                                order_quantity: element.quantity,
                                unit_price: element.unit_price,
                                discount: element.discount,
                                discounted_unit_price: element.discounted_unit_price,
                                total_price: element.total_price
                            }
                            input.advance_items.push(item)
                        })
                        let url = ""
                        if(self.isCustomer)
                        {
                            if(self.effectiveDistributor)
                            {
                                url = `companies/${self.effectiveCompany.id}/distributors/${self.effectiveDistributor.id}/advance_items`
                            }
                            if(self.effectiveDealer)    
                            {
                                url = `companies/${self.effectiveCompany.id}/dealers/${self.effectiveDealer.id}/advance_items`
                            }
                        }
                        else
                        {
                            if(self.customer_type.code == "DISTRIBUTO")
                                url = `companies/${self.effectiveCompany.id}/distributors/${self.selectedCustomer.id}/advance_items`
                            else if(self.customer_type.code == "DEALER")
                                url = `companies/${self.effectiveCompany.id}/dealers/${self.selectedCustomer.id}/advance_items`
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
                                self.$router.push({name:'AdvanceCart'})
                            }
                        })
                    }
                })
            },

            removeProduct(itemId, index){
                this.items.splice(index,1)
            },
        },
        watch:{
            'customer_type.code':function(){
                this.selectedCustomer = null
            },
            'company.id':function(){
                this.selectedCustomer = null
            }
        }
    }
</script>