<?php
namespace Rosetta\Transformers\StockPoint;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Company\StockPoint;
class IndexTransformer extends TransformerAbstract
{

	public function transform(StockPoint $stockPoint)
	{
		return [
			"id"=> $stockPoint->id,
			"code" => $stockPoint->code,
			"name" => $stockPoint->name,
		];
	}
}