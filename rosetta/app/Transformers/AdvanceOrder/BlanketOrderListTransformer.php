<?php
namespace Rosetta\Transformers\AdvanceOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Transformers\Dealers\CompactTransformer as DealerCompactTransformer;
use Rosetta\Transformers\Distributors\CompactTransformer as DistributorCompactTransformer;
use Rosetta\Transformers\SuperStockists\CompactTransformer as SuperStockistCompactTransformer;

class BlanketOrderListTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['customer','so_numbers','si_numbers','stock_points'];
	protected $defaultIncludes = ['customer','so_numbers','si_numbers','stock_points'];
	public function transform(AdvanceBlanketOrder $blanketOrder)
	{
		$data = [
			"id" => $blanketOrder->id,
			"number" => $blanketOrder->order_number,
			"amount" => $blanketOrder->amount,
			"paid_amount" => $blanketOrder->paid_amount(),//#766
			"payment_status" => $blanketOrder->payment_status(),//#766
			"created_at" => $blanketOrder->created_at->format('U'),
			"status" => $blanketOrder->status,
			"items" => $blanketOrder->advance_lines()->count(),
			"customer" => $blanketOrder->orderable,
		//	"customer_type" => get_class($blanketOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER',
			"distributor_id" => $blanketOrder->distributor_id,
			"on_behalf" => $blanketOrder->on_behalf,
			"source" => $blanketOrder->source,
			"net_amount" => round($blanketOrder->net_amount(), 2),//#812 net amount is amount without tax
		];
		if(get_class($blanketOrder->orderable) == Distributor::class)
			$data['customer_type'] = 'DISTRIBUTO';
		if(get_class($blanketOrder->orderable) == Dealer::class)
			$data['customer_type'] = 'DEALER';
		if(get_class($blanketOrder->orderable) == SuperStockist::class)
			$data['customer_type'] = 'SUPER_STOCKIST';
		if($blanketOrder->relatedOrder)
		{
			$data['dealer_name'] = $blanketOrder->relatedOrder->orderable->name;
			$data['dealer_type'] = $blanketOrder->relatedOrder->orderable->type;
		}
		else
		{
			$data['dealer_name'] = null;
			$data['dealer_type'] = null;
		}
		$current_tenant_name = $blanketOrder->company->getSettingsForType('current_tenant')->get('tenant_name');
		$data['tenant_name'] = $current_tenant_name;//#778

		return $data;
	}
	public function includeCustomer(AdvanceBlanketOrder $blanketOrder)
	{
	/*	$customer_type = get_class($blanketOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
		if($customer_type == 'DISTRIBUTO')
			return $this->item($blanketOrder->orderable, new DistributorCompactTransformer);
		if($customer_type == 'DEALER')
			return $this->item($blanketOrder->orderable, new DealerCompactTransformer);*/
		if(get_class($blanketOrder->orderable) == Distributor::class)
			return $this->item($blanketOrder->orderable, new DistributorCompactTransformer);
		if(get_class($blanketOrder->orderable) == Dealer::class)
			return $this->item($blanketOrder->orderable, new DealerCompactTransformer);
		if(get_class($blanketOrder->orderable) == SuperStockist::class)
			return $this->item($blanketOrder->orderable, new SuperStockistCompactTransformer);
	}
	public function includeSONumbers(AdvanceBlanketOrder $order)
	{
		return $this->item($order,function($o){
			return $o->advance_orders->pluck('navision_order_number')->toArray();
		});
	}
	public function includeSINumbers(AdvanceBlanketOrder $order)//#704
	{
		return $this->item($order,function($o){
			return $o->advance_orders->pluck('nav_invoice_number')->toArray();
		});
	}
	public function includeStockPoints(AdvanceBlanketOrder $order)//#762
	{
		return $this->item($order,function($o){
			$stockpoints = [];
			foreach($o->advance_orders as $po)
			{
				if ($po->super_stockist_id) {
					$stockpoints[] = SuperStockist::find($po->super_stockist_id)->load('stock_points')->name;
				} elseif ($po->distributor_id > 0) {
					$stockpoints[] = Distributor::find($po->distributor_id)->load('stock_points')->name;
				} else if ($po->stock_point) {
					$stockpoints[] = $po->stock_point->name;
				}
			}
			return collect($stockpoints)->unique()->values()->all();
		});
	}
}