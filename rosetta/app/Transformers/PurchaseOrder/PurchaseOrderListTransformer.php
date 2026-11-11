<?php
namespace Rosetta\Transformers\PurchaseOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Transformers\Dealers\IndexTransformer as DealerTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer as DistributorTransformer;
use Rosetta\Transformers\SuperStockists\IndexTransformer as SuperStockistTransformer;

class PurchaseOrderListTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['customer'];
	protected $defaultIncludes = ['customer'];
	public function transform(PurchaseOrder $purchaseOrder)
	{
		$data = [
			"id" => $purchaseOrder->id,
			"so" => $purchaseOrder->navision_order_number,
			"number" => $purchaseOrder->order_number,
			"amount" => $purchaseOrder->Amount,
			"created_at" => $purchaseOrder->created_at->format('U'),
			"status" => $purchaseOrder->status,
			"items" => $purchaseOrder->purchase_lines()->count(),
			"customer" => $purchaseOrder->orderable,
		//	"customer_type" => get_class($purchaseOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER',
			"distributor_id" => $purchaseOrder->distributor_id
		];
		if($purchaseOrder->blanket_order->relatedOrder)
			$data['dealer_name'] = $purchaseOrder->blanket_order->relatedOrder->orderable->name;
		else
			$data['dealer_name'] = null;
		if(get_class($purchaseOrder->orderable) == Distributor::class)
		{
			$data['customer_type'] = 'DISTRIBUTO';
		}
		elseif(get_class($purchaseOrder->orderable) == Dealer::class)
		{
			$data['customer_type'] = 'DEALER';
		}
		elseif(get_class($purchaseOrder->orderable) == SuperStockist::class)
		{
			$data['customer_type'] = 'SUPER_STOCKIST';
		}
		return $data;
	}
	public function includeCustomer(PurchaseOrder $purchaseOrder)
	{
	/*	$customer_type = get_class($purchaseOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
		if($customer_type == 'DISTRIBUTO')
			return $this->item($purchaseOrder->orderable, new DistributorTransformer);
		if($customer_type == 'DEALER')
			return $this->item($purchaseOrder->orderable, new DealerTransformer);*/
		if(get_class($purchaseOrder->orderable) == Distributor::class)
			return $this->item($purchaseOrder->orderable, new DistributorTransformer);
		elseif(get_class($purchaseOrder->orderable) == Dealer::class)
			return $this->item($purchaseOrder->orderable, new DealerTransformer);
		elseif(get_class($purchaseOrder->orderable) == SuperStockist::class)
			return $this->item($purchaseOrder->orderable, new SuperStockistTransformer);
	}
}