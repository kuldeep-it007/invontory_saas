<?php
namespace Rosetta\Transformers\SuperStockistStock;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Product\Product;
use Rosetta\Models\Product\Stock;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;
use Rosetta\Transformers\Product\IndexTransformer;
use Rosetta\Transformers\SuperStockistStock\SuperStockistLocationTransformer;

class StockTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['category','collection','colour','location'];
	protected $defaultIncludes = ['product'];
	protected $user_type;
	public function __construct($user_type = null)
	{
		$this->user_type = $user_type;
	}
	public function transform(Stock $stock)
	{
		$this->product = Product::where('id','=',$stock->product_id)->first();
		$blockedQuntity = BlockedItem::active()->where('product_id','=',$this->product->id)->where('super_stockist_id','=',$this->user_type->id)->where('batch_no','=',$stock->batch_no)->sum('order_quantity');

		return [
			"id" => $stock->id,
			"batch_no" => $stock->batch_no,
			"quantity" => $stock->quantity,
			"product" => $stock->product_id,
			"blocked" => (float)$blockedQuntity,
		];
	}
	public function includeProduct(Stock $stock)
	{
		$product = Product::find($stock->product_id);
		return $this->item($product, new IndexTransformer($stock->stockable->company->region, $this->user_type));
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