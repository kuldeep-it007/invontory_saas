<template>
    <aside class="main-sidebar">
        <section class="sidebar">
            <ul class="sidebar-menu" data-widget="tree">
                <li v-if="isCustomer && !(this.dealer && this.dealer.distributor_id != null)">
                    <router-link :to="{name:'order-products'}"><i class="fa fa-gift"></i> <span>Search & Order Products</span></router-link>
                </li>
                <li v-if="isCustomer && this.dealer && this.dealer.distributor_id != null">
                    <router-link :to="{name:'order-distributor-products'}"><i class="fa fa-gift"></i> <span>Search & Order Products</span></router-link>
                </li>
               <!-- <li v-if="isCustomer && !(this.dealer && this.dealer.distributor_id != null)">
                    <router-link :to="{name:'stock'}"><i class="fa fa-box-open"></i> <span>My Stock</span></router-link>
                </li>-->
                <li v-if="isCustomer && this.distributor">
                    <router-link :to="{name:'stock'}"><i class="fa fa-box-open"></i> <span>My Stock</span></router-link>
                </li>
                <li v-if="isCustomer && this.superStockist">
                    <router-link :to="{name:'SuperStockistStock'}"><i class="fa fa-box-open"></i> <span>My Stock</span></router-link>
                </li>
                <li v-if="!isCustomer && this.state.user.company != null && abilities.viewCompanyStock">
                    <router-link :to="{name:'company-stock'}"><i class="fa fa-box-open"></i> <span>Search & View Products</span></router-link>
                </li>
             <!--   <li v-if="abilities.viewAlternateStock && !(this.dealer && this.dealer.distributor_id != null)">
                    <router-link :to="{name:'companies.stockpoints'}"><i class="fa fa-store"></i> <span>Alternate Stockpoints</span></router-link>
                </li>-->
                <li v-if="abilities.viewAlternateStock && this.distributor">
                    <router-link :to="{name:'companies.stockpoints'}"><i class="fa fa-store"></i> <span>Alternate Stockpoints</span></router-link>
                </li>

                <li v-if="abilities.viewPurchaseOrders && (!isCustomer)">
                    <router-link :to="{name:'orders'}"><i class="fa fa-inbox"></i> 
                        <span v-if="!isCustomer">PO's</span></router-link>
                </li>

                <li v-if="abilities.viewPurchaseOrders && !(this.dealer && this.dealer.distributor_id != null) && (this.distributor && this.distributor.super_stockist_id != null)">
                    <router-link :to="{name:'orders'}"><i class="fa fa-inbox"></i> 
                        <span v-if="isCustomer">My PO's to SS</span>
                        <span v-if="!isCustomer">PO's</span>
                    </router-link>
                </li>

                <li v-if="abilities.viewPurchaseOrders && (this.distributor && this.distributor.super_stockist_id == null)">
                    <router-link :to="{name:'orders'}"><i class="fa fa-inbox"></i> 
                        <span v-if="isCustomer">My PO's</span>
                    </router-link>
                </li>

                <li v-if="abilities.viewPurchaseOrders && (this.superStockist) && !(this.distributor && this.distributor.super_stockist_id != null)">
                    <router-link :to="{name:'orders'}"><i class="fa fa-inbox"></i> 
                        <span v-if="isCustomer">My PO's</span>
                    </router-link>
                </li>

                <li v-if="abilities.viewPurchaseOrders && (this.dealer) && (this.dealer && this.dealer.distributor_id == null)">
                    <router-link :to="{name:'orders'}"><i class="fa fa-inbox"></i> 
                        <span v-if="isCustomer">My PO's</span>
                    </router-link>
                </li>

                <li v-if="abilities.viewDepotOrders">
                    <router-link :to="{name:'depot-orders'}"><i class="fa fa-inbox"></i> 
                        <span>Depot PO's</span></router-link>
                </li>
                <li v-if="abilities.manageTransactions">
                    <router-link :to="{name:'transactions'}"><i class="fa fa-credit-card"></i> <span>Transactions</span></router-link>
                </li>
                <li v-if="abilities.viewInvoices">
                    <router-link :to="{name:'invoices'}"><i class="fa fa-file-alt"></i> <span>Invoices</span></router-link>
                </li>
                <li v-if="abilities.viewDepotInvoices"><!--#795-->
                    <router-link :to="{name:'depot-invoices'}"><i class="fa fa-file-alt"></i> <span>Depot Invoices</span></router-link>
                </li>
                <li v-if="abilities.viewPurchaseOrders && !isCustomer && !(this.dealer && this.dealer.distributor_id != null)">
                    <router-link :to="{name:'co-distributor-orders'}"><i class="fa fa-inbox"></i>
                        <span v-if="!isCustomer">Co-Distributor Orders</span></router-link>
                </li>
                <li v-if="this.dealer && this.dealer.distributor_id != null">
                    <router-link :to="{name:'DealerOrders'}"><i class="fa fa-inbox"></i> <span>My PO's</span></router-link>
                </li>
                <li v-if="isCustomer && !(this.dealer && this.dealer.distributor_id != null) &&  abilities.deny_block == false">
                    <router-link :to="{name:'blocked-items'}"><i class="fa fa-lock"></i> <span>My Blocked Items</span></router-link>
                </li>
                <li v-if="this.dealer && this.dealer.distributor_id != null">
                    <router-link :to="{name:'dealer-blocked-items'}"><i class="fa fa-lock"></i> <span>My Blocked Items</span></router-link>
                </li>
                <li v-if="isCustomer  && !(this.dealer && this.dealer.distributor_id != null) &&  abilities.deny_add_to_cart == false">
                    <router-link :to="{name:'cart'}"><i class="fa fa-shopping-cart"></i> <span>My Shopping Cart</span></router-link>
                </li>
                <li v-if="this.dealer && this.dealer.distributor_id != null">
                    <router-link :to="{name:'DealerCart'}"><i class="fa fa-shopping-cart"></i> <span>My Shopping Cart</span></router-link>
                </li>
             <!--   <li class="tree-view" v-if="abilities.viewAdvanceOrders && abilities.deny_advance_ordering == false">
                    <a href="#">
                        <i class="fa fa-th"></i> <span>Advance Orders</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-right pull-right"></i>
                            </span>
                    </a>
                    <ul class="treeview-menu">
                        <li>
                            <router-link :to="{name:'AdvanceOrders'}"><i class="fa fa-briefcase"></i> <span>View Orders</span></router-link>
                        </li>
                        <li v-if="!isCustomer">
                            <router-link :to="{name:'advance'}"><i class="fa fa-cart-plus"></i> <span>Advance Cart</span></router-link>
                        </li>
                        <li v-if="isCustomer">
                            <router-link :to="{name:'AdvanceCart'}"><i class="fa fa-cart-plus"></i> <span>My Advance Cart</span></router-link>
                        </li>
                    </ul>
                </li>-->
                <li class="tree-view" v-if="abilities.viewAdvanceOrders && abilities.deny_advance_ordering == false">
                    <a href="#">
                        <i class="fa fa-th"></i> <span>Advance Orders</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-right pull-right"></i>
                            </span>
                    </a>
                    <ul class="treeview-menu">
                        <li>
                            <router-link :to="{name:'InTransitOrders'}"><i class="fa fa-briefcase"></i> <span>View Orders</span></router-link>
                        </li>
                        <!--<li v-if="!isCustomer">
                            <router-link :to="{name:'InTransitCartAdmin'}"><i class="fa fa-cart-plus"></i> <span>Advance Cart</span></router-link>
                        </li>--><!--#873 -->
                        <li v-if="isCustomer">
                            <router-link :to="{name:'InTransitCart'}"><i class="fa fa-cart-plus"></i> <span>My Advance Cart</span></router-link>
                        </li>
                        <li v-if="isCustomer">
                            <router-link :to="{name:'BlockedAdvance'}"><i class="fa fa-cart-plus"></i> <span>Blocked Advance Cart</span></router-link>
                        </li>
                    </ul>
                </li>


                <li class="tree-view" v-if="isCustomer && this.distributor">
                    <a href="#">
                        <i class="fa fa-th"></i> <span>My Dealers</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-right pull-right"></i>
                            </span>
                    </a>
                    <ul class="treeview-menu">
                        <li>
                            <router-link :to="{name:'my-dealers'}"><i class="fa fa-user-friends"></i> <span>View Dealers</span></router-link>
                        </li>
                        <li>
                            <router-link :to="{name:'sales-orders'}"><i class="fa fa-inbox"></i> <span>View Orders</span></router-link>
                        </li>
                        <li>
                            <router-link :to="{name:'advance-sales-orders'}"><i class="fa fa-inbox"></i> <span>View Advance Orders</span></router-link>
                        </li>
                        <li>
                            <router-link :to="{name:'return-items'}"><i class="fa fa-lock"></i> <span>Return Items</span></router-link>
                        </li>
                        <li v-if="abilities.unBlockMyStock">
                            <router-link :to="{name:'unblock-stock'}"><i class="fa fa-lock"></i> <span>Unblock My Stock</span></router-link>
                        </li>
                        <li v-if="abilities.viewDudBlockedItems">
                            <router-link :to="{name:'company-blocked'}"><i class="fa fa-lock"></i> <span>Company - Blocked Stock</span></router-link>
                        </li>
                        <li v-if="abilities.viewCustomerDiscounts">
                            <router-link :to="{name:'customer-discounts'}"><i class="fa fa-percent"></i> <span>Discounts</span></router-link>
                        </li>
                    </ul>
                </li>
                <li class="tree-view" v-if="isCustomer && this.superStockist">
                    <a href="#">
                        <i class="fa fa-th"></i> <span>My SS Orders</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-right pull-right"></i>
                            </span>
                    </a>
                    <ul class="treeview-menu">                       
                        <li>
                            <router-link :to="{name:'superstockist-sales-orders'}"><i class="fa fa-inbox"></i> <span>View Orders</span></router-link>
                        </li>
                    </ul>
                </li>
                <li class="tree-view" v-if="isCustomer && this.superStockist">
                    <a href="#">
                        <i class="fa fa-th"></i> <span>My Distributors</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-right pull-right"></i>
                            </span>
                    </a>
                    <ul class="treeview-menu">
                        <li>
                            <router-link :to="{name:'my-distributors'}"><i class="fa fa-user-friends"></i> <span>View Distributors</span></router-link>
                        </li>
                        <li v-if="abilities.viewSSDiscounts">
                            <router-link :to="{name:'ss-discounts'}"><i class="fa fa-percent"></i> <span>SS Discounts</span></router-link>
                        </li>
                        <li v-if="abilities.unBlockMyStock">
                            <router-link :to="{name:'unblock-stock'}"><i class="fa fa-lock"></i> <span>Unblock My Stock</span></router-link>
                        </li>
                        <li>
                            <router-link :to="{name:'superstockist-distributor-sales-orders'}"><i class="fa fa-inbox"></i> <span>View Orders</span></router-link>
                        </li>
                    </ul>
                </li>
                <li class="tree-view" v-if="isCustomer && this.distributor">
                    <a href="#">
                        <i class="fa fa-th"></i> <span>My Co Distributors</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-right pull-right"></i>
                            </span>
                    </a>
                    <ul class="treeview-menu">
                        <!--<li>
                            <router-link :to="{name:'my-dealers'}"><i class="fa fa-user-friends"></i> <span>View Dealers</span></router-link>
                        </li>
                        <li>
                            <router-link :to="{name:'sales-orders'}"><i class="fa fa-inbox"></i> <span>View Orders</span></router-link>
                        </li>
                        <li>
                            <router-link :to="{name:'return-items'}"><i class="fa fa-lock"></i> <span>Return Items</span></router-link>
                        </li>
                        <li v-if="abilities.unBlockMyStock">
                            <router-link :to="{name:'unblock-stock'}"><i class="fa fa-lock"></i> <span>Unblock My Stock</span></router-link>
                        </li>
                        -->
                        <li v-if="abilities.manageCoDistributorDiscounts">
                            <router-link :to="{name:'co-distributor-discounts'}"><i class="fa fa-percent"></i> <span>Discounts</span></router-link>
                        </li>
                        <li v-if="abilities.manageCoDistributors">
                            <router-link :to="{name:'co-distributors'}"><i class="fa fa-lock"></i> <span>View Co Distributors</span></router-link>
                        </li>
                        <li v-if="abilities.manageCoDistributorOrders">
                            <router-link :to="{name:'co-distributor-po'}"><i class="fa fa-inbox"></i> <span>View POs</span></router-link>
                        </li>
                        <li v-if="abilities.manageCoDistributorOrders">
                            <router-link :to="{name:'co-distributor-so'}"><i class="fa fa-inbox"></i> <span>View SOs</span></router-link>
                        </li>

                    </ul>
                </li>

                <li class="tree-view" v-if="isCustomer && (this.superStockist || this.distributor)">
                    <a href="#">
                        <i class="fa fa-user-friends"></i> <span>MY Orders</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-right pull-right"></i>
                            </span>
                    </a>
                    <ul class="treeview-menu">
                        <li  v-if="this.superStockist != null">
                            <router-link :to="{name:'orders'}"><i class="fa fa-users"></i> <span>Orders Issued</span></router-link>
                        </li>

                        <li  v-if="this.superStockist != null">
                            <router-link :to="{name:'superstockist-all-sales-orders'}"><i class="fa fa-users"></i> <span>Orders Received</span></router-link>
                        </li>


                        <li  v-if="this.distributor != null">
                            <router-link :to="{name:'orders'}"><i class="fa fa-users"></i> <span>Orders Issued</span></router-link>
                        </li>

                        <li  v-if="this.distributor != null">
                            <router-link :to="{name:'distributor-all-sales-orders'}"><i class="fa fa-users"></i> <span>Orders Received</span></router-link>
                        </li>
                    </ul>
                </li>


                <li class="tree-view" v-if="abilities.viewRetailCustomers">
                    <a href="#">
                        <i class="fa fa-user-friends"></i> <span>Customers</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-right pull-right"></i>
                            </span>
                    </a>
                    <ul class="treeview-menu" v-if="this.state.user.company">
                        <li v-if="abilities.viewDistributors">
                            <router-link :to="{name:'companies.distributors', params:{id:this.state.user.company.id}}"><i class="fa fa-users"></i> <span>Distributors</span></router-link>
                        </li>
                    </ul>
                   <!-- <ul class="treeview-menu" v-if="!this.state.user.company">
                        <li>
                            <router-link :to="{name:'DistributorsSuperAdmin'}"><i class="fa fa-user-tie"></i> <span>Distributors</span></router-link>
                        </li>
                    </ul>-->
                    <ul class="treeview-menu" v-if="this.state.user.company">
                        <li v-if="abilities.viewDealers">
                            <router-link :to="{name:'companies.dealers', params:{id:this.state.user.company.id}}"><i class="fa fa-user-friends"></i> <span>Dealers</span></router-link>
                        </li>
                    </ul>
                   <!-- <ul class="treeview-menu" v-if="!this.state.user.company">
                        <li>
                            <router-link :to="{name:'RetailCustomersSuperAdmin'}"><i class="fa fa-user-tie"></i> <span>Dealers</span></router-link>
                        </li>
                    </ul>-->
                    <ul class="treeview-menu" v-if="this.state.user.company">
                        <li v-if="abilities.viewRetailCustomers">
                            <router-link :to="{name:'companies.RetailCustomers', params:{id:this.state.user.company.id}}"><i class="fa fa-user-tie"></i> <span>Retail Customers</span></router-link>
                        </li>
                    </ul>
                    <ul class="treeview-menu" v-if="!this.state.user.company">
                        <li>
                            <router-link :to="{name:'RetailCustomersSuperAdmin'}"><i class="fa fa-user-tie"></i> <span>Retail Customers</span></router-link>
                        </li>
                    </ul>
                    <!--<ul class="treeview-menu" v-if="!isCustomer">
                        <li v-if="abilities.viewDealers">
                            <router-link :to="{name:'pending-approval-dealers'}"><i class="fa fa-user-friends"></i> <span> Dealers - Pending Approval</span></router-link>
                        </li>
                    </ul>-->
                </li>
                <li v-if="abilities.regions">
                    <router-link :to="{name:'regions'}"><i class="fa fa-globe"></i> <span>Regions</span></router-link>
                </li>
                <li v-if="abilities.companies">
                    <router-link :to="{name:'companies'}"><i class="fa fa-copyright"></i> <span>Companies</span></router-link>
                </li>
                <li v-if="abilities.viewProducts">
                    <router-link :to="{name:'products'}"><i class="fa fa-parking"></i> <span>Products</span></router-link>
                </li>
                <li v-if="abilities.viewProductPrices">
                    <router-link :to="{name:'companies.product_prices'}"><i class="fa fa-parking"></i> <span>MUP</span></router-link>
                </li>
                <li v-if="abilities.viewCategories">
                    <router-link :to="{name:'categories'}"><i class="fa fa-th-large"></i> <span>Categories</span></router-link>
                </li>
                <li v-if="abilities.viewCLRates && !isCustomer">
                    <router-link :to="{name:'companies.cl_rates'}"><i class="fa fa-cut"></i> <span>CL Rates</span></router-link>
                </li>
                <li v-if="isCustomer && this.distributor">
                    <router-link :to="{name:'customers.cl_rates'}"><i class="fa fa-cut"></i> <span>CL Rates</span></router-link>
                </li>
                <li  class="tree-view" v-if="(!isCustomer) && (abilities.viewDiscounts || abilities.manageCoDistributorDiscounts || abilities.viewOnlineDiscounts)">
                    <a href="#">
                        <i class="fa fa-percent"></i> <span>Discounts</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-right pull-right"></i>
                            </span>
                    </a>
                    <ul class="treeview-menu">
                        <li v-if="abilities.viewDiscounts">
                            <router-link :to="{name:'discounts'}"><i class="fa fa-percent"></i> <span>Company</span></router-link>
                        </li>
                        <li v-if="abilities.viewOnlineDiscounts">
                            <router-link :to="{name:'online-discounts'}"><i class="fa fa-percent"></i> <span>Online</span></router-link>
                        </li>
                        <li v-if="abilities.manageCoDistributorDiscounts">
                            <router-link :to="{name:'co-distributor-discounts'}"><i class="fa fa-percent"></i> <span>Co Distributor</span></router-link>
                        </li>
                    </ul>
                    
                </li>
                <li v-if="abilities.manageBlockedItems">
                    <router-link :to="{name:'blocked'}"><i class="fa fa-lock"></i> <span>Blocked Items</span></router-link>
                </li>
                <li v-if="abilities.manageBlockedItems">
                    <router-link :to="{name:'BlockedAdvanceItems'}"><i class="fa fa-lock"></i> <span>Blocked Advance Items</span></router-link>
                </li>
                <li v-if="abilities.viewUser">
                    <router-link :to="{name:'users'}" v-if="this.distributor"><i class="fa fa-users"></i> <span>Users</span></router-link>
                </li>
                <li v-if="abilities.viewStockPointsAdmin">
                    <router-link :to="{name:'companies.locations'}"><i class="fas fa-industry"></i> <span>StockPoints</span></router-link>
                </li>
                <li v-if="abilities.viewProspects">
                    <router-link :to="{name:'companies.prospects'}"><i class="fa fa-user-plus"></i> <span>App Prospects</span></router-link>
                </li>
                <li v-if="abilities.sendNotification">
                    <router-link :to="{name:'SendNotification'}"><i class="fas fa-bell"></i> <span>Notifications</span></router-link>
                </li>
            </ul>
        </section>
    </aside>
</template>
<script>
    import AppMixin from '../vuex-mixin'
    export default{
        mixins:[AppMixin],
        computed: {
            settingMenu(){
                return this.abilities.regions || this.abilities.companies
            },
            state () {
                return this.store.state
            },
            store () {
                return this.$store
            },
            abilities(){
                return this.state.abilities
            }
        },
        methods:{
            logout(){
                this.store.commit('SET_USER',null)
                this.$router.push('login')
            }
        },
        mounted(){

        }
    }

</script>
