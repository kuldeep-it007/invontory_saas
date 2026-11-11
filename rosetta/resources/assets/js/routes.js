import LoginView from './components/LoginView.vue'
import TokenView from './components/TokenView.vue'
import AppLayout from './layouts/app-layout.vue'
const Dashboard = resolve => {
    require.ensure(['./components/Dashboard'], () => {
        resolve(require('./components/Dashboard'))
    })
}
const page403 = resolve => {
    require.ensure(['./components/403'], () => {
        resolve(require('./components/403'))
    })
}
const page404 = resolve => {
    require.ensure(['./components/404'], () => {
        resolve(require('./components/404'))
    })
}
const Regions = resolve => {
    require.ensure(['./components/region/Region'], () => {
        resolve(require('./components/region/Region'))
    })
}
const RegionForm = resolve => {
    require.ensure(['./components/region/Form'], () => {
        resolve(require('./components/region/Form'))
    })
}
const ProductTaxes = resolve => {
    require.ensure(['./components/region/product_taxes/ProductTaxes'], () => {
        resolve(require('./components/region/product_taxes/ProductTaxes'))
    })
}
const Companies = resolve => {
    require.ensure(['./components/companies/Companies'], () => {
        resolve(require('./components/companies/Companies'))
    })
}
const CompanyForm = resolve => {
    require.ensure(['./components/companies/Form'], () => {
        resolve(require('./components/companies/Form'))
    })
}
const SuperStockists = resolve => {
    require.ensure(['./components/superStockists/SuperStockists'], () => {
        resolve(require('./components/superStockists/SuperStockists'))
    })
}
const SuperStockistForm = resolve => {
    require.ensure(['./components/superStockists/Form'], () => {
        resolve(require('./components/superStockists/Form'))
    })
}
const SyncSuperStockist = resolve => {
    require.ensure(['./components/superStockists/SyncSuperStockist'], () => {
        resolve(require('./components/superStockists/SyncSuperStockist'))
    })
}
const Distributors = resolve => {
    require.ensure(['./components/distributors/Distributors'], () => {
        resolve(require('./components/distributors/Distributors'))
    })
}
const DistributorForm = resolve => {
    require.ensure(['./components/distributors/Form'], () => {
        resolve(require('./components/distributors/Form'))
    })
}
const SyncDistributor = resolve => {
    require.ensure(['./components/distributors/SyncDistributor'], () => {
        resolve(require('./components/distributors/SyncDistributor'))
    })
}
const ProductAttributes = resolve => {
    require.ensure(['./components/activeProducts/ProductAttributes'], () => {
        resolve(require('./components/activeProducts/ProductAttributes'))
    })
}
const Profile = resolve => {
    require.ensure(['./components/Profile'], () => {
        resolve(require('./components/Profile'))
    })
}
const Products = resolve => {
    require.ensure(['./components/products/Products'], () => {
        resolve(require('./components/products/Products'))
    })
}
const CompanyProductPrices = resolve => {
    require.ensure(['./components/companies/product_prices/ProductPrices'], () => {
        resolve(require('./components/companies/product_prices/ProductPrices'))
    })
}
const Categories = resolve => {
    require.ensure(['./components/categories/Categories'], () => {
        resolve(require('./components/categories/Categories'))
    })
}
const Collections = resolve => {
    require.ensure(['./components/collections/Collections'], () => {
        resolve(require('./components/collections/Collections'))
    })
}
const Colour = resolve => {
    require.ensure(['./components/colour/Colours'], () => {
        resolve(require('./components/colour/Colours'))
    })
}

const ProductForm = resolve => {
    require.ensure(['./components/products/Form'], () => {
        resolve(require('./components/products/Form'))
    })
}

