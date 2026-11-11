<?php
namespace Rosetta\Transformers\AdvanceOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\AdvanceOrder\BlockedAdvanceItem;
use Rosetta\Models\Product\Product;
use Rosetta\Transformers\ProductTax\ProductTaxTransformer;
use Rosetta\Transformers\Product\ItemCartProductTransformer;

class BlockedAdvanceItemTransformer extends TransformerAbstract
{
	protected $defaultIncludes = ['product','tax'];
	protected $availableIncludes = ['category','collection','colour','tax'];
	protected $user_type;
	public function __construct($user_type = null)
	{
		$this->user_type = $user_type;
	}
	public function transform(BlockedAdvanceItem $blockedItem)
	{
		$data = [
			"id" => $blockedItem->id,
			"product_id" => $blockedItem->product_id,
			"batch_no" => $blockedItem->batch_no,
			"order_quantity" => $blockedItem->order_quantity,
			"rate_type" => $blockedItem->rate_type,
			"unit_price" => $blockedItem->unit_price,
			"discount" => $blockedItem->discount,
			"onlineDiscount" => $blockedItem->onlineDiscount,
			"expires" => $blockedItem->expires_at->format('U'),
			"cool_down_start_time" => $blockedItem->cool_down_start_time->format('U'),
			"cool_down_end_time" => $blockedItem->cool_down_end_time->format('U'),
			"stock_point_id" => $blockedItem->stock_point_id,
		//	"stock_point_name" => $blockedItem->stock_point->name,
			"distributor_id" => $blockedItem->distributor_id,
		//	"super_stockist_id" => $blockedItem->super_stockist_id,
			"nav_intransit_po" => $blockedItem->nav_intransit_po,
			"eta" 			 => $blockedItem->eta,
			"created_at"	 => $blockedItem->created_at->format('U'),
		];
		if($blockedItem->stock_point)
			$data['stock_point_name'] = $blockedItem->stock_point->name;
		else
			$data['stock_point_name'] = null;
		return $data;
	}
	public function includeProduct(BlockedAdvanceItem $blockedItem)
	{
		$region = $blockedItem->blockable->company->region;
		return $this->item($blockedItem->product, new ItemCartProductTransformer($region, $this->user_type));
	}
	public function includeTax(BlockedAdvanceItem $blockedItem)
	{
		$region = $blockedItem->blockable->company->region;
		$category = $blockedItem->product->category;
		$collection = $blockedItem->product->collection;
		if($category)
		{
			$tax = $region->taxes()->where('category_code','=',$category->code)->where('collection_code','=',$collection->code)->first();
			if(!$tax)
			{
				$tax = $region->taxes()->where('category_code','=',$category->code)->first();
			}
			return $this->item($tax, new ProductTaxTransformer());
		}
	}
}