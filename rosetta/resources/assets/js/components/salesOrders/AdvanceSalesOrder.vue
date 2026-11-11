<template>
    <div v-if="abilities.viewSalesOrders">
        <section class="content-header">
            <h1>Order Details<small>{{orderNumber}}</small>
                <small class="pull-right" v-if="order.customer">{{order.customer.name}} ({{order.customer.nav_id}})</small>
            </h1>
        </section>
        <section class="content">
            <div class="row">
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-aqua">
                        <div class="inner">
                           <!-- <h3>{{order.number}}</h3>-->
                            <h3>{{orderNumber}}</h3>
                            <p>SO No.</p>
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
                            <h3>{{orderNetAmount|toFixed}}</h3>
                            <p>Total Amount</p>
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
            <div class="row">
                <div class="col-md-6">
                    <button class="btn btn-primary" @click="checkStatus" v-if="order.status != 'Cancelled'">CHECK STATUS</button>
                    <button class="btn btn-danger" v-if="order.status == 'Open' && order.paid_amount <= 0" @click="cancelSO">Cancel SO</button>
                </div> 
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
                        <p>Order Completely Shipped, please close Sales Order manually in Navision and click on Check Status for AO.</p>
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
                                        <th>SO #</th>
                                        <th>SO Status</th>
                                        <th>Stock Point</th>
                                        <th>Batch No</th>
                                        <th>RRP</th>
                                        <th>Discount %</th>
                                        <th>Disc. Price</th>
                                        <th>Net Amount</th>
                                        <th>Tax Amount</th>
                                        <th>Qty</th>
                                        <th>Shipped Qty</th>
                                        <th v-if="order.status !='Complete' && order.status !='Shipped' && order.status != 'Cancelled' && order.paid_amount <= 0">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, index) in order.advance_lines" :key="line.id">
                                        <td>
                                            <span>{{line.product.category.display_name}} &raquo; {{line.product.collection.display_name}}<template v-if="line.product.colour"> &raquo; {{line.product.colour.display_name}}
                                                 </template>
                                            </span>
                                            <template v-if="!isCustomer"><br>
                                                <span class="product-category">{{line.product.category.code}} &raquo; {{line.product.collection.code}}
                                                    <template v-if="line.product.colour"> &raquo; {{line.product.colour.code}}
                                                    </template>
                                                </span>
                                            </template>
                                        </td>
                                        <td>{{line.navision_order_number}}</td>
                                        <td>{{line.order_status}}</td>
                                        <td>{{line.stock_point.name}}</td>
                                        <td>{{line.batch_no}}</td>
                                        <td>{{line.unit_price}}</td>
                                        
                                        <td>
                                            <template v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped' && line.shipped_quantity == 0 && order.paid_amount <= 0">
                                                <input type="text" class="form-control" style="width:60px;" v-model="line.discount" @keyup="calculateDiscountedPrice(line)" v-validate="{decimal: 2, min_value: 0, max_value: 100}" :data-vv-name="`discount${index}`" :name="`discount${index}`"></input>
                                                <span class="text-danger" v-show="errors.has(`discount${index}`)">Discount can not be negative or more than 100%.</span>
                                            </template>
                                            <template v-if="(line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped') || line.shipped_quantity > 0 || order.paid_amount > 0">{{discount(line)}}
                                            </template>
                                        </td>
                                       
                                     <!--   <td>{{discount(line)}}</td> -->
                                        
                                        <td>
                                            <template v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped' && line.shipped_quantity == 0 && order.paid_amount <= 0">
                                                <input type="text" class="form-control" style="width:60px;" v-model="line.discounted_price" @keyup="calculateDiscount(line)" v-validate="{decimal: 2, min_value: 1, max_value: line.unit_price}" :data-vv-name="`discounted_price${index}`" :name="`discounted_price${index}`"></input>
                                                <span class="text-danger" v-show="errors.has(`discounted_price${index}`)">Discounted Price can not be less than 1 or greater RRP.</span>
                                            </template>
                                            <template v-if="line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped' || line.shipped_quantity > 0 || order.paid_amount > 0">{{discountedPrice(line)}}
                                            </template>
                                        </td>
                                        <td>
                                            <template v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped'">
                                               {{calculateAmount(line)}}
                                            </template>
                                            <template v-if="line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped'">{{line.amount}}</template>
                                        </td>
                                        <td>{{line.tax_amount}}</td>
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
        </section>
    </div>