const Company = resolve => {
    require.ensure(['./components/companies/Company'], () => {
        resolve(require('./components/companies/Company'))
    })
}
const Dealers = resolve => {
    require.ensure(['./components/dealers/Dealers'], () => {
        resolve(require('./components/dealers/Dealers'))
    })
}
const DealerForm = resolve => {
    require.ensure(['./components/dealers/Form'], () => {
        resolve(require('./components/dealers/Form'))
    })
}
const SyncDealer = resolve => {
    require.ensure(['./components/dealers/SyncDealer'], () => {
        resolve(require('./components/dealers/SyncDealer'))
    })
}
const Addresses = resolve => {
    require.ensure(['./components/addresses/Addresses'], () => {
        resolve(require('./components/addresses/Addresses'))
    })
}
const Customers = resolve => {
    require.ensure(['./components/customers/Customers'], () => {
        resolve(require('./components/customers/Customers'))
    })
}
const SystemUsers = resolve => {
    require.ensure(['./components/systemUsers/Users'], () => {
        resolve(require('./components/systemUsers/Users'))
    })
}
const OrderDistributorProducts = resolve => {
    require.ensure(['./components/dealerOrdering/OrderDistributorProducts'], () => {
        resolve(require('./components/dealerOrdering/OrderDistributorProducts'))
    })
}
const OrderProducts = resolve => {
    require.ensure(['./components/ordering/OrderProducts'], () => {
        resolve(require('./components/ordering/OrderProducts'))
    })
}
const Cart = resolve => {
    require.ensure(['./components/ordering/cart/Cart'], () => {
        resolve(require('./components/ordering/cart/Cart'))
    })
}
const DealerCart = resolve => {
    require.ensure(['./components/dealerOrdering/cart/Cart'], () => {
        resolve(require('./components/dealerOrdering/cart/Cart'))
    })
}
const Orders = resolve => {
    require.ensure(['./components/ordering/orders/Orders'], () => {
        resolve(require('./components/ordering/orders/Orders'))
    })
}
const DepotOrders = resolve => {
    require.ensure(['./components/DepotOrders/DepotOrders'], () => {
        resolve(require('./components/DepotOrders/DepotOrders'))
    })
}
const DepotOrder = resolve => {
    require.ensure(['./components/DepotOrders/DepotOrder'], () => {
        resolve(require('./components/DepotOrders/DepotOrder'))
    })
}
const DepotInvoices = resolve => {
    require.ensure(['./components/DepotOrders/DepotInvoices'], () => {
        resolve(require('./components/DepotOrders/DepotInvoices'))
    })
}
const Invoices = resolve => {
    require.ensure(['./components/invoices/Invoices'], () => {
        resolve(require('./components/invoices/Invoices'))
    })
}
const Order = resolve => {
    require.ensure(['./components/ordering/orders/Order'], () => {
        resolve(require('./components/ordering/orders/Order'))
    })
}
const AdvanceSalesOrders = resolve => {
    require.ensure(['./components/SalesOrders/AdvanceSalesOrders'], () => {
        resolve(require('./components/SalesOrders/AdvanceSalesOrders'))
    })
}
const AdvanceSalesOrder = resolve => {
    require.ensure(['./components/SalesOrders/AdvanceSalesOrder'], () => {
        resolve(require('./components/SalesOrders/AdvanceSalesOrder'))
    })
}
const SalesOrders = resolve => {
    require.ensure(['./components/SalesOrders/SalesOrders'], () => {
        resolve(require('./components/SalesOrders/SalesOrders'))
    })
}
const SalesOrder = resolve => {
    require.ensure(['./components/SalesOrders/SalesOrder'], () => {
        resolve(require('./components/SalesOrders/SalesOrder'))
    })
}
const CoDistributorOrders = resolve => {
    require.ensure(['./components/coDistributors/orders/Orders'], () => {
        resolve(require('./components/coDistributors/orders/Orders'))
    })
}
const CoDistributorOrder = resolve => {
    require.ensure(['./components/coDistributors/orders/Order'], () => {
        resolve(require('./components/coDistributors/orders/Order'))
    })
}
const DealerOrders = resolve => {
    require.ensure(['./components/dealerOrdering/orders/Orders'], () => {
        resolve(require('./components/dealerOrdering/orders/Orders'))
    })
}
const DealerOrder = resolve => {
    require.ensure(['./components/dealerOrdering/orders/Order'], () => {
        resolve(require('./components/dealerOrdering/orders/Order'))
    })
}
const Stock = resolve => {
    require.ensure(['./components/stock/Stock'], () => {
        resolve(require('./components/stock/Stock'))
    })
}
const SuperStockistStock = resolve => {
    require.ensure(['./components/superStockistStock/Stock'], () => {
        resolve(require('./components/superStockistStock/Stock'))
    })
}
const StockPoints = resolve => {
    require.ensure(['./components/stock/StockPoints'], () => {
        resolve(require('./components/stock/StockPoints'))
    })
}
const Prospects = resolve => {
    require.ensure(['./components/prospects/Prospects'], () => {
        resolve(require('./components/prospects/Prospects'))
    })
}
const DiscountList = resolve => {
    require.ensure(['./components/discounts/Discounts'], () => {
        resolve(require('./components/discounts/Discounts'))
    })
}
const CustomerDiscountList = resolve => {
    require.ensure(['./components/CustomerDiscounts/Discounts'], () => {
        resolve(require('./components/CustomerDiscounts/Discounts'))
    })
}
const SuperStockistDiscountList = resolve => {
    require.ensure(['./components/superStockistDiscounts/Discounts'], () => {
        resolve(require('./components/superStockistDiscounts/Discounts'))
    })
}
const CoDistributorDiscountList = resolve => {
    require.ensure(['./components/coDistributors/discounts/Discounts'], () => {
        resolve(require('./components/coDistributors/discounts/Discounts'))
    })
}
const CompanyStock = resolve => {
    require.ensure(['./components/companies/CompanyStock'], () => {
        resolve(require('./components/companies/CompanyStock'))
    })
}
const BlockedItems = resolve => {
    require.ensure(['./components/blocked-items/Blocked'], () => {
        resolve(require('./components/blocked-items/Blocked'))
    })
}
const BlockedAdvanceItems = resolve => {
    require.ensure(['./components/blocked-advance-items/BlockedAdvance'], () => {
        resolve(require('./components/blocked-advance-items/BlockedAdvance'))
    })
}
const DudBlockedCompanyStock = resolve => {
    require.ensure(['./components/blocked-items/DudBlockedCompanyStock'], () => {
        resolve(require('./components/blocked-items/DudBlockedCompanyStock'))
    })
}
const UnblockStock = resolve => {
    require.ensure(['./components/unblock/UnblockStock'], () => {
        resolve(require('./components/unblock/UnblockStock'))
    })
}
const Blocked = resolve => {
    require.ensure(['./components/ordering/blocked/Blocked'], () => {
        resolve(require('./components/ordering/blocked/Blocked'))
    })
}
const BlockedAdvance = resolve => {
    require.ensure(['./components/ordering/blockedAdvance/BlockedAdvance'], () => {
        resolve(require('./components/ordering/blockedAdvance/BlockedAdvance'))
    })
}
const Returns = resolve => {
    require.ensure(['./components/returns/Returns'], () => {
        resolve(require('./components/returns/Returns'))
    })
}
const DealerBlocked = resolve => {
    require.ensure(['./components/dealerOrdering/blocked/Blocked'], () => {
        resolve(require('./components/dealerOrdering/blocked/Blocked'))
    })
}
const DealerBlockedAdvance = resolve => {
    require.ensure(['./components/dealerOrdering/blockedAdvance/BlockedAdvance'], () => {
        resolve(require('./components/dealerOrdering/blockedAdvance/BlockedAdvance'))
    })
}
const Users = resolve => {
    require.ensure(['./components/users/Users'], () => {
        resolve(require('./components/users/Users'))
    })
}
const UserForm = resolve => {
    require.ensure(['./components/users/Form'], () => {
        resolve(require('./components/users/Form'))
    })
}
const InTransitOrders = resolve => {
    require.ensure(['./components/InTransitOrders/InTransitOrders'], () => {
        resolve(require('./components/InTransitOrders/InTransitOrders'))
    })
}
const InTransitCart = resolve => {
    require.ensure(['./components/InTransitOrders/InTransitCart'], () => {
        resolve(require('./components/InTransitOrders/InTransitCart'))
    })
}
const ViewInTransitOrder = resolve => {
    require.ensure(['./components/InTransitOrders/ViewInTransitOrder'], () => {
        resolve(require('./components/InTransitOrders/ViewInTransitOrder'))
    })
}
const InTransitCartAdmin = resolve => {
    require.ensure(['./components/InTransitOrders/InTransitCartAdmin'], () => {
        resolve(require('./components/InTransitOrders/InTransitCartAdmin'))
    })
}
const AdvanceOrders = resolve => {
    require.ensure(['./components/AdvanceOrders/AdvanceOrders'], () => {
        resolve(require('./components/AdvanceOrders/AdvanceOrders'))
    })
}
const ViewAdvanceOrder = resolve => {
    require.ensure(['./components/AdvanceOrders/ViewAdvanceOrder'], () => {
        resolve(require('./components/AdvanceOrders/ViewAdvanceOrder'))
    })
}
const AdvanceCart = resolve => {
    require.ensure(['./components/AdvanceOrders/AdvanceCart'], () => {
        resolve(require('./components/AdvanceOrders/AdvanceCart'))
    })
}
const AdvanceCartProducts = resolve => {
    require.ensure(['./components/AdvanceOrders/AdvanceCartProducts'], () => {
        resolve(require('./components/AdvanceOrders/AdvanceCartProducts'))
    })
}
const advance = resolve => {
    require.ensure(['./components/AdvanceOrders/advance'], () => {
        resolve(require('./components/AdvanceOrders/advance'))
    })
}
const RetailCustomers = resolve => {
    require.ensure(['./components/RetailCustomers/RetailCustomers'], () => {
        resolve(require('./components/RetailCustomers/RetailCustomers'))
    })
}
const RetailCustomerForm = resolve => {
    require.ensure(['./components/RetailCustomers/Form'], () => {
        resolve(require('./components/RetailCustomers/Form'))
    })
}
const RetailCustomersSuperAdmin = resolve => {
    require.ensure(['./components/RetailCustomers/RetailCustomers'], () => {
        resolve(require('./components/RetailCustomers/RetailCustomers'))
    })
}
const DistributorsSuperAdmin = resolve => {
    require.ensure(['./components/distributors/Distributors'], () => {
        resolve(require('./components/distributors/Distributors'))
    })
}
const SendNotification = resolve => {
    require.ensure(['./components/Notifications/SendNotification'], () => {
        resolve(require('./components/Notifications/SendNotification'))
    })
}
const MyDealers = resolve => {
    require.ensure(['./components/distributors/MyDealers'], () => {
        resolve(require('./components/distributors/MyDealers'))
    })
}
const MyDistributors = resolve => {
    require.ensure(['./components/superStockists/MyDistributors'], () => {
        resolve(require('./components/superStockists/MyDistributors'))
    })
}//#894
const PendingApprovalDealers = resolve => {
    require.ensure(['./components/distributors/PendingApprovalDealers'], () => {
        resolve(require('./components/distributors/PendingApprovalDealers'))
    })
}
const RejectedDealers = resolve => {
    require.ensure(['./components/distributors/RejectedDealers'], () => {
        resolve(require('./components/distributors/RejectedDealers'))
    })
}
const CreateTempDealers = resolve => {
    require.ensure(['./components/distributors/CreateTempDealers'], () => {
        resolve(require('./components/distributors/CreateTempDealers'))
    })
}


