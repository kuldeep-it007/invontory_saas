<?php

use Illuminate\Support\Facades\Route;
use Rosetta\Events\Notifications;
use Rosetta\Helpers\CustomHelper;
use Rosetta\Models\BlanketOrder;
use Rosetta\Services\Notifications\WhatsApp\WhatsAppService;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::get('notice',['as' => 'master' ,'uses' => 'MasterController@notice']);
Route::post('login',['as' => 'login' ,'uses' => 'LoginController@postLogin']);
Route::post('validate_token',['as' => 'validate_token' ,'uses' => 'LoginController@validateToken']);//#832
Route::post('sign_up',['as' => 'signUp', 'uses' => 'UsersController@signUp']);//#785
Route::get('prospects',['as' => 'list', 'uses' => 'UsersController@listProspects']);//#785
Route::delete('prospects/{id}',['as' => 'delete', 'uses' => 'UsersController@deleteProspect']);//#785
Route::get('master',['as' => 'master' ,'uses' => 'MasterController@index']);
Route::post('otp',['as' => 'otpGenerate','uses' => 'OTPController@create']);
Route::get('tenants',['as' => 'tenants','uses' => 'MasterController@tenants']);
Route::get('stock_point_tenants',['as' => 'tenants','uses' => 'MasterController@stock_point_tenants']);//#776
Route::post('otpLogin',['as' => 'otpLogin', 'uses' => 'LoginController@postOTPLogin']);
Route::group(['prefix' => 'webhook'], function(){
	Route::post('/purchase_orders/{UUId}', ['as' => 'purchase.orders.refresh', 'uses' => 'PurchaseOrdersController@refresh']);
	Route::post('/advance_orders/{UUId}', ['as' => 'advance.orders.refresh', 'uses' => 'AdvanceOrdersController@refresh']);

});

Route::get('whatsapp/{recipientNumber}/{templateName}', function ($recipientNumber, $templateName) {
    return Rosetta\Helpers\CustomHelper::sendWhatsappMessage($recipientNumber, $templateName);
});
Route::post('update-dealer-data',['uses' => 'DealersController@updateDealerInfoFromWhatsapp'])->name('update-dealer-data');

Route::post('companies/{id}/dealers-from-whatsapp', ['as' => 'companies.dealers-from-whatsapp.create' , 'uses' => 'DealersController@createDealerFromWhatsApp']);

Route::get('get-active-sales-managers', ['as' => 'companies.sales-managers', 'uses' => 'SalesManagerController@getActiveSalesManager']);

