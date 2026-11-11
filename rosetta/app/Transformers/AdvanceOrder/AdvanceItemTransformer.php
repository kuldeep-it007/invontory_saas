<?php
namespace Rosetta\Transformers\AdvanceOrder;

use Carbon\Carbon;
use League\Fractal\TransformerAbstract;
use Rosetta\Models\AdvanceOrder\AdvanceItem;
use Rosetta\Models\Product\Product;
use Rosetta\Transformers\AdvanceOrder\StockPointIndexTransformer;
use Rosetta\Transformers\Product\ItemCartProductTransformer;

class AdvanceItemTransformer extends TransformerAbstract
{
	protected $defaultIncludes = ['product'];
	protected $availableIncludes = ['category','collection','colour'];
	public function transform(AdvanceItem $advanceItem)
	{
		$data = [
			"id" => $advanceItem->id,
			"product_id" => $advanceItem->product_id,
			"batch_no" => $advanceItem->batch_no,
			"order_quantity" => $advanceItem->order_quantity,
			"rate_type" => $advanceItem->rate_type,
			"unit_price" => $advanceItem->unit_price,
			"discount" => $advanceItem->discount,
			"discounted_price" => $advanceItem->discounted_price,
			"total_price" => $advanceItem->total_price,
			"expires" => $advanceItem->expires_at->format('U'),
			"eta" => $advanceItem->eta,
			"created_at" => $advanceItem->created_at->format('U'),
			"nav_intransit_po" => $advanceItem->nav_intransit_po,
			"onlineDiscount" => $advanceItem->onlineDiscount,
		];
		if($advanceItem->stock_point)
			$data['stock_point_name'] = $advanceItem->stock_point->name;
		else
			$data['stock_point_name'] = null;
		return $data;
	}
	public function includeProduct(AdvanceItem $advanceItem)
	{
		$region = $advanceItem->advanceable->company->region;
		return $this->item($advanceItem->product, new ItemCartProductTransformer($region, $advanceItem->advanceable));
	}
}