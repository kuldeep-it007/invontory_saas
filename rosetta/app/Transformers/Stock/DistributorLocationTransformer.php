<?php
namespace Rosetta\Transformers\Stock;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;
use Rosetta\Transformers\Location\StateTransformer;
class DistributorLocationTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['state'];
	protected $defaultIncludes = ['state'];
	public function transform(Distributor $distributor)
	{
		return [
			"city" => $distributor->city_code,
			"post_code" => $distributor->post_code,
		];
	}
	public function includeState(Distributor $distributor)
	{
		if($distributor->state)
		{
			return $this->item($distributor->state,new StateTransformer);	
		}
		return null;
		
	}
}