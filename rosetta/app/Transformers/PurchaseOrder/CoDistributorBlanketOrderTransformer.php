<?php
namespace Rosetta\Transformers\PurchaseOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Address;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Distributor;
use Rosetta\Models\User;
use Rosetta\Transformers\Distributors\CompactTransformer;
use Rosetta\Transformers\PurchaseOrder\BlanketOrderListTransformer;
use Rosetta\Transformers\PurchaseOrder\LineTransformer;
use Rosetta\Transformers\AddressTransformer;
use Rosetta\Transformers\BillAddressTransformer;
class CoDistributorBlanketOrderTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['co_distributor','distributor','purchase_lines','related_order'];
	//protected $defaultIncludes = ['shipping_address', 'billing_address'];
	public function transform(BlanketOrder $blanketOrder)
	{
		$data = [
			"id"         => $blanketOrder->id,
			"number"     => $blanketOrder->order_number,
			"amount"     => $blanketOrder->amount,
			"created_at" => $blanketOrder->created_at->format('U'),
			"status"     => $blanketOrder->status,
			"remaining_amount" => round($blanketOrder->amount - $blanketOrder->paid_amount(), 2),
			"paid_amount" => $blanketOrder->paid_amount(),
			"shipped" => (bool)$blanketOrder->shipped,
			"remarks"	 => $blanketOrder->remarks(),
		];
		if($blanketOrder->cancelation_reason)
		{
			$data['cancelation_reason'] = $blanketOrder->cancelation_reason;
			if($blanketOrder->canceled_by)
			{
				$canceled_by = User::find($blanketOrder->canceled_by);
				$data['canceled_by'] = $canceled_by->email;
			}
		}
		return $data;
	}
	public function includeCoDistributor(BlanketOrder $order)
	{
		if($order->distributor)
		{
			return $this->item($order->distributor,new CompactTransformer);
		}
		return null;
	}
	public function includeDistributor(BlanketOrder $order)
	{
		return $this->item($order->orderable, new CompactTransformer);
	}
	public function includePurchaseLines(BlanketOrder $order)
	{
		return $this->collection($order->purchase_lines,new LineTransformer($order->orderable));
	}
	public function includeRelatedOrder(BlanketOrder $order)
	{
		if($order->relatedOrder)
		{
			return $this->item($order->relatedOrder->blanket_order,new BlanketOrderListTransformer);
		}
		else
		{
			return null;
		}
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