<template>
    <div v-if="abilities.viewAdvanceOrders">
        <section class="content-header">
            <h1>Order Details <small>{{order.number}} ({{order.so}})</small>
            <small class="pull-right" v-if="(!isCustomer) && order.customer">{{order.customer.name}} ({{order.customer.nav_id}})</small>
            </h1>
        </section>
        <section class="content">
            <div class="row">
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-aqua">
                        <div class="inner">
                            <h3>{{order.number}}</h3>
                            <p>AO No.</p>
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
                    <button class="btn btn-primary" @click="checkStatus">CHECK STATUS</button>
                    <button class="btn btn-default" @click="shipmentReceived" v-if="yetToReceive.length && isCustomer && order.relatedAO == null">SHIPMENT RECEIVED</button>
                    <button v-if="(order.status == 'Open' || order.status == 'Approved' || order.status == 'Pending_Approval') && !isCustomer && NavPoPresent == false" class="btn btn-danger" @click="cancelSO">Cancel AO</button>
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
                                        <th>SO #</th>
                                        <th>SO Status</th>
                                        <th>Stock Point</th>
                                        <th>Batch No</th>
                                        <th>Qty</th>
                                        <th>Shipped Qty</th>
                                        <th>Received Qty</th>
                                        <th>RRP</th>
                                        <th>Discount %</th>
                                        <th>Disc. Price</th>
                                        <th>Net Amount</th>
                                        <th>Tax Amount</th>
                                        <th>Total Amount</th>
                                        <th>ETA</th>
                                        <th v-if="order.status !='Complete' && order.status != 'Cancelled' && order.status !='Shipped'">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, index) in order.advance_lines" :key="line.id">
                                        <td>
                                            <span>{{line.product.category.display_name}} &raquo; {{line.product.collection.display_name}}     <template v-if="line.product.colour"> &raquo; {{line.product.colour.display_name}}
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
                                        <td>
                                            <template v-if="line.stock_point">{{line.stock_point.name}}
                                            </template>
                                            <template v-if="line.stock_point == null">
                                            </template>
                                        </td>
                                        <td>
                                            <template v-if="line.stock_point">{{line.batch_no}}
                                            </template>
                                            <template v-if="line.stock_point == null">
                                            </template>
                                        </td>
                                        <td class="form-group">
                                            <template v-if="(!isCustomer) && order.status !='Complete' && order.status != 'Cancelled' && line.order_status !='Shipped' && line.nav_po_no == null"><span class="label label-default pull-right">{{line.rate_type}}</span>
                                             <!--   <input type="text" class="form-control" style="width:90px;" v-model="line.quantity" :name="`quantity${index}`"></input>#873-->


                                                <input type="text" class="form-control" style="width:90px;" v-model="line.quantity" v-validate="{ decimal: 2, min_value: (line.shipped_quantity).toFixed(2), max_value: line.original_quantity}" :data-vv-name="`quantity${index}`" :name="`quantity${index}`"></input>
                                            <span class="text-danger" v-show="errors.has(`quantity${index}`)">Quantity can not be less than Shipped Quantity or greater than Original Quantity {{line.original_quantity}}.</span>


                                            </template>
                                            <template v-if="(!isCustomer) && (order.status !='Complete' || order.status != 'Cancelled') && line.nav_po_no != null">{{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                            </template>
                                            <template v-if="(!isCustomer) && order.status == 'Cancelled'">{{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                            </template>
                                            <template v-if="isCustomer">{{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>
                                            </template>
                                           <!-- {{line.quantity}}<span class="label label-default pull-right">{{line.rate_type}}</span>-->
                                        </td>
                                        <td>{{line.shipped_quantity}}</td>
                                        <td>{{line.received_quantity}}</td>
                                        <td>{{line.unit_price}}</td>
                                        <td>
                                            <template v-if="(!isCustomer) && (order.status !='Complete' && order.status != 'Cancelled' && line.order_status != 'Shipped') && line.nav_po_no == null && line.shipped_quantity == 0">
                                                <input type="text" class="form-control" style="width:60px;" v-model="line.discount"></input>
                                            </template>
                                            <template v-if="(!isCustomer) && (order.status !='Complete' || order.status != 'Cancelled' || line.order_status !='Shipped') && line.nav_po_no != null">{{line.discount}}
                                            </template>
                                            <template v-if="(!isCustomer) && order.status == 'Cancelled'">
                                                {{line.discount}}
                                            </template>
                                            <template v-if="isCustomer">{{line.discount}}
                                            </template>
                                           <!-- {{line.discount}}-->
                                        </td>
                                        <td>{{discountedPrice(line)}}<span class="tax">(+{{line.tax_type_code}} @ {{parseFloat(line.tax_percent).toFixed(2)}}%)</span></td>
                                        <td>{{line.amount}}</td>
                                        <td>{{line.tax_amount}}</td>
                                        <td>{{line.net_amount}}</td>
                                        <td>
                                            <template v-if="line.eta">
                                                {{line.eta|format_date_unix}}
                                            </template>
                                        </td>
                                        <td v-if="order.status !='Complete' && order.status != 'Cancelled'">
                                            <div>
                                                <template v-if="(!isCustomer) && line.shipped_quantity == 0">
                                                    <!--<a href="#" class="action text-blue" v-if="abilities.updateAdvanceLines && line.nav_po_no == null" @click.prevent="updateLine(line)"><i class="fa fa-pencil-alt"></i></a>#873-->
                                                    <button class="btn btn-xs btn-success" v-if="abilities.updateAdvanceLines && line.nav_po_no == null" :disabled="errors.has('quantity') || parseFloat(line.original_quantity) == parseFloat(line.shipped_quantity)" @click="updateLine(line)"><i class="fa fa-check-square"></i></button>
                                                <!--    <a href="#" class="action text-red" v-if="abilities.deleteAdvanceLines && line.nav_po_no == null" @click.prevent="deleteLine(line, index)"><i class="fa fa-trash-alt"></i></a>#873-->
                                                    <button class="btn btn-xs btn-danger" v-if="abilities.deleteAdvanceLines && line.nav_po_no == null && !line.shipped_quantity > 0" @click="deleteLine(line, index)"><i class="far fa-trash-alt"></i></button>
                                                <!--    <a href="#" class="action text-green" v-if="abilities.updateAdvanceLines" @click.prevent="setETA(line)">ETA</a>#873-->
                                                </template>
                                                <a href="#" class="action" v-if="abilities.viewAdvanceMessages" @click.prevent="chat(line)"><i class="fa fa-comments" aria-hidden="true"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <line-chat-modal
                ref="lineChatModal"
                :line="selectedLine"
                :effective-company="company"
                :effective-distributor="effectiveDistributor"
                :effective-super-stockist="effectiveSuperStockist"
                :effective-dealer="effectiveDealer">
            </line-chat-modal>
            <line-eta-modal
                ref="lineEtaModal"
                @eta-set="onEtaSet"
                :line="selectedLine"
                :effective-company="company"
                :effective-distributor="effectiveDistributor"
                :effective-super-stockist="effectiveSuperStockist"
                :effective-dealer="effectiveDealer">
            </line-eta-modal>
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
    import lineEtaModal from '../AdvanceOrders/lineEtaModal'
    import lineChatModal from '../AdvanceOrders/lineChatModal'
    import AdvanceOrderFields from './InTransitOrderFields'
    import vuetableCssConfig from '../../configs/VuetableCssConfig'
    import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
    import Vuetable from '../../vendor/vuetable2/Vuetable'
    import VeeValidate from 'vee-validate'
    export default{
        mixins: [AppMixins],
        components:{
            lineEtaModal,
            lineChatModal,
        },
        computed:{
            effectiveCompany(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor.company
                    if(this.superStockist)
                        return this.superStockist.company
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
            effectiveSuperStockist(){
                if(this.isCustomer)
                {
                    if(this.superStockist)
                        return this.superStockist
                }
                if(this.customer_type == "SUPER_STOCKIST")
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
       /*     apiUrl(){
                if(this.effectiveDistributor)
                {
                    return `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDistributor.id}/advance_orders/${this.order.id}`
                }
                if(this.effectiveDealer)
                {
                    return `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/advance_orders/${this.order.id}`
                }   
            },*/
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
                return _.filter(this.order.advance_lines,function(element){
                    return element.shipped_quantity && (element.shipped_quantity != element.received_quantity)
                })
            },
            NavPoPresent(){
                let poPresent = false
                _.filter(this.order.advance_lines,function(element){
                    if(element.nav_po_no != null)
                        poPresent = true
                })
                return poPresent;
            },
        /*    orderNetAmount(){
                if(!this.order)
                    return 0
                return _.reduce(this.order.advance_lines,function(memo,e){return parseFloat(e.net_amount)+memo},0)
            },*/

            orderNetAmount(){
                if(!this.order)
                    return 0
                return _.reduce(this.order.advance_lines,function(memo,e){
                    if(e.order_status == 'Cancelled')
                        return memo + 0
                    return parseFloat(e.net_amount)+memo
                },0)
            }
        },
        data(){
            return {
                order:{
                    id:this.$route.params.id,
                    number:"",
                    amount: "",
                    advance_lines:[],
                    cancelation_reason:null,
                    customer:null
                },
                company:null,
                selectedCustomer:null,
                customer_type:null, 
                cancelFlag:false,
                selectedLine:null,
                alert:false
            }
        },
        mounted(){
            let self = this
            EventBus.$emit('loader:show')
            Vue.axios.get(`intransit_orders/${this.$route.params.id}`)
            .then(response => {
                EventBus.$emit('loader:hide')
                let order = Object.assign({},response.data.ao)
                _.each(order.advance_lines,function(element){
                    element.received_now = (element.shipped_quantity - element.received_quantity).toFixed(2)
                    element.original_quantity = (element.quantity).toFixed(2)
                })
                this.order = Object.assign({},order)
                this.company = Object.assign({}, response.data.ao.customer.company)
                this.selectedCustomer = Object.assign({}, response.data.ao.customer)
                this.customer_type = response.data.ao.customer_type
                this.alert = response.data.alert
                if(self.$route.query.line)
                {
                    let line = _.findWhere(self.order.advance_lines,{id:parseInt(self.$route.query.line)})
                    if(line)
                    {
                        self.chat(line)
                    }
                }
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
                    url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/advance_orders/${this.order.id}/shipment`
                if(this.order.customer_type == 'SUPER_STOCKIST')
                    url = `companies/${this.order.customer.company.id}/super_stockists/${this.order.customer.id}/advance_orders/${this.order.id}/shipment`
                if(this.order.customer_type == 'DEALER')
                    url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/advance_orders/${this.order.id}/shipment`

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
                            _.each(order.advance_lines,function(element){
                                element.received_now = (element.shipped_quantity - element.received_quantity).toFixed(2)
                                element.original_quantity = (element.quantity).toFixed(2)
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
                    url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/intransit_orders/${this.order.id}`
                if(this.order.customer_type == 'SUPER_STOCKIST')
                    url = `companies/${this.order.customer.company.id}/super_stockists/${this.order.customer.id}/intransit_orders/${this.order.id}`
                if(this.order.customer_type == 'DEALER')
                    url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/intransit_orders/${this.order.id}`

                EventBus.$emit('loader:show')
                Vue.axios.put(url)
                .then(response => {
                    EventBus.$emit('loader:hide')
                    let order = Object.assign({},response.data.ao)
                    _.each(order.advance_lines,function(element){
                        element.received_now = element.shipped_quantity - element.received_quantity
                        element.original_quantity = (element.quantity).toFixed(2)
                    })
                    this.order = Object.assign({},order)
                    this.alert = response.data.alert
                })
            },
            discountedPrice(item){
                return (item.unit_price - (item.unit_price * item.discount /100)).toFixed(2);
            },
            setETA(line){
                this.selectedLine = line
                this.$refs.lineEtaModal.show(line)
            },
            onEtaSet(line){
                this.selectedLine.eta = line.eta
            },
           /* itemsLoaded(response){
                let self = this
                if(self.$route.query.line)
                {
                    let line = _.findWhere(response.data.lines,{id:parseInt(self.$route.query.line)})
                    if(line)
                    {
                        self.chat(line)
                    }
                }
            },*/
            chat(line){
                this.selectedLine = line
                this.$refs.lineChatModal.show()
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
                if(this.order.customer_type == 'DISTRIBUTO')
                    url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/intransit_orders/${this.order.id}`
                if(this.order.customer_type == 'SUPER_STOCKIST')
                    url = `companies/${this.order.customer.company.id}/super_stockists/${this.order.customer.id}/intransit_orders/${this.order.id}`
                if(this.order.customer_type == 'DEALER')
                    url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/intransit_orders/${this.order.id}`

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
                            _.each(order.advance_lines,function(element){
                                element.received_now = element.shipped_quantity - element.received_quantity
                                element.original_quantity = (element.quantity).toFixed(2)
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
                            _.each(order.advance_lines,function(element){
                                element.received_now = element.shipped_quantity - element.received_quantity
                                element.original_quantity = (element.quantity).toFixed(2)
                            })
                            this.order = Object.assign({},order)
                            this.cancelFlag = false
                            swal({
                                title: "Warning",
                                text:error.response.data.error_text,
                                icon:'warning'
                            })
                        }
                    })
                })
            },
            deleteLine(line, index){
                if(this.order.advance_lines.length == 1)
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
                            url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/intransit_orders/${this.order.id}`
                        if(this.order.customer_type == 'SUPER_STOCKIST')
                            url = `companies/${this.order.customer.company.id}/super_stockists/${this.order.customer.id}/intransit_orders/${this.order.id}`
                        if(this.order.customer_type == 'DEALER')
                            url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/intransit_orders/${this.order.id}`
                        EventBus.$emit('loader:show')
                        let input = {
                            cancelation_reason:"Cancelled because last Line was deleted."
                        }
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
                                _.each(order.advance_lines,function(element){
                                    element.received_now = element.shipped_quantity - element.received_quantity
                                    element.original_quantity = (element.quantity).toFixed(2)
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
                                _.each(order.advance_lines,function(element){
                                    element.received_now = element.shipped_quantity - element.received_quantity
                                    element.original_quantity = (element.quantity).toFixed(2)
                                })
                                this.order = Object.assign({},order)
                                this.cancelFlag = false
                                swal({
                                    title: "Warning",
                                    text:error.response.data.error_text,
                                    icon:'warning'
                                })
                            }
                        })
                    })
                }
                else
                {
                    let url =""
                    if(this.order.distributor_id)
                    {
                        url = `companies/${this.order.customer.company.id}/distributors/${this.order.distributor_id}/intransit_orders/${line.advance_order_id}/advance_lines/${line.id}`
                    }
                    else if(this.order.relatedAO)
                    {
                        url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/intransit_orders/${line.advance_order_id}/advance_lines/${line.id}`
                    }
                    else
                    {
                        if(this.order.customer_type == 'DISTRIBUTO')
                            url = `companies/${this.order.customer.company.id}/distributors/${this.order.customer.id}/intransit_orders/${line.advance_order_id}/advance_lines/${line.id}`
                        if(this.order.customer_type == 'SUPER_STOCKIST')
                            url = `companies/${this.order.customer.company.id}/super_stockists/${this.order.customer.id}/intransit_orders/${line.advance_order_id}/advance_lines/${line.id}`
                        if(this.order.customer_type == 'DEALER')
                            url = `companies/${this.order.customer.company.id}/dealers/${this.order.customer.id}/intransit_orders/${line.advance_order_id}/advance_lines/${line.id}`
                    }
                    EventBus.$emit('loader:show')
                    Vue.axios.delete(url)
                    .then(response => {
                        EventBus.$emit('loader:hide')
                        this.order.advance_lines.splice(index,1)
                        let order = Object.assign({},response.data.ao)
                        _.each(order.advance_lines,function(element){
                            element.received_now = element.shipped_quantity - element.received_quantity
                            element.original_quantity = (element.quantity).toFixed(2)
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
                            _.each(order.advance_lines,function(element){
                                element.received_now = element.shipped_quantity - element.received_quantity
                                element.original_quantity = (element.quantity).toFixed(2)
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
                            _.each(order.advance_lines,function(element){
                                element.received_now = element.shipped_quantity - element.received_quantity
                                element.original_quantity = (element.quantity).toFixed(2)
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
                    url = `companies/${this.order.customer.company.id}/distributors/${this.order.distributor_id}/advance_orders/${line.advance_order_id}/advance_lines/${line.id}`
                }
                else
                {
                    if(this.effectiveDistributor)
                        url = `companies/${this.effectiveCompany.id}/distributors/${this.effectiveDistributor.id}/advance_orders/${line.advance_order_id}/advance_lines/${line.id}/update`
                    if(this.effectiveSuperStockist)
                        url = `companies/${this.effectiveCompany.id}/super_stockists/${this.effectiveSuperStockist.id}/advance_orders/${line.advance_order_id}/advance_lines/${line.id}/update`
                    else if(this.effectiveDealer)
                        url = `companies/${this.effectiveCompany.id}/dealers/${this.effectiveDealer.id}/advance_orders/${line.advance_order_id}/advance_lines/${line.id}/update`
                }
                this.$validator.validateAll()
                .then(value => {
                    if(value)
                    {  
                        EventBus.$emit('loader:show')
                        Vue.axios.put(url,input)
                        .then(response => {
                            EventBus.$emit('loader:hide')
                            let order = Object.assign({},response.data.ao)
                            _.each(order.advance_lines,function(element){
                                element.received_now = element.shipped_quantity - element.received_quantity
                                element.original_quantity = (element.quantity).toFixed(2)
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
                                _.each(order.advance_lines,function(element){
                                    element.received_now = element.shipped_quantity - element.received_quantity
                                    element.original_quantity = (element.quantity).toFixed(2)
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
                                _.each(order.advance_lines,function(element){
                                    element.received_now = element.shipped_quantity - element.received_quantity
                                    element.original_quantity = (element.quantity).toFixed(2)
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
.action{
    font-size: 11px;
}
.tax {
    display: block;
    color: #888;
    font-size: 11px;
}
</style>