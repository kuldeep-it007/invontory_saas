<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\AvailableStockThreshold;
use Rosetta\Models\Product\Product;
use Rosetta\Transformers\Product\IndexTransformer;
use Rosetta\Transformers\StockPoint\IndexTransformer as StockPointTransformer;

class StockThresholdTransformer extends TransformerAbstract
{
	//protected $availableIncludes = ['category','collection','colour'];
	//protected $defaultIncludes = ['product'];
	protected $availableIncludes = ['product', 'category', 'collection', 'colour', 'stock_point'];
	protected $defaultIncludes = ['product'];
	protected $user_type;
	public function __construct($user_type = null)
	{
		$this->user_type = $user_type;
	}
	public function transform(AvailableStockThreshold $stock)
	{
		return [
			"id" => $stock->id,
			"tenant" => $stock->tenant,
			"threshold_qty" => $stock->threshold_qty,
			"available_qty" => $stock->available_qty,
			"reorder_qty" => $stock->reorder_qty,
			"lc" => $stock->lc,
			"uom" => $stock->uom,
			"company_id" => $stock->company_id,
			//"product" => $stock->product_id,
			//"stock_point_id" => $stock->stock_point_id,
		];
	}
	public function includeProduct(AvailableStockThreshold $stock)
	{
		return $this->item($stock->product, new IndexTransformer);
	}
	public function includeStockPoint(AvailableStockThreshold $stock)
	{
		return $this->item($stock->stock_point, new StockPointTransformer);
	}
}