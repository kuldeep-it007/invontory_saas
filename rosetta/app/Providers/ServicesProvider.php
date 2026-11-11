<?php

namespace Rosetta\Providers;

use Illuminate\Support\ServiceProvider;

class ServicesProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            'Rosetta\Services\Notifications\NotificationsServiceInterface',
            'Rosetta\Services\Notifications\NotificationsService'
        );
        $this->app->bind(
            'Rosetta\Services\Users\UserServiceInterface',
            'Rosetta\Services\Users\UserService'
        );
        $this->app->bind(
            'Rosetta\Services\Categories\CategoryServiceInterface',
            'Rosetta\Services\Categories\CategoryService'
        );
        $this->app->bind(
            'Rosetta\Services\PurchaseOrders\PurchaseOrdersServiceInterface',
            'Rosetta\Services\PurchaseOrders\PurchaseOrdersService'
        );
        $this->app->bind(
            'Rosetta\Services\AdvanceOrders\AdvanceOrdersServiceInterface',
            'Rosetta\Services\AdvanceOrders\AdvanceOrdersService'
        );
        $this->app->bind(
            'Rosetta\Services\CartItems\CartItemsServiceInterface',
            'Rosetta\Services\CartItems\CartItemsService'
        );
        $this->app->bind(
            'Rosetta\Services\Collection\CollectionServiceInterface',
            'Rosetta\Services\Collection\CollectionService'
        );
        $this->app->bind(
            'Rosetta\Services\Colours\RosColourServiceInterface',
            'Rosetta\Services\Colours\RosColourService'
        );
        $this->app->bind(
            'Rosetta\Services\Region\RegionServiceInterface',
            'Rosetta\Services\Region\RegionService'
        );
        $this->app->bind(
            'Rosetta\Services\Companies\CompanyServiceInterface',
            'Rosetta\Services\Companies\CompanyService'
        );
        $this->app->bind(
            'Rosetta\Services\Distributor\DistributorServiceInterface',
            'Rosetta\Services\Distributor\DistributorService'
        );
        $this->app->bind(
            'Rosetta\Services\Dealer\DealerServiceInterface',
            'Rosetta\Services\Dealer\DealerService'
        );
        $this->app->bind(
            'Rosetta\Services\Discount\DiscountsServiceInterface',
            'Rosetta\Services\Discount\DiscountsService'
        );
        $this->app->bind(
            'Rosetta\Services\Messages\MessagesServiceInterface',
            'Rosetta\Services\Messages\MessagesService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\CustomerServiceInterface',
            'Rosetta\Services\NAV\CustomerService'
        );
        $this->app->bind(
            'Rosetta\Services\TaxTypes\TaxTypeServiceInterface',
            'Rosetta\Services\TaxTypes\TaxTypeService'
        );
        $this->app->bind(
            'Rosetta\Services\Location\LocationServiceInterface',
            'Rosetta\Services\Location\LocationService'
        );
        $this->app->bind(
            'Rosetta\Services\MasterData\MasterDataServiceInterface',
            'Rosetta\Services\MasterData\MasterDataService'
        );

        $this->app->bind(
            'Rosetta\Services\NAV\ProductGroupsServiceInterface',
            'Rosetta\Services\NAV\ProductGroupsService'
        );

        $this->app->bind(
            'Rosetta\Services\NAV\ColourServiceInterface',
            'Rosetta\Services\NAV\ColourService'
        );

        $this->app->bind(
            'Rosetta\Services\NAV\ItemCategoriesServiceInterface',
            'Rosetta\Services\NAV\ItemCategoriesService'
        );

        $this->app->bind(
            'Rosetta\Services\NAV\InventoryPosingGroupServiceInterface',
            'Rosetta\Services\NAV\InventoryPosingGroupService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\GeneralProductPostingGroupServiceInterface',
            'Rosetta\Services\NAV\GeneralProductPostingGroupService'
        );

        $this->app->bind(
            'Rosetta\Services\NAV\ItemCardServiceInterface',
            'Rosetta\Services\NAV\ItemCardService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\ItemCardIntransitServiceInterface',
            'Rosetta\Services\NAV\ItemCardIntransitService'
        );
        $this->app->bind(
            'Rosetta\Services\Product\ProductsServiceInterface',
            'Rosetta\Services\Product\ProductsService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\VATProductPostingGroupServiceInterface',
            'Rosetta\Services\NAV\VATProductPostingGroupService'
        );

        $this->app->bind(
            'Rosetta\Services\NAV\InventoryServiceInterface',
            'Rosetta\Services\NAV\InventoryService'
        );

        $this->app->bind(
            'Rosetta\Services\NAV\ILEServiceInterface',
            'Rosetta\Services\NAV\ILEService'
        );

        $this->app->bind(
            'Rosetta\Services\NAV\PendingPurchaseOrdersServiceInterface',
            'Rosetta\Services\NAV\PendingPurchaseOrdersService'
        );

        $this->app->bind(
            'Rosetta\Services\Inventory\InventoryServiceInterface',
            'Rosetta\Services\Inventory\InventoryService'
        );

        $this->app->bind(
            'Rosetta\Services\NAV\STAServiceInterface',
            'Rosetta\Services\NAV\STAService'
        );

        $this->app->bind(
            'Rosetta\Services\NAV\POServiceInterface',
            'Rosetta\Services\NAV\POService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\PurchaseLineServiceInterface',
            'Rosetta\Services\NAV\PurchaseLineService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\SalesOrderServiceInterface',
            'Rosetta\Services\NAV\SalesOrderService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\PortalCUServiceInterface',
            'Rosetta\Services\NAV\PortalCUService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\SalesPriceServiceInterface',
            'Rosetta\Services\NAV\SalesPriceService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\SalesLinesServiceInterface',
            'Rosetta\Services\NAV\SalesLinesService'
        );

        $this->app->bind(
            'Rosetta\Services\CMS\CMSServiceInterface',
            'Rosetta\Services\CMS\CMSService'
        );

        $this->app->bind(
            'Rosetta\Services\NAV\ItemPictureServiceInterface',
            'Rosetta\Services\NAV\ItemPictureService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\PSIServiceInterface',
            'Rosetta\Services\NAV\PSIService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\PSSLServiceInterface',
            'Rosetta\Services\NAV\PSSLService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\AvailableCreditServiceInterface',
            'Rosetta\Services\NAV\AvailableCreditService'
        );
        $this->app->bind(
            'Rosetta\Services\RetailCustomer\RetailCustomerServiceInterface',
            'Rosetta\Services\RetailCustomer\RetailCustomerService'
        );
        $this->app->bind(
            'Rosetta\Services\Dashboard\DashboardServiceInterface',
            'Rosetta\Services\Dashboard\DashboardService'
        );
        $this->app->bind(
            'Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface',
            'Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesService'
        );
        $this->app->bind(
            'Rosetta\Services\CustomerDiscount\CustomerDiscountServiceInterface',
            'Rosetta\Services\CustomerDiscount\CustomerDiscountService'
        );
        $this->app->bind(
            'Rosetta\Services\SalesOrders\SalesOrdersServiceInterface',
            'Rosetta\Services\SalesOrders\SalesOrdersService'
        );
        $this->app->bind(
            'Rosetta\Services\AdvanceSalesOrders\AdvanceSalesOrdersServiceInterface',
            'Rosetta\Services\AdvanceSalesOrders\AdvanceSalesOrdersService'
        );
        $this->app->bind(
            'Rosetta\Services\Customer\CustomerServiceInterface',
            'Rosetta\Services\Customer\CustomerService'
        );
        $this->app->bind(
            'Rosetta\Services\Engineer\EngineerServiceInterface',
            'Rosetta\Services\Engineer\EngineerService'
        );

        if(config('sms.service') == 'ssdindia')
        {
            $this->app->bind(
                'Rosetta\Services\Notifications\SMSServiceInterface',
                'Rosetta\Services\Notifications\SSDSMSService'
            );
        }
        else
        {
            $this->app->bind(
                'Rosetta\Services\Notifications\SMSServiceInterface',
                'Rosetta\Services\Notifications\FakeSMSService'
            );
        }


        $this->app->bind(
            'Rosetta\Services\OTPServiceInterface',
            'Rosetta\Services\OTPService'
        );

        $this->app->bind(
            'Rosetta\Services\BlanketOrders\BlanketOrdersServiceInterface',
            'Rosetta\Services\BlanketOrders\BlanketOrdersService'
        );
        $this->app->bind(
            'Rosetta\Services\AdvanceBlanketOrders\AdvanceBlanketOrdersServiceInterface',
            'Rosetta\Services\AdvanceBlanketOrders\AdvanceBlanketOrdersService'
        );
        $this->app->bind(
            'Rosetta\Services\StockPoints\StockPointServiceInterface',
            'Rosetta\Services\StockPoints\StockPointService'
        );

        $this->app->bind(
            'Rosetta\Services\CoDistributor\CoDistributorDiscountServiceInterface',
            'Rosetta\Services\CoDistributor\CoDistributorDiscountService'
        );
        $this->app->bind(
            'Rosetta\Services\CoDistributor\CoDistributorServiceInterface',
            'Rosetta\Services\CoDistributor\CoDistributorService'
        );
        $this->app->bind(
            'Rosetta\Services\CLRate\CLRateServiceInterface',
            'Rosetta\Services\CLRate\CLRateService'
        );

        $this->app->bind(
            'Rosetta\Services\OnlineDiscounts\OnlineDiscountsServiceInterface',
            'Rosetta\Services\OnlineDiscounts\OnlineDiscountsService'
        );

        $this->app->bind(
            'Rosetta\Services\PaymentGateway\PaymentGatewayServiceInterface',
            'Rosetta\Services\PaymentGateway\PaymentGatewayService'
        );


        $this->app->bind(
            'Rosetta\Services\NAV\BankReceiptServiceInterface',
            'Rosetta\Services\NAV\BankReceiptService'
        );

        $this->app->bind(
            'Rosetta\Services\Payments\PaymentsServiceInterface',
            'Rosetta\Services\Payments\PaymentsService'
        );

        $this->app->bind(
            'Rosetta\Services\Transaction\TransactionServiceInterface',
            'Rosetta\Services\Transaction\TransactionService'
        );
        $this->app->bind(
            'Rosetta\Services\Depot\DepotItemsServiceInterface',
            'Rosetta\Services\Depot\DepotItemsService'
        );
        $this->app->bind(
            'Rosetta\Services\Invoice\InvoiceServiceInterface',
            'Rosetta\Services\Invoice\InvoiceService'
        );
        $this->app->bind(
            'Rosetta\Services\Notifications\SMS\SMSGatewayServiceInterface',
            'Rosetta\Services\Notifications\SMS\SMSGatewayService'
        );
        $this->app->bind(
            'Rosetta\Services\NAV\PaymentStatusServiceInterface',
            'Rosetta\Services\NAV\PaymentStatusService'
        );
        $this->app->bind(
            'Rosetta\Services\Reports\ReportsServiceInterface',
            'Rosetta\Services\Reports\ReportsService'
        );
        $this->app->bind(
            'Rosetta\Services\StockThreshold\StockThresholdServiceInterface',
            'Rosetta\Services\StockThreshold\StockThresholdService'
        );
        $this->app->bind(
            'Rosetta\Services\SuperStockist\SuperStockistServiceInterface',
            'Rosetta\Services\SuperStockist\SuperStockistService'
        );
        $this->app->bind(
            'Rosetta\Services\SSDiscount\SSDiscountServiceInterface',
            'Rosetta\Services\SSDiscount\SSDiscountService'
        );
        $this->app->bind(
            'Rosetta\Services\SSPurchaseOrders\SSPurchaseOrdersServiceInterface',
            'Rosetta\Services\SSPurchaseOrders\SSPurchaseOrdersService'
        );
    }
}
