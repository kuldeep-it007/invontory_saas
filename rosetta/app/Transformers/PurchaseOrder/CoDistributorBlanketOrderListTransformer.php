<?php
namespace Rosetta\Transformers\PurchaseOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Distributor;
use Rosetta\Transformers\Distributors\CompactTransformer;
class CoDistributorBlanketOrderListTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['co_distributor','distributor'];
	public function transform(BlanketOrder $blanketOrder)
	{
		return [
			"id"         => $blanketOrder->id,
			"number"     => $blanketOrder->order_number,
			"amount"     => $blanketOrder->amount,
			"created_at" => $blanketOrder->created_at->format('U'),
			"status"     => $blanketOrder->status,
		];
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
}