</template>
<script>
    import Vue from 'vue'
    import {EventBus} from '../../configs/EventBus'
    import AppMixins from '../../vuex-mixin'
    import OrderFields from './AdvanceOrderFields'
    import vuetableCssConfig from '../../configs/VuetableCssConfig'
    import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
    import Vuetable from '../../vendor/vuetable2/Vuetable'
    import VeeValidate from 'vee-validate'
    import converter from 'number-to-words'
    export default{
        mixins: [AppMixins],
        components:{
        },
        computed:{
            orderNetAmount(){
                if(!this.order)
                    return 0
                return _.reduce(this.order.advance_lines,function(memo,e){return parseFloat(e.net_amount)+memo},0)
            },
            orderAmountDecimal(){
                if(!this.order)
                    return 0
                return (this.orderNetAmount + "" ).replace(/\d+\./,'').padEnd(2,'0')
            },
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
            effectiveDealer(){
                if(this.isCustomer)
                {
                    if(this.dealer)
                        return this.dealer
                }
                if(this.customer_type == "DEALER")
                    return this.selectedCustomer
                else
                    return null
            },
            apiUrl(){
                if(this.effectiveDistributor)
                {
                    return `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDistributor.id}/advance_orders/${this.order.id}`
                }
                if(this.effectiveDealer)
                {
                    return `companies/${this.distributor.company.id}/dealers/${this.effectiveDealer.id}/advance_orders/${this.order.id}`
                }   
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
            },
            invoiceNumber(){
                if(!this.order.number)
                    return ""
                else
                {
                    if(this.order.ros_invoice_number && this.new_approach == 1)
                        return this.order.ros_invoice_number
                    else
                        return this.order.invoice_id
                }
            },
        },
        data(){
            return {
                order:{
                    id:this.$route.params.id,
                    number:"",
                    amount: 0,
                    advance_lines:[],
                    cancelation_reason:null,
                    customer:{
                        name:null,
                        email:null,
                        fax:null,
                        phone:null,
                        state:{
                            code:null,
                        },
                        tax_information:{
                            gst:{
                                number:null,
                            },
                            pan:{
                                number:null,
                            }
                        },
                        paymentTerms:{
                            code:null,
                            value:null,
                        },
                    },
                    address:{
                        address:null,
                        address2:null,
                        state_code:null,
                    },
                    invoice_id:null,
                    nav_invoice_number:null,
                    ros_invoice_number:null,
                },
                company:null,
                selectedCustomer:null,
                customer_type:null,
                cancelFlag:false,
                alert:false,
                new_approach:window.config.new_approach,
                loaded:false,
            }
        },
        filters: 
        {
            toWords: function (value) 
            {
                if (!value) return ''
                return converter.toWords(value);
            }
        },
        mounted(){
            let self = this
            EventBus.$emit('loader:show')
            Vue.axios.get(`advance_sales_orders/${this.$route.params.id}`)
            .then(response => {
                EventBus.$emit('loader:hide')
                let order = Object.assign({},response.data.ao)
                _.each(order.advance_lines,function(element){
                    element.original_quantity = (element.quantity).toFixed(2)
                    element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                })
                this.order = Object.assign({},order)
                this.company = Object.assign({}, response.data.ao.customer.company)
                this.selectedCustomer = Object.assign({}, response.data.ao.customer)
                this.customer_type = response.data.ao.customer_type
                this.alert = response.data.alert
                this.loaded = true
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
        methods:{
            checkStatus(){
                let url =""
                if(this.order.customer_type == 'DEALER')
                    url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/intransit_orders/${this.order.id}`

                EventBus.$emit('loader:show')
                Vue.axios.put(url)
                .then(response => {
                    EventBus.$emit('loader:hide')
                    let order = Object.assign({},response.data.ao)
                    _.each(order.advance_lines,function(element){
                        element.original_quantity = (element.quantity).toFixed(2)
                        element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                    })
                    this.order = Object.assign({},order)
                    this.company = Object.assign({}, response.data.ao.customer.company)
                    this.selectedCustomer = Object.assign({}, response.data.ao.customer)
                    this.customer_type = response.data.ao.customer_type
                    this.alert = response.data.alert
                })
            },
            calculateDiscountedPrice(item){
                item.discounted_price =  (item.unit_price - (item.unit_price * item.discount /100)).toFixed(2);
            },
            calculateDiscount(item){
                item.discount = ((item.unit_price - item.discounted_price )/ item.unit_price * 100).toFixed(2);
            },
            calculateAmount(item){
                return (item.quantity * item.discounted_price).toFixed(2)
            },
            calculateGrossAmount(item){
                return (item.quantity * item.unit_price).toFixed(2)
            },
            cancelSO(){
                this.cancelFlag = true
            },
            quitCancel(){
                this.cancelFlag = false
                this.order.cancelation_reason = null
            },
            confirmCancel(){
                let url = `companies/${this.order.customer.company.id}/distributors/${this.order.distributor_id}/advance_sales_orders/${this.order.id}`

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
                            _.each(order.advance_lines,function(element){
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
                            _.each(order.advance_lines,function(element){
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
            lineStatus(item){
                if(item.quantity == item.shipped_quantity)
                    return 'Shipped'; 
                return 'Pending'; 
            },
            discount(item){
                let disc = ((item.unit_price - item.discounted_price)/ item.unit_price * 100).toFixed(2)
                if(isNaN(disc))
                    return 0
                return disc
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