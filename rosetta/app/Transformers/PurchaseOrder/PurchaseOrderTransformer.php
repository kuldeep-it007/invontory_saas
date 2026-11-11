<?php
namespace Rosetta\Transformers\PurchaseOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Address;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Transformers\AddressTransformer;
use Rosetta\Transformers\Dealers\IndexTransformer as DealerTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer as DistributorTransformer;
use Rosetta\Transformers\PurchaseOrder\LineTransformer;
use Rosetta\Transformers\SuperStockists\IndexTransformer as SuperStockistTransformer;

class PurchaseOrderTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['purchase_lines', 'address', 'customer'];
	protected $defaultIncludes = ['address',];
	public function transform(PurchaseOrder $purchaseOrder)
	{
		$data = [
			"id" => $purchaseOrder->id,
			"number" => $purchaseOrder->order_number,
			"amount" => $purchaseOrder->Amount,
			"created_at" => $purchaseOrder->created_at->format('U'),
			"status" => $purchaseOrder->status,
			"so" => $purchaseOrder->navision_order_number,
		//	"customer_type" => get_class($purchaseOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER',
			"distributor_id" => $purchaseOrder->distributor_id,
			"remaining_amount" => round($purchaseOrder->Amount - $purchaseOrder->paid_amount(), 2),
			"paid_amount" => $purchaseOrder->paid_amount(),
			"invoice_id" => $purchaseOrder->invoice_id,
			"remarks" => $purchaseOrder->special_request,
			"stock_point_id" => $purchaseOrder->stock_point_id,
			"net_amount" => round($purchaseOrder->net_amount(), 2),
			"ss_stock_point_id" => $purchaseOrder->ss_stock_point_id,
			"dist_stock_point_id" => $purchaseOrder->dist_stock_point_id,
			"blanket_order_id" => $purchaseOrder->blanket_order_id,
		];
		if($purchaseOrder->cancelation_reason)
		{
			$data['cancelation_reason'] = $purchaseOrder->cancelation_reason;
			$canceled_by = User::find($purchaseOrder->canceled_by);
			$data['canceled_by'] = (!empty($canceled_by->email))?$canceled_by->email:null;
		}
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
	public function includePurchaseLines(PurchaseOrder $order)
	{
		return $this->collection($order->purchase_lines,new LineTransformer($order->orderable));
	}
	public function includeCustomer(PurchaseOrder $purchaseOrder)
	{
		/*$customer_type = get_class($purchaseOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
		if($customer_type == 'DISTRIBUTO')
			return $this->item($purchaseOrder->orderable, new DistributorTransformer);
		if($customer_type == 'DEALER')
			return $this->item($purchaseOrder->orderable, new DealerTransformer);
		if($customer_type == 'SUPER_STOCKIST')
			return $this->item($purchaseOrder->orderable, new SuperStockistTransformer);*/
		if(get_class($purchaseOrder->orderable) == Distributor::class)
			return $this->item($purchaseOrder->orderable, new DistributorTransformer);
		elseif(get_class($purchaseOrder->orderable) == Dealer::class)
			return $this->item($purchaseOrder->orderable, new DealerTransformer);
		elseif(get_class($purchaseOrder->orderable) == SuperStockist::class)
			return $this->item($purchaseOrder->orderable, new SuperStockistTransformer);
	}
	public function includeAddress(PurchaseOrder $purchaseOrder)
	{
		$purchaseOrderAddress = (!empty($purchaseOrder))?$purchaseOrder->addresses()->first():null;
		if ($purchaseOrderAddress instanceof Address) {
			return $this->item($purchaseOrderAddress, new AddressTransformer);
		}	
		return null; // or handle the error appropriately
	}
}