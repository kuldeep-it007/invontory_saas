<?php
namespace Rosetta\Transformers\StockPoint;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Company\CustomerStockPoint;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
class CustomerTransformer extends TransformerAbstract
{
	public function transform(CustomerStockPoint $stockPoint)
	{
		$data =  [
			"id" => $stockPoint->customerable->id,
			"name" => $stockPoint->customerable->name,
			"nav_id" => $stockPoint->customerable->nav_id,
 		];
 		if($stockPoint->customerable instanceof Distributor)
 		{
 			$data['type'] = "Distributor";
 		}
 		else if($stockPoint->customerable instanceof SuperStockist)
 		{
 			$data['type'] = "SuperStockist";
 		}
 		else
 		{
 			$data['type'] = "Dealer";	
 		}
 		return $data;
	}
}