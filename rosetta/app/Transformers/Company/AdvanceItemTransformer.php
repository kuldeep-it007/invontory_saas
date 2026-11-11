<?php
namespace Rosetta\Transformers\Company;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\AdvanceOrder\AdvanceItem;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Region;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Transformers\Product\ItemCartProductTransformer;
class AdvanceItemTransformer extends TransformerAbstract
{
	protected $defaultIncludes = ['product'];
	protected $availableIncludes = ['product'];
	public function transform(AdvanceItem $item)
	{
		$customer = [];
		if(get_class($item->advanceable) == Distributor::class)
		{
			$customer['type']   = 'DISTRIBUTO';
			$customer['id']     = $item->advanceable->id;
			$customer['nav_id'] = $item->advanceable->nav_id;
			$customer['name']   = $item->advanceable->name;
		}
		if(get_class($item->advanceable) == SuperStockist::class)
		{
			$customer['type']   = 'SUPER_STOCKIST';
			$customer['id']     = $item->advanceable->id;
			$customer['nav_id'] = $item->advanceable->nav_id;
			$customer['name']   = $item->advanceable->name;
		}
		if(get_class($item->advanceable) == Dealer::class)
		{
			$customer['type']   = 'DEALER';
			$customer['id']     = $item->advanceable->id;
			$customer['nav_id'] = $item->advanceable->nav_id;
			$customer['name']   = $item->advanceable->name;
		}
		$data = [
			'id'             => $item->id,
			"product_id"     => $item->product_id,
			"batch_no"       => $item->batch_no,	
			"order_quantity" => $item->order_quantity,
			"rate_type"      => $item->rate_type,
			"unit_price"     => $item->unit_price,
			"discount"       => $item->discount,
			"discounted_price" => $item->discounted_price,
			"total_price"    => $item->total_price,
			"tax_type_code"    => $item->tax_type_code,
			"tax_percent"    => $item->tax_percent,
			"tax_amount"    => $item->tax_amount,
			"net_amount"    => $item->net_amount,
			"expires"        => $item->expires_at->format('U'),
			"customer"       => $customer,
			"company_id"     => $item->company_id,
			"eta" 			 => $item->eta,
			"created_at"	 => $item->created_at->format('U'),
			"nav_intransit_po"	 => $item->nav_intransit_po,
			"stock_point_id" => $item->stock_point_id,
			"onlineDiscount" => $item->onlineDiscount,
		];	

		if($item->stock_point)
			$data['stock_point_name'] = $item->stock_point->name;
		else
			$data['stock_point_name'] = null;
		return $data;
	}
	public function includeProduct(AdvanceItem $item)
	{
		$region = $item->advanceable->company->region;
		return $this->item($item->product, new ItemCartProductTransformer($region, $item->advanceable));
	}
}