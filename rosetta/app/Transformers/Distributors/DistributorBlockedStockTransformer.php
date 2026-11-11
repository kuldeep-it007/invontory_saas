<?php
namespace Rosetta\Transformers\Distributors;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Transformers\Product\ItemCartProductTransformer;

class DistributorBlockedStockTransformer extends TransformerAbstract
{
	protected $defaultIncludes = ['product'];
	protected $availableIncludes = ['category','collection','colour'];
	protected $user_type;
	public function __construct($user_type = null)
	{
		$this->user_type = $user_type;
	}
	public function transform(BlockedItem $blockedItem)
	{
		$customer = [];
		if(get_class($blockedItem->blockable) == Distributor::class)
		{
			$customer['type']   = 'DISTRIBUTO';
			$customer['id']     = $blockedItem->blockable->id;
			$customer['nav_id'] = $blockedItem->blockable->nav_id;
			$customer['name']   = $blockedItem->blockable->name;
		}
		if(get_class($blockedItem->blockable) == SuperStockist::class)
		{
			$customer['type']   = 'SUPERSTOCKIST';
			$customer['id']     = $blockedItem->blockable->id;
			$customer['nav_id'] = $blockedItem->blockable->nav_id;
			$customer['name']   = $blockedItem->blockable->name;
		}
		if(get_class($blockedItem->blockable) == Dealer::class)
		{
			$customer['type']   = 'DEALER';
			$customer['id']     = $blockedItem->blockable->id;
			$customer['nav_id'] = $blockedItem->blockable->nav_id;
			$customer['name']   = $blockedItem->blockable->name;
		}
		return [
			"id" => $blockedItem->id,
			"product_id" => $blockedItem->product_id,
			"batch_no" => $blockedItem->batch_no,
			"order_quantity" => $blockedItem->order_quantity,
			"rate_type" => $blockedItem->rate_type,
			"unit_price" => $blockedItem->unit_price,
			"discount" => $blockedItem->discount,
			"expires" => $blockedItem->expires_at->format('U'),
			"cool_down_start_time" => $blockedItem->cool_down_start_time->format('U'),
			"cool_down_end_time" => $blockedItem->cool_down_end_time->format('U'),
			"customer"       => $customer,
		];
	}
	public function includeProduct(BlockedItem $blockedItem)
	{
		$region = $blockedItem->blockable->company->region;
		return $this->item($blockedItem->product, new ItemCartProductTransformer($region, $this->user_type));
	}
}