<?php
namespace Rosetta\Transformers\AdvanceOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\AdvanceOrder\AdvanceOrder;
class AdvanceOrderSummaryTransformer extends TransformerAbstract
{
	public function transform(AdvanceOrder $order)
	{
		return [
			"id" => $order->id,
			"so_number" => $order->navision_order_number,
		];
	}
}