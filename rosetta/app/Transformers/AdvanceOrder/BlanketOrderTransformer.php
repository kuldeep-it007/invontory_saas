<?php
namespace Rosetta\Transformers\AdvanceOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Address;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Models\AdvanceOrder\AdvanceOrder;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Transformers\AddressTransformer;
use Rosetta\Transformers\AdvanceOrder\AdvanceLineTransformer;
use Rosetta\Transformers\AdvanceOrder\AdvanceOrderTransformer;
use Rosetta\Transformers\Dealers\IndexTransformer as DealerTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer as DistributorTransformer;
use Rosetta\Transformers\SuperStockists\IndexTransformer as SuperStockistTransformer;

class BlanketOrderTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['advance_orders','advance_lines', 'customer','address'];
	protected $defaultIncludes = ['customer','address'];
	protected $loggedInUser;
	public function __construct(User $loggedInUser=null)
	{
		$this->loggedInUser = $loggedInUser;
	}
	public function transform(AdvanceBlanketOrder $order)
	{
		$data = [
			"id" => $order->id,
			"number" => $order->order_number,
			"amount" => $order->amount,
			"created_at" => $order->created_at->format('U'),
			"status" => $order->status,
		//	"customer_type" => get_class($order->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER',			
			"remaining_amount" => round($order->amount - $order->paid_amount(), 2),
			"paid_amount" => $order->paid_amount(),
			"shipped" => (bool)$order->shipped,
			"all_company_lines_shipped" => $order->all_company_lines_shipped(),
			"invoice_id" => $order->invoice_id,
			"ros_invoice_number" => $order->ros_invoice_number,
			"nav_invoice_number" => $order->nav_invoice_numbers(),
			"remarks" => $order->remarks(),
		];
		if(get_class($order->orderable) == Distributor::class)
			$data['customer_type'] = 'DISTRIBUTO';
			$data['distributor_id'] = $order->distributor_id;
		if(get_class($order->orderable) == Dealer::class)
			$data['customer_type'] = 'DEALER';
		if(get_class($order->orderable) == SuperStockist::class)
			$data['customer_type'] = 'SUPER_STOCKIST';
			$data['super_stockist_id'] = $order->super_stockist_id;
		if($order->cancelation_reason)
		{
			$data['cancelation_reason'] = $order->cancelation_reason;
			if($order->canceled_by)
			{
				$canceled_by = User::find($order->canceled_by);
				$data['canceled_by'] = $canceled_by->email;
			}
		}
		if($order->relatedOrder)
		{
			$data['relatedAO'] = $order->relatedOrder->id;
		}
		return $data;
	}
	public function includeAdvanceOrders(AdvanceBlanketOrder $order)
	{
		return $this->collection($order->advance_orders,new AdvanceOrderTransformer($order->orderable));
	}
	public function includeAdvanceLines(AdvanceBlanketOrder $order)
	{
		return $this->collection($order->advance_lines,new AdvanceLineTransformer($order->orderable, $this->loggedInUser));
	}
	public function includeCustomer(AdvanceBlanketOrder $order)
	{
	/*	$customer_type = get_class($order->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
		if($customer_type == 'DISTRIBUTO')
			return $this->item($order->orderable, new DistributorTransformer);
		if($customer_type == 'DEALER')
			return $this->item($order->orderable, new DealerTransformer);*/
		if(get_class($order->orderable) == Distributor::class)
			return $this->item($order->orderable, new DistributorTransformer);
		if(get_class($order->orderable) == Dealer::class)
			return $this->item($order->orderable, new DealerTransformer);
		if(get_class($order->orderable) == SuperStockist::class)
			return $this->item($order->orderable, new SuperStockistTransformer);
	}
	public function includeAddress(AdvanceBlanketOrder $order)
	{
		$purchaseOrder = (!empty($order->advance_orders()->first()))?$order->advance_orders()->first()->addresses()->first():null;
		if ($purchaseOrder instanceof Address) {
			return $this->item($purchaseOrder, new AddressTransformer);
		}	
		return null; // or handle the error appropriately
	}
}