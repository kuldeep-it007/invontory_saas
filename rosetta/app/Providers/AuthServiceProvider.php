<?php

namespace Rosetta\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'Rosetta\Model' => 'Rosetta\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('regions','Rosetta\Policies\RegionPolicy@regions');
        Gate::define('companies','Rosetta\Policies\CompanyPolicy@companies');
        Gate::define('viewCategories','Rosetta\Policies\CategoryPolicy@view');
        Gate::define('createCategories','Rosetta\Policies\CategoryPolicy@create');
        Gate::define('viewCollections','Rosetta\Policies\CollectionPolicy@view');
        Gate::define('createCollections','Rosetta\Policies\CollectionPolicy@create');
        Gate::define('viewColours','Rosetta\Policies\ColourPolicy@view');
        Gate::define('createColours','Rosetta\Policies\ColourPolicy@create');
        Gate::define('tax_type_lookup', 'Rosetta\Policies\TaxTypePolicy@lookup');
        Gate::define('createDealers', 'Rosetta\Policies\DealerPolicy@create');
        Gate::define('updateDealers', 'Rosetta\Policies\DealerPolicy@update');
        Gate::define('deleteDealers', 'Rosetta\Policies\DealerPolicy@delete');
        Gate::define('viewDealers', 'Rosetta\Policies\DealerPolicy@view');
        Gate::define('approveDealers', 'Rosetta\Policies\DealerPolicy@approve');
        Gate::define('rejectDealers', 'Rosetta\Policies\DealerPolicy@reject');
        Gate::define('createDistributors', 'Rosetta\Policies\DistributorPolicy@create');
        Gate::define('updateDistributors', 'Rosetta\Policies\DistributorPolicy@update');
        Gate::define('manageProfile', 'Rosetta\Policies\DistributorPolicy@profile');
        Gate::define('manageProfile', 'Rosetta\Policies\DealerPolicy@profile');
        Gate::define('deleteDistributors', 'Rosetta\Policies\DistributorPolicy@delete');
        Gate::define('viewDistributors', 'Rosetta\Policies\DistributorPolicy@view');
        Gate::define('updateUser', 'Rosetta\Policies\UserPolicy@update');
        Gate::define('createUser', 'Rosetta\Policies\UserPolicy@create');
        Gate::define('viewUser', 'Rosetta\Policies\UserPolicy@view');
        Gate::define('deleteUser', 'Rosetta\Policies\UserPolicy@delete');
        Gate::define('viewPurchaseOrders', 'Rosetta\Policies\PurchaseOrderPolicy@view');
        Gate::define('updatePurchaseLines', 'Rosetta\Policies\PurchaseOrderPolicy@updateLine');
        Gate::define('deletePurchaseLines', 'Rosetta\Policies\PurchaseOrderPolicy@deleteLine');
        Gate::define('viewAdvanceOrders', 'Rosetta\Policies\AdvanceOrderPolicy@view');
        Gate::define('createAdvanceOrders', 'Rosetta\Policies\AdvanceOrderPolicy@create');
        Gate::define('updateAdvanceOrders', 'Rosetta\Policies\AdvanceOrderPolicy@update');
        Gate::define('updateAdvanceLines', 'Rosetta\Policies\AdvanceOrderPolicy@updateLine');
        Gate::define('deleteAdvanceLines', 'Rosetta\Policies\AdvanceOrderPolicy@deleteLine');
        Gate::define('deleteAdvanceItems', 'Rosetta\Policies\AdvanceOrderPolicy@deleteItem');
        Gate::define('createRetailCustomers', 'Rosetta\Policies\RetailCustomerPolicy@create');
        Gate::define('updateRetailCustomers', 'Rosetta\Policies\RetailCustomerPolicy@update');
        Gate::define('deleteRetailCustomers', 'Rosetta\Policies\RetailCustomerPolicy@delete');
        Gate::define('viewRetailCustomers', 'Rosetta\Policies\RetailCustomerPolicy@view');
        Gate::define('sendNotification', 'Rosetta\Policies\NotificationPolicy@send');
        Gate::define('viewStockPoints','Rosetta\Policies\StockPointPolicy@viewStockPoints');
        Gate::define('viewStockPointCustomers','Rosetta\Policies\StockPointPolicy@viewStockPointCustomers');
        Gate::define('addStockPoint','Rosetta\Policies\StockPointPolicy@addStockPoint');
        Gate::define('deleteStockPointCustomers','Rosetta\Policies\StockPointPolicy@deleteStockPointCustomers');
        Gate::define('addStockPointCustomers','Rosetta\Policies\StockPointPolicy@addStockPointCustomers');
        Gate::define('impersonate','Rosetta\Policies\UserPolicy@impersonate');
        Gate::define('viewOnlineDiscounts','Rosetta\Policies\OnlineDiscountsPolicy@listDiscounts');
        Gate::define('deleteOnlineDiscount','Rosetta\Policies\OnlineDiscountsPolicy@delete');
        Gate::define('addOnlineDiscount','Rosetta\Policies\OnlineDiscountsPolicy@create');
        Gate::define('manageTransactions','Rosetta\Policies\TransactionPolicy@manage');
        Gate::define('viewInvoices','Rosetta\Policies\InvoicePolicy@view');
        Gate::define('printInvoices','Rosetta\Policies\InvoicePolicy@print');
    }
}