const CompanyStockPoints = resolve => {
    require.ensure(['./components/stockpoints/StockPoints'], () => {
        resolve(require('./components/stockpoints/StockPoints'))
    })
}
const StockpointCustomers = resolve => {
    require.ensure(['./components/stockpoints/Customers'], () => {
        resolve(require('./components/stockpoints/Customers'))
    })
}
const MyCoDistributors = resolve => {
    require.ensure(['./components/coDistributors/coDistributors'], () => {
        resolve(require('./components/coDistributors/coDistributors'))
    })
}

const CoDistributorPurchaseOrders = resolve => {
    require.ensure(['./components/coDistributors/purchaseOrders/PurchaseOrders'], () => {
        resolve(require('./components/coDistributors/purchaseOrders/PurchaseOrders'))
    })
}
const CoDistributorPurchaseOrder = resolve => {
    require.ensure(['./components/coDistributors/purchaseOrders/PurchaseOrder'], () => {
        resolve(require('./components/coDistributors/purchaseOrders/PurchaseOrder'))
    })
}
const CoDistributorSalesOrders = resolve => {
    require.ensure(['./components/coDistributors/salesOrders/SalesOrders'], () => {
        resolve(require('./components/coDistributors/salesOrders/SalesOrders'))
    })
}
const CoDistributorSalesOrder = resolve => {
    require.ensure(['./components/coDistributors/salesOrders/SalesOrder'], () => {
        resolve(require('./components/coDistributors/salesOrders/SalesOrder'))
    })
}

