<?php
namespace Rosetta\Transformers\CustomerDiscount;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\CustomerDiscount;
use Rosetta\Transformers\Dealers\IndexTransformer as DealerTransformer;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;

class CustomerDiscountTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['customer','category','collection','colour'];
	protected $defaultIncludes = ['customer','category','collection','colour'];
	
	public function transform(CustomerDiscount $discount)
	{
		return [
			"id" => $discount->id,
			"discount" => $discount->discount,
		];
	}
	public function includeCustomer(CustomerDiscount $discount)
	{
		if(!$discount->customer_id)
		{
			return null;
		}
		if($discount->customer_type == 'DEALER')
		{
			return $this->item($discount->dealer,new DealerTransformer);
		}
	}
	public function includeCategory(CustomerDiscount $discount)
	{
		if($discount->category_code)
		{
			return $this->item($discount->category,new CategoryTransformer(null, null, $discount->parentDistributor));
		}
		return null;
	}
	public function includeCollection(CustomerDiscount $discount)
	{
		if($discount->collection_code)
		{
			$collection = $discount->category->collections()->find($discount->collection_code);
			return $this->item($collection,new CollectionTransformer(null, null, $discount->parentDistributor));
		}
		return null;
	}
	public function includeColour(CustomerDiscount $discount)
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