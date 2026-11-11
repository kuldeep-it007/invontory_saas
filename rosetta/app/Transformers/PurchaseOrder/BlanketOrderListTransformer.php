<?php
namespace Rosetta\Transformers\PurchaseOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Address;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Transformers\Dealers\CompactTransformer as DealerCompactTransformer;
use Rosetta\Transformers\Distributors\CompactTransformer as DistributorCompactTransformer;
use Rosetta\Transformers\SuperStockists\CompactTransformer as SuperStockistCompactTransformer;
use Rosetta\Transformers\AddressTransformer;
use Rosetta\Transformers\BillAddressTransformer;

class BlanketOrderListTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['customer','so_numbers','si_numbers','stock_points'];
	protected $defaultIncludes = ['customer','so_numbers','si_numbers','stock_points'];
	protected $loggedInUser;

	public function __construct(User $loggedInUser=null)
	{
		$this->loggedInUser = $loggedInUser;
	}
	public function transform(BlanketOrder $blanketOrder)
	{
		if(!empty($this->loggedInUser->filter_purchase_orders) && ($this->loggedInUser->filter_purchase_orders==true))
			{
				if(!empty($this->loggedInUser->superStockist)){
					$superStockistData = $this->loggedInUser->superStockist;
					$superStockistId = $superStockistData->id;
					$purchaseOrders = $blanketOrder->purchase_orders->filter(function($po) use ($superStockistId){
						return $po->super_stockist_id == $superStockistId;
					});
				}elseif(!empty($this->loggedInUser->distributor)){
					$distributorData = $this->loggedInUser->distributor;
					$distributorId = $distributorData->id;
					$purchaseOrders = $blanketOrder->purchase_orders->filter(function($po) use ($distributorId){
						return $po->distributor_id == $distributorId;
					});
				}
				
				$amount = $purchaseOrders->sum('Amount');
			}
			else
			{
				$amount = $blanketOrder->amount;
			}

		$data = [
			"id" => $blanketOrder->id,
			"number" => $blanketOrder->order_number,
			"amount" => $amount,
			"paid_amount" => $blanketOrder->paid_amount(),//#766
			"payment_status" => $blanketOrder->payment_status(),//#766
			//"remaining_amount" => $blanketOrder->remaining_amount,
			//"payment_status" => $purchaseOrder->payment_status,
			"created_at" => $blanketOrder->created_at->format('U'),
			"status" => $blanketOrder->status,
			"items" => $blanketOrder->purchase_lines()->count(),
			"customer" => $blanketOrder->orderable,
	//		"customer_type" => get_class($blanketOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER',
			"distributor_id" => $blanketOrder->distributor_id,
			"on_behalf" => $blanketOrder->on_behalf,
			"source" => $blanketOrder->source,
			"net_amount" => round($blanketOrder->net_amount(), 2),//#812 net amount is amount without tax
			"is_read" => $blanketOrder->is_read,
		];

		if ($blanketOrder->relatedOrder) {			
			$data['dealer_name'] = (!empty($blanketOrder->relatedOrder->orderable->name))?$blanketOrder->relatedOrder->orderable->name:'';
			$data['distributor_name'] = (!empty($blanketOrder->relatedOrder->orderable->name))?$blanketOrder->relatedOrder->orderable->name:'';			
			if((!empty($order->relatedOrder->orderable->type))){
				$data['dealer_type'] = (!empty($blanketOrder->relatedOrder->orderable->type))?$blanketOrder->relatedOrder->orderable->type:'';
			}elseif ((!empty($blanketOrder->relatedOrder->orderable)) && get_class($blanketOrder->relatedOrder->orderable) == Distributor::class) {
				$data['dealer_type'] = 'Distributor';
				$data['dealer_name'] = (!empty($blanketOrder->relatedOrder->blanket_order->relatedOrder->orderable->name))?$blanketOrder->relatedOrder->blanket_order->relatedOrder->orderable->name:$blanketOrder->relatedOrder->orderable->name;	
				$data['distributor_name'] = $blanketOrder->relatedOrder->orderable->name;
			} elseif ((!empty($blanketOrder->relatedOrder->orderable)) && get_class($blanketOrder->relatedOrder->orderable) == Dealer::class) {
				$data['dealer_type'] = 'Dealer';
			} elseif ((!empty($blanketOrder->relatedOrder->orderable)) && get_class($blanketOrder->relatedOrder->orderable) == SuperStockist::class) {
				$data['dealer_type'] = 'Superstockist';
			}
		} else {
			$data['dealer_name'] = $blanketOrder->orderable->name;
			$data['distributor_name'] = $blanketOrder->orderable->name;	
			if ((!empty($blanketOrder->orderable)) && get_class($blanketOrder->orderable) == Distributor::class) {
				$data['dealer_type'] = 'Distributor';
			}elseif ((!empty($blanketOrder->orderable)) && get_class($blanketOrder->orderable) == Dealer::class) {
				$data['dealer_type'] = 'Dealer';
			}elseif ((!empty($blanketOrder->orderable)) && get_class($blanketOrder->orderable) == SuperStockist::class) {
				$data['dealer_type'] = 'Superstockist';
			}else {
				$data['dealer_type'] = $blanketOrder->orderable->type;
			}
		}
		$current_tenant_name = $blanketOrder->company->getSettingsForType('current_tenant')->get('tenant_name');
		$data['tenant_name'] = $current_tenant_name;//#778
		if((!empty($blanketOrder->orderable)) && get_class($blanketOrder->orderable) == Distributor::class)
		{
			$data['customer_type'] = 'DISTRIBUTO';
		}
		elseif((!empty($blanketOrder->orderable)) && get_class($blanketOrder->orderable) == Dealer::class)
		{
			$data['customer_type'] = 'DEALER';
		}
		elseif((!empty($blanketOrder->orderable)) && get_class($blanketOrder->orderable) == SuperStockist::class)
		{
			$data['customer_type'] = 'SUPER_STOCKIST';
		}
		return $data;
	}
	public function includeCustomer(BlanketOrder $blanketOrder)
	{
	/*	$customer_type = get_class($blanketOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
		if($customer_type == 'DISTRIBUTO')
			return $this->item($blanketOrder->orderable, new DistributorCompactTransformer);
		if($customer_type == 'DEALER')
			return $this->item($blanketOrder->orderable, new DealerCompactTransformer);*/
		if((!empty($blanketOrder->orderable)) && get_class($blanketOrder->orderable) == Distributor::class)
			return $this->item($blanketOrder->orderable, new DistributorCompactTransformer);
		elseif((!empty($blanketOrder->orderable)) && get_class($blanketOrder->orderable) == Dealer::class)
			return $this->item($blanketOrder->orderable, new DealerCompactTransformer);
		elseif((!empty($blanketOrder->orderable)) && get_class($blanketOrder->orderable) == SuperStockist::class)
			return $this->item($blanketOrder->orderable, new SuperStockistCompactTransformer);
	}
	public function includeSONumbers(BlanketOrder $order)
	{
		return $this->item($order,function($o){
			return $o->purchase_orders->pluck('navision_order_number')->toArray();
		});
	}
	public function includeSINumbers(BlanketOrder $order)//#704
	{
		return $this->item($order,function($o){
			return $o->purchase_orders->pluck('nav_invoice_number')->toArray();
		});
	}
	public function includeStockPoints(BlanketOrder $order) //#762
	{
		return $this->item($order, function ($o) {

			if(!empty($this->loggedInUser->filter_purchase_orders) && ($this->loggedInUser->filter_purchase_orders==true))
			{
				if(!empty($this->loggedInUser->superStockist)){
					$superStockistData = $this->loggedInUser->superStockist;
					$superStockistId = $superStockistData->id;
					$purchaseOrders = $o->purchase_orders->filter(function($po) use ($superStockistId){
						return $po->super_stockist_id == $superStockistId;
					});
				}elseif(!empty($this->loggedInUser->distributor)){
					$distributorData = $this->loggedInUser->distributor;
					$distributorId = $distributorData->id;
					$purchaseOrders = $o->purchase_orders->filter(function($po) use ($distributorId){
						return $po->distributor_id == $distributorId;
					});
				}			
			}
			else
			{
				$purchaseOrders = $o->purchase_orders;
			}

			$stockpoints = [];
			foreach ($purchaseOrders as $po) {
				if ($po->stock_point) {
					$stockpoints[] = $po->stock_point->name;
				} else if ($po->ss_stock_point_id) {
					$ssStockPoints = SuperStockist::find($po->ss_stock_point_id)->load('stock_points');
					$stockpoints[] = (!empty($ssStockPoints->display_name))?$ssStockPoints->display_name:$ssStockPoints->name;
				} elseif ($po->dist_stock_point_id > 0) {
					$distributorStockPoints = Distributor::find($po->dist_stock_point_id);
					if(!empty($distributorStockPoints)){
						$distributorStockPoints = $distributorStockPoints->load('stock_points');
						$stockpoints[] = (!empty($distributorStockPoints->display_name))?$distributorStockPoints->display_name:$distributorStockPoints->name;
					}
					
				} else if ($po->super_stockist_id) {
					$ssStockPoints = SuperStockist::find($po->super_stockist_id)->load('stock_points');
					$stockpoints[] = (!empty($ssStockPoints->display_name))?$ssStockPoints->display_name:$ssStockPoints->name;
				} elseif ($po->distributor_id > 0) {
					$distributorStockPoints = Distributor::find($po->distributor_id)->load('stock_points');
					$stockpoints[] = (!empty($distributorStockPoints->display_name))?$distributorStockPoints->display_name:$distributorStockPoints->name;
				}
			}
			return collect($stockpoints)->unique()->values()->all();
		});
	}

	public function includeShippingAddress(BlanketOrder $order)
	{
		$purchaseOrder = (!empty($order->purchase_orders()->first()))?$order->purchase_orders()->first()->addresses()->first():null;
		if ($purchaseOrder instanceof Address) {
			return $this->item($purchaseOrder, new AddressTransformer);
		}	
		return null; // or handle the error appropriately
	}
	
	public function includeBillingAddress(BlanketOrder $order)
	{
		return $this->item($order->orderable, new BillAddressTransformer);
	}	
}