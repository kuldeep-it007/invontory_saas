<?php
namespace Rosetta\Transformers\PurchaseOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Address;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Transformers\AddressTransformer;
use Rosetta\Transformers\BillAddressTransformer;
use Rosetta\Transformers\Dealers\IndexTransformer as DealerTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer as DistributorTransformer;
use Rosetta\Transformers\PurchaseOrder\LineTransformer;
use Rosetta\Transformers\PurchaseOrder\PurchaseOrderTransformer;
use Rosetta\Transformers\SuperStockists\IndexTransformer as SuperStockistTransformer;
use Rosetta\Transformers\SuperStockists\IndexTransformer;

class BlanketOrderTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['purchase_orders','purchase_lines', 'customer','address'];
	protected $defaultIncludes = ['customer','address', 'purchase_lines', 'purchase_orders'];
	protected $loggedInUser;
	public function __construct(User $loggedInUser=null)
	{
		$this->loggedInUser = $loggedInUser;
	}
	public function transform(BlanketOrder $order)
	{
		$groupedArrayKeysForSsStockPoint = $order->purchase_orders->pluck('ss_stock_point_id')->unique()->implode(',');
        $groupedArrayKeysForDistStockPoint = $order->purchase_orders->pluck('dist_stock_point_id')->unique()->implode(',');

		$data = [
			"id" => $order->id,
			"number" => $order->order_number,
			"amount" => $order->amount,
			"created_at" => $order->created_at->format('U'),
			
		//	"customer_type" => get_class($order->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER',
			"distributor_id" => $order->distributor_id,
			"super_stockist_id" => $order->super_stockist_id,
			"remaining_amount" => round($order->amount - $order->paid_amount(), 2),
			"paid_amount" => $order->paid_amount(),
			"shipped" => (bool)$order->shipped,
			"all_company_lines_shipped" => $order->all_company_lines_shipped(),
			"all_codistributor_lines_shipped" => $order->all_codistributor_lines_shipped(),
			"all_co_super_stockists_lines_shipped" => $order->all_co_super_stockist_lines_shipped(),
			"invoice_id" => $order->invoice_id,
			"ros_invoice_number" => $order->ros_invoice_number,
			"nav_invoice_number" => $order->nav_invoice_numbers(),
			"remarks" => $order->remarks(),
			"orderable_id" => $order->orderable_id,
		];
		if (empty($groupedArrayKeysForSsStockPoint) && empty($groupedArrayKeysForDistStockPoint)) {
			$data['status'] = $order->status;
		}else{
			$data['status'] = $order->order_status;
		}
		
		if(get_class($order->orderable) == Distributor::class)
		{
			$data['customer_type'] = 'DISTRIBUTO';
		}
		elseif(get_class($order->orderable) == Dealer::class)
		{
			$data['customer_type'] = 'DEALER';
		}
		elseif(get_class($order->orderable) == SuperStockist::class)
		{
			$data['customer_type'] = 'SUPER_STOCKIST';
		}
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
			$data['relatedPO'] = $order->relatedOrder->id;
		}
		return $data;
	}
	public function includePurchaseOrders(BlanketOrder $order)
	{
		$purchaseOrders = $order->purchase_orders;
		return $this->collection($purchaseOrders,new PurchaseOrderTransformer($order->orderable, $this->loggedInUser));
	}
	public function includePurchaseLines(BlanketOrder $order)
	{
		if(!empty($this->loggedInUser->filter_purchase_lines) && ($this->loggedInUser->filter_purchase_lines==true))
			{
			if(!empty($this->loggedInUser->superStockist)){
				$superStockistData = $this->loggedInUser->superStockist;
				$superStockistId = $superStockistData->id;
				$purchaseLines = $order->purchase_lines->filter(function($po) use ($superStockistId){
					return $po->purchase_order->super_stockist_id == $superStockistId;
				});
			}elseif(!empty($this->loggedInUser->distributor)){
				$distributorData = $this->loggedInUser->distributor;
				$distributorId = $distributorData->id;
				$purchaseLines = $order->purchase_lines->filter(function($po) use ($distributorId){
					return $po->purchase_order->distributor_id == $distributorId;
				});
			}			
		}
		else
		{
			$purchaseLines = $order->purchase_lines;
		}
		return $this->collection($purchaseLines,new LineTransformer($order->orderable, $this->loggedInUser));
	}
	public function includeCustomer(BlanketOrder $order)
	{
	/*	$customer_type = get_class($order->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
		if($customer_type == 'DISTRIBUTO')
			return $this->item($order->orderable, new DistributorTransformer);
		if($customer_type == 'DEALER')
			return $this->item($order->orderable, new DealerTransformer);
		if($customer_type == 'SUPER_STOCKIST')
			return $this->item($order->orderable, new SuperStockistTransformer);*/
		if(get_class($order->orderable) == Distributor::class)
			return $this->item($order->orderable, new DistributorTransformer);
		elseif(get_class($order->orderable) == Dealer::class)
			return $this->item($order->orderable, new DealerTransformer);
		elseif(get_class($order->orderable) == SuperStockist::class)
			return $this->item($order->orderable, new SuperStockistTransformer);
	}
	public function includeAddress(BlanketOrder $order)
	{
		$purchaseOrder = (!empty($order->purchase_orders()->first()))?$order->purchase_orders()->first()->addresses()->first():null;
		if ($purchaseOrder instanceof Address) {
			return $this->item($purchaseOrder, new AddressTransformer);
		}	
		return null; // or handle the error appropriately
	}

	public function includeShippingAddress(BlanketOrder $order)
	{
		$purchaseOrder = (!empty($order->purchase_orders()->first()))?$order->purchase_orders()->first()->addresses()->first():null;
		if ($purchaseOrder instanceof Address) {
			return $this->item($purchaseOrder, new AddressTransformer);
		}	
		return null; // or handle the error appropriately
	}
	
	public function includeBillingAddress(BlanketOrder $order)
	{
		return $this->item($order->orderable, new BillAddressTransformer);
	}	
}