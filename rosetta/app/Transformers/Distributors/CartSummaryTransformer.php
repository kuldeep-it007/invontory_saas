<?php
namespace Rosetta\Transformers\Distributors;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;
class CartSummaryTransformer extends TransformerAbstract
{
	public function transform(Distributor $distributor)
	{
		return [
			"cart" => $distributor->cartItems()->count(),
		];
	}
}