<?php
namespace Rosetta\Transformers\Product;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\Product;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;
class ProductTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['category','collection','colour'];
	protected $defaultIncludes = ['category','collection','colour'];
	public function transform(Product $product)
	{
		return [
			"id" => $product->id,
			"nav_id" => $product->nav_id,
			"name" => $product->name,
			"description" => $product->nav->getDescription_2(),
			"search_description" => $product->nav->getSearch_Description(),
			"base_unit_of_measure" => $product->base_unit_of_measure,
			"purchase_unit_of_measure" => $product->purchase_unit_of_measure,
			"sales_unit_of_measure" => $product->sales_unit_of_measure,
			"image" => $product->image,
		];
	}
	public function includeCategory(Product $product)
	{
		if(!$product->category)
			return null;
		return $this->item($product->category,new CategoryTransformer);
	}
	public function includeCollection(Product $product)
	{
		if(!$product->collection)
			return null;
		$collection = $product->category->collections()->find($product->collection_code);
		return $this->item($collection,new CollectionTransformer);
	}
	public function includeColour(Product $product)
	{
		if(!$product->colour_code)
			return null;
		$collection = $product->category->collections()->find($product->collection_code);
		$colour = $collection->colours()->find($product->colour_code);
		if(!$colour)
			return null;
		return $this->item($colour,new ColourTransformer);
	}
}