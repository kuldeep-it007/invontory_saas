<?php
namespace Rosetta\Transformers\StockPoint;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Company\CustomerStockPoint;
class CompanyIndexTransformer extends TransformerAbstract
{
	public function transform(CustomerStockPoint $stockPoint)
	{
		return [
			"id" => $stockPoint->stock_point->id,
			"code" => $stockPoint->stock_point->code,
			"name" => $stockPoint->stock_point->name,
		];
	}
}