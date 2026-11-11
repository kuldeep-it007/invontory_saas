<?php
namespace Rosetta\Transformers\CLRate;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\CLRate;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;

class CLRateTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['category','collection','colour','customer'];
	protected $defaultIncludes = ['category','collection','colour','customer'];
	public function transform(CLRate $rate)
	{
		return [
			"id" => $rate->id,
			"rate" => $rate->percent,
			"customer_type" => $rate->related_type,
		];
	}
	public function includeCategory(CLRate $rate)
	{
		if($rate->category_code)
		{
			return $this->item($rate->category,new CategoryTransformer);
		}
		return null;
	}
	public function includeCollection(CLRate $rate)
	{
		if($rate->collection_code)
		{
			$collection = $rate->category->collections()->find($rate->collection_code);
			return $this->item($collection,new CollectionTransformer);
		}
		return null;
	}
	public function includeColour(CLRate $rate)
	{
		$collection = $rate->collection()->find($rate->collection_code);
        if($rate->colour_code)
        {
        	$colour = $rate->colour()->find($rate->colour_code);
        	return $this->item($colour,new ColourTransformer);
        }
		return null;
	}

	public function includeCustomer(CLRate $rate)
	{
		if($rate->related_id)
		{
			return $this->item($rate,function($r){
				$m = null;
				if($r->related_type == 'DISTRIBUTO')
				{
					$m = Distributor::find($r->related_id);
				}
				else
				{
					$m = Dealer::find($r->related_id);
				}
				return [
					"id "=> $m->id,
					"name" => $m->name,
					"nav_id" => $m->nav_id
				];

			});
		}
		return null;
	}
}