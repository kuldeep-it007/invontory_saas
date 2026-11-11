<?php
namespace Rosetta\Transformers\Invoice;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Transformers\Invoice\DealerTransformer;
use Rosetta\Transformers\Invoice\PurchaseOrderTransformer;

class InvoiceListTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['so_numbers','stock_point', 'si_numbers'];
	protected $defaultIncludes = ['so_numbers','stock_point','si_numbers'];
	public function transform(BlanketOrder $blanketOrder)
	{
		$data = [
			"Distributor_BO_Id" => $blanketOrder->id,
			"Dealer_BO_Id" => $blanketOrder->relatedOrder->blanket_order->id,
			"ros_invoice_number" => $blanketOrder->relatedOrder->blanket_order->ros_invoice_number,
			"Dealer_order_id" => $blanketOrder->relatedOrder->id,
			"Dealer_po_number" => $blanketOrder->relatedOrder->blanket_order->order_number,
			"Dealer_order_amount" => $blanketOrder->relatedOrder->Amount,	
			"Dealer_po_printed" => $blanketOrder->relatedOrder->printed,
			"Dealer" => $blanketOrder->relatedOrder->orderable->name,
			"Dealer_order_date" => $blanketOrder->relatedOrder->created_at->format('U'),
			"status" => $blanketOrder->relatedOrder->blanket_order->status,
			"items" => $blanketOrder->purchase_lines()->count(),
		//	"relatedOrders" => $blanketOrder->relatedOrder,
		];
		return $data;
	}
	public function includeSONumbers(BlanketOrder $order)
	{
		return $this->item($order,function($o){
			return $o->purchase_orders->pluck('navision_order_number')->toArray();
		});
	}
	public function includeSINumbers(BlanketOrder $order)
	{
		return $this->item($order,function($o){
			return $o->purchase_orders->pluck('nav_invoice_number')->toArray();
		});
	}
	public function includeStockPoint(BlanketOrder $order)
	{
		return $this->item($order,function($o){
			return $o->purchase_orders->pluck('stock_point_id')->toArray();
		});
	}
/*	public function includeCustomerOrder(BlanketOrder $blanketOrder)//related Order OR Dealer Order which DUD placed with Distributor
	{
		return $this->item($blanketOrder->relatedOrder, new PurchaseOrderTransformer);
	}*/
}