Route::group(['middleware'=>['jwt.auth']],function(){
	Route::get('orders/{orderId}/print_dc',['as' => 'purchase.orders.put','uses' => 'PurchaseOrdersController@print_dc']);
	Route::get('orders/{orderId}/depot_print_dc',['as' => 'purchase.orders.put','uses' => 'DepotOrdersController@depot_print_dc']);
	Route::put('notifications/{id}',['as' => 'notifications.update', 'uses' => 'NotificationsController@markAsRead']);
	Route::put('notifications',['as' => 'notifications.update', 'uses' => 'NotificationsController@markAllAsRead']);
	Route::post('pusherAuth',['uses' => 'UsersController@pusherAuth']);
	Route::group(['prefix' => 'users'],function(){
		Route::get('self',['as' => 'users.self', 'uses' => 'UsersController@self']);
		Route::get('self/abilities',['as' => 'users.self.abilities', 'uses' => 'UsersController@selfAbilities']);
		Route::put('/{id}', ['as' => 'users.update', 'uses' => 'UsersController@update']);
		Route::put('/reset/{id}', ['as' => 'users.reset', 'uses' => 'UsersController@reset']);
		Route::put('/resetCustomerPassword/{id}', ['as' => 'users.reset', 'uses' => 'UsersController@resetCustomerPassword']);
		Route::post('/', ['as' => 'users.create', 'uses' => 'UsersController@create']);
		Route::get('/', ['as' => 'users.index', 'uses' => 'UsersController@index']);
		Route::get('/{id}',['as' => 'users.get', 'uses' => 'UsersController@get']);
		Route::delete('/{id}',['as' => 'users.delete', 'uses' => 'UsersController@delete']);
		Route::get('/{id}/notifications',['as' => 'users.get', 'uses' => 'UsersController@getAllNotifications']);
		Route::post('impersonate',['as' => 'users.impersonate', 'uses' => 'UsersController@impersonate']);
	});
	Route::group(['prefix' => 'countries'], function(){
		Route::get('/',['as' => 'countries.index', 'uses' => 'LocationController@getCountries']);
		Route::get('/{countryCode}/states',['as' => 'countries.states.index', 'uses' => 'LocationController@getStates']);
		Route::get('/{countryCode}/states/{stateCode}/cities',['as' => 'countries.states.cities.index', 'uses' => 'LocationController@getCities']);
	});
	Route::get('/dashboard', ['as' => 'dashboard.index', 'uses' => 'DashboardController@index']);
	Route::get('taxes',['as' => 'taxes.get', 'uses' => 'TaxTypesController@index']);

	Route::get('categories/{customer_type}/{customer_id}',['as' => 'categories.get','uses' => 'CategoriesController@index'])->where('customer_type','(distributors|dealers)');
	Route::get('categories/{category_code}/collections/{customer_type}/{customer_id}',['as' => 'collections.search', 'uses' => 'CollectionsController@search'])->where('customer_type','(distributors|dealers)');
	Route::get('categories/{category_code}/collections/{collection_code}/colours/{customer_type}/{customer_id}',['as' => 'colours.search', 'uses' => 'ColoursController@search'])->where('customer_type','(distributors|dealers)');
	
	Route::get('categories',['as' => 'categories.get', 'uses' => 'CategoriesController@index']);
	Route::post('categories', ['as' => 'categories.create', 'uses' => 'CategoriesController@create']);
	Route::put('categories/{code}', ['as' => 'categories.update', 'uses' => 'CategoriesController@update']);
	Route::get('categories/{category_code}/collections',['as' => 'collections.search', 'uses' => 'CollectionsController@search']);
	Route::post('categories/{category_code}/collections',['as' => 'collections.create', 'uses' => 'CollectionsController@create']);
	Route::get('categories/{category_code}/collections/{collection_code}/colours',['as' => 'colours.search', 'uses' => 'ColoursController@search']);
	Route::post('collections/{collection_code}/colours',['as' => 'colours.create', 'uses' => 'ColoursController@create']);
	Route::get('categories/{category_code}/collections/{collection_code}/techspecs',['as' => 'categories.collections.techspecs', 'uses' => 'CollectionsController@techspecs']);
	Route::group(['prefix' => 'regions'], function(){
		Route::get('/',['as' => 'regions.index','uses' => 'RegionsController@index']);
		Route::post('/',['as' => 'regions.create','uses' => 'RegionsController@create']);
		Route::put('/{id}',['as' => 'regions.update','uses' => 'RegionsController@update']);
		Route::get('/{id}',['as' => 'regions.get','uses' => 'RegionsController@get']);
		Route::get('/{id}/companies',['as' => 'regions.companies.get','uses' => 'RegionsController@companies']);
		Route::get('/{id}/product_taxes',['as' => 'regions.product_taxes.get','uses' => 'RegionsController@product_taxes']);
		Route::put('/{id}/update_product_taxes',['as' => 'regions.product_taxes.update','uses' => 'RegionsController@update_product_taxes']);
		Route::post('/{id}/add_product_taxes',['as' => 'regions.product_taxes.add','uses' => 'RegionsController@add_product_taxes']);
		Route::delete('/{id}/delete_product_taxes/{taxId}',['as' => 'regions.product_taxes.delete','uses' => 'RegionsController@delete_product_taxes']);
	});
	Route::get('depot_orders',['as' => 'depot.orders.listDepotBlanketOrders','uses' => 'DepotOrdersController@listDepotBlanketOrders']);//#776
	Route::get('depot_invoices',['as' => 'depot.invoices.listDepotInvoices','uses' => 'DepotOrdersController@listDepotInvoices']);//#795
	Route::group(['prefix' => 'companies'], function(){
		Route::get('/',['as' => 'companies.index','uses' => 'CompaniesController@index']);
		Route::post('/',['as' => 'companies.create','uses' => 'CompaniesController@create']);
		Route::post('/validate',['as' => 'companies.validate','uses' => 'CompaniesController@validateNav']);
		Route::put('/{id}',['as' => 'companies.update','uses' => 'CompaniesController@update']);
		Route::get('/{id}',['as' => 'companies.get','uses' => 'CompaniesController@get']);
		Route::get('/{id}/stock_threshold',['as' => 'companies.stock_threshold','uses' => 'StockThresholdController@index']);
		Route::put('/{id}/stock_threshold',['as' => 'companies.stock_threshold','uses' => 'StockThresholdController@refresh']);
		Route::put('/{id}/stock_threshold/{thresholdId}',['as' => 'companies.stock_threshold.update','uses' => 'StockThresholdController@updateThreshold']);
		Route::delete('/{id}/stock_threshold',['as' => 'companies.stock_threshold.delete','uses' => 'StockThresholdController@deleteThreshold']);
		Route::post('/{id}/export_stock_threshold',['as' => 'companies.stock_threshold','uses' => 'StockThresholdController@export']);
		Route::post('/{id}/export_orders',['as' => 'companies.post','uses' => 'CompaniesController@exportOrders']);//#814
		Route::get('/{id}/dashboard',['as' => 'companies.dashboard','uses' => 'CompaniesController@dashboard']);
		Route::get('/{id}/inventory',['as' => 'companies.inventory','uses' => 'CompaniesController@inventory']);
		Route::post('/{id}/unblock_acrossTenants',['as' => 'companies.unblock_acrossTenants','uses' => 'CompaniesController@unblock_acrossTenants']);
		Route::post('/{id}/unblock_advance_items_acrossTenants',['as' => 'companies.unblock_advance_items_acrossTenants','uses' => 'CompaniesController@unblock_advance_items_acrossTenants']);
		Route::get('/{id}/inventory/{itemID}',['as' => 'companies.inventory','uses' => 'CompaniesController@inventoryDetails']);
		Route::get('/{id}/intransit_inventory/{itemID}',['as' => 'companies.inventory','uses' => 'CompaniesController@intransitInventoryDetails']);
		Route::post('/{id}/categories/{category_code}/collections/{collection_code}/set_mup',['as' => 'companies.collection_prices','uses' => 'CompaniesController@setCollectionPrice']);
		Route::post('{id}/super_stockists/{superStockistId}/add_stock',['as' => 'super_stockists.stock.add','uses' => 'SuperStockistsController@addStock']);
		Route::delete('{id}/super_stockists/{superStockistId}/delete_stock/{stockId}',['as' => 'super_stockists.salstockes.delete','uses' => 'SuperStockistsController@deleteStock']);
		Route::get('/{id}/super_stockists', ['as' => 'companies.super_stockists.get' , 'uses' => 'SuperStockistsController@index']);
		Route::post('/{id}/super_stockists', ['as' => 'companies.super_stockists.create' , 'uses' => 'SuperStockistsController@create']);
		Route::post('/{id}/sync_super_stockist', ['as' => 'companies.super_stockist.sync' , 'uses' => 'SuperStockistsController@sync']);
		Route::get('/{id}/super_stockists/{superStockistId}/searchDistributors', ['as' => 'companies.super_stockists..distributors.search' , 'uses' => 'DistributorsController@search']);
		Route::get('/{id}/distributors', ['as' => 'companies.distributors.get' , 'uses' => 'DistributorsController@index']);
		Route::get('/{id}/super_stockists/{superStockistId}/distributors', ['as' => 'companies.super_stockists.distributors.get' , 'uses' => 'DistributorsController@index']);
		Route::get('/{companyID}/super_stockists/{superStockistId}/addresses', ['as' => 'companies.super_stockists.addresses' , 'uses' => 'SuperStockistsController@getShippingAddresses']);
		Route::post('/{companyID}/super_stockists/{superStockistId}/addresses', ['as' => 'companies.super_stockists.addresses.create' , 'uses' => 'SuperStockistsController@createShippingAddresses']);
		Route::put('/{companyID}/super_stockists/{superStockistId}/addresses/{ADDRESS_CODE}', ['as' => 'companies.super_stockists.addresses.update' , 'uses' => 'SuperStockistsController@updateShippingAddress']);
		Route::delete('/{companyID}/super_stockists/{superStockistId}/addresses/{ADDRESS_CODE}', ['as' => 'companies.super_stockists.addresses.delete' , 'uses' => 'SuperStockistsController@deleteShippingAddress']);
		Route::get('/{companyID}/super_stockists/{superStockistId}', ['as' => 'companies.super_stockists.get' , 'uses' => 'SuperStockistsController@getForCompany']);
		Route::put('/{id}/super_stockists/{superStockistId}', ['as' => 'companies.super_stockists.update' , 'uses' => 'SuperStockistsController@updateCompanySuperStockist']);

		Route::get('/{id}/distributorSearch', ['as' => 'companies.distributors.get' , 'uses' => 'DistributorsController@search']);
		Route::get('/{id}/super_stockists/{superStockistId}/distributors/{distributorId}', ['as' => 'companies.super_stockists.distributors.get' , 'uses' => 'DistributorsController@getForSuperStockist']);
		Route::get('/{id}/dealerSearch', ['as' => 'companies.dealers.get' , 'uses' => 'DealersController@search']);
		Route::post('/{id}/distributors', ['as' => 'companies.distributors.create' , 'uses' => 'DistributorsController@create']);
		Route::post('/{id}/super_stockists/{superStockistId}/distributors', ['as' => 'companies.super_stockists.distributors.create' , 'uses' => 'DistributorsController@create']);
		Route::put('/{id}/distributors/{distributorId}', ['as' => 'companies.distributors.update' , 'uses' => 'DistributorsController@updateCompanyDistributor']);
		Route::put('/{id}/super_stockists/{superStockistId}/distributors/{distributorId}', ['as' => 'companies.super_stockists.distributors.update' , 'uses' => 'DistributorsController@update']);
		Route::put('/{id}/distributors/{distributorID}/profile', ['as' => 'companies.distributors.profile' , 'uses' => 'DistributorsController@profile']);
		Route::get('/{companyID}/distributors/{distributorID}', ['as' => 'companies.distributors.get' , 'uses' => 'DistributorsController@getForCompany']);
		Route::get('/{companyID}/distributors/{distributorID}/addresses', ['as' => 'companies.distributors.addresses' , 'uses' => 'DistributorsController@getShippingAddresses']);
		Route::post('/{companyID}/distributors/{distributorID}/addresses', ['as' => 'companies.distributors.addresses.create' , 'uses' => 'DistributorsController@createShippingAddresses']);
		Route::put('/{companyID}/distributors/{distributorID}/addresses/{ADDRESS_CODE}', ['as' => 'companies.distributors.addresses.update' , 'uses' => 'DistributorsController@updateShippingAddress']);
		Route::delete('/{companyID}/distributors/{distributorID}/addresses/{ADDRESS_CODE}', ['as' => 'companies.distributors.addresses.delete' , 'uses' => 'DistributorsController@deleteShippingAddress']);
		Route::post('/{companyID}/dealers/{dealerID}/addresses', ['as' => 'companies.dealers.addresses.create' , 'uses' => 'DealersController@createShippingAddresses']);
		Route::put('/{companyID}/dealers/{dealerID}/addresses/{ADDRESS_CODE}', ['as' => 'companies.dealers.addresses.update' , 'uses' => 'DealersController@updateShippingAddress']);
		Route::get('/{companyID}/dealers/{dealerID}/addresses', ['as' => 'companies.dealers.addresses' , 'uses' => 'DealersController@getShippingAddresses']);
		Route::delete('/{companyID}/dealers/{dealerID}/addresses/{ADDRESS_CODE}', ['as' => 'companies.dealers.addresses.delete' , 'uses' => 'DealersController@deleteShippingAddress']);
		Route::post('/{id}/dealers', ['as' => 'companies.dealers.create' , 'uses' => 'DealersController@create']);
		Route::post('/{id}/distributors/{distributorId}/export_dealers',['as' => 'distributor.dealers.export','uses' => 'DealersController@exportDealers']);//#826
		//Route::get('/{id}/distributors/{distributorId}/export_dealers',['as' => 'distributor.dealers.export','uses' => 'DealersController@exportDealers']);//#813
		//Route::get('/{id}/export_dealers',['as' => 'companies.dealers.export','uses' => 'DealersController@exportDealers']);//#813
		Route::post('/{id}/export_dealers',['as' => 'companies.dealers.export','uses' => 'DealersController@exportDealers']);//#826
		Route::put('/{id}/dealers/{dealerId}', ['as' => 'companies.dealers.update' , 'uses' => 'DealersController@updateCompanyDealer']);
		Route::put('/{id}/dealers/{dealerId}/profile', ['as' => 'companies.dealers.profile' , 'uses' => 'DealersController@profile']);
		Route::get('/{id}/dealers/{dealerId}', ['as' => 'companies.dealers.get' , 'uses' => 'DealersController@getForCompany']);
		Route::get('/{id}/dealers', ['as' => 'companies.dealers.get' , 'uses' => 'DealersController@index']);
		Route::post('/{id}/distributors/{distributorId}/dealers', ['as' => 'companies.distributors.dealers.create' , 'uses' => 'DealersController@create']);
		Route::put('/{id}/distributors/{distributorId}/dealers/{dealerId}', ['as' => 'companies.distributors.dealers.update' , 'uses' => 'DealersController@update']);
		Route::get('/{id}/distributors/{distributorId}/dealers', ['as' => 'companies.distributors.dealers.get' , 'uses' => 'DealersController@index']);
		Route::get('/{id}/distributors/{distributorId}/searchDealers', ['as' => 'companies.distributors.dealers.search' , 'uses' => 'DealersController@search']);
		Route::get('/{id}/distributors/{distributorId}/dealers/{dealerId}', ['as' => 'companies.distributors.dealers.get' , 'uses' => 'DealersController@getForDistributor']);
		Route::get('{id}/distributors/{distributorId}/temp_dealers', ['as' => 'companies.get', 'uses' => 'DealersController@getTempDealers']);
		Route::get('{id}/temp_dealers', ['as' => 'companies.get', 'uses' => 'DealersController@getTempDealers']);
		Route::get('{id}/distributors/{distributorId}/rejected_dealers', ['as' => 'companies.get', 'uses' => 'DealersController@getRejectedDealers']);
		Route::get('{id}/temp_dealers/{tempDealerId}', ['as' => 'companies.get', 'uses' => 'DealersController@findCompTempDealer']);
		Route::put('{id}/temp_dealers/{tempDealerId}', ['as' => 'companies.approve', 'uses' => 'DealersController@approveCompTempDealer']);
		Route::put('{id}/reject_temp_dealers/{tempDealerId}', ['as' => 'companies.reject', 'uses' => 'DealersController@rejectTempDealer']);
		Route::get('{id}/taxes', ['as' => 'companies.taxes', 'uses' => 'CompaniesController@taxes']);
		Route::get('{id}/states', ['as' => 'companies.states', 'uses' => 'CompaniesController@states']);
		Route::get('{id}/sales-managers', ['as' => 'companies.sales-managers', 'uses' => 'SalesManagerController@getActiveSalesManager']);		
		Route::get('{id}/billinglocations', ['as' => 'companies.billing.locations', 'uses' => 'CompaniesController@billingLocations']);
		Route::post('{id}/billinglocation', ['as' => 'companies.billing.location', 'uses' => 'CompaniesController@createBillingLocation']);
		Route::post('{id}/distributors/{distributorId}/cart_items',['as' => 'distributors.cart.items','uses' => 'DistributorsController@addToCart']);
		Route::delete('{id}/distributors/{distributorId}/cart_items',['as' => 'distributors.cart.items','uses' => 'DistributorsController@deleteFromCart']);
		Route::post('{id}/distributors/{distributorId}/block_items',['as' => 'distributors.cart.items.block','uses' => 'DistributorsController@blockItems']);
		Route::post('{id}/distributors/{distributorId}/unBlock_items',['as' => 'distributors.cart.items.unBlock','uses' => 'DistributorsController@unBlockItems']);
		Route::get('{id}/distributors/{distributorId}/cart_items',['as' => 'distributors.cart.items','uses' => 'DistributorsController@getCart']);
		Route::get('{id}/distributors/{distributorId}/blocked_items',['as' => 'distributors.cart.items.block','uses' => 'DistributorsController@getBlockedItems']);
		Route::put('{id}/distributors/{distributorId}/cart_items',['as' => 'distributors.cart.items.update','uses' => 'DistributorsController@updateCart']);
		Route::post('{id}/dealers/{dealerId}/cart_items',['as' => 'dealers.cart.items','uses' => 'DealersController@addToCart']);
		Route::post('{id}/dealers/{dealerId}/block_items',['as' => 'dealers.cart.items.block','uses' => 'DealersController@blockItems']);
		Route::post('{id}/dealers/{dealerId}/unBlock_items',['as' => 'dealers.cart.items.unBlock','uses' => 'DealersController@unBlockItems']);
		Route::get('{id}/dealers/{dealerId}/cart_items',['as' => 'dealers.cart.items','uses' => 'DealersController@getCart']);
		Route::get('{id}/dealers/{dealerId}/blocked_items',['as' => 'dealers.cart.items.block','uses' => 'DealersController@getBlockedItems']);
		Route::delete('{id}/dealers/{dealerId}/cart_items',['as' => 'dealers.cart.items','uses' => 'DealersController@deleteFromCart']);
		Route::put('{id}/dealers/{dealerId}/cart_items',['as' => 'dealers.cart.items.update','uses' => 'DealersController@updateCart']);
		Route::post('{id}/{customer_type}/{customer_id}/purchase_orders',['as' => 'distributors.purchase.orders','uses' => 'PurchaseOrdersController@create'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::get('{id}/{customer_type}/{customer_id}/purchase_orders',['as' => 'distributors.purchase.orders','uses' => 'PurchaseOrdersController@index'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::get('{id}/{customer_type}/{customer_id}/blanket_orders',['as' => 'distributors.purchase.orders','uses' => 'PurchaseOrdersController@blanketOrders'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::get('{id}/{customer_type}/{customer_id}/advance_blanket_orders',['as' => 'distributors.advance.orders','uses' => 'AdvanceOrdersController@blanketOrders'])
			->where('customer_type','(distributors|dealers|super_stockists)');

		Route::get('{id}/{customer_type}/{customer_id}/blanket_orders/{blanket_order_id}/payments',['as' => 'distributors.purchase.orders','uses' => 'PaymentGatewayController@getPaymentStatus'])
			->where('customer_type','(distributors|dealers|super_stockists)');

		Route::get('{id}/blanket_orders/{blanket_order_id}/payments',['as' => 'purchase.orders','uses' => 'PaymentGatewayController@getPaymentStatus'])
			->where('customer_type','(distributors|dealers|super_stockists)');


		Route::put('{id}/{customer_type}/{customer_id}/purchase_orders/{orderId}',['as' => 'distributors.purchase.orders','uses' => 'PurchaseOrdersController@update'])
			->where('customer_type','(distributors|dealers|super_stockists)');

		Route::put('{id}/{customer_type}/{customer_id}/ship_invoice/{orderId}', ['as' => 'distributors.purchase.orders', 'uses' => 'PurchaseOrdersController@shipAndInvoice'])
			->where('customer_type', '(distributors|super_stockists)');

		Route::delete('{id}/{customer_type}/{customer_id}/purchase_orders/{orderId}',['as' => 'distributors.purchase.orders','uses' => 'PurchaseOrdersController@delete'])
			->where('customer_type','(distributors|dealers|super_stockists)');

		Route::delete('{id}/{customer_type}/{customer_id}/depot_purchase_orders/{orderId}',['as' => 'distributors.purchase.orders','uses' => 'DepotOrdersController@deletePO'])
			->where('customer_type','(distributors|dealers)');//#796

		Route::get('{id}/purchase_orders/',['as' => 'companies.purchase.orders.listCompanyPOs','uses' => 'PurchaseOrdersController@listCompanyPOs']);
		Route::get('{id}/blanket_orders/',['as' => 'companies.blanket.orders.listCompanyBlanketOrders','uses' => 'PurchaseOrdersController@listCompanyBlanketOrders']);
		Route::get('{id}/advance_blanket_orders/',['as' => 'companies.blanket.orders.listCompanyBlanketOrders','uses' => 'AdvanceOrdersController@listCompanyBlanketOrders']);
		Route::get('{id}/inter_depot_orders',['as' => 'companies.depot.orders.listDepotBlanketOrders','uses' => 'DepotOrdersController@listInterDepotBlanketOrders']);
		Route::get('{id}/current_depot_orders/',['as' => 'companies.depot.orders.listCurrentDepotBlanketOrders','uses' => 'DepotOrdersController@listCurrentDepotBlanketOrders']);
		Route::get('{id}/depot_orders/{blanket_order_id}/payments',['as' => 'depot.orders','uses' => 'DepotOrdersController@getPaymentStatus'])
			->where('customer_type','(distributors|dealers)');//#778

		Route::get('{id}/co_distributor_blanket_orders/',['as' => 'co_distributor.blanket.orders.listCoDistributorBlanketOrders','uses' => 'PurchaseOrdersController@listCoDistributorBlanketOrders']);
		Route::delete('{id}/{customer_type}/{customer_id}/purchase_orders/{purchase_order_id}/purchase_lines/{line_id}',['as' => 'distributors.purchase.orders.delete.line','uses' => 'PurchaseOrdersController@deleteLine']);
		Route::delete('{id}/{customer_type}/{customer_id}/depot_purchase_orders/{purchase_order_id}/purchase_lines/{line_id}',['as' => 'distributors.purchase.orders.delete.line','uses' => 'DepotOrdersController@deleteLine']);//#797
		Route::put('{id}/{customer_type}/{customer_id}/purchase_orders/{purchase_order_id}/purchase_lines/{line_id}',['as' => 'distributors.purchase.orders.update.line','uses' => 'PurchaseOrdersController@updateLine']);
		Route::put('{id}/{customer_type}/{customer_id}/depot_purchase_orders/{purchase_order_id}/purchase_lines/{line_id}',['as' => 'distributors.purchase.orders.update.line','uses' => 'DepotOrdersController@updateLine']);//#978
		Route::post('{id}/{customer_type}/{customer_id}/purchase_orders/{purchase_order_id}/shipment',['as' => 'distributors.purchase.orders.shipment','uses' => 'PurchaseOrdersController@updateShipment'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::post('{id}/{customer_type}/{customer_id}/advance_orders/{advance_order_id}/shipment',['as' => 'distributors.advance.orders.shipment','uses' => 'AdvanceOrdersController@updateShipment'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::post('{id}/stock',['as' => 'companies.stock','uses' => 'CompaniesController@stock']);
		Route::get('{id}/blocked_items',['as' => 'companies.blocked.get','uses' => 'CompaniesController@blockedIndex']);
		Route::get('{id}/blocked_advance_items',['as' => 'companies.blocked_advance.get','uses' => 'CompaniesController@blockedAdvanceIndex']);
		Route::get('{id}/distributors/{distributorId}/dud_blocked_company_items',['as' => 'companies.blocked.get','uses' => 'CompaniesController@dudBlockedCompanyStock']);
		Route::get('{id}/advance_orders/',['as' => 'companies.advance.orders.listCompanyAOs','uses' => 'AdvanceOrdersController@listCompanyAOs']);
		Route::get('{id}/{customer_type}/{customer_id}/advance_orders',['as' => 'distributors.advance.orders','uses' => 'AdvanceOrdersController@index'])
			->where('customer_type','(distributors|dealers)');
		Route::post('{id}/{customer_type}/{customer_id}/advance_orders',['as' => 'distributors.advance.orders','uses' => 'AdvanceOrdersController@create'])
			->where('customer_type','(distributors|dealers)');
		Route::post('{id}/{customer_type}/{customer_id}/intransit_orders',['as' => 'distributors.advance.orders','uses' => 'AdvanceOrdersController@createIntransitOrder'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::put('{id}/{customer_type}/{customer_id}/advance_orders/{orderId}',['as' => 'distributors.advance.orders','uses' => 'AdvanceOrdersController@update'])
			->where('customer_type','(distributors|dealers)');
		Route::put('{id}/{customer_type}/{customer_id}/intransit_orders/{orderId}',['as' => 'distributors.advance.orders','uses' => 'AdvanceOrdersController@updateInTransitOrder'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::get('{id}/{customer_type}/{customer_id}/advance_items',['as' => 'distributors.advance.items','uses' => 'AdvanceOrdersController@getAdvanceItems'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::get('{id}/{customer_type}/{customer_id}/blocked_advance_items',['as' => 'distributors.advance.items','uses' => 'AdvanceOrdersController@getBlockedAdvanceItems'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::post('{id}/{customer_type}/{customer_id}/delete_advance_items',['as' => 'customers.advance.items.delete','uses' => 'AdvanceOrdersController@deleteAdvanceItems'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::post('{id}/{customer_type}/delete_advance_items',['as' => 'advance.items.delete','uses' => 'AdvanceOrdersController@deleteCustomerAdvanceItems'])
			->where('customer_type','(distributors|dealers)');
		Route::post('{id}/{customer_type}/{customer_id}/advance_items',['as' => 'distributors.advance.items','uses' => 'AdvanceOrdersController@addToAdvanceCart'])
			->where('customer_type','(distributors|dealers)');
		Route::post('{id}/{customer_type}/{customer_id}/advance_cart_items',['as' => 'distributors.advance.items','uses' => 'AdvanceOrdersController@addIntransitToAdvanceCart'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::post('{id}/{customer_type}/{customer_id}/block_advance_items',['as' => 'distributors.advance.items','uses' => 'AdvanceOrdersController@blockAdvanceItems'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::post('{id}/{customer_type}/{customer_id}/unBlock_advance_items',['as' => 'distributors.advance.items','uses' => 'AdvanceOrdersController@unBlockAdvanceItems'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::post('{id}/{customer_type}/{customer_id}/advance_item/{advance_item_id}/messages', ['as' => 'messages.create', 'uses' => 'AdvanceOrdersController@addMessage']);
		Route::get('{id}/{customer_type}/{customer_id}/advance_item/{advance_item_id}/messages', ['as' => 'messages.index', 'uses' => 'AdvanceOrdersController@getMessages']);
		Route::put('{id}/{customer_type}/{customer_id}/advance_items/{advance_item_id}',['as' => 'distributors.advance.items','uses' => 'AdvanceOrdersController@updateAdvanceCartItem'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::put('{id}/{customer_type}/{customer_id}/advance_orders/{orderId}/advance_lines/{lineId}',['as' => 'distributors.advance.orders','uses' => 'AdvanceOrdersController@updateLine'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::post('{id}/{customer_type}/{customer_id}/advance_orders/{orderId}/advance_lines/{lineId}/messages', ['as' => 'messages.create', 'uses' => 'AdvanceOrdersController@addLineMessage'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::get('{id}/{customer_type}/{customer_id}/advance_orders/{orderId}/advance_lines/{lineId}/messages', ['as' => 'messages.index', 'uses' => 'AdvanceOrdersController@getLineMessages'])
			->where('customer_type','(distributors|dealers|super_stockists)');

		Route::delete('{id}/{customer_type}/{customer_id}/advance_orders/{orderId}',['as' => 'customer.advance.orders','uses' => 'AdvanceOrdersController@delete'])
			->where('customer_type','(distributors|dealers)');
		Route::delete('{id}/{customer_type}/{customer_id}/intransit_orders/{orderId}',['as' => 'customer.advance.orders','uses' => 'AdvanceOrdersController@deleteInTransitOrder'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::delete('{id}/{customer_type}/{customer_id}/advance_orders/{advance_order_id}/advance_lines/{line_id}',['as' => 'customer.advance.orders.delete.line','uses' => 'AdvanceOrdersController@deleteLine'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::delete('{id}/{customer_type}/{customer_id}/intransit_orders/{advance_order_id}/advance_lines/{line_id}',['as' => 'customer.advance.orders.delete.line','uses' => 'AdvanceOrdersController@deleteInTransitLine'])
			->where('customer_type','(distributors|dealers|super_stockists)');

		Route::put('{id}/{customer_type}/{customer_id}/advance_orders/{orderId}/advance_lines/{lineId}/update',['as' => 'distributors.advance.orders','uses' => 'AdvanceOrdersController@updateLineQtyDisc'])
			->where('customer_type','(distributors|dealers|super_stockists)');
			

		Route::get('/{id}/retail_customers', ['as' => 'companies.retailCustomers.get' , 'uses' => 'RetailCustomersController@index']);
		Route::post('/{id}/retailCustomers', ['as' => 'companies.retailCustomers.create' , 'uses' => 'RetailCustomersController@create']);
		Route::put('/{id}/retailCustomers/{retailCustomerID}', ['as' => 'companies.retailCustomers.update' , 'uses' => 'RetailCustomersController@update']);
		Route::get('/{companyID}/retailCustomers/{retailCustomerID}', ['as' => 'companies.retailCustomers.get' , 'uses' => 'RetailCustomersController@get']);
		Route::get('/{companyID}/retailCustomers/{retailCustomerID}/addresses', ['as' => 'companies.retailCustomers.addresses' , 'uses' => 'RetailCustomersController@getShippingAddresses']);
		Route::post('/{companyID}/retailCustomers/{retailCustomerID}/addresses', ['as' => 'companies.retailCustomers.addresses.create' , 'uses' => 'RetailCustomersController@createShippingAddresses']);
		Route::put('/{companyID}/retailCustomers/{retailCustomerID}/addresses/{ADDRESS_CODE}', ['as' => 'companies.retailCustomers.addresses.update' , 'uses' => 'RetailCustomersController@updateShippingAddress']);
		Route::delete('/{companyID}/retailCustomers/{retailCustomerID}/addresses/{ADDRESS_CODE}', ['as' => 'companies.retailCustomers.addresses.delete' , 'uses' => 'RetailCustomersController@deleteShippingAddress']);
		Route::post('{id}/notification',['as' => 'notification.send', 'uses' => 'NotificationsController@sendNotification']);

		Route::get('/{companyID}/product_attributes',['as' => 'company.product_attributes.index', 'uses' => 'ActiveProductAttributesController@list']);
		Route::post('/{companyID}/product_attributes',['as' => 'company.product_attributes.add', 'uses' => 'ActiveProductAttributesController@add']);
		Route::put('/{companyID}/product_attributes/{attribute_id}',['as' => 'company.product_attributes.update', 'uses' => 'ActiveProductAttributesController@update']);
		Route::delete('/{companyID}/product_attributes/{attribute_id}',['as' => 'company.product_attributes.delete', 'uses' => 'ActiveProductAttributesController@delete']);
		Route::delete('/{companyID}/product_attribute_colours/{attribute_id}',['as' => 'company.product_attribute_colours.delete', 'uses' => 'ActiveProductAttributesController@deleteAttributesWithColours']);

		Route::get('{companyID}/{customer_type}/{customer_id}/product_attributes', ['as' => 'customer.product_attributes.index', 'uses' => 'ActiveProductAttributesController@list'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::get('{companyID}/{customer_type}/{customer_id}/customer_product_attributes', ['as' => 'customer.product_attributes.index', 'uses' => 'ActiveProductAttributesController@getAttributesForCustomer'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::post('/{id}/sync_distributor', ['as' => 'companies.distributor.sync' , 'uses' => 'DistributorsController@sync']);
		Route::post('/{id}/sync_dealer', ['as' => 'companies.dealers.sync' , 'uses' => 'DealersController@sync']);
		Route::post('/{id}/export_distributors',['as' => 'companies.distributors.export','uses' => 'DistributorsController@exportDistributors']);//#826
	//	Route::post('/{id}/super_stockists/{superStockistId}/sync_distributor', ['as' => 'companies.super_stockist.distributor.sync' , 'uses' => 'DistributorsController@syncUnderSuperStockist']);
		Route::get('{id}/super_stockists/{superStockistId}/blocked_items',['as' => 'super_stockists.cart.items.block','uses' => 'SuperStockistsController@getBlockedItems']);
		Route::post('{id}/super_stockists/{superStockistId}/unBlock_items',['as' => 'super_stockists.cart.items.unBlock','uses' => 'SuperStockistsController@unBlockItems']);
		Route::post('{id}/super_stockists/{superStockistId}/block_items',['as' => 'super_stockists.cart.items.block','uses' => 'SuperStockistsController@blockItems']);
		Route::post('{id}/super_stockists/{superStockistId}/unBlock_items',['as' => 'super_stockists.cart.items.unBlock','uses' => 'SuperStockistsController@unBlockItems']);
		Route::post('{id}/super_stockists/{superStockistId}/cart_items',['as' => 'super_stockists.cart.items','uses' => 'SuperStockistsController@addToCart']);
		Route::delete('{id}/super_stockists/{superStockistId}/cart_items',['as' => 'super_stockists.cart.items','uses' => 'SuperStockistsController@deleteFromCart']);
		Route::get('{id}/super_stockists/{superStockistId}/cart_items',['as' => 'super_stockists.cart.items','uses' => 'SuperStockistsController@getCart']);
		Route::post('/{id}/super_stockists/{superStockistId}/export_distributors',['as' => 'super_stockist.distributors.export','uses' => 'DistributorsController@exportDistributors']);//#826
		Route::get('{companyID}/{customer_type}/{customer_id}/users', ['as' => 'customer.users.index', 'uses' => 'UsersController@list'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::get('/{id}/superStockistSearch', ['as' => 'companies.super_stockists.get' , 'uses' => 'SuperStockistsController@search']);
		Route::delete('{companyID}/{customer_type}/{customer_id}/users/{user_id}', ['as' => 'customer.users.index', 'uses' => 'UsersController@deleteUser'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::post('{companyID}/{customer_type}/{customer_id}/users', ['as' => 'customer.users.create', 'uses' => 'UsersController@createUser'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::put('{companyID}/{customer_type}/{customer_id}/users', ['as' => 'customer.users.update', 'uses' => 'UsersController@updateUser'])
			->where('customer_type','(distributors|dealers)');
		Route::get('{companyID}/{customer_type}/{id}/inventory',['as' => 'distributors.inventory','uses' => 'DistributorsController@getInventory'])
			->where('customer_type','(distributors|dealers|super_stockists)');
		Route::get('{companyID}/distributors/{id}/inventory/{inventoryId}',['as' => 'distributors.inventory','uses' => 'DistributorsController@getInventoryDetails']);
		Route::get('{companyID}/distributors/{id}/intransit_inventory/{inventoryId}',['as' => 'distributors.inventory','uses' => 'DistributorsController@getInTransitInventoryDetails']);
		Route::post('{id}/dealers/{dealer_id}/purchase_orders',['as' => 'dealers.purchase.orders','uses' => 'PurchaseOrdersController@create']);
		Route::get('{id}/distributors/{distributor_id}/sales_orders',['as' => 'distributors.sales.orders','uses' => 'SalesOrdersController@index']);
		Route::get('{id}/distributors/{distributor_id}/advance_sales_orders',['as' => 'distributors.advance.sales.orders','uses' => 'AdvanceSalesOrdersController@index']);
		Route::delete('{id}/distributors/{distributor_id}/advance_sales_orders/{orderId}',['as' => 'distributors.advance.sales.orders','uses' => 'AdvanceSalesOrdersController@delete']);
		Route::delete('{id}/distributors/{distributor_id}/sales_orders/{orderId}',['as' => 'distributors.sales.orders','uses' => 'SalesOrdersController@delete']);
		// Route::delete('{id}/{customer_type}/{customer_id}/sales_orders/{orderId}',['as' => 'distributors.sales.orders','uses' => 'SalesOrdersController@delete']);
		Route::delete('{id}/distributors/{distributor_id}/depot_sales_orders/{orderId}',['as' => 'distributors.sales.orders','uses' => 'DepotOrdersController@deleteSO']);//#796
		Route::delete('{id}/distributors/{distributor_id}/sales_orders/{orderId}/sales_lines/{line_id}',['as' => 'distributors.sales.orders.line.delete','uses' => 'SalesOrdersController@deleteLine']);
		Route::delete('{id}/distributors/{distributor_id}/depot_sales_orders/{orderId}/sales_lines/{line_id}',['as' => 'distributors.sales.orders.line.delete','uses' => 'DepotOrdersController@deleteSOLine']);
		Route::put('{id}/distributors/{distributor_id}/sales_orders/{orderId}/sales_lines/{line_id}',['as' => 'distributors.sales.orders.line.update','uses' => 'SalesOrdersController@updateLine']);
		Route::post('{id}/distributors/{distributor_id}/sales_orders/{orderId}/payments',['as' => 'distributors.sales.orders.payments','uses' => 'SalesOrdersController@payment']);
		Route::put('{id}/distributors/{distributor_id}/sales_orders/{orderId}/ship',['as' => 'distributors.sales.orders.ship','uses' => 'SalesOrdersController@ship']);
		Route::put('{id}/distributors/{distributor_id}/sales_orders/{orderId}/addInvoice',['as' => 'distributors.sales.orders.ship','uses' => 'SalesOrdersController@addInvoice']);

		Route::post('/{companyID}/dealers/{dealerID}/customers', ['as' => 'companies.dealers.customers.create' , 'uses' => 'DealersController@createCustomers']);
		Route::put('/{companyID}/dealers/{dealerID}/customers/{customerId}', ['as' => 'companies.dealers.customers.update' , 'uses' => 'DealersController@updateCustomer']);
		Route::get('/{companyID}/dealers/{dealerID}/customers', ['as' => 'companies.dealers.customers' , 'uses' => 'DealersController@getCustomers']);
		Route::delete('/{companyID}/dealers/{dealerID}/customers/{customerId}', ['as' => 'companies.dealers.customers.delete' , 'uses' => 'DealersController@deleteCustomer']);
		Route::post('/{companyID}/dealers/{dealerID}/engineers', ['as' => 'companies.dealers.engineers.create' , 'uses' => 'DealersController@createEngineers']);
		Route::get('/{companyID}/dealers/{dealerID}/engineers', ['as' => 'companies.dealers.engineers' , 'uses' => 'DealersController@getEngineers']);
		Route::get('/{companyID}/distributors/{distributor_id}/engineers', ['as' => 'companies.distributors.engineers' , 'uses' => 'DistributorsController@getEngineers']);
		Route::get('{id}/distributors/{distributorId}/my_blocked_stock',['as' => 'distributors.blocked.stock','uses' => 'DistributorsController@getMyBlockedStock']);
		Route::post('{id}/distributors/{distributorId}/unBlock_my_stock/{itemId}',['as' => 'distributors.stock.unBlock','uses' => 'DistributorsController@unBlockMyStock']);
		Route::post('{id}/distributors/{distributorId}/add_stock',['as' => 'distributors.stock.add','uses' => 'DistributorsController@addStock']);
		Route::delete('{id}/distributors/{distributorId}/delete_stock/{stockId}',['as' => 'distributors.salstockes.delete','uses' => 'DistributorsController@deleteStock']);
		Route::get('{id}/stockpoints',['as' => 'companies.stockpoints','uses' => 'CompaniesController@stockpoints']);
		Route::get('{id}/stockpoints/{stockpointid}/customers',['as' => 'companies.stockpoints.customers','uses' => 'CompaniesController@getStockpointCustomers']);
		Route::post('{id}/stockpoints',['as' => 'companies.stockpoints.create','uses' => 'CompaniesController@createStockpoint']);
		Route::delete('{id}/stockpoints/{stockpointid}/{customer_type}/{customerid}',['as' => 'companies.stockpoints.delete','uses' => 'CompaniesController@deleteCustomerStockPoint'])->where('customer_type','(distributors|dealers|super_stockists)');
		Route::post('{id}/stockpoints/{stockpointid}/{customer_type}',['as' => 'companies.stockpoints.custome.add','uses' => 'CompaniesController@addCustomerToStockPoint'])->where('customer_type','(distributors|dealers|super_stockists)');
		Route::get('{id}/categories/{category_code}/collections/{collection_code}/colours/{colour_code}/get_product_price',['as' => 'product.price.get', 'uses' => 'ProductsController@getProductPrice']);
		Route::group(['prefix' => '{id}/distributors/{distributor_ID}/co_distributors'],function(){
			Route::get('/',['as' => 'companies.distributor.co_distributors','uses' => 'CoDistributorController@index']);
			Route::put('/',['as' => 'companies.distributor.co_distributors','uses' => 'CoDistributorController@update']);
			Route::get('/orders',['as' => 'co_distributors.orders' ,'uses' => 'CoDistributorController@orders']);
			Route::get('/orders/{order_id}',['as' => 'co_distributors.orders.get' ,'uses' => 'CoDistributorController@getOrder']);
			Route::delete('/sales_orders/{orderId}',['as' => 'co_distributors.salesOrders','uses' => 'CoDistributorController@deleteSalesOrder']);
			Route::put('/sales_orders/{orderId}/ship',['as' => 'co_distributors.salesOrders.ship','uses' => 'CoDistributorController@shipSalesOrder']);
			Route::post('/sales_orders/{orderId}/payments',['as' => 'co_distributors.salesOrders.payments','uses' => 'CoDistributorController@payment']);
			Route::get('/related_orders/{order_id}',['as' => 'co_distributors.related_orders.get' ,'uses' => 'CoDistributorController@getRelatedOrder']);

			Route::post('/',['as' => 'companies.distributor.co_distributors.create','uses' => 'CoDistributorController@create']);
			Route::delete('/sales_orders/{orderId}/sales_lines/{lineId}',['as' => 'co_distributors.salesOrders.salesLines.delete','uses' => 'CoDistributorController@deleteSalesLine']);
			Route::put('/sales_orders/{orderId}/sales_lines/{lineId}',['as' => 'co_distributors.salesOrders.salesLines.update','uses' => 'CoDistributorController@updateSalesLine']);
		});

		Route::group(['prefix' => "{id}/cl_rates"],function(){
			Route::get('/',['as' => 'companies.cl_rates','uses' => 'CompaniesController@getCLRates']);
			Route::post('/',['as' => 'companies.cl_rates','uses' => 'CompaniesController@createCLRate']);
			Route::delete('/{cl_id}',['as' => 'companies.cl_rates','uses' => 'CompaniesController@deleteCLRate']);
		});
		Route::group(['prefix' => "{id}/distributors/{distributor_id}/cl_rates"],function(){
			Route::get('/',['as' => 'companies.cl_rates','uses' => 'DistributorsController@getCLRates']);
			Route::post('/',['as' => 'companies.cl_rates','uses' => 'DistributorsController@createCLRate']);
			Route::delete('/{cl_id}',['as' => 'companies.cl_rates','uses' => 'DistributorsController@deleteCLRate']);
		});
		
		Route::group(['prefix' => "{id}/product_prices"],function(){
			Route::get('/',['as' => 'companies.product_prices','uses' => 'CompaniesController@getProductPrices']);
			Route::post('/',['as' => 'companies.product_prices','uses' => 'CompaniesController@addProductPrice']);
			Route::delete('/{price_id}',['as' => 'companies.product_prices','uses' => 'CompaniesController@editProductPrice']);
		});
		Route::group(['prefix' => "{id}/online_discounts"],function(){
			Route::get('/',['as' => 'companies.online_discounts.list', 'uses' => 'OnlineDiscountsController@getOnlineDisconts']);
			Route::delete('/{discountID}',['as' => 'companies.online_discounts.delete','uses' => 'OnlineDiscountsController@delete']);
			Route::post('/',['as' => 'companies.online_discounts.create','uses' => 'OnlineDiscountsController@create']);
			Route::put('/{discountID}',['as' => 'companies.online_discounts.update','uses' => 'OnlineDiscountsController@update']);
		});

		Route::group(['prefix' =>"{id}/transactions"],function(){
			Route::get('/',['as' => 'companies.transactions','uses' =>'TransactionsController@index']);
		});
	
		Route::get('{id}/invoices',['as' => 'customer.invoices','uses' => 'InvoiceController@index']);
		Route::get('{id}/invoices/{dealer_bo_id}',['as' => 'customer.invoices.get','uses' => 'InvoiceController@getInvoice']);
		Route::put('{id}/invoices/{distributor_bo_id}/mark_printed',['as' => 'customer.invoices.mark_printed','uses' => 'InvoiceController@mark_printed']);

		Route::get('{id}/current_depot_invoices',['as' => 'customer.depot_invoices','uses' => 'DepotOrdersController@listCurrentDepotInvoices']);//#795
		Route::get('{id}/inter_depot_invoices',['as' => 'companies.depot.orders.listDepotInvoices','uses' => 'DepotOrdersController@listInterDepotInvoices']);//#795
		
		Route::post('{id}/{customer_type}/{customer_id}/calculate_payment',['as' => 'customers.purchase.calculate_payments','uses' => 'PurchaseOrdersController@calculatePayment'])
			->where('customer_type','(distributors|dealers|super_stockists)');

		Route::put('{id}/distributors/{distributor_id}/payment_type',['as' => 'companies.distributors.payment_type','uses' => 'DistributorsController@setPaymentType']);
		Route::put('{id}/dealers/{dealer_id}/payment_type',['as' => 'companies.dealers.payment_type','uses' => 'DealersController@setPaymentType']);
		Route::put('{id}/distributors/{distributor_id}/set_permissions',['as' => 'companies.distributors.payment_type','uses' => 'DistributorsController@setPermissions']);//#807
		Route::get('{id}/thresholdtenants',['as' => 'companies.thresholdtenants','uses' => 'CompaniesController@thresholdTenants']);
		Route::get('{id}/thresholdtenants/{tenant}/stockpoints',['as' => 'companies.thresholdtenants.stockpoints','uses' => 'CompaniesController@thresholdTenantStockPoints']);
		Route::post('{id}/stock_threshold/{tenant}/import',['as' => 'companies.stock_threshold.import','uses' => 'StockThresholdController@importThreshold']);
		Route::get('{id}/superstockists/{superstockistId}/my_blocked_stock',['as' => 'superstockists.blocked.stock','uses' => 'SuperStockistsController@getMyBlockedStock']);
		Route::post('{id}/superstockists/{superstockistId}/unBlock_my_stock/{itemId}',['as' => 'superstockists.stock.unBlock','uses' => 'SuperStockistsController@unBlockMyStock']);
		Route::get('{id}/superstockists/{superstockist_id}/sales_orders',['as' => 'superstockists.sales.orders','uses' => 'SalesOrdersController@superStockistIndex']);
		Route::put('{id}/superstockists/{superstockist_id}/sales_orders/{orderId}/sales_lines/{line_id}',['as' => 'superstockists.sales.orders.line.update','uses' => 'SalesOrdersController@updateLineForSuperstockists']);
		/*Route::group(['prefix' => "{id}/distributors/{distributor_id}/payment_type"],function(){
			Route::put('/',['as' => 'companies.distributors.payment_type','uses' => 'DistributorsController@setPaymentType']);
		});*/
		Route::get('{id}/superstockists/{superstockist_id}/sales_orders_report',['as' => 'superstockists.sales.orders','uses' => 'SalesOrdersController@superStockistIndexReports']);
	});

	Route::post('distributors/{id}/inventory',['as' => 'distributors.inventory','uses' => 'DistributorsController@inventory']);
	Route::post('distributors/{id}/inventory/{inventory_id}/adjust',['as' => 'distributors.inventory.adjust','uses' => 'DistributorsController@inventoryAdjust']);
	Route::post('super_stockists/{id}/inventory',['as' => 'super_stockists.inventory','uses' => 'SuperStockistsController@inventory']);
	Route::post('super_stockists/{id}/inventory/{inventory_id}/adjust',['as' => 'super_stockists.inventory.adjust','uses' => 'SuperStockistsController@inventoryAdjust']);
	Route::post('super_stockists/{id}/inventory',['as' => 'super_stockists.inventory','uses' => 'SuperStockistsController@inventory']);
	Route::post('dealers/{id}/inventory',['as' => 'dealers.inventory','uses' => 'DealersController@inventory']);
	Route::get('distributors/{id}/inventory/{ProductID}',['as' => 'distributors.inventory','uses' => 'DistributorsController@inventoryDetails']);
	Route::get('dealers/{id}/inventory/{ProductID}',['as' => 'dealers.inventory','uses' => 'DealersController@inventoryDetails']);

	Route::post('distributors/{id}/return_items',['as' => 'distributors.return_items','uses' => 'SalesOrdersController@addReturns']);
	Route::put('distributors/{id}/return_items/{return_item_id}',['as' => 'distributors.return_items','uses' => 'SalesOrdersController@manageReturns']);
	Route::get('distributors/{id}/return_items',['as' => 'distributors.return_items','uses' => 'SalesOrdersController@getReturns']);
	Route::post('distributors/{id}/export_stock',['as' => 'distributors.stock.export','uses' => 'DistributorsController@exportStock']);
	Route::post('distributors/{id}/import_stock',['as' => 'distributors.stock.import','uses' => 'DistributorsController@importStock']);
	Route::post('super_stockists/{id}/import_stock',['as' => 'super_stockists.stock.import','uses' => 'SuperStockistsController@importStock']);
	Route::post('super_stockists/{id}/export_stock',['as' => 'super_stockists.stock.export','uses' => 'SuperStockistsController@exportStock']);

	Route::group(['prefix' => 'products'], function(){
		Route::get('/',['as' => 'products.index', 'uses' => 'ProductsController@index']);
		Route::get('/{id}', ['as' => 'products.get', 'uses' => 'ProductsController@get']);
		Route::put('/{id}',['as' => 'products.update', 'uses' => 'ProductsController@update']);
		Route::post('/',['as' => 'products.create', 'uses' => 'ProductsController@create']);
		Route::put('/{id}/toggle-status',['as' => 'products.toggle-status', 'uses' => 'ProductsController@toggleStatus']);
	});
	Route::get('getProducts',['as' => 'products.get', 'uses' => 'ProductsController@getProducts']);
	
	Route::get('purchase_orders/{purchase_order_id}',['as' => 'purchase.orders.get','uses' => 'PurchaseOrdersController@getOrder']);
	//Route::get('purchase_orders/{purchase_order_id}',['as' => 'purchase.orders.get','uses' => 'PurchaseOrdersController@getPaymentStatus']);
	Route::get('sales_orders/{purchase_order_id}',['as' => 'sales.orders.get','uses' => 'SalesOrdersController@getOrder']);
	Route::get('advance_sales_orders/{advance_order_id}',['as' => 'advance.sales.orders.get','uses' => 'AdvanceSalesOrdersController@getOrder']);
	Route::get('depot_orders/{purchase_order_id}',['as' => 'depot.orders.get','uses' => 'DepotOrdersController@getOrder']);//#777
	Route::get('depot_invoices/{invoice_id}',['as' => 'depot.invoices.get','uses' => 'DepotOrdersController@getInvoice']);//#795
	Route::get('depot_invoices/{dealer_bo_id}',['as' => 'customer.depot_invoices.get','uses' => 'DepotOrdersController@getInvoice']);//#795
	Route::put('depot_invoices/{distributor_bo_id}/mark_printed',['as' => 'customer.depot_invoices.mark_printed','uses' => 'DepotOrdersController@mark_printed']);//#795
	Route::get('advance_orders/{advance_order_id}',['as' => 'advance.orders.get','uses' => 'AdvanceOrdersController@getAdvanceOrder']);
	Route::get('intransit_orders/{advance_order_id}',['as' => 'advance.orders.get','uses' => 'AdvanceOrdersController@getInTransitOrder']);
	Route::group(['prefix' => 'discounts'], function(){
		Route::get('/',['as' => 'discounts.index', 'uses' => 'DiscountsController@index']);
		Route::delete('/{id}', ['as' => 'discounts.delete', 'uses' => 'DiscountsController@delete']);
		Route::put('/{id}',['as' => 'discounts.update', 'uses' => 'DiscountsController@update']);
		Route::post('/',['as' => 'discounts.create', 'uses' => 'DiscountsController@create']);
	});
	Route::group(['prefix' => 'customer_discounts'], function(){
		Route::get('/',['as' => 'discounts.index', 'uses' => 'CustomerDiscountController@index']);
		Route::delete('/{id}', ['as' => 'discount.delete', 'uses' => 'CustomerDiscountController@delete']);
		Route::put('/{id}',['as' => 'discount.update', 'uses' => 'CustomerDiscountController@update']);
		Route::post('/',['as' => 'discount.create', 'uses' => 'CustomerDiscountController@create']);
	});

	Route::group(['prefix' => 'ss_discounts'], function(){
		Route::get('/',['as' => 'discounts.index', 'uses' => 'SSDiscountController@index']);
		Route::delete('/{id}', ['as' => 'discount.delete', 'uses' => 'SSDiscountController@delete']);
		Route::put('/{id}',['as' => 'discount.update', 'uses' => 'SSDiscountController@update']);
		Route::post('/',['as' => 'discount.create', 'uses' => 'SSDiscountController@create']);
	});

	Route::group(['prefix' => 'co_distributor_discounts'], function(){
		Route::get('/',['as' => 'co_distributor_discounts.index', 'uses' => 'CoDistributorDiscountController@index']);
		Route::delete('/{id}', ['as' => 'co_distributor_discounts.delete', 'uses' => 'CoDistributorDiscountController@delete']);
		Route::post('/',['as' => 'co_distributor_discounts.create', 'uses' => 'CoDistributorDiscountController@create']);
		Route::put('/{id}',['as' => 'co_distributor_discounts.update', 'uses' => 'CoDistributorDiscountController@update']);
	});

	Route::get('dealers/{dealer_id}/users', ['as' => 'dealer.users.index', 'uses' => 'UsersController@dealerUsers']);
	Route::delete('dealers/{dealer_id}/users/{user_id}', ['as' => 'dealer.users.index', 'uses' => 'UsersController@deleteDealerUser']);
	Route::post('dealers/{dealer_id}/users', ['as' => 'dealer.users.create', 'uses' => 'UsersController@createDealerUser']);

	Route::post('temp_dealers', ['as' => 'post', 'uses' => 'DealersController@createTempDealers']);
	Route::get('temp_dealers', ['as' => 'get', 'uses' => 'DealersController@getTempDealers']);
	Route::get('temp_dealers/{tempDealerId}', ['as' => 'get', 'uses' => 'DealersController@findTempDealer']);
	Route::put('temp_dealers/{tempDealerId}', ['as' => 'approve', 'uses' => 'DealersController@approveTempDealer']);
	Route::delete('temp_dealers/{tempDealerId}', ['as' => 'reject', 'uses' => 'DealersController@rejectTempDealer']);
	Route::group(['prefix' => 'master'],function(){
		Route::get('techspecs',['as' => 'master.techspecs','uses' => 'MasterController@techspecs']);
		Route::get('imageclasses',['as' => 'master.imageclasses','uses' => 'MasterController@imageclasses']);
	});
	Route::group(['prefix' => 'customers_advance_items'], function(){
		Route::get('/',['as' => 'customers_advance_items.index', 'uses' => 'CompaniesController@customersAdvanceItems']);
	});

	Route::group(['prefix' => 'pg'],function(){
		Route::get('/{transaction_id}',['as' => 'pg.get','uses' => 'PaymentGatewayController@checkOrder']);
	});
});
Route::post('warranty',['uses' => 'WarrantyController@warranty']);

Route::get('migrate',['uses' => 'MigrationController@index']);
Route::get('migrate-active-products',['uses' => 'MigrationController@handleActivateProducts']);
Route::get('migrate-active-products-for-dealers',['uses' => 'MigrationController@handleActivateProductsForDealers']);
Route::get('generate-query',['uses' => 'MigrationController@generateQuery']);
Route::get('migrate-active-products-by-id',['uses' => 'MigrationController@handleActivateProductsById']);

Route::get('/whatsapp', function () {
	$dudBlanketOrder = BlanketOrder::find(request()->id);
	CustomHelper::sendWhatsappNotification($dudBlanketOrder);
	// new Notifications('', $dudBlanketOrder);
    //return app(WhatsAppService::class)->orderPlacedFromSuperStokistStock();
})->name('whatsapp');

Route::get('stock-reports', ['as' => 'stock.reports', 'uses' => 'ExportController@exportStocks']);