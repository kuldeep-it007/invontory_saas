<?php
namespace Rosetta\Transformers\AdvanceOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\AdvanceOrder\AdvanceOrder;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Transformers\Dealers\IndexTransformer as DealerTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer as DistributorTransformer;
use Rosetta\Transformers\SuperStockists\IndexTransformer as SuperStockistTransformer;

class IndexTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['customer'];
	protected $defaultIncludes = ['customer'];
	public function transform(AdvanceOrder $advanceOrder)
	{
		$data = [
			"id" => $advanceOrder->id,
			"so" => $advanceOrder->navision_order_number,
			"number" => $advanceOrder->order_number,
			"amount" => $advanceOrder->amount,
			"created_at" => $advanceOrder->created_at->format('U'),
			"status" => $advanceOrder->status,
			"items" => $advanceOrder->advance_lines()->count(),
			"customer" => $advanceOrder->orderable,
	//		"customer_type" => get_class($advanceOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER',
		];
		if(get_class($advanceOrder->orderable) == Distributor::class)
			$data['customer_type'] = 'DISTRIBUTO';
		if(get_class($advanceOrder->orderable) == Dealer::class)
			$data['customer_type'] = 'DEALER';
		if(get_class($advanceOrder->orderable) == SuperStockist::class)
			$data['customer_type'] = 'SUPER_STOCKIST';
		return $data;
	}
	public function includeCustomer(AdvanceOrder $advanceOrder)
	{
		/*$customer_type = get_class($advanceOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
		if($customer_type == 'DISTRIBUTO')
			return $this->item($advanceOrder->orderable, new DistributorTransformer);
		if($customer_type == 'DEALER')
			return $this->item($advanceOrder->orderable, new DealerTransformer);*/
		if(get_class($advanceOrder->orderable) == Distributor::class)
			return $this->item($advanceOrder->orderable, new DistributorTransformer);
		if(get_class($advanceOrder->orderable) == Dealer::class)
			return $this->item($advanceOrder->orderable, new DealerTransformer);
		if(get_class($advanceOrder->orderable) == SuperStockist::class)
			return $this->item($advanceOrder->orderable, new SuperStockistTransformer);
	}
}