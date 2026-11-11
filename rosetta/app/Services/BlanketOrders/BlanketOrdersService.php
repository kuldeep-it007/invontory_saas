<?php
namespace Rosetta\Services\BlanketOrders;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Services\BlanketOrders\BlanketOrdersServiceInterface;

class BlanketOrdersService implements BlanketOrdersServiceInterface
{
	public function all($company, $customer, Array $params = [])
	{
		if(array_has($params,'page'))
		{
			$purchaseOrders = $customer->blanketOrders()->concrete()->when($params, function ($query) use ($params){
				if(array_has($params, 'status'))
				{
					$query->where('order_status', '=', $params['status']);
				}
				if(array_has($params, 'start_date'))
				{
					$query->where('created_at', '>=', $params['start_date']);
				}
				if(array_has($params, 'end_date'))
				{
					$query->where('created_at', '<=', $params['end_date']);
				}
				if(array_has($params,'so'))
				{
					$query->whereHas('purchase_orders',function($q) use($params){
						$q->where('navision_order_number','=',$params['so']);
					});
				}
				if(array_has($params,'stock_point_id'))//#762
				{
					$query->whereHas('purchase_orders',function($q) use($params){
						$q->where('stock_point_id','=',$params['stock_point_id']);
					});
				}
				if(array_has($params,'order_number'))
				{
					$query->where('order_number','like',"%{$params['order_number']}%");
				}
			})->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$purchaseOrders = $customer->blanketOrders()->concrete()->when($params, function ($query) use ($params){
				if(array_has($params, 'status'))
				{
					$query->where('order_status', '=', $params['status']);
				}
				if(array_has($params, 'start_date'))
				{
					$query->where('created_at', '>=', $params['start_date']);
				}
				if(array_has($params, 'end_date'))
				{
					$query->where('created_at', '<=', $params['end_date']);
				}
				if(array_has($params,'so'))
				{
					$query->whereHas('purchase_orders',function($q) use($params){
						$q->where('navision_order_number','=',$params['so']);
					});
				}
				if(array_has($params,'stock_point_id'))//#762
				{
					$query->whereHas('purchase_orders',function($q) use($params){
						$q->where('stock_point_id','=',$params['stock_point_id']);
					});
				}
				if(array_has($params,'order_number'))
				{
					$query->where('order_number','like',"%{$params['order_number']}%");
				}
			})->orderBy('created_at','desc')->get();
		}
		return $purchaseOrders;
	}
	public function distributorBlanketOrders($distributorId, Array $params = [])
	{
		$showAll = (array_has($params,'show_all') && $params['show_all']==true)?true:false;
		if(array_has($params,'page'))
		{
			#Track-0001 Start
			//$blanketOrders = BlanketOrder::where('distributor_id', '=', $distributorId)->concrete();
			$blanketOrders = BlanketOrder::whereHas('purchase_orders', function ($q) use ($distributorId) { $q->where('distributor_id', '=', $distributorId); })
			->concrete();
			#Track-0001 End
			if ($showAll == false) {
				$blanketOrders = $blanketOrders->where('orderable_type','=',Dealer::class);
			}
			$blanketOrders = $blanketOrders->when($params, function ($query) use ($params){
					if(array_has($params, 'status'))
					{
						$query->where('order_status', '=', $params['status']);
					}
					if(array_has($params, 'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
					}
					if(array_has($params, 'end_date'))
					{
						$query->where('created_at', '<=', $params['end_date']);
					}
					if(array_has($params,'number'))
					{
						$query->where('order_number','like',"%{$params['number']}%");
					}
					if(array_has($params, 'customer_id'))
					{
						$query->where('orderable_type', '=', Dealer::class)->where('orderable_id', '=', $params['customer_id']);
					}
					if(array_has($params,'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
						$query->where('created_at', '<=', $params['end_date']);
					}
				})->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$blanketOrders = BlanketOrder::where('distributor_id', '=', $distributorId)->concrete();
			if ($showAll == false) {
				$blanketOrders = $blanketOrders->where('orderable_type','=',Dealer::class);
			}
			$blanketOrders = $blanketOrders->when($params, function ($query) use ($params){
					if(array_has($params, 'status'))
					{
						$query->where('order_status', '=', $params['status']);
					}
					if(array_has($params, 'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
					}
					if(array_has($params, 'end_date'))
					{
						$query->where('created_at', '<=', $params['end_date']);
					}
					if(array_has($params, 'customer_id'))
					{
						$query->where('orderable_type', '=', Dealer::class)->where('orderable_id', '=', $params['customer_id']);
					}
					if(array_has($params,'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
						$query->where('created_at', '<=', $params['end_date']);
					}
				})->orderBy('created_at','desc')->get();
		}
		return $blanketOrders;
	}
	public function listCompanyBlanketOrders($companyId, Array $params = [])
	{
		$company = Company::find($companyId);
		$customer = null;
		if(array_has($params, 'selectedCustomerType') && array_has($params, 'selectedCustomerId'))
		{
			if($params['selectedCustomerType'] == "Distributor")
	            $customer = Distributor::find($params['selectedCustomerId']);
	        elseif($params['selectedCustomerType'] == "Dealer")
	            $customer = Dealer::find($params['selectedCustomerId']);
	        elseif($params['selectedCustomerType'] == "superStockist")
	            $customer = SuperStockist::find($params['selectedCustomerId']);
		}
	//	var_dump($customer);
	//	exit(0);
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
		        elseif($params['selectedCustomerTypeCode'] == "SUPER_STOCKIST")
		           $queryMain = $company->blanketOrders()->where('orderable_type','=',SuperStockist::class)->concrete();
		        else
		        	$queryMain = $company->blanketOrders()->concrete();
			}
			else
				$queryMain = $company->blanketOrders()->concrete();
		}
		if(array_has($params,'page'))
		{
			$blanketOrders = $queryMain->when($params, function ($query) use ($params){
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
				if(array_has($params,'dealer_id'))//#704
				{
					$query->whereHas('relatedOrder',function($q) use($params){
						$q->where('orderable_type','=',Dealer::class)->where('orderable_id','=',$params['dealer_id']);
					});
				}//#839
				if(array_has($params,'stock_point_id'))//#762
				{
					$query->whereHas('purchase_orders',function($q) use($params){
						$q->where('stock_point_id','=',$params['stock_point_id']);
					});
				}
				if(array_has($params,'start_date'))
				{
					$query->where('created_at', '>=', $params['start_date']);
					$query->where('created_at', '<=', $params['end_date']);
				}
			})->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$blanketOrders = $queryMain->when($params, function ($query) use ($params){
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
				if(array_has($params,'stock_point_id'))//#762
				{
					$query->whereHas('purchase_orders',function($q) use($params){
						$q->where('stock_point_id','=',$params['stock_point_id']);
					});
				}
				if(array_has($params,'start_date'))
				{
					$query->where('created_at', '>=', $params['start_date']);
					$query->where('created_at', '<=', $params['end_date']);
				}
			})->orderBy('created_at','desc')->get();
		}
		return $blanketOrders;
	}

	public function listCoDistributorBlanketOrders($companyId, Array $params = [])
	{
		$company = Company::find($companyId);
		if(array_has($params,'page'))
		{
			$blanketOrders = BlanketOrder::where('orderable_type','=',Distributor::class)->concrete()
				->whereNotNull('distributor_id')
				->when($params, function ($query) use ($params){
					if(array_has($params, 'selectedCustomerId'))
					{
						$query->where('distributor_id', '=', $params['selectedCustomerId']);
					}
					if(array_has($params, 'status'))
					{
						$query->where('order_status', '=', $params['status']);
					}
					if(array_has($params,'order_number'))
					{
						$query->where('order_number','like',"%{$params['order_number']}%");
					}
					if(array_has($params, 'customer_id'))
					{
						$query->where('orderable_id', '=', $params['customer_id']);
					}
					if(array_has($params,'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
						$query->where('created_at', '<=', $params['end_date']);
					}
				})->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$blanketOrders = BlanketOrder::where('orderable_type','=',Distributor::class)->concrete()
				->whereNotNull('distributor_id')
				->when($params, function ($query) use ($params){
					if(array_has($params, 'selectedCustomerId'))
					{
						$query->where('distributor_id', '=', $params['selectedCustomerId']);
					}
					if(array_has($params, 'status'))
					{
						$query->where('order_status', '=', $params['status']);
					}
					if(array_has($params,'order_number'))
					{
						$query->where('order_number','like',"%{$params['order_number']}%");
					}
					if(array_has($params, 'customer_id'))
					{
						$query->where('orderable_id', '=', $params['customer_id']);
					}
					if(array_has($params,'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
						$query->where('created_at', '<=', $params['end_date']);
					}
				})->orderBy('created_at','desc')->get();
		}
		return $blanketOrders;
	}
	public function coDistributorBlanketOrders($distributor, Array $params = [],$type = 1)
	{
		if($type == 1)
		{
			//orders placed by $distributor
			if(array_has($params,'page'))
			{
				$blanketOrders = $distributor->coDistributorBlanketOrders()->concrete()->when($params, function ($query) use ($params){
					if(array_has($params, 'status'))
					{
						$query->where('order_status', '=', $params['status']);
					}
					if(array_has($params, 'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
					}
					if(array_has($params, 'end_date'))
					{
						$query->where('created_at', '<=', $params['end_date']);
					}
					if(array_has($params,'number'))
					{
						$query->where('order_number','like',"%{$params['number']}%");
					}
					if(array_has($params, 'customer_id'))
					{
						$query->where('distributor_id', '=', $params['customer_id']);
					}
					if(array_has($params,'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
						$query->where('created_at', '<=', $params['end_date']);
					}
				})->orderBy('created_at','desc')->paginate($params['per_page']);
			}
			else
			{

				$blanketOrders = $distributor->coDistributorBlanketOrders()->concrete()->when($params, function ($query) use ($params){
					if(array_has($params, 'status'))
					{
						$query->where('order_status', '=', $params['status']);
					}
					if(array_has($params, 'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
					}
					if(array_has($params, 'end_date'))
					{
						$query->where('created_at', '<=', $params['end_date']);
					}
					if(array_has($params, 'customer_id'))
					{
						$query->where('distributor_id', '=', $params['customer_id']);
					}
					if(array_has($params,'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
						$query->where('created_at', '<=', $params['end_date']);
					}
				})->orderBy('created_at','desc')->get();
			}
		}
		else
		{
			//orders placed by for $distributor
			if(array_has($params,'page'))
			{
				$blanketOrders = BlanketOrder::where('distributor_id', '=', $distributor->id)->concrete()
					->where('orderable_type','=',Distributor::class)
					->when($params, function ($query) use ($params){
						if(array_has($params, 'status'))
						{
							$query->where('order_status', '=', $params['status']);
						}
						if(array_has($params, 'start_date'))
						{
							$query->where('created_at', '>=', $params['start_date']);
						}
						if(array_has($params, 'end_date'))
						{
							$query->where('created_at', '<=', $params['end_date']);
						}
						if(array_has($params,'number'))
						{
							$query->where('order_number','like',"%{$params['number']}%");
						}
						if(array_has($params, 'customer_id'))
						{
							$query->where('orderable_id', '=', $params['customer_id']);
						}
						if(array_has($params,'start_date'))
						{
							$query->where('created_at', '>=', $params['start_date']);
							$query->where('created_at', '<=', $params['end_date']);
						}
					})->orderBy('created_at','desc')->paginate($params['per_page']);
			}
			else
			{
				$blanketOrders = BlanketOrder::where('distributor_id', '=', $distributor->id)->concrete()
					->where('orderable_type','=',Distributor::class)
					->when($params, function ($query) use ($params){
						if(array_has($params, 'status'))
						{
							$query->where('order_status', '=', $params['status']);
						}
						if(array_has($params, 'start_date'))
						{
							$query->where('created_at', '>=', $params['start_date']);
						}
						if(array_has($params, 'end_date'))
						{
							$query->where('created_at', '<=', $params['end_date']);
						}
						if(array_has($params, 'customer_id'))
						{
							$query->where('orderable_id', '=', $params['customer_id']);
						}
						if(array_has($params,'start_date'))
						{
							$query->where('created_at', '>=', $params['start_date']);
							$query->where('created_at', '<=', $params['end_date']);
						}
					})->orderBy('created_at','desc')->get();
			}
		}
		
		return $blanketOrders;
	}
	public function getOrder(User $user, Distributor $distributor,int $orderID, int $type=1,Array $params = [])
	{
		if($type == 1)
		{
			$bo = $distributor->coDistributorBlanketOrders()->find($orderID);
			if(!$bo)
				throw new ModelNotFoundException;
		}
		else
		{
			$bo = $distributor->parentCoDistributorBlanketOrders()->find($orderID);
			if(!$bo)
				throw new ModelNotFoundException;
		}

		return $bo;
	}
	public function getRelatedOrder(Distributor $distributor,int $orderID)
	{
		$bo = BlanketOrder::find($orderID);
	//	$bo = $distributor->parentCoDistributorBlanketOrders()->find($orderID);
		if(!$bo)
			throw new ModelNotFoundException;

		return $bo;
	}

	public function superStockistBlanketOrders($superStockistId, Array $params = [])
	{
		$showAll = (array_has($params,'show_all') && $params['show_all']==true)?true:false;

		if(array_has($params,'page'))
		{
			//$blanketOrders = BlanketOrder::where('super_stockist_id', '=', $superStockistId)->concrete();

			$blanketOrders = BlanketOrder::whereHas('purchase_orders', function ($q) use ($superStockistId) { $q->where('super_stockist_id', '=', $superStockistId); })->concrete();

			// ->whereHas('purchase_orders', function($query){
			// 	$query->whereNull('stock_point_id');
			// })
			if ($showAll == false) {
				$blanketOrders = $blanketOrders->when($params, function ($query) use ($params) {
					if (array_has($params, 'is_ss') && $params['is_ss'] == true) {
						$query->where('orderable_type', '=', SuperStockist::class);
					} else if (array_has($params, 'is_dist') && $params['is_dist'] == true) {
						$query->where('orderable_type', '=', Distributor::class);
					} else if (array_has($params, 'is_dealer') && $params['is_dealer'] == true) {
						$query->where('orderable_type', '=', Dealer::class);
					} else {
						$query->where('orderable_type', '=', Distributor::class);
					}
				});
			}
				$blanketOrders = $blanketOrders->	when($params, function ($query) use ($params){
					if(array_has($params, 'status'))
					{
						$query->where('order_status', '=', $params['status']);
					}
					if(array_has($params, 'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
					}
					if(array_has($params, 'end_date'))
					{
						$query->where('created_at', '<=', $params['end_date']);
					}
					if(array_has($params,'number'))
					{
						$query->where('order_number','like',"%{$params['number']}%");
					}
					if(array_has($params, 'customer_id'))
					{
						$query->where('orderable_type', '=', Distributor::class)->where('orderable_id', '=', $params['customer_id']);
					}
					if(array_has($params,'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
						$query->where('created_at', '<=', $params['end_date']);
					}
				})->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$blanketOrders = BlanketOrder::where('super_stockist_id', '=', $superStockistId)->concrete()
				//->where('orderable_type','=',Distributor::class)
				//->orWhere('orderable_type','=',SuperStockist::class)
				->whereHas(['purchase_orders'=>function($query){
					$query->whereNull('stock_point_id');
				}]);
				if ($showAll == false) {
					$blanketOrders = $blanketOrders->when($params, function ($query) use ($params) {
						if (array_has($params, 'is_ss') && $params['is_ss'] == true) {
							$query->where('orderable_type', '=', SuperStockist::class);
						} else if (array_has($params, 'is_dist') && $params['is_dist'] == true) {
							$query->where('orderable_type', '=', Distributor::class);
						} else if (array_has($params, 'is_dealer') && $params['is_dealer'] == true) {
							$query->where('orderable_type', '=', Dealer::class);
						} else {
							$query->where('orderable_type', '=', Distributor::class);
						}
					});
				}
				$blanketOrders = $blanketOrders->when($params, function ($query) use ($params){
					if(array_has($params, 'status'))
					{
						$query->where('order_status', '=', $params['status']);
					}
					if(array_has($params, 'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
					}
					if(array_has($params, 'end_date'))
					{
						$query->where('created_at', '<=', $params['end_date']);
					}
					if(array_has($params, 'customer_id'))
					{
						$query->where('orderable_type', '=', Dealer::class)->where('orderable_id', '=', $params['customer_id']);
					}
					if(array_has($params,'start_date'))
					{
						$query->where('created_at', '>=', $params['start_date']);
						$query->where('created_at', '<=', $params['end_date']);
					}
				})->orderBy('created_at','desc')->get();
		}
		return $blanketOrders;
	}
}