<?php
namespace Rosetta\Services\Invoice;

use Auth;
use Rosetta\Models\User;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;

class InvoiceService implements InvoiceServiceInterface
{
    public function dealerBlanketOrders(Array $params = [])
	{
		if(array_has($params,'page'))
		{
			$filters['stock_point_id'] =  $params['stock_point_id'];
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
	public function mark_printed($companyId, $orderId, Array $params, User $user)
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
//		$dealerBO->refresh();
//		return $dealerBO;
	}
	public function dealerBlanketOrder($dealerBOId)
	{
		$dealerBO = BlanketOrder::find($dealerBOId);
		return $dealerBO;
	}
}