<?php
namespace Rosetta\Transformers\Invoice;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\User;
use Rosetta\Transformers\Invoice\DealerTransformer;
use Rosetta\Transformers\Invoice\DistributorTransformer;

class PurchaseOrderTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['customer'];
	protected $defaultIncludes = ['customer'];
	public function transform(PurchaseOrder $purchaseOrder)
	{
		$data = [
			"id" => $purchaseOrder->id,
			"number" => $purchaseOrder->order_number,
			"amount" => $purchaseOrder->Amount,
			"so" => $purchaseOrder->navision_order_number,
		];
		return $data;
	}
	public function includeCustomer(PurchaseOrder $purchaseOrder)
	{
		$customer_type = get_class($purchaseOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
		if($customer_type == 'DISTRIBUTO')
			return $this->item($purchaseOrder->orderable, new DistributorTransformer);
		if($customer_type == 'DEALER')
			return $this->item($purchaseOrder->orderable, new DealerTransformer);
	}
}