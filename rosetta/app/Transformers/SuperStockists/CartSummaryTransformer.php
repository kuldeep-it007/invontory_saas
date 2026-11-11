<?php
namespace Rosetta\Transformers\SuperStockists;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\SuperStockist\SuperStockist;
class CartSummaryTransformer extends TransformerAbstract
{
	public function transform(SuperStockist $superStockist)
	{
		return [
			"cart" => $superStockist->cartItems()->count(),
		];
	}
}