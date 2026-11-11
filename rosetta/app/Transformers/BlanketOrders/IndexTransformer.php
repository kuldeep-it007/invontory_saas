<?php

namespace Rosetta\Transformers\BlanketOrders;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Address;
use Rosetta\Models\BlanketOrder;
use Rosetta\Transformers\BlanketOrders\PurchaseOrderSummaryTransformer;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\Distributor;
use Rosetta\Models\Dealer;
use Rosetta\Transformers\AddressTransformer;
use Rosetta\Transformers\BillAddressTransformer;

class IndexTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['pos', 'so_numbers', 'stock_points', 'si_numbers'];
	protected $defaultIncludes = ['pos', 'so_numbers', 'stock_points', 'si_numbers'];
	public function transform(BlanketOrder $order)
	{
		$data = [
			"id"         => $order->id,
			"number"     => $order->order_number,
			"amount"     => $order->amount,
			"created_at" => $order->created_at->format('U'),
			"status"     => $order->status,
			"net_amount" => round($order->net_amount(), 2), //#812 net amount is amount without tax
			"is_read" => $order->is_read,
		];

		if (get_class($order->orderable) == Distributor::class) {
			$data['customer_type'] = 'DISTRIBUTO';			
		} elseif (get_class($order->orderable) == Dealer::class) {
			$data['customer_type'] = 'DEALER';
		} elseif (get_class($order->orderable) == SuperStockist::class) {
			$data['customer_type'] = 'SUPER_STOCKIST';
		}

		if ($order->relatedOrder) {			
			if((!empty($order->relatedOrder->orderable->type))){
				$data['distributor_name'] = $order->relatedOrder->orderable->name;
				$data['dealer_name'] = $order->relatedOrder->orderable->name;
				$data['dealer_type'] = $order->relatedOrder->orderable->type;
			}elseif (get_class($order->relatedOrder->orderable) == Distributor::class) {
				$data['distributor_name'] = $order->relatedOrder->orderable->name;				
				$data['dealer_name'] = (!empty($order->relatedOrder->blanket_order->relatedOrder->orderable->name))?$order->relatedOrder->blanket_order->relatedOrder->orderable->name:$order->relatedOrder->orderable->name;
				$data['dealer_type'] = 'Distributor';
			} elseif (get_class($order->relatedOrder->orderable) == Dealer::class) {
				$data['distributor_name'] = $order->relatedOrder->orderable->name;
				$data['dealer_name'] = $order->relatedOrder->orderable->name;
				$data['dealer_type'] = 'Dealer';
			} elseif (get_class($order->relatedOrder->orderable) == SuperStockist::class) {
				//$data['dealer_name'] = $order->relatedOrder->orderable->name;
				//$data['dealer_type'] = 'Superstockist';
			}
		} else {
			$data['dealer_name'] = null;
			$data['dealer_type'] = null;
		}
		return $data;
	}
	public function includePos(BlanketOrder $order)
	{
		return $this->collection($order->purchase_orders, new PurchaseOrderSummaryTransformer);
	}
	public function includeSONumbers(BlanketOrder $order)
	{
		return $this->item($order, function ($o) {
			return $o->purchase_orders->pluck('navision_order_number')->toArray();
		});
	}
	public function includeSINumbers(BlanketOrder $order) //#765
	{
		return $this->item($order, function ($o) {
			return $o->purchase_orders->pluck('nav_invoice_number')->toArray();
		});
	}
	public function includeStockPoints(BlanketOrder $order) //#763
	{
		return $this->item($order, function ($o) {
			$stockpoints = [];
			foreach ($o->purchase_orders as $po) {

				if ($po->stock_point) {
					$stockpoints[] = $po->stock_point->name;
				}else if ($po->ss_stock_point_id) {
					$ssStockPoints = SuperStockist::find($po->ss_stock_point_id)->load('stock_points');
					$stockpoints[] = (!empty($ssStockPoints->display_name))?$ssStockPoints->display_name:$ssStockPoints->name;
				} elseif ($po->dist_stock_point_id > 0) {
					$distributorStockPoints = Distributor::find($po->dist_stock_point_id)->load('stock_points');
					$stockpoints[] = (!empty($distributorStockPoints->display_name))?$distributorStockPoints->display_name:$distributorStockPoints->name;
				}else if ($po->super_stockist_id) {
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
