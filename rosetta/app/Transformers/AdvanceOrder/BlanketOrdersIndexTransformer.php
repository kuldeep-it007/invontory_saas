<?php
namespace Rosetta\Transformers\AdvanceOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Transformers\AdvanceOrder\AdvanceOrderSummaryTransformer;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\Distributor;
use Rosetta\Models\Dealer;

class BlanketOrdersIndexTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['pos','so_numbers','stock_points','si_numbers'];
	protected $defaultIncludes = ['pos','so_numbers','stock_points','si_numbers'];
	public function transform(AdvanceBlanketOrder $order)
	{
		$data = [
			"id"         => $order->id,
			"number"     => $order->order_number,
			"amount"     => $order->amount,
			"created_at" => $order->created_at->format('U'),
			"status"     => $order->status,
			"net_amount" => round($order->net_amount(), 2),//#812 net amount is amount without tax

		];

		if ($order->relatedOrder) {			
			$data['dealer_name'] = $order->relatedOrder->orderable->name;
			if((!empty($order->relatedOrder->orderable->type))){
				$data['dealer_type'] = $order->relatedOrder->orderable->type;
			}elseif (get_class($order->relatedOrder->orderable) == Distributor::class) {
				$data['dealer_type'] = 'Distributor';
			} elseif (get_class($order->relatedOrder->orderable) == Dealer::class) {
				$data['dealer_type'] = 'Dealer';
			} elseif (get_class($order->relatedOrder->orderable) == SuperStockist::class) {
				$data['dealer_type'] = 'Superstockist';
			}
		} else {
			$data['dealer_name'] = null;
			$data['dealer_type'] = null;
		}
		return $data;
	}
	public function includePos(AdvanceBlanketOrder $order)
	{
		return $this->collection($order->advance_orders,new AdvanceOrderSummaryTransformer);
	}
	public function includeSONumbers(AdvanceBlanketOrder $order)
	{
		return $this->item($order,function($o){
			return $o->advance_orders->pluck('navision_order_number')->toArray();
		});
	}
	public function includeSINumbers(AdvanceBlanketOrder $order)//#765
	{
		return $this->item($order,function($o){
			return $o->advance_orders->pluck('nav_invoice_number')->toArray();
		});
	}
	public function includeStockPoints(AdvanceBlanketOrder $order)//#763
	{
		return $this->item($order,function($o){
			$stockpoints = [];
			foreach($o->advance_orders as $po)
			{
				if ($po->stock_point) {
					$stockpoints[] = $po->stock_point->name;
				}else if ($po->super_stockist_id) {
					$stockpoints[] = SuperStockist::find($po->super_stockist_id)->load('stock_points')->name;
				} elseif ($po->distributor_id > 0) {
					$stockpoints[] = Distributor::find($po->distributor_id)->load('stock_points')->name;
				}
			}
			return collect($stockpoints)->unique()->values()->all();
		});
	}
}