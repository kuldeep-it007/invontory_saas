<?php
namespace Rosetta\Transformers\BlanketOrders;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
class PurchaseOrderSummaryTransformer extends TransformerAbstract
{
	public function transform(PurchaseOrder $order)
	{
		return [
			"id" => $order->id,
			"so_number" => $order->navision_order_number,
		];
	}
}