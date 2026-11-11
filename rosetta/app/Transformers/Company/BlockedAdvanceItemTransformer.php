<?php
namespace Rosetta\Transformers\Company;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\AdvanceOrder\BlockedAdvanceItem;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Region;
use Rosetta\Transformers\Product\ItemCartProductTransformer;
class BlockedAdvanceItemTransformer extends TransformerAbstract
{
	protected $defaultIncludes = ['product'];
	protected $availableIncludes = ['product'];
	public function transform(BlockedAdvanceItem $item)
	{
		$customer = null;
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
			"stock_point_name" => $item->stock_point->name,	//#859
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
	public function includeProduct(BlockedAdvanceItem $item)
	{
		if(get_class($item->blockable) == Company::class)
			$region = $item->blockable->region;
		else
			$region = $item->blockable->company->region;
		return $this->item($item->product, new ItemCartProductTransformer($region, $item->blockable));
	}
}