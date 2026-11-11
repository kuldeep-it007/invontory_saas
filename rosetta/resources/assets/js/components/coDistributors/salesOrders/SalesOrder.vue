<template>
    <div v-if="abilities.viewSalesOrders && order">
        <section class="content-header">
            <h1>Order Details<small>{{orderNumber}}</small>
                <small class="pull-right">{{order.distributor.name}} ({{order.distributor.nav_id}})</small>
            </h1>
        </section>
        <section class="content">
            <div class="row">
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-aqua">
                        <div class="inner">
                           <!-- <h3>{{order.number}}</h3>-->
                            <h3>{{orderNumber}}</h3>
                            <p>PO No.</p>
                        </div>
                        <div class="icon">
                            <i class="ion ion-bag"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-aqua">
                        <div class="inner">
                            <h3>{{orderDate}}</h3>
                            <p>Date</p>
                        </div>
                        <div class="icon">
                            <i class="ion ion-bag"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-aqua">
                        <div class="inner">
                            <h3>{{order.amount|toFixed}}</h3>
                            <p>Amount</p>
                        </div>
                        <div class="icon">
                            <i class="ion ion-bag"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box status" :class="statusClass">
                        <div class="inner text-uppercase text-center">
                            <h3>{{order.status}}</h3>
                        </div>
                        <div class="icon">
                            <i class="ion ion-bag"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row" v-if="order.customer || order.address">
                <div class="col-md-6" v-if="order.customer">
                    <div class="small-box bg-green" style="height: 180px !important; overflow-y: auto !important;">
                        <h4 style="padding-left: 10px !important; padding-top: 0px; margin-bottom: 0px;">Billing Details</h4>
                        <div class="inner">
                            <p v-if="order.customer">
                                <p>
                                    <span v-html="(order.customer.name)?'<strong>Name</strong> : '+order.customer.name+'<br>':''"></span>
                                    <strong>Address:</strong> {{order.customer.address}}<br>
                                    <span v-html="(order.customer.address_2)?'<strong>Address 2</strong> : '+order.customer.address_2+'<br>':''"></span>
                                    <span v-html="(order.customer.city)?'<strong>City</strong> : '+order.customer.city+'<br>':''"></span>
                                    <span v-html="(order.customer.state.name)?'<strong>State</strong> : '+order.customer.state.name+'<br>':''"></span>
                                    <span v-html="(order.customer.post_code)?'<strong>Post Code</strong> : '+order.customer.post_code+'<br>':''"></span>
                                    <span v-html="(order.customer.phone)?'<strong>Phone</strong> : '+order.customer.phone+'<br>':''"></span>
                                </p>
                            </p>                            
                        </div>
                    </div>
                </div>
                <div class="col-md-6" v-if="order.address">
                    <div class="small-box bg-green" style="height: 180px !important; overflow-y: auto !important;">
                        <h4 style="padding-left: 10px !important; padding-top: 0px; margin-bottom: 0px;">Shipping Details</h4>
                        <div class="inner">
                            <p v-if="order.address">
                                <p>
                                    <span v-html="(order.address.name)?'<strong>Name</strong> : '+order.address.name+'<br>':''"></span>
                                    <strong>Address:</strong> {{order.address.address}}<br>
                                    <span v-html="(order.address.address_2)?'<strong>Address 2</strong> : '+order.address.address_2+'<br>':''"></span>
                                    <span v-html="(order.address.city)?'<strong>City</strong> : '+order.address.city+'<br>':''"></span>
                                    <span v-html="(order.address.state)?'<strong>State</strong> : '+order.address.state+'<br>':''"></span>
                                    <span v-html="(order.address.post_code)?'<strong>Post Code</strong> : '+order.address.post_code+'<br>':''"></span>
                                    <span v-html="(order.address.phone)?'<strong>Phone</strong> : '+order.address.phone+'<br>':''"></span>
                                </p>
                            </p>                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                 <!--   <button class="btn btn-primary" @click="checkStatus" v-if="order.status != 'Cancelled' && order.status != 'Shipped'">CHECK STATUS</button>-->
                    <button class="btn btn-danger" v-if="order.status == 'Open' && order.paid_amount <= 0" @click="cancelSO">Cancel SO</button>
                    <button class="btn btn-success" v-if="order.status != 'Cancelled' && order.paid_amount < order.amount" @click="addPayment">Add Payment</button>
                    <button class="btn btn-primary" v-if="order.status != 'Shipped' && order.status != 'Cancelled' && order.shipped == false" @click="print_ship">Print & Ship</button>
                    <button class="btn btn-primary" v-if="order.status == 'Shipped' || order.shipped == true" @click="print">Print</button>
                </div>
                <!--
                <div class="col-md-6" v-if="order.status != 'Cancelled'">
                    <div class="col-md-8 col-md-offset-4" style="padding-right: 0;">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Enter Invoice Number" v-model="order.invoice_id">
                            <span class="input-group-btn">
                                <button class="btn btn-primary" type="button" :disabled="!order.invoice_id" @click="addInvoice">Save</button>
                            </span>
                        </div>
                    </div>    
                </div>
                -->
                
            </div>
            <div class="row">
                <template v-if="cancelFlag == true">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Cancellation Reason</label>
                            <textarea class="form-control" v-model="order.cancelation_reason" rows="5"></textarea>
                        </div>
                    </div>
                    <div class="col-md-12 text-right">
                        <div class="form-group">
                            <button class="btn btn-success" @click="quitCancel">Cancel</button>&nbsp;
                            <button :disabled="order.cancelation_reason == null || order.cancelation_reason == ''" class="btn btn-danger pull-right" @click="confirmCancel">Confirm Cancellation</button>
                        </div>
                    </div>
                </template>
                <template v-if="order.status == 'Cancelled'">
                    <div class="col-md-12">
                        <div class="alert alert-danger">
                            <p><strong>Cancellation Reason:</strong> {{order.cancelation_reason}}</p>
                        </div>
                        <div class="text-right"><strong>Canceled by:</strong> {{order.canceled_by}}</div>
                    </div>
                </template>
            </div>
            <div class="row"  v-if="alert == true && !isCustomer" style="margin-top:10px;">
                <div class="col-md-12">
                    <div class="alert alert-danger">
                        <p>Order Completely Shipped, please close Sales Order manually in Navision and click on Check Status for PO.</p>
                    </div>
                </div>
            </div>
           <template v-if="order.remarks">
                <div class="row" style="margin-top: 10px;margin-bottom: 10px;">
                    <div class="col-md-12">
                        <div class="alert alert-warning">
                           <strong >Customer Remarks :</strong> {{order.remarks}}
                        </div>
                    </div>
                </div>
            </template>
            <h2>Items</h2>
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-widget">
                        <div class="box-body table-responsive">
                            <table class="table table-bordered table-condensed">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th v-if="order.distributor">Distributor</th>
                                        <th>Batch No</th>
                                        <th>RRP</th>
                                        <th>Discount %</th>
                                        <th>Disc. Price</th>
                                        <th>Amount</th>
                                        <th>Qty</th>
                                        <th>Shipped Qty</th>
                                        <th v-if="order.status !='Complete' && order.status !='Shipped' && order.status != 'Cancelled' && order.paid_amount <= 0">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, index) in order.purchase_lines" :key="line.id">
                                        <td>
                                            <span>{{line.product.category.display_name}} &raquo; {{line.product.collection.display_name}}<template v-if="line.product.colour"> &raquo; {{line.product.colour.display_name}}
                                                 </template>
                                            </span>
                                        </td>
                                        <td>{{order.distributor.name}}</td>
                                        <td>{{line.batch_no}}</td>
                                        <td>{{line.unit_price}}</td>
                                   <!--     <td>
                                           {{discount(line)}}
                                        </td>
                                        -->
                                         
                                        <td>
                                            <template v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped' && line.shipped_quantity == 0 && order.paid_amount <= 0">
                                                <input type="text" class="form-control" style="width:60px;" v-model="line.discount" @keyup="calculateDiscountedPrice(line)" v-validate="{decimal: 2, min_value: 0, max_value: 100}" :data-vv-name="`discount${index}`" :name="`discount${index}`"></input>
                                                <span class="text-danger" v-show="errors.has(`discount${index}`)">Discount can not be negative or more than 100%.</span>
                                            </template>
                                            <template v-if="(line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped') || line.shipped_quantity > 0 || order.paid_amount > 0">{{discount(line)}}
                                            </template>
                                        </td>
                                        

                                     <!--   <td>
                                            {{discountedPrice(line)}}
                                        </td>-->

                                        <td>
                                            <template v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped' && line.shipped_quantity == 0 && order.paid_amount <= 0">
                                                <input type="text" class="form-control" style="width:60px;" v-model="line.discounted_price" @keyup="calculateDiscount(line)" v-validate="{decimal: 2, min_value: 1, max_value: line.unit_price}" :data-vv-name="`discounted_price${index}`" :name="`discounted_price${index}`"></input>
                                                <span class="text-danger" v-show="errors.has(`discounted_price${index}`)">Discounted Price can not be less than 1 or greater RRP.</span>
                                            </template>
                                            <template v-if="line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped' || line.shipped_quantity > 0 || order.paid_amount > 0">{{discountedPrice(line)}}
                                            </template>
                                        </td>
                                        <td>
                                            {{calculateAmount(line)}}
                                        </td>
                                    <!--    <td class="form-group">
                                            {{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                        </td>
-->
                                        <td class="form-group">
                                            <template v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped' && line.shipped_quantity != line.original_quantity && order.paid_amount <= 0"><span class="label label-default pull-right">{{line.rate_type}}</span>
                                                <input type="text" class="form-control" style="width:90px;" v-model="line.quantity" v-validate="{ decimal: 2, min_value: (line.shipped_quantity).toFixed(2), max_value: line.original_quantity}" :data-vv-name="`quantity${index}`" :name="`quantity${index}`"></input>
                                            <span class="text-danger" v-show="errors.has(`quantity${index}`)">Quantity can not be greater than Original Quantity {{line.original_quantity}} or less than Shipped Quantity.</span>
                                            </template>
                                            <template v-if="(line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped') && (parseFloat(line.original_quantity) == parseFloat(line.shipped_quantity)) || order.paid_amount > 0">{{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                            </template>
                                        </td>

                                        <td>{{line.shipped_quantity}}</td>
                                         <td class="shrink" v-if="order.paid_amount <= 0">
                                            <div>
                                                <template v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped'">
                                                    <button class="btn btn-xs btn-success" :disabled="errors.has('quantity') || parseFloat(line.original_quantity) == parseFloat(line.shipped_quantity)" @click="updateLine(line)"><i class="fa fa-check-square"></i></button>
                                                    <button class="btn btn-xs btn-danger" v-if="!line.shipped_quantity > 0" @click="deleteLine(line, index)"><i class="far fa-trash-alt"></i></button>
                                               </template>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <payment-modal
                ref="PaymentModal" 
                @payment-added="paymentAdded"
                :company="this.distributor.company"
                :distributor="this.distributor"
                :order="this.order">
            </payment-modal>
        </section>

        <div ref="printElement" class="visible-print purchase-order" v-if="related_order">
            <table>
                <tr>
                    <td>
                        <h1 style="font-size: 16px;">{{related_order.distributor.name}}</h1>
                        <p>{{related_order.distributor.address}} {{related_order.distributor.address2}}</p>
                    </td>
                </tr>
            </table>

            <table class="table table-bordered">
                <tr>
                    <td colspan="4" style="width: 50%;">
                        <b>GSTIN No {{related_order.distributor.tax_information.gst.number}}</b>
                    </td>
                    <td colspan="4" style="width: 50%;">
                        <b>PAN No. {{related_order.distributor.tax_information.pan.number}}</b>
                    </td>
                </tr>
                <tr>
                    <td colspan="8" class="text-center"><b>TAX INVOICE</b></td>
                </tr>
                <tr>
                    <td colspan="4" style="width: 50%;">
                        <p><b>Invoice to:</b></p>
                        <p><b>{{related_order.customer.name}}</b></p>
                        <p>{{related_order.customer.address}} {{related_order.customer.address2}} {{related_order.customer.state.code}}</p>
                        <p><b>GSTIN/UniqID {{related_order.customer.tax_information.gst.number}}</b></p>

                    </td>
                    <td colspan="4" style="width: 50%;">
                        <p><b>Invoice No:</b> {{related_order.customer.name}}</p>
                        <p><b>Sales Order No:</b>{{related_order.number.replace('PO', 'SO')}}</p>
                        <p><b>Purchase Order No:</b>{{related_order.number}}</p>
                        <p><b>Order Date:</b>{{orderDate}}</p>
                        <p><b>Payment Terms:</b> {{related_order.customer.paymentTerms.value}}</p>
                        
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="width: 50%;">
                        <p><b>Ship To :</b></p>
                        <p><b>{{related_order.customer.name}}</b></p>
                        <p>{{related_order.address.address}} {{related_order.address.address2}} {{related_order.address.state_code}}</p>
                        <p><b>GSTIN/UniqID {{related_order.customer.tax_information.gst.number}}</b></p>

                    </td>
                    <td colspan="4" style="width: 50%;">
                        <p><b>Remarks:</b></p>
                        <p>{{related_order.remarks}}</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        Sr No
                    </td>
                    <td>
                        PRODUCT DESCRIPTION
                    </td>
                    <td>
                        QTY
                    </td>
                    <td>
                        UOM
                    </td>
                    <td>
                        RATE
                    </td>
                    <td>
                        GROSS AMOUNT
                    </td>
                    <td>
                        Discount Amount
                    </td>
                    <td>
                        NET AMOUNT
                    </td>
                </tr>
                <tr v-for="(line,index) in related_order.purchase_lines" :key="line.id">
                    <td>
                        {{index+1}}
                    </td>
                    <td>
                        {{line.product.category.display_name}} {{line.product.collection.display_name}} <template v-if="line.product.colour"> {{line.product.colour.display_name}}</template>
                    </td>
                    <td>
                        {{line.quantity}} 
                    </td>
                    <td>
                        {{line.product.base_unit_of_measure}}
                    </td>
                    <td>
                        {{discountedPrice(line)}}
                    </td>
                    <td>
                        {{calculateAmount(line)}}
                    </td>
                    <td>
                        0.00
                    </td>
                    <td>
                        {{calculateAmount(line)}}
                    </td>
                </tr>

                <tr>
                    <td colspan="2"><b>Net Total</b></td>
                    <td><b>&nbsp;</b></td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td><b>&nbsp;</b></td>
                    <td><b>&nbsp;</b></td>
                    <td><b>{{related_order.amount}}</b></td>
                </tr>
            </table>
        </div>


    </div>
</template>
<script>
    import Vue from 'vue'
    import {EventBus} from '../../../configs/EventBus.js'
    import AppMixins from '../../../vuex-mixin'
    import VeeValidate from 'vee-validate'
    import PaymentModal from './PaymentModal'
    import { Printd } from 'printd'
    export default{
        mixins: [AppMixins],
        components:{
            PaymentModal,
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
                if(this.customer_type == "DISTRIBUTO")
                    return this.selectedCustomer
                else
                    return null
            },
            orderDate(){
                if(!this.order.created_at)
                    return ""
                return moment.unix(this.order.created_at).format('DD MMM YYYY')
            },
            statusClass(){
                if(this.order.status == 'Open')
                    return 'bg-black'
                if(this.order.status == 'Pending_Approval')
                    return 'bg-orange'
                if(this.order.status == 'Complete')
                    return 'bg-green'
                if(this.order.status == 'Shipped')
                    return 'bg-green'
                if(this.order.status == 'Partially Shipped')
                    return 'bg-green'
                if(this.order.status == 'Cancelled')
                    return 'bg-red'

                return 'bg-aqua'
            },
            orderNumber(){
                if(!this.order.number)
                    return ""
                return this.order.number.replace('PO', 'SO')
            }
        },
        data(){
            return {
                order:null,
                company:null,
                selectedCustomer:null,
                customer_type:null,
                cancelFlag:false,
                alert:false,
                related_order:null
            }
        },
        mounted(){
            this.getOrder()
        },
        methods:{
            getOrder(){
                let self = this
                let url = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors/orders/${this.$route.params.id}?type=2`
                EventBus.$emit('loader:show')
                Vue.axios.get(url)
                .then(response => {
                    EventBus.$emit('loader:hide')
                    let order = Object.assign({},response.data)
                    _.each(order.purchase_lines,function(element){
                        element.original_quantity = (element.quantity).toFixed(2)
                        element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                    })
                    this.order = Object.assign({},order)
                })
                .catch(error => {
                    if(error.response.status == 412)
                    {
                        swal({
                                title: "Error",
                                text:error.response.data.error,
                                icon:'error'
                            })
                        .then(value => {
                            this.$router.push({name:'dashboard'})
                        })
                    }
                })
            },
            checkStatus(){
                this.getOrder()
            },
            addInvoice(){
                let self = this
                let url = `companies/${self.distributor.company.id}/distributors/${self.order.distributor_id}/sales_orders/${self.order.id}/addInvoice`

                let input = {
                        invoice_id:self.order.invoice_id
                    }
                    EventBus.$emit('loader:show')
                    Vue.axios.put(url,input)
                    .then(response => {
                        EventBus.$emit('loader:hide')
                        let order = Object.assign({},response.data.po)
                        _.each(order.purchase_lines,function(element){
                            element.original_quantity = (element.quantity).toFixed(2)
                            element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                        })
                        self.order = Object.assign({},order)
                        swal({
                            title:"Invoice Number set!",
                            icon:"success"
                        })
                    })
                    .catch(error => {})
            },
            print_ship(){
                let self = this
                let url = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors/sales_orders/${this.order.id}/ship`
                let url1 = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors/related_orders/${this.order.related_order.id}`

                swal({
                    title: 'Print & Ship',
                    text: 'Assumption made is that the Distributor\'s credit days should start from the time you click on the "Print & Ship" button. Please ensure you have entered the Invoice Number issued to Distributor.',
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    },
                })
                .then(value => {
                    if(!value)
                        return;
                    let input = {
                        invoice_id:self.order.invoice_id
                    }
                    EventBus.$emit('loader:show')
                    Vue.axios.put(url,input)
                    .then(response => {
                        EventBus.$emit('loader:hide')
                        let order = Object.assign({},response.data)
                        _.each(order.purchase_lines,function(element){
                            element.original_quantity = (element.quantity).toFixed(2)
                            element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                        })
                        self.order = Object.assign({},order)
   
                        EventBus.$emit('loader:show')
                        Vue.axios.get(url1)
                        .then(response => {
                            EventBus.$emit('loader:hide')
                            this.related_order = Object.assign({},response.data)
                            let d = new Printd()
                            let bootstrapCss = window.config.api_url.replace('api/','')+'vendors/bootstrap/css/bootstrap.min.css'
                            Vue.nextTick(function(){
                                d.print(self.$refs.printElement,[bootstrapCss, `
                                    table{
                                            font-family: "system-ui",Verdana;
                                            font-size:14px;
                                        }
                                `])    
                            })
                        })
                        .catch(error => {})
                    })
                    .catch(error => {})
                })
            },
            print(){
                let self = this

                let url1 = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors/related_orders/${this.order.related_order.id}`
                EventBus.$emit('loader:show')
                Vue.axios.get(url1)
                .then(response => {
                    EventBus.$emit('loader:hide')
                    this.related_order = Object.assign({},response.data)
                    let d = new Printd()
                    let bootstrapCss = window.config.api_url.replace('api/','')+'vendors/bootstrap/css/bootstrap.min.css'
                    Vue.nextTick(function(){
                        d.print(self.$refs.printElement,[bootstrapCss, `
                            table{
                                    font-family: "system-ui",Verdana;
                                    font-size:14px;
                                }
                        `])    
                    })
                })
                .catch(error => {})
            },
            calculateDiscountedPrice(item){
                item.discounted_price =  (item.unit_price - (item.unit_price * item.discount /100)).toFixed(2);
            },
            calculateDiscount(item){
                item.discount = ((item.unit_price - item.discounted_price )/ item.unit_price * 100).toFixed(2);
            },
            calculateAmount(item){
                item.discounted_price =  (item.unit_price - (item.unit_price * item.discount /100)).toFixed(2);
                return (item.quantity * item.discounted_price).toFixed(2)
            },
            cancelSO(){
                this.cancelFlag = true
            },
            paymentAdded(payment_amount)
            {
                this.order.remaining_amount = this.order.remaining_amount - payment_amount
                this.order.paid_amount = this.order.paid_amount + payment_amount
            },
            addPayment(){
                this.$refs.PaymentModal.show()           
            },
            quitCancel(){
                this.cancelFlag = false
                this.order.cancelation_reason = null
            },
            confirmCancel(){
                let url = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors/sales_orders/${this.order.id}`

                swal({
                    title: 'If you want to go ahead with cancelation, then Sales Order will be deleted. Press Cancel if you wish to keep the Order.',
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    },
                })
                .then(value => {
                    if(!value)
                    {
                        this.cancelFlag = false
                        this.order.cancelation_reason = null
                        return;
                    }
                    let input = {
                        cancelation_reason:this.order.cancelation_reason
                    }
                    EventBus.$emit('loader:show')
                    Vue.axios.delete(url, {data:input})
                    .then(response => {
                        EventBus.$emit('loader:hide')
                        this.order = Object.assign({},response.data)
                        this.cancelFlag = false
                    })
                    .catch(error => {
                        if(error.response.status == 503)
                        {
                            let order = Object.assign({},error.response.data.order)
                            _.each(order.purchase_lines,function(element){
                                element.original_quantity = (element.quantity).toFixed(2)
                                element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                            })
                            this.order = Object.assign({},order)
                            this.cancelFlag = false
                            swal({
                                title: "Warning",
                                text:error.response.data.error_text,
                                icon:'warning'
                            })
                        }
                        if(error.response.status == 412)
                        {
                            let order = Object.assign({},error.response.data.order)
                            _.each(order.purchase_lines,function(element){
                                element.original_quantity = (element.quantity).toFixed(2)
                                element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                            })
                            this.order = Object.assign({},order)
                            this.cancelFlag = false
                            swal({
                                title: "Error",
                                text:error.response.data.error_text,
                                icon:'error'
                            })
                        }
                    })
                })
            },
            discountedPrice(item){
                return (item.unit_price - (item.unit_price * item.discount /100)).toFixed(2);
            },
            discount(item){
                let disc = ((item.unit_price - item.discounted_price)/ item.unit_price * 100).toFixed(2)
                if(isNaN(disc))
                    return 0
                return disc
            },
            deleteLine(line, index){
                if(this.order.purchase_lines.length == 1)
                {
                    swal({
                        title: 'Order should have atleast one Product. If you want to go ahead with delete then the Order will be Cancelled. Press Cancel if you wish to keep the Order.',
                        icon:'warning',
                        buttons: {
                            cancel: true,
                            confirm: true,
                        },
                    })
                    .then(value => {
                        if(!value)
                        {
                            return
                        }
                        let url = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors/sales_orders/${this.order.id}`

                        EventBus.$emit('loader:show')
                        let input = {
                            cancelation_reason:"Cancelled because last Line was deleted."
                        }
                        Vue.axios.delete(url, {data:input})
                        .then(response => {
                            EventBus.$emit('loader:hide')
                            this.order = Object.assign({},response.data)
                        })
                        .catch(error => {
                            if(error.response.status == 503)
                            {
                                let order = Object.assign({},error.response.data.order)
                                _.each(order.purchase_lines,function(element){
                                    element.original_quantity = (element.quantity).toFixed(2)
                                    element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                                })
                                this.order = Object.assign({},order)
                                this.cancelFlag = false
                                swal({
                                    title: "Warning",
                                    text:error.response.data.error_text,
                                    icon:'warning'
                                })
                            }
                            if(error.response.status == 412)
                            {
                                let order = Object.assign({},error.response.data.order)
                                _.each(order.purchase_lines,function(element){
                                    element.original_quantity = (element.quantity).toFixed(2)
                                    element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                                })
                                this.order = Object.assign({},order)
                                this.cancelFlag = false
                                swal({
                                    title: "Error",
                                    text:error.response.data.error_text,
                                    icon:'error'
                                })
                            }
                        })
                    })
                }
                else
                {
                    let url = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors/sales_orders/${this.order.id}/sales_lines/${line.id}`

                    EventBus.$emit('loader:show')
                    Vue.axios.delete(url)
                    .then(response => {
                        EventBus.$emit('loader:hide')
                        this.order.purchase_lines.splice(index,1)
                        let order = Object.assign({},response.data.po)
                        _.each(order.purchase_lines,function(element){
                            element.original_quantity = (element.quantity).toFixed(2)
                            element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                        })
                        this.order = Object.assign({},order)
                        this.alert = response.data.alert
                        swal({
                            title:"Line deleted!",
                            icon:"success"
                        })
                    })
                    .catch(error => {
                        if(error.response.status == 503)
                        {
                            this.cancelFlag = false

                            let order = Object.assign({},error.response.data.order)
                            _.each(order.purchase_lines,function(element){
                                element.original_quantity = (element.quantity).toFixed(2)
                                element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                            })
                            this.order = Object.assign({},order)
                            swal({
                                title: "Warning",
                                text:error.response.data.error_text,
                                icon:'warning'
                            })
                        }
                        if(error.response.status == 412)
                        {
                            this.cancelFlag = false

                            let order = Object.assign({},error.response.data.order)
                            _.each(order.purchase_lines,function(element){
                                element.original_quantity = (element.quantity).toFixed(2)
                                element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                            })
                            this.order = Object.assign({},order)
                            swal({
                                title: "Error",
                                text:error.response.data.error_text,
                                icon:'error'
                            })
                        }
                    })
                }
            },
            updateLine(line){
                let input = {
                    discount:line.discount,
                    quantity:line.quantity,
                }
                let url = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors/sales_orders/${this.order.id}/sales_lines/${line.id}`

                this.$validator.validateAll()
                .then(value => {
                    if(value)
                    {
                        EventBus.$emit('loader:show')
                        Vue.axios.put(url,input)
                        .then(response => {
                            EventBus.$emit('loader:hide')
                            let order = Object.assign({},response.data.po)
                            _.each(order.purchase_lines,function(element){
                                element.original_quantity = (element.quantity).toFixed(2)
                                element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                            })
                            this.order = Object.assign({},order)
                            this.alert = response.data.alert
                            swal({
                                title:"Line updated!",
                                icon:"success"
                            })
                        })
                        .catch(error => {
                            if(error.response.status == 412)
                            {
                                let order = Object.assign({},error.response.data.order)
                                _.each(order.purchase_lines,function(element){
                                    element.original_quantity = (element.quantity).toFixed(2)
                                    element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                                })
                                this.order = Object.assign({},order)
                                swal({
                                    title: "Error",
                                    text:error.response.data.error_text,
                                    icon:'error'
                                })
                            }
                            if(error.response.status == 503)
                            {
                                let order = Object.assign({},error.response.data.order)
                                _.each(order.purchase_lines,function(element){
                                    element.original_quantity = (element.quantity).toFixed(2)
                                    element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                                })
                                this.order = Object.assign({},order)
                                swal({
                                    title: "Warning",
                                    text:error.response.data.error_text,
                                    icon:'warning'
                                })
                            }
                        })
                    }
                })
            },
        }
    }
</script>
<style scoped>
.status h3{
    padding: 20px 0;
    margin-bottom: 0;
}
.product-category {
    display: block;
    color: #888;
    font-size: 11px;
}
.product-title {
    display: block;
}
table td{
    vertical-align: middle !important;
}
.small-box .inner h3{
    font-size: 22px;
}
</style>