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
                   
                    <button class="btn btn-primary" @click="checkStatus" v-if="order.status != 'Cancelled' && this.is_super_stockist_stockpoint==false">CHECK STATUS</button>
                    <button class="btn btn-primary blink-effect" @click="shipAndInvoice" v-if="(order.status != 'Cancelled' && order.status != 'Complete') && this.is_super_stockist_stockpoint==true">SHIP & INVOICE</button>
                    <!-- PENDING-->
                    <button class="btn btn-danger" v-if="(order.status == 'Open' || order.status == 'Approved') && order.paid_amount <= 0" @click="cancelSO">Cancel SO</button>
                    
                    <!-- PENDING 
                    <button class="btn btn-success" v-if="order.status != 'Cancelled' && order.paid_amount < order.amount" @click="addPayment">Add Payment</button>
                    -->
                    <!-- PENDING
                    <button class="btn btn-primary" v-if="order.status != 'Cancelled' && order.shipped == false && order.all_company_lines_shipped == true && order.all_codistributor_lines_shipped == true" @click="print_ship" key="print_ship">Print & Ship</button>
                    -->
                    <!-- PENDING
                    <button class="btn btn-primary" v-if="new_approach == 0 && (order.status == 'Shipped' || order.shipped == true)" @click="print"  key="print">Print</button>
                    <button class="btn btn-primary" v-if="new_approach == 1 && order.status != 'Cancelled' && order.status != 'Open'" @click="print"  key="new_print">Print</button>
                    -->
                </div>
                <div class="col-md-6" v-if="order.status != 'Cancelled'">
                    <div class="col-md-8 col-md-offset-4" style="padding-right: 0;">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Enter Invoice Number" v-model="order.invoice_id">
                        <span class="input-group-btn">
                            <button class="btn btn-primary" type="button" :disabled="!order.invoice_id" @click="addInvoice">Save</button>
                        </span>
                    </div><!-- /input-group -->
                </div>    
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
                                        <th v-if="order.status !='Complete' && order.status !='Shipped' && order.status != 'Cancelled' && order.paid_amount <= 0">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, index) in order.purchase_lines" :key="line.id" >
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
                                            <template>
                                                <p v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped' && line.shipped_quantity == 0 && order.paid_amount <= 0 && (line.stock_point.type==state.user.type && line.stock_point.id==state.user.id)">
                                                <input type="text" class="form-control" style="width:60px;" v-model="line.discount" @keyup="calculateDiscountedPrice(line)" v-validate="{decimal: 2, min_value: 0, max_value: 100}" :data-vv-name="`discount${index}`" :name="`discount${index}`">
                                                <span class="text-danger" v-show="errors.has(`discount${index}`)">Discount can not be negative or more than 100%.</span>
                                                </p>
                                                <!-- <p class="form-control" style="border:0px" v-else>{{discount(line)}}</p> -->
                                            </template>
                                           
                                            <template v-if="(line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped') || line.shipped_quantity > 0 || order.paid_amount > 0">{{discount(line)}}
                                            </template>
                                        </td>
                                       
                                     <!--   <td>{{discount(line)}}</td> -->
                                        
                                        <td>
                                            <template>
                                                <p v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped' && line.shipped_quantity == 0 && order.paid_amount <= 0 && (line.stock_point.type==state.user.type && line.stock_point.id==state.user.id)">
                                                   <input type="text" class="form-control" style="width:60px;" v-model="line.discounted_price" @keyup="calculateDiscount(line)" v-validate="{decimal: 2, min_value: 1, max_value: line.unit_price}" :data-vv-name="`discounted_price${index}`" :name="`discounted_price${index}`">
                                                <span class="text-danger" v-show="errors.has(`discounted_price${index}`)">Discounted Price can not be less than 1 or greater RRP.</span> 
                                                </p>
                                                <!-- <p class="form-control" style="border:0px" v-else>{{discountedPrice(line)}}</p>                                                 -->
                                            </template>
                                            <template v-if="line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped' || line.shipped_quantity > 0 || order.paid_amount > 0">{{discountedPrice(line)}}
                                            </template>
                                        </td>
                                        
                                       <!-- <td>{{discountedPrice(line)}}</td>-->
                                        <td>
                                            <template v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped'">
                                                <p class="form-control" style="border:0px">{{calculateAmount(line)}}</p>
                                            </template>
                                            <template v-if="line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped'">{{line.amount}}</template>
                                        </td>
                                        <td class="form-group">
                                            <template>
                                                <p v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped' && line.shipped_quantity != line.original_quantity && order.paid_amount <= 0 && (line.stock_point.type==state.user.type && line.stock_point.id==state.user.id)">
                                                <span class="label label-default pull-right">{{line.rate_type}}</span>
                                                <input type="text" class="form-control" style="width:60px;" v-model="line.quantity" v-validate="{ decimal: 2, min_value: (line.shipped_quantity).toFixed(2), max_value: line.original_quantity}" :data-vv-name="`quantity${index}`" :name="`quantity${index}`">
                                            <span class="text-danger" v-show="errors.has(`quantity${index}`)">Quantity can not be greater than Original Quantity {{line.original_quantity}} or less than Shipped Quantity.</span>
                                            </p>
                                            <p class="form-control" style="border:0px" v-else>{{line.quantity}}</p>  
                                            </template>
                                            <template v-if="(line.order_status =='Complete' || line.order_status == 'Cancelled' || line.order_status == 'Shipped') && (parseFloat(line.original_quantity) == parseFloat(line.shipped_quantity)) || order.paid_amount > 0"><span class="label label-default pull-right">{{line.rate_type}}</span>
                                            </template>
                                        </td>
                                        <td><p class="form-control" style="border:0px">{{line.shipped_quantity}}</p></td>
                                        <td class="shrink" v-if="order.paid_amount <= 0">
                                            <div>
                                                <template v-if="line.order_status !='Complete' && line.order_status != 'Cancelled' && line.order_status != 'Shipped' && (line.stock_point.type==state.user.type && line.stock_point.id==state.user.id)">
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
                :company="this.superStockist.company"
                :distributor="this.superStockist"
                :order="this.order">
            </payment-modal>
        </section>
        <div ref="printElement" class="visible-print purchase-order" v-if="loaded">
            <div style="position:absolute;width:250px;right: 0;top:20px;">
                <img src="/images/rosetta.png" style="width:250px;">
            </div>
            <table style="width: 50%;">
                <tr>
                    <td>
                        <h1 style="font-size: 18px;">{{this.superStockist.name}}</h1><br>
                        <p><b>Office: </b>{{this.superStockist.address}} {{this.superStockist.address2}} {{this.superStockist.state.code}} {{this.superStockist.zipcode}}</p>
                        <p><b>Contact No: </b>{{this.superStockist.phone}}</p>
                        <p><b>Email: </b>{{this.superStockist.email}}</p>
                    </td>
                </tr>
            </table>
            <br>
            <table class="table table-bordered">
                <tr>
                    <td v-if="order.customer.company.region.country_code == 'IN'" colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <b>GSTIN No {{this.superStockist.tax_information.gst.number}}</b>
                    </td>
                    <td v-if="order.customer.company.region.country_code == 'AE'" colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <b>TIN No {{this.superStockist.tax_information.vat.number}}</b>
                    </td>
                    <td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <b>PAN No. {{this.superStockist.tax_information.pan.number}}</b>
                    </td>
                </tr>
                <tr>
                    <td colspan="10" style="width: 50%;border-color: #000 !important;padding-left: 4px;" class="text-center"><b>PROFORMA INVOICE</b></td>
                </tr>
                <tr>
                    <td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <p><b>Proforma Invoice To: </b></p>
                        <p><b>{{order.customer.name}}</b></p>
                        <p>{{order.customer.address}} {{order.customer.address2}} {{order.customer.state.code}}</p>
                        <p v-if="order.customer.company.region.country_code == 'IN'">
                            <b>GSTIN/UniqID {{order.customer.tax_information.gst.number}}</b></p>
                        <p v-if="order.customer.company.region.country_code == 'AE'">
                            <b>TIN/UniqID {{order.customer.tax_information.vat.number}}</b></p>
                    </td>
                    <td colspan="5" style="width: 50%;border-color: #000 !important;padding-left: 4px;">
                        <p><b>Proforma Invoice No: </b>{{invoiceNumber}}</p>
                        <p><b>Sales Order No: </b>{{orderNumber}}</p>
                        <p><b>Purchase Order No: </b>{{order.number}}</p>
                        <p><b>Order Date: </b>{{orderDate}}</p>
                        <!--<p><b>Payment Terms: </b> {{order.customer.paymentTerms.value}}</p>-->
                        
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
                       <!-- {{line.unit_price}} #635-->
                    </td>
                    <td style="border-color: #000 !important;padding-left: 4px;">
                        {{line.product.base_unit_of_measure}}
                    </td>
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
                <!--    <td><b>&nbsp;</b></td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td><b>&nbsp;</b></td>
                    <td><b>&nbsp;</b></td>-->
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
    import {EventBus} from '../../configs/EventBus'
    import AppMixins from '../../vuex-mixin'
    import OrderFields from './OrderFields'
    import vuetableCssConfig from '../../configs/VuetableCssConfig'
    import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
    import Vuetable from '../../vendor/vuetable2/Vuetable'
    import VeeValidate from 'vee-validate'
    import PaymentModal from './PaymentModal'
    import { Printd } from 'printd'
    import converter from 'number-to-words'
    export default{
        mixins: [AppMixins],
        components:{
            PaymentModal,
        },
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
                    if(this.superStockist)
                        return this.superStockist.company
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
                    return `companies/${this.distributor.company.id}/dealers/${this.effectiveDealer.id}/purchase_orders/${this.order.id}`
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
                 /*   if(this.order.invoice_id)
                        return this.order.invoice_id
                    else if(this.order.ros_invoice_number)
                        return this.order.ros_invoice_number
                    else
                        return ""*/

                }
            },
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
                is_super_stockist_stockpoint: false
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
            this.is_super_stockist_stockpoint = false
            EventBus.$emit('loader:show')
            Vue.axios.get(`sales_orders/${this.$route.params.id}`)
            .then(response => {
                EventBus.$emit('loader:hide')
                let order = Object.assign({},response.data.po)
                _.each(order.purchase_lines,function(element){
                //    element.received_now = (element.shipped_quantity - element.received_quantity).toFixed(2)
                    element.original_quantity = (element.quantity).toFixed(2)
                    element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                })
                this.order = Object.assign({},order)
                this.company = Object.assign({}, response.data.po.customer.company)
                this.selectedCustomer = Object.assign({}, response.data.po.customer)
                this.customer_type = response.data.po.customer_type
                this.alert = response.data.alert
                this.loaded = true
                this.checkStockPoint();
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
         /*   invoice_series(){
                if(!this.order.customer.name)
                    return
                let temp = ''
                if(this.order.customer.name)
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
                let temp_ros_invoice_number = "PI/" + fiscalyear + "/" + code.slice(5) + "/" + temp
                if(!this.order.ros_invoice_number)
                    this.order.ros_invoice_number = temp_ros_invoice_number
                return this.order.ros_invoice_number
            },*/
            checkStatus(){
                let url =""
                if(this.order.customer_type == 'DEALER')
                    url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/purchase_orders/${this.order.id}`

                if(this.order.customer_type == 'DISTRIBUTO')
                    url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/purchase_orders/${this.order.id}`

                EventBus.$emit('loader:show')
                Vue.axios.put(url)
                .then(response => {
                    EventBus.$emit('loader:hide')
                    let order = Object.assign({},response.data.po)
                    _.each(order.purchase_lines,function(element){
                 //       element.received_now = element.shipped_quantity - element.received_quantity
                        element.original_quantity = (element.quantity).toFixed(2)
                        element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                    })
                    this.order = Object.assign({},order)
                    this.company = Object.assign({}, response.data.po.customer.company)
                    this.selectedCustomer = Object.assign({}, response.data.po.customer)
                    this.customer_type = response.data.po.customer_type
                    this.alert = response.data.alert
                })
            },
            addInvoice(){
                let self = this
                let url = `companies/${self.order.customer.company.id}/distributors/${self.order.distributor_id}/sales_orders/${self.order.id}/addInvoice`

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
                let url = `companies/${self.order.customer.company.id}/distributors/${self.order.distributor_id}/sales_orders/${self.order.id}/ship`

                swal({
                    title: 'Print & Ship',
                    text: 'Assumption made is that the Dealer\'s credit days should start from the time you click on the "Print & Ship" button. Please ensure you have entered the Invoice Number issued to Dealer.',
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
                        invoice_id:self.order.invoice_id,
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
                        let d = new Printd()
                        let bootstrapCss = window.config.api_url.replace('api/','')+'vendors/bootstrap/css/bootstrap.min.css'
                        d.print(this.$refs.printElement,[bootstrapCss,`
                            table{
                                        font-family: "system-ui",Arial;
                            font-size:14px;
                                    }
                            `])
                    })
                    .catch(error => {})
                })
            },
            print(){
                    let d = new Printd()
                    let bootstrapCss = window.config.api_url.replace('api/','')+'vendors/bootstrap/css/bootstrap.min.css'
                    d.print(this.$refs.printElement,[bootstrapCss, `
                    table{
                                font-family: "system-ui",Verdana;
                    font-size:14px;
                            }
                    `])
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
            paymentAdded(payment_amount)
            {
               // this.order.remaining_amount = (this.order.remaining_amount - payment_amount).toFixed(2)
                //this.order.paid_amount = (this.order.paid_amount + payment_amount).toFixed(2)
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
                let url = `companies/${this.order.customer.company.id}/distributors/${this.order.distributor_id}/sales_orders/${this.order.id}`

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
                           //     element.received_now = element.shipped_quantity - element.received_quantity
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
                           //     element.received_now = element.shipped_quantity - element.received_quantity
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
              //  return ((item.unit_price - item.discounted_price)/ item.unit_price * 100).toFixed(2);
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
                        let url = `companies/${this.order.customer.company.id}/distributors/${this.order.distributor_id}/sales_orders/${this.order.id}`

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
                                 //   element.received_now = element.shipped_quantity - element.received_quantity
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
                               //     element.received_now = element.shipped_quantity - element.received_quantity
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
                    let url = `companies/${this.order.customer.company.id}/distributors/${this.order.distributor_id}/sales_orders/${line.purchase_order_id}/sales_lines/${line.id}`
                    EventBus.$emit('loader:show')
                    Vue.axios.delete(url)
                    .then(response => {
                        EventBus.$emit('loader:hide')
                        this.order.purchase_lines.splice(index,1)
                        let order = Object.assign({},response.data.po)
                        _.each(order.purchase_lines,function(element){
                         //   element.received_now = element.shipped_quantity - element.received_quantity
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
                             //   element.received_now = element.shipped_quantity - element.received_quantity
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
                             //   element.received_now = element.shipped_quantity - element.received_quantity
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
                    nav_order_no:line.navision_order_number
                }
                let url = `companies/${this.order.customer.company.id}/superstockists/${this.order.super_stockist_id}/sales_orders/${line.purchase_order_id}/sales_lines/${line.id}`
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
                        //        element.received_now = element.shipped_quantity - element.received_quantity
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
                                //    element.received_now = element.shipped_quantity - element.received_quantity
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
                               //     element.received_now = element.shipped_quantity - element.received_quantity
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
            shipAndInvoice(){             
                let url = ""
                if (this.order.customer_type == 'DEALER')
                    // url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/ship_invoice/${this.order.id}`
                    url = `companies/${this.order.customer.company.id}/super_stockists/${this.order.customer.id}/ship_invoice/${this.order.id}`

                if (this.order.customer_type == 'DISTRIBUTO')
                    url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/ship_invoice/${this.order.id}`

                if (this.order.customer_type == 'SUPER_STOCKIST')
                    url = `companies/${this.order.customer.company.id}/super_stockists/${this.order.customer.id}/ship_invoice/${this.order.id}`

                EventBus.$emit('loader:show')
                Vue.axios.put(url)
                .then(response => {
                    EventBus.$emit('loader:hide')
                    let order = Object.assign({},response.data.po)
                    _.each(order.purchase_lines,function(element){
                 //       element.received_now = element.shipped_quantity - element.received_quantity
                        element.original_quantity = (element.quantity).toFixed(2)
                        element.discounted_price = ((element.unit_price) - (element.discount * element.unit_price/100)).toFixed(2)
                    })
                    this.order = Object.assign({},order)
                    this.company = Object.assign({}, response.data.po.customer.company)
                    this.selectedCustomer = Object.assign({}, response.data.po.customer)
                    this.customer_type = response.data.po.customer_type
                    this.alert = response.data.alert
                })
            },
            checkStockPoint(){
                let ss_stockpoint_id = null;
                let dist_stockpoint_id = null;
                if (Array.isArray(this.order.purchase_orders) && this.order.purchase_orders.length > 0) {
                    let purchaseOrder = this.order.purchase_orders.find(x => x.blanket_order_id === this.order.id);
                    if (purchaseOrder) {
                        ss_stockpoint_id = this.order.purchase_orders.map(order => order.ss_stock_point_id);
                        ss_stockpoint_id = [...new Set(ss_stockpoint_id)];

                        dist_stockpoint_id = this.order.purchase_orders.map(order => order.dist_stock_point_id);
                        dist_stockpoint_id = [...new Set(dist_stockpoint_id)];
                    }
                }
                this.is_super_stockist_stockpoint = (ss_stockpoint_id && ss_stockpoint_id.length > 0)?ss_stockpoint_id.includes(this.state.user.id):false;
            }
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

@keyframes blink {
  0% {
    background-color: black;
    color: white;
  }
  50% {
    background-color: transparent;
    color: black;
  }
  100% {
    color: white;
    background-color: black;
  }
}

.blink-effect {
  animation: blink 1s infinite;
  color: black;
}
</style>