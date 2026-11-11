<template>
    <div v-if="abilities.viewDepotOrders">
        <section class="content-header">
            <h1>Order Details <small>{{order.number}}</small>
                <small class="pull-right" v-if="(!isCustomer) && order.customer">{{order.customer.name}} ({{order.customer.nav_id}})</small>
            </h1>
        </section>
        <section class="content">
            <div class="row">
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-aqua">
                        <div class="inner">
                            <h3>{{order.number}}</h3>
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
                <div class="col-md-8" v-if="order.status != 'Cancelled'">
                 <!--   <button class="btn btn-primary" @click="checkStatus">CHECK STATUS</button>
                    <button class="btn btn-default" @click="shipmentReceived" v-if="yetToReceive.length && isCustomer && order.relatedPO == null">SHIPMENT RECEIVED</button>-->
                    <button v-if="(order.status == 'Open' || order.status == 'Approved' || order.status == 'Pending_Approval') && !isCustomer" class="btn btn-danger" @click="cancelSO">Cancel PO</button>
                    <button v-if="(order.status == 'Complete' || order.status == 'Shipped' || order.status == 'Partially Shipped') && !isCustomer" class="btn btn-danger" @click="printDC">Print DC</button>
                </div>
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
                <template v-if="order.status == 'Cancelled' && !isCustomer">
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
                        <p>One or more Sales Order/s Completely Shipped, please close Sales Order manually in Navision and click on Check Status for PO.</p>
                    </div>
                </div>
            </div>
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
                                        <th>Qty</th>
                                        <th>Shipped Qty</th>
                                        <th>Received Qty</th>
                                        <th>Tax Amount</th>
                                        <th>Total Amount</th>
                                        <th v-if="!isCustomer && order.status !='Complete' && order.status != 'Cancelled' && order.status !='Shipped'">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, index) in order.purchase_lines" :key="line.id">
                                        <td>
                                            <span>{{line.product.category.display_name}} &raquo; {{line.product.collection.display_name}}<template v-if="line.product.colour"> &raquo; {{line.product.colour.display_name}}
                                                 </template>
                                            </span>
                                        </td>
                                        <td>{{line.navision_order_number}}</td>
                                        <td>{{line.order_status}}</td>
                                        <td>{{line.stock_point.name}}</td>
                                        <td>{{line.batch_no}}</td>
                                        <td>{{line.unit_price}}</td>
                                        <td>
                                            <template v-if="(!isCustomer) && (line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped') && line.shipped_quantity == 0">
                                                <input type="text" class="form-control" style="width:60px;" v-model="line.discount"  @keyup="calculateDiscountedPrice(line)"  v-validate="{decimal: 2, min_value: 0, max_value: 100}" :data-vv-name="`discount${index}`" :name="`discount${index}`"></input>
                                                <span class="text-danger" v-show="errors.has(`discount${index}`)">Discount can not be negative or more than 100%.</span>
                                            </template>
                                            <template v-if="(!isCustomer) && (line.order_status !='Complete' || line.order_status != 'Cancelled' || line.order_status !='Shipped') && line.shipped_quantity > 0">{{discount(line)}}
                                            </template>
                                            <template v-if="(!isCustomer) && line.order_status == 'Cancelled'">
                                                {{discount(line)}}
                                            </template>
                                            <template v-if="isCustomer">{{discount(line)}}
                                            </template>
                                        </td>
                                     <!--   <td>{{discount(line)}}</td>-->
                                        <td>{{discountedPrice(line)}}</td>
                                       <!-- <td>
                                            <template v-if="(!isCustomer) && (line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped') && line.shipped_quantity == 0">
                                                <input type="text" class="form-control" style="width:60px;" v-model="line.discounted_price" @keyup="calculateDiscount(line)" v-validate="{decimal: 2, min_value: 1, max_value: line.unit_price}" :data-vv-name="`discounted_price${index}`" :name="`discounted_price${index}`"></input>
                                                <span class="text-danger" v-show="errors.has(`discounted_price${index}`)">Discounted Price can not be less than 1 or greater RRP.</span>
                                            </template>
                                            <template v-if="(!isCustomer) && (line.order_status !='Complete' || line.order_status != 'Cancelled' || line.order_status != 'Shipped') && line.shipped_quantity > 0">{{discountedPrice(line)}}<span class="tax">(+{{line.tax_type_code}} @ {{parseFloat(line.tax_percent).toFixed(2)}}%)</span>
                                            </template>
                                            <template v-if="(!isCustomer) && line.order_status == 'Cancelled'">
                                                {{discountedPrice(line)}}<span class="tax">(+{{line.tax_type_code}} @ {{parseFloat(line.tax_percent).toFixed(2)}}%)</span>
                                            </template>
                                            <template v-if="isCustomer">{{discountedPrice(line)}}<span class="tax">(+{{line.tax_type_code}} @ {{parseFloat(line.tax_percent).toFixed(2)}}%)</span>
                                            </template>
                                        </td>-->
                                        <!--<td>{{line.amount}}</td>-->
                                        <td>
                                            <template v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped'">
                                               {{calculateAmount(line)}}
                                            </template>
                                            <template v-if="line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped'">{{line.amount}}</template>
                                        </td>
                                        <td class="form-group">
                                            <template v-if="(!isCustomer) && line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped' && line.shipped_quantity != line.original_quantity"><span class="label label-default pull-right">{{line.rate_type}}</span>
                                                <input type="text" class="form-control" style="width:90px;" v-model="line.quantity" v-validate="{ decimal: 2, min_value: (line.shipped_quantity).toFixed(2), max_value: line.original_quantity}" :data-vv-name="`quantity${index}`" :name="`quantity${index}`"></input>
                                            <span class="text-danger" v-show="errors.has(`quantity${index}`)">Quantity can not be less than Shipped Quantity or greater than Original Quantity {{line.original_quantity}}.</span>
                                            </template>
                                            <template v-if="(!isCustomer) && (line.order_status !='Complete' || line.order_status != 'Cancelled' && line.order_status != 'Shipped') && (line.original_quantity == line.shipped_quantity)">{{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                            </template>
                                            <template v-if="(!isCustomer) && line.order_status == 'Cancelled'">{{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                            </template>
                                            <template v-if="isCustomer">{{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                            </template>
                                        </td>
                                      <!--  <td>{{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                        </td>-->
                                        <td>{{line.shipped_quantity}}</td>
                                        <td>{{line.received_quantity}}</td>
                                        <td>
                                            <template v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped'">
                                               {{calculateTaxAmount(line)}}
                                            </template>
                                            <template v-if="line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped'">{{line.tax_amount}}</template>
                                        </td>
                                        <td>
                                            <template v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped'">
                                               {{calculateNetAmount(line)}}
                                            </template>
                                            <template v-if="line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped'">{{line.net_amount}}</template>
                                        </td>
                                        <td class="shrink" v-if="!isCustomer && line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped'">
                                            <div>
                                                <button class="btn btn-xs btn-success" :disabled="errors.has('quantity') || parseFloat(line.original_quantity) == parseFloat(line.shipped_quantity)" @click="updateLine(line)"><i class="fa fa-check-square"></i></button>
                                                <button class="btn btn-xs btn-danger" v-if="!line.shipped_quantity > 0" @click="deleteLine(line, index)"><i class="far fa-trash-alt"></i></button>
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
        <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">Update reveived items</h4>
                    </div>
                    <div class="modal-body">
                        <p>
                            Update received item quantity below. This will update your stock.
                        </p>
                        <table class="table table-bordered table-condensed">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Batch No</th>
                                    <th>Shipped Quantity</th>
                                    <th>Received Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in yetToReceive" :key="line.id">
                                    <td><span class="product-category">{{line.product.category.display_name}} &raquo; {{line.product.collection.display_name}}<template v-if="line.product.colour"> &raquo; {{line.product.colour.display_name}}</template></span> </td>
                                    <td>{{line.batch_no}}</td>
                                    <td>{{(line.shipped_quantity - line.received_quantity).toFixed(2)}}</td>
                                    <td :class="[errors.has(`line-${line.id}`)? 'has-error' : '']">
                                        <input type="text" class="form-control" v-model="line.received_now" v-validate="`decimal:2|max_value:${(line.shipped_quantity - line.received_quantity).toFixed(2)}`" :data-vv-name="`line-${line.id}`">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-sm btn-default" @click="updateShipment">Update</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    import Vue from 'vue'
    import {EventBus} from '../../configs/EventBus'
    import AppMixins from '../../vuex-mixin'
    import OrderFields from './OrderFields'
    import vuetableCssConfig from '../../configs/VuetableCssConfig'
    import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
    import Vuetable from '../../vendor/vuetable2/Vuetable'
    import VeeValidate from 'vee-validate'
    import { saveAs } from 'file-saver'
    export default{
        mixins: [AppMixins],
        computed:{
            orderNetAmount(){
                if(!this.order)
                    return 0
                return _.reduce(this.order.purchase_lines,function(memo,e){return parseFloat(e.net_amount)+memo},0)
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
                    return `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDistributor.id}/purchase_orders/${this.order.id}`
                }
                if(this.effectiveDealer)
                {
                    return `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/purchase_orders/${this.order.id}`
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
            yetToReceive(){
                return _.filter(this.order.purchase_lines,function(element){
                    return element.shipped_quantity && (element.shipped_quantity != element.received_quantity)
                })
            }
        },
        data(){
            return {
                order:{
                    id:this.$route.params.id,
                    number:"",
                    amount: "",
                    purchase_lines:[],
                    cancelation_reason:null,
                    customer:null
                },
                company:null,
                selectedCustomer:null,
                customer_type:null,
                cancelFlag:false,
                alert:false,
            }
        },
        mounted(){
            let self = this
            EventBus.$emit('loader:show')
            let url = `depot_orders/${this.$route.params.id}`
            if(this.$route.query.tenant)
            {
                url = `depot_orders/${this.$route.params.id}?tenant=${this.$route.query.tenant}`
            }
            Vue.axios.get(url)
            .then(response => {
                EventBus.$emit('loader:hide')
                let order = Object.assign({},response.data.po)
                _.each(order.purchase_lines,function(element){
                    element.received_now = (element.shipped_quantity - element.received_quantity).toFixed(2)
                    element.original_quantity = (element.quantity).toFixed(2)
                    element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                })
                this.order = Object.assign({},order)
                this.company = Object.assign({}, response.data.po.customer.company)
                this.selectedCustomer = Object.assign({}, response.data.po.customer)
                this.customer_type = response.data.po.customer_type
                this.alert = response.data.alert
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
            updateShipment(){
                let url =""
                if(this.order.customer_type == 'DISTRIBUTO')
                    url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/purchase_orders/${this.order.id}/shipment`
                if(this.order.customer_type == 'DEALER')
                    url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/purchase_orders/${this.order.id}/shipment`

                let self = this
                
                this.$validator.validateAll()
                .then(value => {
                    if(value)
                    {
                        let input = [];
                        let lines = _.each(self.yetToReceive,function(element){
                            if(element.received_now)
                            {
                                input.push({
                                    id:element.id,
                                    quantity:element.received_now
                                })
                            }
                        })
                        $(self.$refs.modal).modal('hide');
                        EventBus.$emit('loader:show')
                        Vue.axios.post(url,input)
                        .then(response => {
                            EventBus.$emit('loader:hide')
                            let order = Object.assign({},response.data)
                            _.each(order.purchase_lines,function(element){
                                element.received_now = (element.shipped_quantity - element.received_quantity).toFixed(2)
                                element.original_quantity = (element.quantity).toFixed(2)
                                element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                            })
                            self.order = Object.assign({},order)
                        })
                    }
                    else
                    {
                        swal({
                            title: "Error",
                            text:"Make sure that you have entered correct quantity for items received.",
                            icon:'error'
                        })
                    }
                })
            },
            shipmentReceived(){
                $(this.$refs.modal).modal('show');
            },
            checkStatus(){
                let url =""
                if(this.order.customer_type == 'DISTRIBUTO')
                    url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/purchase_orders/${this.order.id}`
                if(this.order.customer_type == 'DEALER')
                    url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/purchase_orders/${this.order.id}`

                EventBus.$emit('loader:show')
                Vue.axios.put(url)
                .then(response => {
                    EventBus.$emit('loader:hide')
                    let order = Object.assign({},response.data.po)
                    _.each(order.purchase_lines,function(element){
                        element.received_now = element.shipped_quantity - element.received_quantity
                        element.original_quantity = (element.quantity).toFixed(2)
                        element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                    })
                    this.order = Object.assign({},order)
                    this.alert = response.data.alert
                })
            },
            calculateDiscountedPrice(item){
                item.discounted_price =  (item.unit_price - (item.unit_price * item.discount /100)).toFixed(2);
            },
            calculateDiscount(item){
                item.discount = ((item.unit_price - item.discounted_price )/ item.unit_price * 100).toFixed(2);
            },
            discount(item){
                let disc = ((item.unit_price - item.discounted_price)/ item.unit_price * 100).toFixed(2)
                if(isNaN(disc))
                    return 0
                return disc
              //  return ((item.unit_price - item.discounted_price)/ item.unit_price * 100).toFixed(2);
            },
            discountedPrice(item){
               return parseFloat(item.unit_price - (item.unit_price * item.discount /100)).toFixed(2)
                //return (item.unit_price - (item.unit_price * item.discount /100)).toFixed(2);
            },
            calculateAmount(item){
                return parseFloat(this.discountedPrice(item) * item.quantity).toFixed(2)
        //        return (item.quantity * item.discounted_price).toFixed(2)
            },
            calculateTaxAmount(item){
                return parseFloat(((this.discountedPrice(item) * item.tax_percent)/100 * item.quantity).toFixed(2))
               // return ((this.discountedPrice(item) * item.tax_percent)/100 * item.quantity).toFixed(2)
            },
            calculateNetAmount(item){
                return (parseFloat(this.calculateAmount(item)) + parseFloat(this.calculateTaxAmount(item))).toFixed(2)
           //     return (this.calculateAmount(item) + this.calculateTaxAmount(item)).toFixed(2)
             //   return 100
            },
            printDC(){
                let self = this
                let url = ""
                if(this.$route.query.tenant)
                    url = `orders/${this.order.id}/depot_print_dc?tenant=${this.$route.query.tenant}`
                else
                    url = `orders/${this.order.id}/print_dc`

                EventBus.$emit('loader:show')
                Vue.axios.get(url)
                .then(response => {
                    EventBus.$emit('loader:hide')
                    if(response.data.pdf != null)
                    {
                        var base64str = response.data.pdf;
                        // decode base64 string, remove space for IE compatibility
                        var binary = atob(base64str.replace(/\s/g, ''));
                        var len = binary.length;
                        var buffer = new ArrayBuffer(len);
                        var view = new Uint8Array(buffer);
                        for (var i = 0; i < len; i++) {
                            view[i] = binary.charCodeAt(i);
                        }
                        // create the blob object with content-type "application/pdf"               
                        var blob = new Blob( [view], { type: "application/pdf" });
                        saveAs(blob, 'delivery-challan.pdf');
                    }
                    else
                    {
                        swal({
                                title: "Nothing to print!",
                                text:"All the items have been printed previously.",
                                icon:'error'
                            })
                    }
                })  
                .catch(error => {
                    console.log(error)
                })
            },
            cancelSO(){
                this.cancelFlag = true
            },
            quitCancel(){
                this.cancelFlag = false
                this.order.cancelation_reason = null
            },
            confirmCancel(){
                let url =""
                if(this.order.distributor_id)//This order will not be visible to Depot Manager or Superadmin. need to check if this is required
                {
                    if(this.$route.query.tenant)
                    {
                        url = `companies/${this.order.customer.company.id}/distributors/${this.order.distributor_id}/depot_sales_orders/${this.order.id}?tenant=${this.$route.query.tenant}`
                    }
                    else
                        url = `companies/${this.order.customer.company.id}/distributors/${this.order.distributor_id}/sales_orders/${this.order.id}`
                }
                else
                {
                    if(this.order.customer_type == 'DISTRIBUTO')
                    {
                        if(this.$route.query.tenant)
                        {
                            url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/depot_purchase_orders/${this.order.id}?tenant=${this.$route.query.tenant}`
                        }
                        else
                            url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/purchase_orders/${this.order.id}`
                    }
                    if(this.order.customer_type == 'DEALER')
                    {
                        if(this.$route.query.tenant)
                        {
                            url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/depot_purchase_orders/${this.order.id}?tenant=${this.$route.query.tenant}`
                        }
                        else
                            url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/purchase_orders/${this.order.id}`
                    }
                }
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
                        if(error.response.status == 412)
                        {
                            let order = Object.assign({},error.response.data.order)
                            _.each(order.purchase_lines,function(element){
                                element.received_now = element.shipped_quantity - element.received_quantity
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
                        if(error.response.status == 503)
                        {

                            let order = Object.assign({},error.response.data.order)
                            _.each(order.purchase_lines,function(element){
                                element.received_now = element.shipped_quantity - element.received_quantity
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
                        if(error.response.status == 400)
                        {
                            swal({
                                title: error.response.data.error_text,
                                icon:'error',
                            })
                            return
                        }
                        if(error.response.status == 500)
                        {
                           swal({
                                title:'Oops...',
                                text:"Something went wrong while processing your request. Please contact your Relationship Manager.",
                                icon:'error',
                            })
                            return
                        }
                    })
                })
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
                        let url = ""
                        if(this.order.customer_type == 'DISTRIBUTO')
                        {
                            if(this.$route.query.tenant)
                            {
                                url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/depot_purchase_orders/${this.order.id}?tenant=${this.$route.query.tenant}`
                            }
                            else
                            {
                                url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/purchase_orders/${this.order.id}`
                            }
                        }
                        if(this.order.customer_type == 'DEALER')
                        {
                            if(this.$route.query.tenant)
                            {
                                url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/depot_purchase_orders/${this.order.id}?tenant=${this.$route.query.tenant}`
                            }
                            else
                            {
                                url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/purchase_orders/${this.order.id}`
                            }
                        }
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
                            if(error.response.status == 412)
                            {
                                let order = Object.assign({},error.response.data.order)
                                _.each(order.purchase_lines,function(element){
                                    element.received_now = element.shipped_quantity - element.received_quantity
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
                            if(error.response.status == 503)
                            {
                                let order = Object.assign({},error.response.data.order)
                                _.each(order.purchase_lines,function(element){
                                    element.received_now = element.shipped_quantity - element.received_quantity
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
                            if(error.response.status == 400)
                            {
                                swal({
                                    title: error.response.data.error_text,
                                    icon:'error',
                                })
                                return
                            }
                            if(error.response.status == 500)
                            {
                               swal({
                                    title:'Oops...',
                                    text:"Something went wrong while processing your request. Please contact your Relationship Manager.",
                                    icon:'error',
                                })
                                return
                            }
                        })
                    })
                }
                else
                {
                    let url =""
                    if(this.order.relatedPO)//#797
                    {
                        if(this.$route.query.tenant)
                        {
                            url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/depot_sales_orders/${line.purchase_order_id}/sales_lines/${line.id}?tenant=${this.$route.query.tenant}`
                        }
                        else
                        {
                            url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/sales_orders/${line.purchase_order_id}/sales_lines/${line.id}`
                        }
                    }
                    else
                    {
                        if(this.order.customer_type == 'DISTRIBUTO')
                        {
                            if(this.$route.query.tenant)
                            {
                                url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/depot_purchase_orders/${line.purchase_order_id}/purchase_lines/${line.id}?tenant=${this.$route.query.tenant}`
                            }
                            else
                            {
                                url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/purchase_orders/${line.purchase_order_id}/purchase_lines/${line.id}`
                            }
                        }
                        if(this.order.customer_type == 'DEALER')
                        {
                            if(this.$route.query.tenant)
                            {
                                url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/depot_purchase_orders/${line.purchase_order_id}/purchase_lines/${line.id}?tenant=${this.$route.query.tenant}`
                            }
                            else
                            {
                                url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/purchase_orders/${line.purchase_order_id}/purchase_lines/${line.id}`
                            }
                        }
                    }
                    EventBus.$emit('loader:show')
                    Vue.axios.delete(url)
                    .then(response => {
                        EventBus.$emit('loader:hide')
                        this.order.purchase_lines.splice(index,1)
                        let order = Object.assign({},response.data.po)
                        _.each(order.purchase_lines,function(element){
                            element.received_now = element.shipped_quantity - element.received_quantity
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
                        if(error.response.status == 412)
                        {
                            this.cancelFlag = false

                            let order = Object.assign({},error.response.data.order)
                            _.each(order.purchase_lines,function(element){
                                element.received_now = element.shipped_quantity - element.received_quantity
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
                            this.cancelFlag = false

                            let order = Object.assign({},error.response.data.order)
                            _.each(order.purchase_lines,function(element){
                                element.received_now = element.shipped_quantity - element.received_quantity
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
                        if(error.response.status == 400)
                        {
                            swal({
                                title: error.response.data.error_text,
                                icon:'error',
                            })
                            return
                        }
                        if(error.response.status == 500)
                        {
                           swal({
                                title:'Oops...',
                                text:"Something went wrong while processing your request. Please contact your Relationship Manager.",
                                icon:'error',
                            })
                            return
                        }
                    })
                }
            },
            updateLine(line){
                let input = {
                    discount:line.discount,
                    quantity:line.quantity,
                    nav_order_no:line.navision_order_number
                }
                let url = ""
                if(this.order.distributor_id)
                {
                    url = `companies/${this.order.customer.company.id}/distributors/${this.order.distributor_id}/sales_orders/${line.purchase_order_id}/sales_lines/${line.id}`
                }
                else
                {
                    if(this.effectiveDistributor)
                    {
                        if(this.$route.query.tenant)
                        {
                            url = `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDistributor.id}/depot_purchase_orders/${line.purchase_order_id}/purchase_lines/${line.id}?tenant=${this.$route.query.tenant}`
                        }
                        else
                        {
                            url = `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDistributor.id}/purchase_orders/${line.purchase_order_id}/purchase_lines/${line.id}`
                        }
                    }
                    else if(this.effectiveDealer)
                    {
                        if(this.$route.query.tenant)
                        {
                            url = `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/depot_purchase_orders/${line.purchase_order_id}/purchase_lines/${line.id}?tenant=${this.$route.query.tenant}`
                        }
                        else
                        {
                            url = `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/purchase_orders/${line.purchase_order_id}/purchase_lines/${line.id}`
                        }
                    }
                }
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
                                element.received_now = element.shipped_quantity - element.received_quantity
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
                                    element.received_now = element.shipped_quantity - element.received_quantity
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
                                    element.received_now = element.shipped_quantity - element.received_quantity
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
                            if(error.response.status == 400)
                            {
                                swal({
                                    title: error.response.data.error_text,
                                    icon:'error',
                                })
                                return
                            }
                            if(error.response.status == 500)
                            {
                               swal({
                                    title:'Oops...',
                                    text:"Something went wrong while processing your request. Please contact your Relationship Manager.",
                                    icon:'error',
                                })
                                return
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
.tax {
    display: block;
    color: #888;
    font-size: 11px;
}
</style>