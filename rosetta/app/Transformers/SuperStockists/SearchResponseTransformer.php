<?php 
namespace Rosetta\Transformers\SuperStockists;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\SuperStockist\SuperStockist;

class SearchResponseTransformer extends TransformerAbstract
{
	public function transform(SuperStockist $superStockist)
	{
		$data = [
			"id" => $superStockist->id,
			"nav_id" => $superStockist->nav_id,
			"name" => $superStockist->name,
			"type" => 'superStockist',
	//		"super_stockist_id" => $superStockist->super_stockist_id,
		];
		return $data;
	}
}
