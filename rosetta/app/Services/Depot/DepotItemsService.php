<?php
namespace Rosetta\Services\Depot;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Rosetta\Classes\Settings;
use Rosetta\Exceptions\Order\ViewOrderPermissionDeniedException;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\Setting;
use Rosetta\Models\User;
use Rosetta\Services\Depot\DepotItemsServiceInterface;
use Rosetta\Services\PurchaseOrders\PurchaseOrdersServiceInterface;
use Illuminate\Support\Facades\Log;


class DepotItemsService implements DepotItemsServiceInterface
{
	protected $httpClient;
	public function block(Array $params, $company)
	{	
		//$current_tenant = $company->getSettingsForType('current_tenant')->get('tenant_code');
		$tenants = $company->getSettingsForType('tenants')->get('tenant_codes');
		$tenant_codes = explode(',',$tenants);
		foreach($tenant_codes as $tenant)
		{
			$depotProduct = DB::connection($tenant)
					->table('products')
					->where('nav_id',$params['product_nav_id'])
					->first();
			if($depotProduct)
			{
				
				$depotStockPoint = DB::connection($tenant)
					->table('stock_points')
					->where('code',$params['stock_point_code'])
					->first();
				
				if($depotStockPoint)
				{
					$depotCompany = DB::connection($tenant)
					->table('companies')
					->where('name',$params['company_name'])
					->first();

					if($depotCompany)
					{
						DB::connection($tenant)
						->table('blocked_items')
						->insert([
							'product_id' => $depotProduct->id,
							'batch_no' => $params['batch_no'],
							'order_quantity' => $params['order_quantity'],
							'rate_type' => $params['rate_type'],
							'unit_price' => $params['unit_price'],  
							'discount' => $params['discount'],
							'company_id' => $depotCompany->id,
							'blockable_id' => $depotCompany->id,
							'blockable_type' => 'Rosetta\Models\Company',
							'stock_point_id' =>  $depotStockPoint->id,
							'comment' => $params['comment'],
							'UUID' => $params['UUId'],
							'expires_at' => $params['expires_at']->format('U'),
							'cool_down_start_time' => $params['cool_down_start_time']->format('U'),//#820
							'cool_down_end_time' => $params['cool_down_end_time']->format('U'),//#820
							'created_at' => Carbon::now()->format('U'),
							'updated_at' => Carbon::now()->format('U'),
						]);
					}
				}
			}
			DB::disconnect($tenant);
		}
	}
	public function blockAdvanceItem(Array $params, $company)
	{	
		//$current_tenant = $company->getSettingsForType('current_tenant')->get('tenant_code');
		$tenants = $company->getSettingsForType('tenants')->get('tenant_codes');
		$tenant_codes = explode(',',$tenants);
		foreach($tenant_codes as $tenant)
		{
			$depotProduct = DB::connection($tenant)
					->table('products')
					->where('nav_id',$params['product_nav_id'])
					->first();
			if($depotProduct)
			{
				
				$depotStockPoint = DB::connection($tenant)
					->table('stock_points')
					->where('code',$params['stock_point_code'])
					->first();
				
				if($depotStockPoint)
				{
					$depotCompany = DB::connection($tenant)
					->table('companies')
					->where('name',$params['company_name'])
					->first();

					if($depotCompany)
					{
						DB::connection($tenant)
						->table('blocked_advance_items')
						->insert([
							'product_id' => $depotProduct->id,
							'nav_intransit_po' => $params['nav_intransit_po'],
							'order_quantity' => $params['order_quantity'],
							'rate_type' => $params['rate_type'],
							'unit_price' => $params['unit_price'],  
							'discount' => $params['discount'],
							'company_id' => $depotCompany->id,
							'blockable_id' => $depotCompany->id,
							'blockable_type' => 'Rosetta\Models\Company',
							'stock_point_id' =>  $depotStockPoint->id,
							'comment' => $params['comment'],
							'UUID' => $params['UUId'],
							'expires_at' => $params['expires_at']->format('U'),
							'cool_down_start_time' => $params['cool_down_start_time']->format('U'),//#820
							'cool_down_end_time' => $params['cool_down_end_time']->format('U'),//#820
							'created_at' => Carbon::now()->format('U'),
							'updated_at' => Carbon::now()->format('U'),
						]);
					}
				}
			}
			DB::disconnect($tenant);
		}
	}
	public function unBlock($UUId, $company)
	{	
		$current_tenant = $company->getSettingsForType('current_tenant')->get('tenant_code');
		$tenants = $company->getSettingsForType('tenants')->get('tenant_codes');
		$tenant_codes = explode(',',$tenants);
		foreach($tenant_codes as $tenant)
		{
			$depotBlockedItem = DB::connection($tenant)
					->table('blocked_items')
					->where('UUID',$UUId)
					->first();

			if($depotBlockedItem)
			{
				DB::connection($tenant)
					->table('blocked_items')
					->where('UUID',$UUId)
					->delete();
			}
			DB::disconnect($tenant);
		}
	}
	public function unBlockAdvanceItem($UUId, $company)
	{	
		$current_tenant = $company->getSettingsForType('current_tenant')->get('tenant_code');
		$tenants = $company->getSettingsForType('tenants')->get('tenant_codes');
		$tenant_codes = explode(',',$tenants);
		foreach($tenant_codes as $tenant)
		{
			$depotBlockedItem = DB::connection($tenant)
					->table('blocked_advance_items')
					->where('UUID',$UUId)
					->first();

			if($depotBlockedItem)
			{
				DB::connection($tenant)
					->table('blocked_advance_items')
					->where('UUID',$UUId)
					->delete();
			}
			DB::disconnect($tenant);
		}
	}
	public function loginToDepot($tenant_url)
	{
		$this->httpClient = new Client([
                			'base_uri' => $tenant_url,
                            'headers' => ['Accept'        => 'application/json',
                                                'Content-Type'  => 'application/json'],
		                  ]);
		$api_user_email = config('brand.api_user_email');
		$api_user_password = config('brand.api_user_password');
		$options = [
        'json' => [
            "email" => $api_user_email,
            "password" => $api_user_password
           ]
       	];
        $response = $this->httpClient->post("api/login", $options);
		$body = $response->getBody();
        $json = json_decode($body);
        $token = $json->token;
        return $token;
	}
	public function getInterDepotOrderPaymentStatus($tenant, $companyId, $blanketOrderId)//#778
	{
		$setting = Setting::where('key','stock_point_tenants')->first();
		$tenant = collect(json_decode($setting->value))->where('name','=',$tenant)->first();

		$token = $this->loginToDepot($tenant->url);
		$url = 'api/companies/'.$companyId.'/depot_orders/'.$blanketOrderId.'/payments';
		$query =  [
            "tenant_name" => $tenant->name,
        ];
		$options = [
			'query' 		=> $query,
            'headers'		=> array('Authorization' => 'bearer ' . $token,        
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'),
       	];
        $response = $this->httpClient->get($url, $options);
        $body = $response->getBody();
        return json_decode($body);
	}
	public function listInterDepotBlanketOrders(Array $params, $companyId)
	{
		$setting = Setting::where('key','stock_point_tenants')->first();
		$tenant = collect(json_decode($setting->value))->where('name','=',$params['tenant'])->first();
		$company = Company::find($companyId);
		$token = $this->loginToDepot($tenant->url);

        $query =  [
            "stock_point_code" => $params['stock_point_code'],
            "company_name" => $company->name,
        ];
        if(array_has($params, 'status'))
        {
        	$query['status'] = $params['status'];
        }
        if(array_has($params, 'si'))
        {
        	$query['si'] = $params['si'];
        }
        if(array_has($params, 'so'))
        {
        	$query['so'] = $params['so'];
        }
        if(array_has($params, 'page'))
        {
        	$query['page'] = $params['page'];
        }
        if(array_has($params, 'per_page'))
        {
        	$query['per_page'] = $params['per_page'];
        }

        $options = [
        	'query' => $query,
            'headers' => array('Authorization' => 'bearer ' . $token,        
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'),
       ];
        $url =  'api/depot_orders';
        $response = $this->httpClient->get($url, $options);
        $body = $response->getBody();
        return json_decode($body, true);
	}
	public function listDepotBlanketOrders(Array $params, $companyId)
	{
		$company = Company::find($companyId);
		$customer = null;
		$stock_point = null;
		if(array_has($params, 'selectedCustomerType') && array_has($params, 'selectedCustomerId'))
		{
			if($params['selectedCustomerType'] == "Distributor")
	            $customer = Distributor::find($params['selectedCustomerId']);
	        elseif($params['selectedCustomerType'] == "Dealer")
	            $customer = Dealer::find($params['selectedCustomerId']);
		}
		if($customer)
		{
			if($customer->distributor_id == null)
				$queryMain = $customer->blanketOrders()->concrete();//->whereNull('distributor_id'); #422
			else
				$queryMain = $company->blanketOrders()->where('orderable_type','=',Dealer::class)
				->whereNull('distributor_id')->concrete();
		}
		else
		{
			if(array_has($params, 'selectedCustomerTypeCode'))
			{
				if($params['selectedCustomerTypeCode'] == "DISTRIBUTO")
		         	$queryMain = $company->blanketOrders()->where('orderable_type','=',Distributor::class)->concrete();
		        elseif($params['selectedCustomerTypeCode'] == "DEALER")
		           $queryMain = $company->blanketOrders()->where('orderable_type','=',Dealer::class)->concrete();
		        else
		        	$queryMain = $company->blanketOrders()->concrete();
			}
			else
				$queryMain = $company->blanketOrders()->concrete();
		}
		if(array_has($params,'stock_point_code'))
		{
			$stock_point = StockPoint::where('code','=',$params['stock_point_code'])->first();
		}
		if(array_has($params,'page'))
		{
			$blanketOrders = $queryMain->when($params, function ($query) use ($params,$stock_point){
				if(array_has($params, 'status'))
				{
					$query->where('order_status', '=', $params['status']);
				}
				if(array_has($params,'so'))
				{
					//$query->where('navision_order_number', '=', $params['so']);
					$query->whereHas('purchase_orders',function($q) use($params){
						$q->where('navision_order_number','=',$params['so']);
					});
				}
				if(array_has($params,'si'))//#704
				{
					$query->whereHas('purchase_orders',function($q) use($params){
						$q->where('nav_invoice_number','=',$params['si']);
					});
				}
				if(array_has($params,'stock_point_code'))//#762
				{
					$query->whereHas('purchase_orders',function($q) use($stock_point){
						$q->where('stock_point_id','=',$stock_point->id);
					});
				}
			})->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$blanketOrders = $queryMain->when($params, function ($query) use ($params,$stock_point){
				if(array_has($params, 'status'))
				{
					$query->where('order_status', '=', $params['status']);
				}
				if(array_has($params,'so'))
				{
					//$query->where('navision_order_number', '=', $params['so']);
					$query->whereHas('purchase_orders',function($q) use($params){
						$q->where('navision_order_number','=',$params['so']);
					});
				}
				if(array_has($params,'si'))//#704
				{
					$query->whereHas('purchase_orders',function($q) use($params){
						$q->where('nav_invoice_number','=',$params['si']);
					});
				}
				if(array_has($params,'stock_point_code'))//#762
				{
					$query->whereHas('purchase_orders',function($q) use($stock_point){
						$q->where('stock_point_id','=',$stock_point->id);
					});
				}
			})->orderBy('created_at','desc')->get();
		}
		return $blanketOrders;
	}
	public function findInterDepotOrder($orderId, $tenant)
	{
		$token = $this->loginToDepot($tenant->url);
        $options = [
            'headers' => array('Authorization' => 'bearer ' . $token,        
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'),
       ];
        $url =  'api/depot_orders/'.$orderId;
        $response = $this->httpClient->get($url, $options);
        $body = $response->getBody();
        return json_decode($body, true);	
	}
	public function findCurrentDepotOrder($orderId)
	{
		$purchaseOrderService = resolve('Rosetta\Services\PurchaseOrders\PurchaseOrdersServiceInterface');
		$alert = '';
		$blanketOrder = BlanketOrder::find($orderId);
		$orderCompanyId = $blanketOrder->orderable->company->id;
		$orderCustomerId = $blanketOrder->orderable->id;
		$purchaseOrders = $blanketOrder->purchase_orders;
		$orderDistributorId = $blanketOrder->distributor_id;
		foreach($purchaseOrders as $purchaseOrder)
		{
			if($purchaseOrder->distributor_id == null)
			{
				list($purchaseOrder,$alert) = $purchaseOrderService->update($purchaseOrder->orderable->company, $purchaseOrder->orderable, $purchaseOrder->id);
			}
			else
			{
				$relatedBO = BlanketOrder::where('related_po_id','=',$purchaseOrder->id)->first();
				if($relatedBO)
				{
					$relatedPO = $relatedBO->purchase_orders()->first();
					if($relatedPO->navision_order_number)
					{
						list($relatedPO,$alert) = $purchaseOrderService->update($relatedPO->orderable->company, $relatedPO->orderable, $relatedPO->id);
					}
				}
				$alert = '';
			}
		}
		$blanketOrder = BlanketOrder::find($orderId);
    	return [$blanketOrder,$alert];
    }
/*	public function unBlock(Array $params, $customer)
	{
		$customer->blockedItems()->whereIn('id', $params['blocked_Ids'])->delete();
	}*/
	public function listDepotInvoices(Array $params = [])
	{
		if(array_has($params,'page'))
		{
			if(array_has($params,'stock_point_code'))
			{
				$stock_point = StockPoint::where('code','=',$params['stock_point_code'])->first();
			}
			$filters['stock_point_id'] =  $stock_point->id;
			$filters['printed'] =  $params['printed'];
			$query = BlanketOrder::where('orderable_type','=',Distributor::class)
				->where('printed','=',$params['printed'])
				->whereNotNull('related_po_id')->concrete();

			$query->whereHas('purchase_orders',function($q) use($filters){
						$q->where('stock_point_id','=',$filters['stock_point_id']);
						$q->where('printed','=',$filters['printed']);
						$q->whereNotNull('nav_invoice_number');
					});
			return $query->paginate($params['per_page']);
		}
		else
		{
			$blanketOrders = BlanketOrder::whereNotNull('distributor_id')->concrete()
				->where('orderable_type','=',Dealer::class)
				->when($params, function ($query) use ($params){
					if(array_has($params, 'status'))
					{
						$query->where('order_status', '=', $params['status']);
					}
					if(array_has($params, 'date'))
					{
						$query->where('created_at', '>=', $params['date']);
					}
				})->orderBy('created_at','desc')->get();
		}
		return $blanketOrders;
	}
	public function listInterDepotInvoices(Array $params, $companyId)
	{
		$setting = Setting::where('key','stock_point_tenants')->first();
		$tenant = collect(json_decode($setting->value))->where('name','=',$params['tenant'])->first();
		$company = Company::find($companyId);
		$token = $this->loginToDepot($tenant->url);

        $query =  [
            "stock_point_code" => $params['stock_point_code'],
            "company_name" => $company->name,
        ];
        if(array_has($params, 'stock_point_code'))
        {
        	$query['stock_point_code'] = $params['stock_point_code'];
        }
        if(array_has($params, 'tenant'))
        {
        	$query['tenant'] = $params['tenant'];
        }
        if(array_has($params, 'printed'))
        {
        	$query['printed'] = $params['printed'];
        }
        if(array_has($params, 'page'))
        {
        	$query['page'] = $params['page'];
        }
        if(array_has($params, 'per_page'))
        {
        	$query['per_page'] = $params['per_page'];
        }
        $options = [
        	'query' => $query,
            'headers' => array('Authorization' => 'bearer ' . $token,        
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'),
       ];
        $url =  'api/depot_invoices';
        $response = $this->httpClient->get($url, $options);
        $body = $response->getBody();
        return json_decode($body, true);
	}
	public function findCurrentDepotInvoice($dealerBOId)
	{
		$dealerBO = BlanketOrder::find($dealerBOId);
		return $dealerBO;
	}
	public function findInterDepotInvoice($dealerBOId, $tenant)
	{
		$token = $this->loginToDepot($tenant->url);
        $options = [
            'headers' => array('Authorization' => 'bearer ' . $token,        
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'),
       	];
        $url =  'api/depot_invoices/'.$dealerBOId;
        $response = $this->httpClient->get($url, $options);
        $body = $response->getBody();
        return json_decode($body, true);	
	}
	public function current_depot_mark_printed($orderId, Array $params, User $user)
	{
		$distributorBO = BlanketOrder::find($orderId);
		$distributorPO = PurchaseOrder::where('navision_order_number','=',$params['distributor_nav_so_number'])->first();
		$dealerPO = PurchaseOrder::find($params['dealer_po_id']);
		$dealerBO = BlanketOrder::find($params['dealer_bo_id']);
		$distributorPO->printed = "1";
		$distributorPO->save();
		$distributorBO->printed = "1";
		$distributorBO->save();
		$dealerPO->printed = "1";
		$dealerPO->save();
		
		$printedPOs = $dealerBO->purchase_orders()->where('printed','=',1)->count();
		$totalPOs = $dealerBO->purchase_orders->count();
	
		if($printedPOs == $totalPOs)
		{
			$dealerBO->printed = "1";
			$dealerBO->save();
		}
	}
	public function inter_depot_mark_printed($orderId, Array $params, User $user, $tenant)
	{
		$token = $this->loginToDepot($tenant->url);
		$query =  [
            "dealer_bo_id" => $params['dealer_bo_id'],
            "dealer_po_id" => $params['dealer_po_id'],
            "distributor_bo_id" => $params['distributor_bo_id'],
            "distributor_nav_so_number" => $params['distributor_nav_so_number'],
        ];
        $options = [
        	'query' => $query,
            'headers' => array('Authorization' => 'bearer ' . $token,        
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'),
       	];
        $url = 'api/depot_invoices/'.$orderId.'/mark_printed';

        $response = $this->httpClient->put($url, $options);
        $body = $response->getBody();
        return json_decode($body, true);
	}
	public function CancelInterDepotOrder(Array $params, $companyID, $distributorId, $orderId, $tenant)
	{
		$token = $this->loginToDepot($tenant->url);
		$query =  [
            $request['cancelation_reason'] => $params['cancelation_reason']
        ];
        $options = [
        	'query' => $query,
            'headers' => array('Authorization' => 'bearer ' . $token,        
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'),
       	];
        $url = 'companies/'.$companyID.'/distributors/'.$distributorId.'/sales_orders/'.$orderId;
        $response = $this->httpClient->delete($url, $options);
        $body = $response->getBody();
        return json_decode($body, true);
	}
	public function CancelInterDepotPurchaseOrder(Array $params, $companyID, $customerType,$customerID, $orderId, $tenant)
	{
		$token = $this->loginToDepot($tenant->url);
		$query =  [
            'cancelation_reason' => $params['cancelation_reason']
        ];
        $options = [
        	'json' 			=> $query,
            'headers' 		=> array('Authorization' => 'bearer ' . $token,        
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'),
       	];
       	if($customerType == "distributors")
        {
            $url = 'api/companies/'.$companyID.'/distributors/'.$customerID.'/purchase_orders/'.$orderId;
        }
        elseif($customerType == "dealers")
        {
            $url = 'api/companies/'.$companyID.'/dealers/'.$customerID.'/purchase_orders/'.$orderId;
        }
      //  Log::info($url);
        $response = $this->httpClient->delete($url, $options);
        $body = $response->getBody();
        return json_decode($body, true);
	}
	public function DeleteInterDepotPurchaseLine(Array $params, $companyID, $customerType,$customerID, $orderId, $lineId, $tenant)
	{
		$token = $this->loginToDepot($tenant->url);
        $options = [
            'headers' 		=> array('Authorization' => 'bearer ' . $token,        
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'),
       	];
       	if($customerType == "distributors")
        {
            $url = 'api/companies/'.$companyID.'/distributors/'.$customerID.'/purchase_orders/'.$orderId.'/purchase_lines/'.$lineId;
        }
        elseif($customerType == "dealers")
        {
            $url = 'api/companies/'.$companyID.'/dealers/'.$customerID.'/purchase_orders/'.$orderId.'/purchase_lines/'.$lineId;
        }
     //   Log::info($url);
        $response = $this->httpClient->delete($url, $options);
        $body = $response->getBody();
        return json_decode($body, true);
	}
	public function DeleteInterDepotSalesLine($companyID, $distributorId, $orderId, $lineId, $tenant)
	{
		$token = $this->loginToDepot($tenant->url);
        $options = [
            'headers' 		=> array('Authorization' => 'bearer ' . $token,        
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'),
       	];
        $url = 'api/companies/'.$companyID.'/distributors/'.$distributorId.'/sales_orders/'.$orderId.'/sales_lines/'.$lineId;
      //  Log::info($url);
        $response = $this->httpClient->delete($url, $options);
        $body = $response->getBody();
        return json_decode($body, true);
	}
	public function UpdateInterDepotPurchaseLine(Array $params, $companyID, $customerType, $customerID, $orderId, $lineId, $tenant)
	{
		$token = $this->loginToDepot($tenant->url);
		$query =  [
			'discount'     => $params['discount'],
            'quantity' 	   => $params['quantity'],
            'nav_order_no' => $params['nav_order_no']
        ];
        $options = [
        	'json' 			=> $query,
            'headers' 		=> array('Authorization' => 'bearer ' . $token,        
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'),
       	];
       	if($customerType == "distributors")
        {
            $url = 'api/companies/'.$companyID.'/distributors/'.$customerID.'/purchase_orders/'.$orderId.'/purchase_lines/'.$lineId;
        }
        elseif($customerType == "dealers")
        {
            $url = 'api/companies/'.$companyID.'/dealers/'.$customerID.'/purchase_orders/'.$orderId.'/purchase_lines/'.$lineId;
        }
     //   Log::info($url);
        $response = $this->httpClient->put($url, $options);
        $body = $response->getBody();
        return json_decode($body, true);
	}
	public function print_dc($orderId, $tenant)
	{
		$token = $this->loginToDepot($tenant->url);
        $options = [
            'headers' 		=> array('Authorization' => 'bearer ' . $token,        
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'),
       	];
        $url = 'api/orders/'.$orderId.'/print_dc';
        Log::info($url);
        $response = $this->httpClient->get($url, $options);
        $body = $response->getBody();
        return json_decode($body, true);
	}
}