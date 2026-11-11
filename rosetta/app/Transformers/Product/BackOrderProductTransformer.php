<?php
namespace Rosetta\Transformers\Product;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\Product;
use Rosetta\Models\Region;
use Rosetta\Models\Company;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;
use Rosetta\Transformers\Product\PriceTransformer;
class BackOrderProductTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['category','collection','colour', 'price'];
	protected $region, $company, $customerType, $customer;
	public function __construct(Region $region = null, Company $company = null, $customerType=null, $customer=null)
	{
		$this->region = $region;
		$this->company = $company;
		$this->customer = $customer;
		$this->customerType = $customerType;
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
		];
	}
	public function includeCategory(Product $product)
	{
		if(!$product->category)
			return null;
		return $this->item($product->category,new CategoryTransformer(null, null, $this->customer));
	}
	public function includeCollection(Product $product)
	{
		if(!$product->collection)
			return null;
		$collection = $product->category->collections()->find($product->collection_code);
		return $this->item($collection,new CollectionTransformer(null, null, $this->customer));
	}
	public function includeColour(Product $product)
	{
		if(!$product->colour_code)
			return null;
		$collection = $product->category->collections()->find($product->collection_code);
		$colour = $collection->colours()->find($product->colour_code);
		if(!$colour)
			return null;
		return $this->item($colour,new ColourTransformer(null, null, $this->customer, $product->category->code));
	}
	public function includePrice(Product $product)
	{
		return $this->item($product, new PriceTransformer($this->company,$this->customer,$this->customerType));
	}
}