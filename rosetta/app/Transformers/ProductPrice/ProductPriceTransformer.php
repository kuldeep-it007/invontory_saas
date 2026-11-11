<?php
namespace Rosetta\Transformers\ProductPrice;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Product;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;
use Rosetta\Transformers\ProductPrice\PriceTransformer;
class ProductPriceTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['category','collection','colour','price'];
	protected $defaultIncludes = ['category','collection','colour','price'];
	protected $company;
	public function __construct(Company $company)
	{
		$this->company = $company;
	}
	public function transform(Product $product)
	{
		return [
			"id" => $product->id,
			"nav_id" => $product->nav_id,
			"name" => $product->name,
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
	public function includePrice(Product $product)
	{
		return $this->item($product,new PriceTransformer($this->company));
	}
}