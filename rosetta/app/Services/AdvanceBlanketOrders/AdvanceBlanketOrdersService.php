<?php
namespace Rosetta\Services\AdvanceBlanketOrders;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rosetta\Models\Address;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Models\AdvanceOrder\AdvanceLine;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Notification;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Services\AdvanceBlanketOrders\AdvanceBlanketOrdersServiceInterface;
use Rosetta\Services\NAV\AvailableCreditServiceInterface;
use Rosetta\Services\NAV\SalesOrderServiceInterface;


class AdvanceBlanketOrdersService implements AdvanceBlanketOrdersServiceInterface
{
	protected $salesOrderService, $availableCreditService;
	public function __construct(
		SalesOrderServiceInterface $salesOrderService,  
		AvailableCreditServiceInterface $availableCreditService
	)
	{
		$this->salesOrderService            = $salesOrderService;
		$this->availableCreditService       = $availableCreditService;
	}
	public function all($company, $customer, Array $params = [])
	{
		if(array_has($params,'page'))
		{
			$advanceOrders = $customer->advanceBlanketOrders()->concrete()->when($params, function ($query) use ($params){
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
					$query->whereHas('advance_orders',function($q) use($params){
						$q->where('navision_order_number','=',$params['so']);
					});
				}
				if(array_has($params,'si'))//#873
				{
					$query->whereHas('advance_orders',function($q) use($params){
						$q->where('nav_invoice_number','=',$params['si']);
					});
				}
				if(array_has($params,'stock_point_id'))//#762
				{
					$query->whereHas('advance_orders',function($q) use($params){
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
			$advanceOrders = $customer->advanceBlanketOrders()->concrete()->when($params, function ($query) use ($params){
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
					$query->whereHas('advance_orders',function($q) use($params){
						$q->where('navision_order_number','=',$params['so']);
					});
				}
				if(array_has($params,'si'))//#873
				{
					$query->whereHas('advance_orders',function($q) use($params){
						$q->where('nav_invoice_number','=',$params['si']);
					});
				}
				if(array_has($params,'stock_point_id'))//#762
				{
					$query->whereHas('advance_orders',function($q) use($params){
						$q->where('stock_point_id','=',$params['stock_point_id']);
					});
				}
				if(array_has($params,'order_number'))
				{
					$query->where('order_number','like',"%{$params['order_number']}%");
				}
			})->orderBy('created_at','desc')->get();
		}
		return $advanceOrders;
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
		if($customer)
		{
			if($customer->distributor_id == null)
				$queryMain = $customer->advanceBlanketOrders()->concrete();//->whereNull('distributor_id'); #422
			else
				$queryMain = $company->advanceBlanketOrders()->where('orderable_type','=',Dealer::class)
				->whereNull('distributor_id')->concrete();
		}
		else
		{
			if(array_has($params, 'selectedCustomerTypeCode'))
			{
				if($params['selectedCustomerTypeCode'] == "DISTRIBUTO")
		         	$queryMain = $company->advanceBlanketOrders()->where('orderable_type','=',Distributor::class)->concrete();
		        elseif($params['selectedCustomerTypeCode'] == "DEALER")
		           $queryMain = $company->advanceBlanketOrders()->where('orderable_type','=',Dealer::class)->concrete();
		        elseif($params['selectedCustomerTypeCode'] == "SUPER_STOCKIST")
		           $queryMain = $company->advanceBlanketOrders()->where('orderable_type','=',SuperStockist::class)->concrete();
		        else
		        	$queryMain = $company->advanceBlanketOrders()->concrete();
			}
			else
				$queryMain = $company->advanceBlanketOrders()->concrete();
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
					$query->whereHas('advance_orders',function($q) use($params){
						$q->where('navision_order_number','=',$params['so']);
					});
				}
				if(array_has($params,'si'))//#704
				{
					$query->whereHas('advance_orders',function($q) use($params){
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
					$query->whereHas('advance_orders',function($q) use($params){
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
					$query->whereHas('advance_orders',function($q) use($params){
						$q->where('navision_order_number','=',$params['so']);
					});
				}
				if(array_has($params,'si'))//#704
				{
					$query->whereHas('advance_orders',function($q) use($params){
						$q->where('nav_invoice_number','=',$params['si']);
					});
				}
				if(array_has($params,'stock_point_id'))//#762
				{
					$query->whereHas('advance_orders',function($q) use($params){
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
	public function distributorBlanketOrders($distributorId, Array $params = [])
	{
		if(array_has($params,'page'))
		{
			$blanketOrders = AdvanceBlanketOrder::where('distributor_id', '=', $distributorId)->concrete()
				->where('orderable_type','=',Dealer::class)
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
			$blanketOrders = AdvanceBlanketOrder::where('distributor_id', '=', $distributorId)->concrete()
				->where('orderable_type','=',Dealer::class)
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