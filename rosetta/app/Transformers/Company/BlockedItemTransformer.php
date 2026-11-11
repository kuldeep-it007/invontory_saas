<?php
namespace Rosetta\Transformers\Company;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Region;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Transformers\Product\ItemCartProductTransformer;
class BlockedItemTransformer extends TransformerAbstract
{
	protected $defaultIncludes = ['product'];
	protected $availableIncludes = ['product'];
	public function transform(BlockedItem $item)
	{
		$customer = null;
		if(get_class($item->blockable) == SuperStockist::class)
		{
			$customer['type']   = 'SUPERSTOCKIST';
			$customer['id']     = $item->blockable->id;
			$customer['nav_id'] = $item->blockable->nav_id;
			$customer['name']   = $item->blockable->name;
		}

		if(get_class($item->blockable) == Distributor::class)
		{
			$customer['type']   = 'DISTRIBUTO';
			$customer['id']     = $item->blockable->id;
			$customer['nav_id'] = $item->blockable->nav_id;
			$customer['name']   = $item->blockable->name;
		}
		if(get_class($item->blockable) == Dealer::class)
		{
			$customer['type']   			= 'DEALER';
			$customer['id']     			= $item->blockable->id;
			$customer['nav_id'] 			= $item->blockable->nav_id;
			$customer['name']   			= $item->blockable->name;
			$customer['distributor_name']   = $item->blockable->distributor ? $item->blockable->distributor->name : '';
			$customer['distributor_nav_id'] = $item->blockable->distributor ? $item->blockable->distributor->nav_id : '';
		}
		return [
			'id'             => $item->id,
			"batch_no"       => $item->batch_no,
			"stock_point_name" => (!empty($item->stock_point->name))?$item->stock_point->name:'',	//#859
			"order_quantity" => $item->order_quantity,
			"rate_type"      => $item->rate_type,
			"unit_price"     => $item->unit_price,
			"discount"       => $item->discount,
			"expires"        => $item->expires_at->format('U'),
			"cool_down_start_time" => $item->cool_down_start_time->format('U'),
			"cool_down_end_time" => $item->cool_down_end_time->format('U'),
			"customer"       => $customer,
			"comment" 		 => $item->comment,
		];	
	}
	public function includeProduct(BlockedItem $item)
	{
		if(get_class($item->blockable) == Company::class)
			$region = $item->blockable->region;
		else
			$region = $item->blockable->company->region;
		return $this->item($item->product, new ItemCartProductTransformer($region, $item->blockable));
	}
}