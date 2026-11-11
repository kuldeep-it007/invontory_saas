<template>
    <div v-if="abilities.viewPurchaseOrders">
        <section class="content-header">
            <h1>Order Details<small>{{order.number}}</small>
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
                    <button class="btn btn-primary" v-if="order.status == 'Shipped'" @click="print">Print</button>
                    <button class="btn btn-primary" @click="checkStatus" v-if="order.status != 'Cancelled'">CHECK STATUS</button>
                </div>
                <template v-if="order.status == 'Cancelled'">
                    <div class="col-md-12">
                        <div class="alert alert-danger">
                            <p><strong>Cancellation Reason:</strong> {{order.cancelation_reason}}</p>
                        </div>
                        <div class="text-right"><strong>Canceled by:</strong> {{order.canceled_by}}</div>
                    </div>
                </template>
                <template v-if="order.remarks">
                    <div class="col-md-12">
                        <div class="alert alert-warning" style="margin-top : 10px; margin-bottom : 0px; padding-top: 10px;padding-bottom: 2px;">
                            <p><strong >Customer Remarks :</strong> {{order.remarks}}</p>
                        </div>
                    </div>
                </template>
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
                                        <th>Stock Point</th>
                                        <th>Batch No</th>
                                        <th>RRP</th>
                                        <th>Discount %</th>
                                        <th>Disc. Price</th>
                                        <th>Net Amount</th>
                                        <th>Qty</th>
                                        <th>Shipped Qty</th>
                                        <th v-if="order.status =='Shipped'">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, index) in order.purchase_lines" :key="line.id">
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
                                        <td>{{line.stock_point.name}}</td>
                                        <td>{{line.batch_no}}</td>
                                        <td>{{line.unit_price}}</td>
                                        <td>
                                            <template v-if="(!isCustomer) && (order.status !='Complete' && order.status != 'Cancelled') && line.shipped_quantity == 0 && line.quantity != 0">
                                                <input type="text" class="form-control" style="width:60px;" v-model="line.discount"></input>
                                            </template>
                                            <template v-if="(!isCustomer) && (order.status !='Complete' || order.status != 'Cancelled') && line.shipped_quantity > 0">{{line.discount}}
                                            </template>
                                            <template v-if="(!isCustomer) && order.status == 'Cancelled'">
                                                {{line.discount}}
                                            </template>
                                            <template v-if="isCustomer">{{line.discount}}
                                            </template>
                                        </td>
                                        <td>{{discountedPrice(line)}}</td>
                                        <td>{{line.amount}}</td>
                                        <td class="form-group">
                                            <template v-if="(!isCustomer) && order.status !='Complete' && order.status != 'Cancelled' && line.shipped_quantity != line.original_quantity"><span class="label label-default pull-right">{{line.rate_type}}</span>
                                                <input type="text" class="form-control" style="width:90px;" v-model="line.quantity" v-validate="{ decimal: 2, min_value: (line.shipped_quantity).toFixed(2), max_value: line.original_quantity}" :data-vv-name="`quantity${index}`" :name="`quantity${index}`"></input>
                                            <span class="text-danger" v-show="errors.has(`quantity${index}`)">Quantity can not be less than Shipped Quantity or greater than Original Quantity.</span>
                                            </template>
                                            <template v-if="(!isCustomer) && (order.status !='Complete' || order.status != 'Cancelled') && (line.original_quantity == line.shipped_quantity)">{{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                            </template>
                                            <template v-if="(!isCustomer) && order.status == 'Cancelled'">{{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                            </template>
                                            <template v-if="isCustomer">{{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                            </template>
                                        </td>
                                        <td>{{line.shipped_quantity}}</td>
                                        <td class="shrink" v-if="order.status =='Shipped'">
                                            <div>
                                                <button class="btn btn-xs btn-success" @click="returnQty(line)"><i class="fas fa-undo-alt"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <return-stock-modal v-if="company"
                ref="returnStockModal"
                @stock-returned="onStockReturn"
                :line="selectedLine"
                :companyId="this.company.id"
                :distributorId="this.dealer.distributor.id"
                :dealerId="this.dealer.id">
            </return-stock-modal>
        </section>
        <div ref="printElement" class="visible-print purchase-order" v-if="loaded">
            <div style="position:absolute;width:250px;right: 0;top:20px;">
                <img src="/images/rosetta.png" style="width:250px;">
            </div>
            <table style="width: 50%;">
                <tr>
                    <td>
                        <h1 style="font-size: 18px;">{{this.dealer.distributor.name}}</h1><br>
                        <p><b>Office: </b>{{this.dealer.distributor.address}} {{this.dealer.distributor.address2}} {{this.dealer.distributor.state.code}} {{this.dealer.distributor.zipcode}}</b></p>
                        <p><b>Contact No: </b>{{this.dealer.distributor.phone}}</p>
                        <p><b>Email: </b>{{this.dealer.distributor.email}}</p>
                    </td>
                </tr>
            </table>
            <br>
            <table class="table table-bordered">
                <tr>
                    <td v-if="order.customer.company.region.country_code == 'IN'" colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <b>GSTIN No {{this.dealer.distributor.tax_information.gst.number}}</b>
                    </td>
                    <td v-if="order.customer.company.region.country_code == 'AE'" colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <b>TIN No {{this.dealer.distributor.tax_information.vat.number}}</b>
                    </td>
                    <td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <b>PAN No. {{this.dealer.distributor.tax_information.pan.number}}</b>
                    </td>
                </tr>
                <tr>
                    <td colspan="10" style="width: 50%;border-color: #000 !important;padding-left: 4px;" class="text-center"><b>PROFORMA INVOICE</b></td>
                </tr>
                <tr>
                     <td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <p><b>Proforma Invoice To: </b></p>
                        <p><b>{{order.customer.name}}</b></p>
                        <p>{{order.customer.address}} {{order.customer.address2}} {{order.customer.state_code}}</p>
                        <p v-if="order.customer.company.region.country_code == 'IN'">
                            <b>GSTIN/UniqID {{order.customer.tax_information.gst.number}}</b></p>
                        <p v-if="order.customer.company.region.country_code == 'AE'">
                            <b>TIN/UniqID {{order.customer.tax_information.vat.number}}</b></p>
                    </td>
                     <td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <p><b>Proforma Invoice No: </b>{{invoiceNumber}}</p>
                        <p><b>Order No: </b> {{order.number}} </p>
                        <p><b>Order Date: </b> {{orderDate}}</p>
                        <p><b>Payment Terms: </b> {{order.customer.paymentTerms.value}}</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <p><b>Ship To: </b></p>
                        <p><b>{{order.customer.name}}</b></p>
                        <p>{{order.address.address}} {{order.address.address2}} {{order.address.state_code}}</p>
                        <p v-if="order.customer.company.region.country_code == 'IN'">
                            <b>GSTIN/UniqID {{order.customer.tax_information.gst.number}}</b></p>
                        <p v-if="order.customer.company.region.country_code == 'AE'">
                            <b>TIN/UniqID {{order.customer.tax_information.vat.number}}</b></p>
                    </td>
                     <td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <p><b>Ship To Contact: </b>{{order.customer.contact}} | {{order.customer.phone}}</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;vertical-align:top;">
                        <p><b>Remarks: </b>{{order.remarks}}</p>
                    </td>
                    <td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <p><b>Transporter Name: </b></p>
                        <p><b>Vehicle No.: </b></p>
                        <p><b>Transp. Method: </b></p>
                        <br>
                        <p><b>LR /RR No.: </b></p>
                        <p><b>LR /RR Date.: </b></p>
                        <br>
                    </td>
                </tr>
                <tr>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                       <b>Sr No</b>
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        <b>PRODUCT DESCRIPTION</b>
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        <b>STOCK LOCATION</b>
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        <b>STATUS</b>
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        <b>QTY</b>
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        <b>RATE</b>
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        <b>UOM</b>
                    </td>
                 <!--   <td style="border-color: #000 !important;padding-left: 4px;">
                        <b>GROSS AMOUNT</b>
                    </td>-->
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        <b>NET AMOUNT</b>
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        <b v-if="order.customer.company.region.country_code == 'AE'">VAT (%)</b>
                        <b v-if="order.customer.company.region.country_code == 'IN'">GST (%)</b>
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        <b>TOTAL AMOUNT</b>
                    </td>
                </tr>

                <tr v-for="(line,index) in order.purchase_lines" :key="line.id">
                    <td style="border-color: #000 !important;padding-left: 4px;">
                       <b>{{index+1}}</b>
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        {{line.product.category.display_name}} {{line.product.collection.display_name}} <template v-if="line.product.colour"> {{line.product.colour.display_name}}</template>
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        {{line.stock_point.name}} 
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        {{lineStatus(line)}} 
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        {{line.quantity}} 
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                       {{discountedPrice(line)}}
                    <!--    {{line.unit_price}} #635-->
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        {{line.product.base_unit_of_measure}}
                    </td>
                 <!--   <td style="border-color: #000 !important;padding-left: 4px;">
                        {{calculateGrossAmount(line)}}
                    </td>-->
                    <td style="border-color: #000 !important;padding-left: 4px;">
                       {{calculateAmount(line)}}
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        {{line.tax_percent}}
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                       <!-- {{calculateAmount(line)}}-->
                        {{line.net_amount}}
                    </td>
                </tr>
                <tr>
                    <td colspan="5" style="border-color: #000 !important;padding-left: 4px;"><b>Total Amount</b></td>
                    <td colspan="5" style="border-color: #000 !important;padding-left: 4px;" class="text-right"><b>{{orderNetAmount.toFixed(2)}}</b></td>
                </tr>
            </table>
            <table>
                <tr>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        <h1 style="font-size: 18px;">
                            <b>AMOUNT IN WORDS: **** 
                                <span style="font-weight:bold;text-transform: uppercase;">{{ orderNetAmount.toFixed(2) | toWords}}</span> {{order.customer.company.region.currency_major}} 
                                <span v-if="orderAmountDecimal != 0" style="font-weight:bold;text-transform: uppercase;">
                                    AND {{orderAmountDecimal | toWords}} {{order.customer.company.region.currency_minor}} 
                                </span> ONLY
                            </b>
                        </h1>
                    </td>
                </tr>
            </table>
         <div style="position: absolute;bottom: 0;text-align: right;width:100%;">{{order.nav_invoice_number}}</div>
        </div>
        
    </div>
</template>
<script>
    import Vue from 'vue'
    import {EventBus} from '../../../configs/EventBus'
    import AppMixins from '../../../vuex-mixin'
    import OrderFields from './OrderFields'
    import vuetableCssConfig from '../../../configs/VuetableCssConfig'
    import VuetablePagination from '../../../vendor/vuetable2/VuetablePagination'
    import Vuetable from '../../../vendor/vuetable2/Vuetable'
    import VeeValidate from 'vee-validate'
    import { Printd } from 'printd'
    import returnStockModal from './returnStockModal'
    import converter from 'number-to-words'
  //  var converter = require('number-to-words');
    export default{
        mixins: [AppMixins],
        computed:{
            orderNetAmount(){
                if(!this.order)
                    return 0
                return _.reduce(this.order.purchase_lines,function(memo,e){return parseFloat(e.net_amount)+memo},0)
            },
            orderAmountDecimal(){
                if(!this.order)
                    return 0
               // return this.order.amount % 10;
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
            invoiceNumber(){
                if(!this.order.number)
                    return ""
                else
                {
                    if(this.order.ros_invoice_number)
                        return this.order.ros_invoice_number
                    else if(this.order.invoice_id)
                        return this.order.invoice_id
                    else
                        return ""

                }
            },
        },
        components:{
            returnStockModal
        },
        data(){
            return {
                order:{
                    id:this.$route.params.id,
                    number:"",
                    amount: 0,
                    purchase_lines:[],
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
                    invoice_id:null
                },
                company:null,
                selectedCustomer:null,
                customer_type:null,
                cancelFlag:false,
                alert:false,
                selectedLine:null,
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
            Vue.axios.get(`purchase_orders/${this.$route.params.id}`)
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
           /* invoice_series(){
                let temp = ''
                if(this.order)
                {
                   let number = this.order.customer.distributor.invoice_series + 1
                 temp = ("00000" + number).slice(-5)
                }

                let fiscalyear = "";
                let today = new Date();
                if ((today.getMonth() + 1) <= 3)
                {
                    fiscalyear = (today.getFullYear() - 1) + "-" + today.getFullYear()
                } 
                else 
                {
                    fiscalyear = today.getFullYear() + "-" + (today.getFullYear() + 1)
                }

                let code = this.order.customer.distributor.nav_id
                let ros_invoice_number = "PI/" + fiscalyear + "/" + code.slice(5) + "/" + temp
                if(!this.order.ros_invoice_number)
                    return ros_invoice_number
                else
                    return this.order.ros_invoice_number
            },*/
            print(){
                    let d = new Printd()
                    let bootstrapCss = window.config.api_url.replace('api/','')+'vendors/bootstrap/css/bootstrap.min.css'
                    d.print(this.$refs.printElement,[bootstrapCss, `
                    table{
                                font-family: "system-ui",Arial;
                    font-size:14px;
                            }
                    `])
            },
            discountedPrice(item){
                return (item.unit_price - (item.unit_price * item.discount /100)).toFixed(2);
            },
            lineStatus(item){
                if(item.quantity == item.shipped_quantity)
                    return 'Shipped'; 
                return 'Pending'; 
            },
            calculateAmount(item){
                return (item.quantity * item.discounted_price).toFixed(2)
            },
            calculateGrossAmount(item){
                return (item.quantity * item.unit_price).toFixed(2)
            },
            returnQty(line){
                this.selectedLine = line
                this.$refs.returnStockModal.show(line)
            },
            onStockReturn(returnItem){
               this.selectedLine.returnItems.push(returnItem)
            },
            checkStatus(){
                let url =""
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
                    this.company = Object.assign({}, response.data.po.customer.company)
                    this.selectedCustomer = Object.assign({}, response.data.po.customer)
                    this.customer_type = response.data.po.customer_type
                    this.alert = response.data.alert
                    this.loaded = true
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
.foo
{
    position: absolute;
    bottom: 0;
    right: 10px;
    text-align: right;
    width: 100%;
}
</style>