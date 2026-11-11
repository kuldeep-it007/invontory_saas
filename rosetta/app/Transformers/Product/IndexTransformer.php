<?php
namespace Rosetta\Transformers\Product;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\Product;
use Rosetta\Models\Region;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;
class IndexTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['category','collection','colour'];
	protected $region, $activable_type;
	public function __construct(Region $region = null, $activable_type = null)
	{
		$this->region = $region;
		$this->activable_type = $activable_type;
	}
	public function transform(Product $product)
	{
		$bom = $product->base_unit_of_measure;

		if($this->region)
		{
			$uom = $this->region->getUOM('sales_unit_of_measure',$bom);
			if($uom)
			{
				$bom = $uom->to;
			}
		}
		return [
			"id" => $product->id,
			"nav_id" => $product->nav_id,
			"name" => $product->name,
			"base_unit_of_measure" => $bom,
			"status" => $product->status,
		];
	}
	public function includeCategory(Product $product)
	{
		if(!$product->category)
			return null;
		if($this->activable_type == null)
			return $this->item($product->category,new CategoryTransformer(null, null, $this->activable_type));
		else
			return $this->item($product->category,new CategoryTransformer(null, null, $this->activable_type, true));
	}
	public function includeCollection(Product $product)
	{
		if(!$product->collection)
			return null;
		$collection = $product->category->collections()->find($product->collection_code);
		if($this->activable_type == null)
			return $this->item($collection,new CollectionTransformer(null, null, $this->activable_type));
		else
			return $this->item($collection,new CollectionTransformer(null, null, $this->activable_type, true));
	}
	public function includeColour(Product $product)
	{
		if(!$product->colour_code)
			return null;
		$collection = $product->category->collections()->find($product->collection_code);
		$colour = $collection->colours()->find($product->colour_code);
		if(!$colour)
			return null;
		if($this->activable_type == null)
			return $this->item($colour,new ColourTransformer(null, null, $this->activable_type, $product->category->code));
		else
			return $this->item($colour,new ColourTransformer(null, null, $this->activable_type, $product->category->code, true));
	}
}