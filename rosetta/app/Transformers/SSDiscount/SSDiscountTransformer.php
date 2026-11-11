<?php
namespace Rosetta\Transformers\SSDiscount;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\SSDiscount;
use Rosetta\Transformers\Distributors\IndexTransformer as DistributorTransformer;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;

class SSDiscountTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['customer','category','collection','colour'];
	protected $defaultIncludes = ['customer','category','collection','colour'];
	
	public function transform(SSDiscount $discount)
	{
		return [
			"id" => $discount->id,
			"discount" => $discount->discount,
		];
	}
	public function includeCustomer(SSDiscount $discount)
	{
		if(!$discount->customer_id)
		{
			return null;
		}
		if($discount->customer_type == 'DISTRIBUTO')
		{
			return $this->item($discount->distributor,new DistributorTransformer);
		}
	}
	public function includeCategory(SSDiscount $discount)
	{
		if($discount->category_code)
		{
			return $this->item($discount->category,new CategoryTransformer(null, null, $discount->parentSuperStockist));
		}
		return null;
	}
	public function includeCollection(SSDiscount $discount)
	{
		if($discount->collection_code)
		{
			$collection = $discount->category->collections()->find($discount->collection_code);
			return $this->item($collection,new CollectionTransformer(null, null, $discount->parentSuperStockist));
		}
		return null;
	}
	public function includeColour(SSDiscount $discount)
	{
		$collection = $discount->collection()->find($discount->collection_code);
        if($discount->colour_code)
        {
        	$colour = $collection->colours()->find($discount->colour_code);
    
        	return $this->item($colour,new ColourTransformer(null, null, $discount->parentSuperStockist, $discount->category_code));
        }
		return null;
	}
}