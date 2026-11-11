<?php

namespace Rosetta\Transformers\Discount;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Discount;
use Rosetta\Transformers\Distributors\CompactTransformer as DistributorTransformer;
use Rosetta\Transformers\Dealers\CompactTransformer as DealerTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;
use Rosetta\Transformers\Region\IndexTransformer;
use Rosetta\Transformers\SuperStockists\CompactTransformer as SsTransformer;


class DiscountTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['region', 'customer', 'category', 'collection', 'colour', 'state'];
	protected $defaultIncludes = ['region', 'customer', 'category', 'collection', 'colour', 'state'];
	public function transform(Discount $discount)
	{
		return [
			"id" => $discount->id,
			"discount" => $discount->discount,
			"customer_type" => $discount->customer_type,
			"city" => $discount->city_code,
			"post_code" => $discount->post_code,
			"region_company_id" => $discount->region->companies()->first()->id, //#860
		];
	}
	public function includeRegion(Discount $discount)
	{
		return $this->item($discount->region, new IndexTransformer);
	}
	public function includeCustomer(Discount $discount)
	{
		if (!$discount->customer_id) {
			return null;
		}
		if ($discount->customer_type == 'DISTRIBUTO') {
			return $this->item($discount->distributor, new DistributorTransformer);
		}
		if ($discount->customer_type == 'SUPER_STOCKIST_DISTRIBUTOR') {
			return $this->item($discount->distributorunderss, new DistributorTransformer);
		}
		if ($discount->customer_type == 'DEALER') {
			return $this->item($discount->dealer, new DealerTransformer);
		}
		if ($discount->customer_type == 'SUPER_STOCKIST_DEALER') {
			return $this->item($discount->dealerunderdistributorunderss, new DealerTransformer);
		}

		if ($discount->customer_type == 'SUPER_STOCKIST') {
			return $this->item($discount->superstockist, new SsTransformer);
		}
	}
	public function includeCategory(Discount $discount)
	{
		if ($discount->category_code) {
			return $this->item($discount->category, new CategoryTransformer);
		}
		return null;
	}
	public function includeCollection(Discount $discount)
	{
		if ($discount->collection_code) {
			$collection = $discount->category->collections()->find($discount->collection_code);
			return $this->item($collection, new CollectionTransformer);
		}
		return null;
	}
	public function includeColour(Discount $discount)
	{
		$collection = $discount->collection()->find($discount->collection_code);
		if ($discount->colour_code) {
			$colour = $collection->colours()->find($discount->colour_code);
			return $this->item($colour, new ColourTransformer);
		}
		return null;
	}
	public function includeState(Discount $discount)
	{
		if ($discount->state) {
			return $this->item($discount->state, new StateTransformer);
		}
		return null;
	}
}