const CoDistributors = resolve => {
    require.ensure(['./components/companies/coDistributors/CoDistributors'], () => {
        resolve(require('./components/companies/coDistributors/CoDistributors'))
    })
}

const CompanyCLRates = resolve => {
    require.ensure(['./components/companies/cl_rates/CLRates'], () => {
        resolve(require('./components/companies/cl_rates/CLRates'))
    })
}
const CustomerCLRates = resolve => {
    require.ensure(['./components/customerCLRates/CLRates'], () => {
        resolve(require('./components/customerCLRates/CLRates'))
    })
}

const Impersonate = resolve => {
    require.ensure(['./components/Impersonate'], () => {
        resolve(require('./components/Impersonate'))
    })
}
const ViewOnlineDiscounts = resolve => {
    require.ensure(['./components/onlineDiscounts/Discounts'], () => {
        resolve(require('./components/onlineDiscounts/Discounts'))
    })
}
const PGStatus = resolve => {
    require.ensure(['./components/payment-gateway/Status'], () => {
        resolve(require('./components/payment-gateway/Status'))
    })
}

const Transactions = resolve => {
    require.ensure(['./components/transactions/Transactions'], () => {
        resolve(require('./components/transactions/Transactions'))
    })
}

const AboutUs = resolve => {
    require.ensure(['./components/about'], () => {
        resolve(require('./components/about'))
    })
}

const ReplenishReport = resolve => {
    require.ensure(['./components/reports/ReplenishReport'], () => {
        resolve(require('./components/reports/ReplenishReport'))
    })
}

const SuperStockistSalesOrders = resolve => {
    require.ensure(['./components/SalesOrders/SuperStockistSalesOrders'], () => {
        resolve(require('./components/SalesOrders/SuperStockistSalesOrders'))
    })
}
const SuperStockistDistributorSalesOrders = resolve => {
    require.ensure(['./components/SalesOrders/SuperStockistDistributorSalesOrders'], () => {
        resolve(require('./components/SalesOrders/SuperStockistDistributorSalesOrders'))
    })
}

const SuperStockistAllSalesOrders = resolve => {
    require.ensure(['./components/SalesOrders/SuperStockistAllSalesOrders'], () => {
        resolve(require('./components/SalesOrders/SuperStockistAllSalesOrders'))
    })
}

const DistributorAllSalesOrders = resolve => {
    require.ensure(['./components/SalesOrders/DistributorAllSalesOrders'], () => {
        resolve(require('./components/SalesOrders/DistributorAllSalesOrders'))
    })
}

const SuperStockistSalesOrder = resolve => {
    require.ensure(['./components/SalesOrders/SuperStockistSalesOrder'], () => {
        resolve(require('./components/SalesOrders/SuperStockistSalesOrder'))
    })
}

