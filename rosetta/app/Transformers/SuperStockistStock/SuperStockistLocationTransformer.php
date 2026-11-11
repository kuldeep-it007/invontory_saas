<?php
namespace Rosetta\Transformers\SuperStockistStock;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Transformers\Location\StateTransformer;
class SuperStockistLocationTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['state'];
	protected $defaultIncludes = ['state'];
	public function transform(SuperStockist $superStockist)
	{
		return [
			"city" => $superStockist->city_code,
			"post_code" => $superStockist->post_code,
		];
	}
	public function includeState(SuperStockist $superStockist)
	{
		if($superStockist->state)
		{
			return $this->item($superStockist->state,new StateTransformer);	
		}
		return null;
		
	}
}