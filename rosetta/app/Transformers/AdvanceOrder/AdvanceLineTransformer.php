<?php
namespace Rosetta\Transformers\AdvanceOrder;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\AdvanceOrder\AdvanceLine;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Transformers\Product\ItemCartProductTransformer;
use Rosetta\Transformers\StockPoint\IndexTransformer as StockPointIndexTransformer;
use Rosetta\Transformers\Distributors\CompactTransformer as DistributorCompactTransformer;
use Rosetta\Transformers\SuperStockists\CompactTransformer as SuperStockistCompactTransformer;

class AdvanceLineTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['product','stock_point'];
	protected $user_type, $loggedInUser;
	public function __construct($user_type = null, $loggedInUser=null)
	{
		$this->user_type = $user_type;
		$this->loggedInUser = $loggedInUser;
	}
	public function transform(AdvanceLine $line)
	{
		$data = [
			"id" => $line->id,
			"quantity" => $line->quantity,
			"shipped_quantity" => $line->shipped_quantity, 
			"received_quantity" => $line->received_quantity, 
			"unit_price" => $line->unit_price, 
			"discount" => $line->discount, 
			"rate_type" => $line->rate_type, 
			"amount" => $line->amount, 
			"tax_amount" => $line->tax_amount, 
			"net_amount" => $line->net_amount, 
			"tax_type_code" => $line->tax_type_code, 
			"tax_percent" => $line->tax_percent, 
			"batch_no" => $line->batch_no, 
			"eta" => $line->eta,
			"advance_order_id" => $line->advance_order_id,
			"nav_po_no" => $line->nav_po_no,
			"nav_intransit_po" => $line->nav_intransit_po,
			"batch_no" => $line->batch_no,
			"order_status" => $line->advance_order->status,
		];
		if($line->advance_order->stock_point)
			$data['stock_point_code'] = $line->advance_order->stock_point->code;
		else
			$data['stock_point_code'] = null;
		$navision_order_number = $line->advance_order->navision_order_number;
		if($navision_order_number == null)
		{
			if($line->advance_order->relatedOrder)
			{
				$navision_order_number = $line->advance_order->relatedOrder->advance_orders->first()->navision_order_number;
			}
		}
		$data['navision_order_number'] = $navision_order_number;
		return $data;
	}
	public function includeStockPoint(AdvanceLine $line)
	{
		if($line->advance_order->stock_point)
		{
			return $this->item($line->advance_order->stock_point, new StockPointIndexTransformer);
		}
		else 
		{
			if($line->advance_order->relatedOrder)
			{
				if($line->advance_order->relatedOrder->advance_orders->first()->stock_point_id != null)
				{
					return $this->item($line->advance_order->relatedOrder->advance_orders->first()->stock_point, new StockPointIndexTransformer);
				}
				else
				{
					if($this->loggedInUser->dealer_id != null)
					{
						if($this->loggedInUser->dealer->distributor_id != null)
						{
							return $this->item(Distributor::find($line->advance_order->advance_blanket_order->distributor_id),new DistributorCompactTransformer);
						}
					} 
					else 
					/** Add code include superstokist stock Start */
					if(!empty($line->advance_order->relatedOrder->super_stockist_id)){
						return $this->item(SuperStockist::find($line->advance_order->relatedOrder->super_stockist_id),new SuperStockistCompactTransformer);
					}
					/** Add code include superstokist stock End */
					else{
							return $this->item(Distributor::find($line->advance_order->relatedOrder->distributor_id),new DistributorCompactTransformer);
					}
				}
			}
			else
			{
				if($line->advance_order->advance_blanket_order->distributor_id)
				{
					return $this->item(Distributor::find($line->advance_order->advance_blanket_order->distributor_id),new DistributorCompactTransformer);
				}
			}
		}
	}
	public function includeProduct(AdvanceLine $line)
	{
		return $this->item($line->product,new ItemCartProductTransformer(null, $this->user_type));
	}
}