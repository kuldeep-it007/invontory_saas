<?php
namespace Rosetta\Transformers\Invoice;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Distributor;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Transformers\Invoice\DealerTransformer;
use Rosetta\Transformers\Distributors\CompactTransformer as DistributorCompactTransformer;

class InvoiceList1Transformer extends TransformerAbstract
{
	protected $availableIncludes = ['customer','so_numbers'];
	protected $defaultIncludes = ['customer','so_numbers'];
	public function transform(BlanketOrder $blanketOrder)
	{
		$data = [
			"id" => $blanketOrder->id,
			"number" => $blanketOrder->order_number,
			"amount" => $blanketOrder->amount,
			"items" => $blanketOrder->purchase_lines()->count(),
		];
		return $data;
	}
	public function includeCustomer(BlanketOrder $blanketOrder)
	{
		$customer_type = get_class($blanketOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
		if($customer_type == 'DISTRIBUTO')
			return $this->item($blanketOrder->orderable, new DistributorCompactTransformer);
		if($customer_type == 'DEALER')
			return $this->item($blanketOrder->orderable, new DealerTransformer);
	}
	public function includeSONumbers(BlanketOrder $order)
	{
		return $this->item($order,function($o){
			return $o->purchase_orders->pluck('navision_order_number')->toArray();
		});
	}
}