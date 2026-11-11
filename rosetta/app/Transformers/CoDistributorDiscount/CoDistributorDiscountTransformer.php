<?php
namespace Rosetta\Transformers\CoDistributorDiscount;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\CoDistributorDiscount;
use Rosetta\Transformers\Distributors\CompactTransformer;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;

class CoDistributorDiscountTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['customer','category','collection','colour'];
	protected $defaultIncludes = ['customer','category','collection','colour'];
	
	public function transform(CoDistributorDiscount $discount)
	{
		return [
			"id" => $discount->id,
			"discount" => $discount->discount,
		];
	}
	public function includeCustomer(CoDistributorDiscount $discount)
	{
		if(!$discount->customer_id)
		{
			return null;
		}

		return $this->item($discount->coDistributor,new CompactTransformer);
	}
	public function includeCategory(CoDistributorDiscount $discount)
	{
		if($discount->category_code)
		{
			return $this->item($discount->category,new CategoryTransformer(null, null, $discount->parentDistributor));
		}
		return null;
	}
	public function includeCollection(CoDistributorDiscount $discount)
	{
		if($discount->collection_code)
		{
			$collection = $discount->category->collections()->find($discount->collection_code);
			return $this->item($collection,new CollectionTransformer(null, null, $discount->parentDistributor));
		}
		return null;
	}
	public function includeColour(CoDistributorDiscount $discount)
	{
		$collection = $discount->collection()->find($discount->collection_code);
        if($discount->colour_code)
        {
        	$colour = $collection->colours()->find($discount->colour_code);
    
        	return $this->item($colour,new ColourTransformer(null, null, $discount->parentDistributor, $discount->category_code));
        }
		return null;
	}
}