<?php
namespace Rosetta\Transformers\PurchaseOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Address;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Transformers\AddressTransformer;
use Rosetta\Transformers\Dealers\IndexTransformer as DealerTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer as DistributorTransformer;
use Rosetta\Transformers\SuperStockists\IndexTransformer as SuperStockistTransformer;
use Rosetta\Transformers\PurchaseOrder\LineTransformer;
class RelatedBlanketOrderTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['customer','distributor','purchase_lines','address'];
	protected $defaultIncludes = ['customer','address'];
	public function transform(BlanketOrder $blanketOrder)
	{
		$data = [
			"id"         => $blanketOrder->id,
			"number"     => $blanketOrder->order_number,
			"amount"     => $blanketOrder->amount,
			"created_at" => $blanketOrder->created_at->format('U'),
			"status"     => $blanketOrder->status,
		];
		return $data;
	}
	public function includeDistributor(BlanketOrder $blanketOrder)
	{
		return $this->item($blanketOrder->distributor, new DistributorTransformer);
	}
	public function includePurchaseLines(BlanketOrder $blanketOrder)
	{
		return $this->collection($blanketOrder->purchase_lines,new LineTransformer($blanketOrder->orderable));
	}
	public function includeCustomer(BlanketOrder $blanketOrder)
	{
	/*	$customer_type = get_class($blanketOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
		if($customer_type == 'DEALER')
			return $this->item($blanketOrder->orderable, new DealerTransformer);*/
		if(get_class($blanketOrder->orderable) == Distributor::class)
			return $this->item($blanketOrder->orderable, new DistributorTransformer);
		elseif(get_class($blanketOrder->orderable) == Dealer::class)
			return $this->item($blanketOrder->orderable, new DealerTransformer);
		elseif(get_class($blanketOrder->orderable) == SuperStockist::class)
			return $this->item($blanketOrder->orderable, new SuperStockistTransformer);
	}
	public function includeAddress(BlanketOrder $blanketOrder)
	{
		$purchaseOrder = (!empty($blanketOrder->purchase_orders()->first()))?$blanketOrder->purchase_orders()->first()->addresses()->first():null;
		if ($purchaseOrder instanceof Address) {
			return $this->item($purchaseOrder, new AddressTransformer);
		}	
		return null; // or handle the error appropriately
	}
}