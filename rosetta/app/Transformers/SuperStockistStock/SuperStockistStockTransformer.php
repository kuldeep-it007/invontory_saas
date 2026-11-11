<?php
namespace Rosetta\Transformers\SuperStockistStock;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\Product\Product;
use Rosetta\Models\Product\Stock;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;
use Rosetta\Transformers\Product\IndexTransformer;
use Rosetta\Transformers\Product\SuperStockistPriceTransformer;
use Rosetta\Transformers\SuperStockistStock\SuperStockistLocationTransformer;

class SuperStockistStockTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['category','collection','colour','location', 'price'];
	protected $defaultIncludes = ['product'];
	protected $customer, $customer_type;
	public function __construct($customer = null, $customer_type = null)
	{
		$this->customer = $customer;
		$this->customer_type = $customer_type;
	}
	public function transform(Stock $stock)
	{
		$this->product = Product::where('id','=',$stock->product_id)->first();
		$blockedQuntity = BlockedItem::active()->where('product_id','=',$this->product->id)->where('super_stockist_id','=',$stock->superStockist->id)->sum('order_quantity');

		return [
			"id" => $stock->id,
			"batch_no" => $stock->batch_no,
			"quantity" => $stock->quantity,
			"product" => $stock->product_id,
			"base_unit_of_measure" => $this->product->nav->getBase_Unit_of_Measure(),
			"blocked"			   => (float)$blockedQuntity,
		];
	}
	public function includeCategory(Stock $stock)
	{
		$region = null;
		if($stock->stockable->company)
			$region = $stock->stockable->company->region;
		if($this->product->category)
			return $this->item($this->product->category, new CategoryTransformer($region,$this->product->sales_unit_of_measure, $this->customer));
		return null;
	}
	public function includeCollection(Stock $stock)
	{
		$region = null;
		if($stock->stockable->company)
			$region = $stock->stockable->company->region;
		if($this->product->collection)
			return $this->item($this->product->collection, new CollectionTransformer($region,$this->product->sales_unit_of_measure, $this->customer));
		return null;
	}
	public function includeColour(Stock $stock)
	{
		if($this->product->colour)
		{
			$colour = Colour::where('code','=',$this->product->colour_code)
				->where('collection_code','=',$this->product->collection_code)
				->first();
			$region = null;
			if($stock->stockable->company)
				$region = $stock->stockable->company->region;
			if(!$colour)
				return null;
			return $this->item($colour, new ColourTransformer($region,$this->product->sales_unit_of_measure, $this->customer, $this->product->category->code));
		}
		return null;
	}
	public function includePrice(Stock $stock)
	{
		return $this->item($this->product,new SuperStockistPriceTransformer($this->customer,$this->customer_type));
	}
	public function includeProduct(Stock $stock)
	{
		$product = Product::find($stock->product_id);
		return $this->item($product, new IndexTransformer($stock->stockable->company->region, $this->customer));
	}
	public function includeLocation(Stock $stock)
	{
		if($stock->superStockist)
		{
			return $this->item($stock->superStockist,new SuperStockistLocationTransformer);
		}
		return null;
	}
}