const routes = [
    {
        path: '/about',
        component: AboutUs,
        name: 'about'
    },
    {
        path: '/login',
        component: LoginView,
        name: 'login'
    },
    {
        path: '/token',
        component: TokenView,
        name: 'token'
    },
    {
        path: '/pg/:ref',
        component: PGStatus,
        name: 'pg-status'
    },
    {
        path: '/',
        component: AppLayout,
        children: [
            {
                path: "",
                component: Dashboard,
                name: 'dashboard'
            },
            {
                path: '403',
                component: page403,
                name: '403'
            },
            {
                path: "order-products",
                component: OrderDistributorProducts,
                name: "order-distributor-products"
            },
            {
                path: "order",
                component: OrderProducts,
                name: "order-products"
            },
            {
                path: "cart",
                component: Cart,
                name: "cart"
            },
            {
                path: "dealer-cart",
                component: DealerCart,
                name: "DealerCart"
            },
            {
                path: "return-items",
                component: Returns,
                name: "return-items"
            },
            {
                path: "blocked-items",
                component: Blocked,
                name: "blocked-items"
            },
            {
                path: "blocked-advance-items",
                component: BlockedAdvance,
                name: "BlockedAdvance"
            },
            {
                path: "dealer-blocked-items",
                component: DealerBlocked,
                name: "dealer-blocked-items"
            },
            {
                path: "dealer-blocked-advance-items",
                component: DealerBlockedAdvance,
                name: "dealer-blocked-advance-items"
            },
            {
                path: "orders",
                component: Orders,
                name: "orders"
            },
            {
                path: "depot-orders",
                component: DepotOrders,
                name: "depot-orders"
            },
            {
                path: "depot-orders/:id",
                component: DepotOrder,
                name: "DepotOrders.view"
            },
            {
                path: "depot-invoices",
                component: DepotInvoices,
                name: "depot-invoices"
            },
            {
                path: "stock",
                component: Stock,
                name: "stock"
            },
            {
                path: "SuperStockistStock",
                component: SuperStockistStock,
                name: "SuperStockistStock"
            },
            {
                path: "company-stock",
                name: "company-stock",
                component: CompanyStock
            },
            {
                path: "orders/:id",
                component: Order,
                name: "orders.view"
            },
            {
                path: "advance-sales-orders",
                component: AdvanceSalesOrders,
                name: "advance-sales-orders"
            },
            {
                path: "advance-sales-orders/:id",
                component: AdvanceSalesOrder,
                name: "AdvanceSalesOrders.view"
            },
            {
                path: "sales-orders",
                component: SalesOrders,
                name: "sales-orders"
            },
            {
                path: "distributor-all-sales-orders",
                component: DistributorAllSalesOrders,
                name: "distributor-all-sales-orders"
            },
            {
                path: "sales-orders/:id",
                component: SalesOrder,
                name: "SalesOrders.view"
            },
            {
                path: "dealer-orders",
                component: DealerOrders,
                name: "DealerOrders"
            },
            {
                path: "dealer-orders/:id",
                component: DealerOrder,
                name: "DealerOrders.view"
            },
            {
                path: "stockpoints",
                name: "companies.stockpoints",
                component: StockPoints
            },
            {
                path: "prospects",
                name: "companies.prospects",
                component: Prospects
            },//#785
            {
                path: "discounts",
                name: "discounts",
                component: DiscountList
            },
            {
                path: "customer-discounts",
                name: "customer-discounts",
                component: CustomerDiscountList
            },
            {
                path: "ss-discounts",
                name: "ss-discounts",
                component: SuperStockistDiscountList
            },
            {
                path: "blocked",
                name: "blocked",
                component: BlockedItems
            },
            {
                path: "BlockedAdvanceItems",
                name: "BlockedAdvanceItems",
                component: BlockedAdvanceItems
            },
            {
                path: "company-blocked",
                name: "company-blocked",
                component: DudBlockedCompanyStock
            },
            {
                path: "unblock-stock",
                name: "unblock-stock",
                component: UnblockStock
            },
            {
                path: "profile",
                name: 'profile',
                component: Profile
            },
            {
                path: "InTransitOrders",
                name: "InTransitOrders",
                component: InTransitOrders
            },
            {
                path: "InTransitorder/:id",
                component: ViewInTransitOrder,
                name: "InTransitorder.view"
            },
            {
                path: "InTransitCart",
                component: InTransitCart,
                name: "InTransitCart"
            },
            {
                path: "InTransitCartAdmin",
                component: InTransitCartAdmin,
                name: "InTransitCartAdmin"
            },
            {
                path: "AdvanceOrders",
                name: "AdvanceOrders",
                component: AdvanceOrders
            },
            {
                path: "advanceorder/:id",
                component: ViewAdvanceOrder,
                name: "advanceorder.view"
            },
            {
                path: "AdvanceCart",
                component: AdvanceCart,
                name: "AdvanceCart"
            },
            {
                path: "AdvanceCartProducts",
                name: "AdvanceCartProducts",
                component: AdvanceCartProducts
            },
            {
                path: "advance",
                component: advance,
                name: "advance"
            },
            {
                path: "RetailCustomers",
                name: "RetailCustomersSuperAdmin",
                component: RetailCustomers
            },
            {
                path: "Distributors",
                name: "DistributorsSuperAdmin",
                component: Distributors
            },
            {
                path: "SendNotification",
                name: "SendNotification",
                component: SendNotification
            },
            {
                path: "my-dealers",
                component: MyDealers,
                name: "my-dealers"
            },
            {
                path: "my-distributors",
                component: MyDistributors,
                name: "my-distributors"
            },
            {
                path: "add-distributor",
                component: DistributorForm,
                name: "add-distributor"
            },
            {
                path: "my-distributors/:distributorid/edit",
                component: DistributorForm,
                name: "my-distributors.edit"
            },
            {
                path: "my-distributors/:distributorid/users",
                name: "my-distributors.users",
                component: SystemUsers,
                meta: {
                    type: "distributorUnderSuperstockist"
                }
            },
            {
                path: "co-distributors",
                component: MyCoDistributors,
                name: "co-distributors"
            },
            {
                path: "my-dealers/:dealerid/edit",
                name: "my-dealers.edit",
                component: CreateTempDealers,
            },
            {
                path: "my-dealers/:dealerid/users",
                name: "my-dealers.users",
                component: SystemUsers,
                meta: {
                    type: "dealerUnderDistributor"
                }
            },
            {
                path: "pending-approval-dealers",
                component: PendingApprovalDealers,
                name: "pending-approval-dealers"
            },
            {
                path: "rejected-dealers",
                component: RejectedDealers,
                name: "rejected-dealers"
            },
            {
                path: "create-temp-dealers",
                component: CreateTempDealers,
                name: "create-temp-dealers"
            },
            {
                path: "locations",
                component: CompanyStockPoints,
                name: "companies.locations"
            },
            {
                path: "locations/:id/customers",
                component: StockpointCustomers,
                name: "companies.locations.customers"
            },
            {
                path: "companies/:companyid/locations/:id/customers",
                component: StockpointCustomers,
                name: "admin.companies.locations.customers"
            },
            {
                path: "co-distributor-discounts",
                name: "co-distributor-discounts",
                component: CoDistributorDiscountList
            },
            {
                path: "co-distributor-orders",
                name: "co-distributor-orders",
                component: CoDistributorOrders
            },
            {
                path: "co-distributor-order/:id",
                name: "co-distributor-order-view",
                component: CoDistributorOrder
            },
            {
                path: "co-distributor-po",
                name: "co-distributor-po",
                component: CoDistributorPurchaseOrders
            },
            {
                path: "co-distributor-po/:id",
                name: "co-distributor-po-view",
                component: CoDistributorPurchaseOrder
            },
            {
                path: "co-distributor-so",
                name: "co-distributor-so",
                component: CoDistributorSalesOrders
            },
            {
                path: "co-distributor-so/:id",
                name: "co-distributor-so-view",
                component: CoDistributorSalesOrder
            },
            {
                path: "cl-rates",
                name: "companies.cl_rates",
                component: CompanyCLRates
            },
            {
                path: "my-cl-rates",
                name: "customers.cl_rates",
                component: CustomerCLRates

            },
            {
                path: "product-prices",
                name: "companies.product_prices",
                component: CompanyProductPrices
            },
            {
                path: "impersonate/:userid",
                name: "users.impersonate",
                component: Impersonate,
            },
            {
                path: 'online-discounts',
                name: 'online-discounts',
                component: ViewOnlineDiscounts
            },
            {
                path: 'transactions',
                name: 'transactions',
                component: Transactions
            },
            {
                path: 'invoices',
                name: 'invoices',
                component: Invoices
            },
            {
                path: "superstockist-sales-orders",
                component: SuperStockistSalesOrders,
                name: "superstockist-sales-orders"
            },
            {
                path: "superstockist-distributor-sales-orders",
                component: SuperStockistDistributorSalesOrders,
                name: "superstockist-distributor-sales-orders"
            },
            {
                path: "superstockist-all-sales-orders",
                component: SuperStockistAllSalesOrders,
                name: "superstockist-all-sales-orders"
            },
            {
                path: "superstockist-sales-orders/:id",
                component: SuperStockistSalesOrder,
                name: "superstockistSalesOrders.view"
            },

            /*         {
                         path:"RetailCustomers",
                         name:"RetailCustomers",
                         component:RetailCustomers
                     },
                     {
                         path:"create",
                         name : "retailCustomers.create",
                         component:RetailCustomerForm,
                     },
                     {
                         path:":retailCustomerId/edit",
                         name : "retailCustomers.edit",
                         component:RetailCustomerForm,
                     },*/
            {
                path: "regions",
                component: {
                    render(c) { return c('router-view') }
                },
                children: [
                    {
                        path: "",
                        name: "regions",
                        component: Regions,
                        meta: {
                            perm: "regions"
                        }
                    },
                    {
                        path: "create",
                        name: "regions.create",
                        component: RegionForm,
                        meta: {
                            perm: "regions"
                        }
                    },
                    {
                        path: ":id/edit",
                        name: "regions.edit",
                        component: RegionForm,
                        meta: {
                            perm: "regions"
                        }
                    },
                    {
                        path: ":id/product_taxes",
                        component: {
                            render(c) { return c('router-view') }
                        },
                        children: [
                            {
                                path: "",
                                name: "regions.product_taxes",
                                component: ProductTaxes,
                            },
                            /*    {
                                    path:"add",
                                    name : "regions.taxes.add",
                                    component:TaxForm,
                                },
                                {
                                    path:":regionId/edit",
                                    name : "regions.taxes.edit",
                                    component:TaxForm,
                                }*/
                        ]
                    }
                ]
            },
            {
                path: "users",
                component: {
                    render(c) { return c('router-view') }
                },
                children: [
                    {
                        path: "",
                        name: "users",
                        component: Users
                    },
                    {
                        path: "create",
                        name: "users.create",
                        component: UserForm
                    },
                    {
                        path: ":id/edit",
                        name: "users.edit",
                        component: UserForm
                    }
                ]
            },
            {
                path: "companies",
                component: {
                    render(c) { return c('router-view') }
                },
                children: [
                    {
                        path: "",
                        name: "companies",
                        component: Companies,
                        meta: {
                            perm: "companies"
                        }
                    },
                    {
                        path: ":id/replenishreport",
                        name: "companies.replenishreport",
                        component: ReplenishReport,
                        meta: {
                            perm: "companies"
                        }
                    },
                    {
                        path: ":id/view",
                        name: "company",
                        component: Company,
                        meta: {
                            perm: "companies"
                        }
                    },
                    {
                        path: "create",
                        name: "companies.create",
                        component: CompanyForm,
                        meta: {
                            perm: "companies"
                        }
                    },
                    {
                        path: ":id/edit",
                        name: "companies.edit",
                        component: CompanyForm,
                        meta: {
                            perm: "companies"
                        }
                    },
                    {
                        path: ":id/co-distributors",
                        name: 'companies.codistributors.list',
                        component: CoDistributors
                    },
                    {
                        path: ":id/super_stockists",
                        component: {
                            render(c) { return c('router-view') }
                        },
                        children: [
                            {
                                path: "",
                                name: "companies.super_stockists",
                                component: SuperStockists,
                            },
                            {
                                path: "create",
                                name: "companies.super_stockists.create",
                                component: SuperStockistForm,
                            },
                            {
                                path: ":super_stockist_id/edit",
                                name: "companies.super_stockists.edit",
                                component: SuperStockistForm,
                            },
                            {
                                path: "sync",
                                name: "companies.super_stockists.sync",
                                component: SyncSuperStockist,
                            },
                            {
                                path: ":super_stockist_id/distributors",
                                name: "companies.super_stockists.distributors",
                                component: Distributors,
                            },
                            {
                                path: ":super_stockist_id/distributors/sync",
                                name: "companies.super_stockists.distributors.sync",
                                component: SyncDistributor,
                            },
                            {
                                path: ":super_stockist_id/distributors/create",
                                name: "companies.super_stockists.distributors.create",
                                component: DistributorForm,
                            },
                            {
                                path: ":super_stockist_id/distributors/:distributorid/edit",
                                name: "companies.super_stockists.distributors.edit",
                                component: DistributorForm,
                            },
                            {
                                path: ":super_stockist_id/product_attributes",
                                name: "companies.super_stockists.product_attributes",
                                component: ProductAttributes,
                            },
                            {
                                path: ":super_stockist_id/users",
                                name: "companies.super_stockists.users",
                                component: SystemUsers,
                                meta: {
                                    type: "superStockist"
                                }
                            },
                            {
                                path: ":super_stockist_id/stock",
                                name: "companies.super_stockists.stock",
                                component: SuperStockistStock,
                            },
                        ]
                    },
                    {
                        path: ":id/distributors",
                        component: {
                            render(c) { return c('router-view') }
                        },
                        children: [
                            {
                                path: "",
                                name: "companies.distributors",
                                component: Distributors,
                            },
                            {
                                path: "create",
                                name: "companies.distributors.create",
                                component: DistributorForm,
                            },
                            {
                                path: ":distributorid/edit",
                                name: "companies.distributors.edit",
                                component: DistributorForm,
                            },
                            {
                                path: ":distributorid/dealers",
                                name: "companies.distributors.dealers",
                                component: Dealers,
                            },
                            {
                                path: ":distributorid/dealers/create",
                                name: "companies.distributors.dealers.create",
                                component: DealerForm,
                            },
                            {
                                path: ":distributorid/dealers/sync",
                                name: "companies.distributors.dealers.sync",
                                component: SyncDealer,
                            },
                            {
                                path: ":distributorid/dealers/:dealerid/edit",
                                name: "companies.distributors.dealers.edit",
                                component: DealerForm,
                            },
                            {
                                path: ":distributorid/dealers/:dealerid/product_attributes",
                                name: "companies.distributors.dealers.product_attributes",
                                component: ProductAttributes,
                            },
                            {
                                path: ":distributorid/dealers/:dealerid/users",
                                name: "companies.distributors.dealers.users",
                                component: SystemUsers,
                                meta: {
                                    type: "dealer"
                                }
                            },
                            {
                                path: ":distributorid/addresses",
                                name: "companies.distributors.addresses",
                                component: Addresses,
                                meta: {
                                    type: "distributor"
                                }
                            },
                            {
                                path: ":distributorid/product_attributes",
                                name: "companies.distributors.product_attributes",
                                component: ProductAttributes,
                            },
                            {
                                path: "sync",
                                name: "companies.distributors.sync",
                                component: SyncDistributor,
                            },
                            {
                                path: ":distributorid/users",
                                name: "companies.distributors.users",
                                component: SystemUsers,
                                meta: {
                                    type: "distributor"
                                }
                            },
                            {
                                path: ":distributorid/stock",
                                name: "companies.distributors.stock",
                                component: Stock,
                            },
                        ]
                    },
                    {
                        path: ":id/dealers",
                        component: {
                            render(c) { return c('router-view') }
                        },
                        children: [
                            {
                                path: "",
                                name: "companies.dealers",
                                component: Dealers,
                            },
                            {
                                path: "create",
                                name: "companies.dealers.create",
                                component: DealerForm,
                            },
                            {
                                path: ":dealerid/edit",
                                name: "companies.dealers.edit",
                                component: DealerForm,
                            },
                            {
                                path: ":dealerid/product_attributes",
                                name: "companies.dealers.product_attributes",
                                component: ProductAttributes,
                            },
                            {
                                path: "sync",
                                name: "companies.dealers.sync",
                                component: SyncDealer,
                            },
                            {
                                path: ":dealerid/users",
                                name: "companies.dealers.users",
                                component: SystemUsers,
                                meta: {
                                    type: "dealer"
                                }
                            },
                            {
                                path: ":dealerid/addresses",
                                name: "companies.dealers.addresses",
                                component: Addresses,
                                meta: {
                                    type: "dealer"
                                }
                            },
                            {
                                path: ":dealerid/customers",
                                name: "companies.dealers.customers",
                                component: Customers,
                                meta: {
                                    type: "dealer"
                                }
                            },
                        ]
                    },
                    {
                        path: ":id/RetailCustomers",
                        component: {
                            render(c) { return c('router-view') }
                        },
                        children: [
                            {
                                path: "",
                                name: "companies.RetailCustomers",
                                component: RetailCustomers,
                            },
                            {
                                path: "create",
                                name: "companies.retailCustomers.create",
                                component: RetailCustomerForm,
                            },
                            {
                                path: ":retailCustomerId/edit",
                                name: "companies.retailCustomers.edit",
                                component: RetailCustomerForm,
                            },
                            {
                                path: ":retailCustomerId/addresses",
                                name: "companies.retailCustomers.addresses",
                                component: Addresses,
                                meta: {
                                    type: "retailCustomer"
                                }
                            }
                        ]
                    },
                    {
                        path: ":id/product_attributes",
                        component: {
                            render(c) { return c('router-view') }
                        },
                        children: [
                            {
                                path: "",
                                name: "companies.product_attributes",
                                component: ProductAttributes,
                            },
                        ]
                    }
                ]
            },
            {
                path: "products",
                component: {
                    render(c) { return c('router-view') }
                },
                children: [
                    {
                        path: "",
                        name: "products",
                        component: Products,
                    },
                    {
                        path: ":id/edit",
                        name: "products.edit",
                        component: ProductForm,
                    },
                    {
                        path: "create",
                        name: "products.create",
                        component: ProductForm,
                    }
                ]
            },
            {
                path: "categories",
                component: {
                    render(c) { return c('router-view') }
                },
                children: [
                    {
                        path: "",
                        name: "categories",
                        component: Categories,
                    },
                    {
                        path: ":category_code/collections",
                        component: {
                            render(c) { return c('router-view') }
                        },
                        children: [
                            {
                                path: "",
                                name: "categories.collections",
                                component: Collections,
                            },


                            {
                                path: ":collection_code/colours",
                                component: {
                                    render(c) { return c('router-view') }
                                },
                                children: [
                                    {
                                        path: "",
                                        name: "collections.colours",
                                        component: Colour,
                                    },
                                ]
                            },


                        ]
                    }
                ]
            },
            {
                path: "*",
                component: page404,
                name: '404'
            }
        ]
    }
]

export default routes