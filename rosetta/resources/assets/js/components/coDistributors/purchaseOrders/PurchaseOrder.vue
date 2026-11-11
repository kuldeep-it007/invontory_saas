<template>
    <div v-if="abilities.viewSalesOrders && order">
        <section class="content-header">
            <h1>Order Details<small>{{order.number}}</small>
                <small class="pull-right">{{order.co_distributor.name}} ({{order.co_distributor.nav_id}})</small>
            </h1>
        </section>
        <section class="content">
            <div class="row">
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-aqua">
                        <div class="inner">
                           <!-- <h3>{{order.number}}</h3>-->
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
             <div class="row" v-if="order.related_order" style="margin-top: 10px;margin-bottom: 10px;">
                <div class="col-md-12">
                    <div class="alert alert-warning">
                        This order is related to SO <b>{{relatedOrderNumber}}</b> placed by <b>{{order.related_order.customer.name}}</b> on <b>{{relatedOrderDate}}</b>
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
         <!--    <div class="row">
               <div class="col-md-6">
                    <button class="btn btn-primary" @click="checkStatus" v-if="order.status != 'Cancelled' && order.status != 'Shipped'">CHECK STATUS</button>
                    <button class="btn btn-danger" v-if="order.status == 'Open' && order.paid_amount <= 0" @click="cancelSO">Cancel SO</button>
                    <button class="btn btn-success" v-if="order.status != 'Cancelled' && order.paid_amount < order.amount" @click="addPayment">Add Payment</button>
                    <button class="btn btn-primary" v-if="order.status != 'Shipped' && order.status != 'Cancelled' && order.shipped == false && order.all_company_lines_shipped == true" @click="print_ship">Print & Ship</button>
                    <button class="btn btn-primary" v-if="order.status == 'Shipped' || order.shipped == true" @click="print">Print</button>
                </div>-->
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
        
                
            </div>        -->
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
            <h2>Items</h2>
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-widget">
                        <div class="box-body table-responsive">
                            <table class="table table-bordered table-condensed">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Co Distributor</th>
                                        <th>Batch No</th>
                                        <th>RRP</th>
                                        <th>Discount %</th>
                                        <th>Disc. Price</th>
                                        <th>Amount</th>
                                        <th>Qty</th>
                                        <th>Shipped Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, index) in order.purchase_lines" :key="line.id">
                                        <td>
                                            <span>{{line.product.category.display_name}} &raquo; {{line.product.collection.display_name}}<template v-if="line.product.colour"> &raquo; {{line.product.colour.display_name}}
                                                 </template>
                                            </span>
                                        </td>
                                        <td>{{order.co_distributor.name}}</td>
                                        <td>{{line.batch_no}}</td>
                                        <td>{{line.unit_price}}</td>
                                        <td>
                                           {{discount(line)}}
                                        </td>
                                        <!--<td>{{discountedPrice(line)}}</td>-->
                                        <td>
                                            {{discountedPrice(line)}}
                                        </td>
                                        <td>
                                            {{calculateAmount(line)}}
                                        </td>
                                        <td class="form-group">
                                            {{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                        </td>
                                        <td>{{line.shipped_quantity}}</td>
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
    import {EventBus} from '../../../configs/EventBus.js'
    import AppMixins from '../../../vuex-mixin'
    import VeeValidate from 'vee-validate'
    export default{
        mixins: [AppMixins],
        components:{
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
            relatedOrderNumber(){
                return this.order.related_order.number.replace('PO', 'SO')
            },
            relatedOrderDate(){
                return moment.unix(this.order.related_order.created_at).format('DD MMM YYYY')
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
            }
        },
        mounted(){
            this.getOrder()
        },
        methods:{
            getOrder(){
                let self = this
                let url = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors/orders/${this.$route.params.id}?type=1`
                EventBus.$emit('loader:show')
                Vue.axios.get(url)
                .then(response => {
                    EventBus.$emit('loader:hide')
                    let order = Object.assign({},response.data)
                    _.each(order.purchase_lines,function(element){
                    //    element.received_now = (element.shipped_quantity - element.received_quantity).toFixed(2)
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
                        let d = new Printd()
                        let bootstrapCss = window.config.api_url.replace('api/','')+'vendors/bootstrap/css/bootstrap.min.css'
                        d.print(this.$refs.printElement,[bootstrapCss,`
                            table{
                                        font-family: "system-ui",Verdana;
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
                let url = `companies/${this.order.customer.company.id}/distributors/${this.order.distributor_id}/sales_orders/${line.purchase_order_id}/sales_lines/${line.id}`
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