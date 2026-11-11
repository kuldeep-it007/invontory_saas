<?php
namespace Rosetta\Transformers\SuperStockistStock;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\Stock;

class SuperStockistRemainingStockTransformer extends TransformerAbstract
{
	public function transform(Stock $stock)
	{
		return [
			"batch_no" => $stock->batch_no,
			"remaining_quantity" => $stock->quantity,
		];
	}
}