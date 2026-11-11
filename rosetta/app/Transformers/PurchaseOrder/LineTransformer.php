<?php
namespace Rosetta\Transformers\PurchaseOrder;

use Carbon\Carbon;
use Carbon\addMonths;
use Rosetta\Models\Distributor;
use League\Fractal\TransformerAbstract;
use Rosetta\Models\PurchaseOrder\PurchaseLine;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Transformers\ReturnItem\ReturnItemTransformer;
use Rosetta\Transformers\Product\ItemCartProductTransformer;
use Rosetta\Transformers\StockPoint\IndexTransformer as StockPointIndexTransformer;
use Rosetta\Transformers\Distributors\CompactTransformer as DistributorCompactTransformer;
use Rosetta\Transformers\SuperStockists\CompactTransformer as SuperStockistCompactTransformer;


class LineTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['product', 'returnItems','stock_point'];
	protected $defaultIncludes = ['product'];
	protected $user_type, $loggedInUser;
	public function __construct($user_type = null, $loggedInUser=null)
	{
		$this->user_type = $user_type;
		$this->loggedInUser = $loggedInUser;
	}
	public function transform(PurchaseLine $line)
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
			"batch_no" => $line->batch_no, 
			"return_requested" => $line->returnItems()->sum('quantity'),
			"return_approved" => $line->approvedReturnItems()->sum('quantity'),
			"return_pending" => $line->pendingReturnItems()->sum('quantity'),
			"return_rejected" => $line->rejectedReturnItems()->sum('quantity'),
			"created_at" => $line->created_at->format('U'),
			"purchase_order_id" => $line->purchase_order_id,
		//	"navision_order_number" => $line->purchase_order->navision_order_number,
			"order_number" => $line->purchase_order->order_number,
			"order_status" => $line->purchase_order->status,
			"remaining_amount" => round($line->purchase_order->Amount - $line->purchase_order->paid_amount(), 2),
			"paid_amount" => $line->purchase_order->paid_amount(),
			"tax_amount" => $line->tax_amount, 
			"net_amount" => $line->net_amount, 
			"tax_type_code" => $line->tax_type_code, 
			"tax_percent" => $line->tax_percent, 
		//	"blanket_order_distribtuor_id" => $line->purchase_order->blanket_order->distributor_id,
		"product_id" => $line->product_id
		];
		$returnDuration = $line->purchase_order->created_at->addDays(config('rosetta.return_duration'));
		$today = Carbon::now()->startOfDay();
		if($returnDuration->greaterThanOrEqualTo($today))
		{
			$data['return_allowed'] = true;
		}
		else
			$data['return_allowed'] = false;
		if($line->purchase_order->stock_point)
			$data['stock_point_code'] = $line->purchase_order->stock_point->code;
		else
			$data['stock_point_code'] = null;
		$navision_order_number = $line->purchase_order->navision_order_number;
		if($navision_order_number == null)
		{
			if($line->purchase_order->relatedOrder)//DUD TO DIST ORDER
			{
				$linePurchaseOrder = $line->purchase_order->relatedOrder->purchase_orders->first();
				$navision_order_number = (!empty($linePurchaseOrder))?$linePurchaseOrder->navision_order_number:'';
			}
			if($navision_order_number == null)
			{
				if($line->purchase_order->relatedOrder)
				{
					$distPO = $line->purchase_order->relatedOrder->purchase_orders->first();
					if(!empty($distPO->relatedOrder))//DISTTO SS ORDER
					{
						$navision_order_number = (!empty($distPO->relatedOrder->purchase_orders->first()->navision_order_number))?$distPO->relatedOrder->purchase_orders->first()->navision_order_number:'';
					}
				}
			}
		}
		$data['navision_order_number'] = $navision_order_number;
		return $data;
	}
	public function includeStockPoint(PurchaseLine $line)
	{	
		if($line->purchase_order->ss_stock_point_id && $line->purchase_order->stock_point_id == null){
			$stockPoint = SuperStockist::find($line->purchase_order->ss_stock_point_id);
			$stockPoint->showStockPoint = 'yes';
			return $this->item($stockPoint, new SuperStockistCompactTransformer);
		} elseif($line->purchase_order->dist_stock_point_id && $line->purchase_order->stock_point_id == null){
			$stockPoint = Distributor::find($line->purchase_order->dist_stock_point_id);
			$stockPoint->showStockPoint = 'yes';
			return $this->item($stockPoint, new DistributorCompactTransformer);
		}elseif($line->purchase_order->super_stockist_id && $line->purchase_order->stock_point_id == null){
			$stockPoint = SuperStockist::find($line->purchase_order->super_stockist_id);
			$stockPoint->showStockPoint = 'yes';
			return $this->item($stockPoint, new SuperStockistCompactTransformer);
		}elseif($line->purchase_order->distributor_id && $line->purchase_order->stock_point_id == null){
			$stockPoint = Distributor::find($line->purchase_order->distributor_id);
			$stockPoint->showStockPoint = 'yes';
			return $this->item($stockPoint, new DistributorCompactTransformer);
		}else	
		if($line->purchase_order->stock_point)
		{
			return $this->item($line->purchase_order->stock_point, new StockPointIndexTransformer);
		}
		else 
		{
			if($line->purchase_order->relatedOrder)
			{
				if($line->purchase_order->relatedOrder->purchase_orders->first()->stock_point_id != null)
				{
					return $this->item($line->purchase_order->relatedOrder->purchase_orders->first()->stock_point, new StockPointIndexTransformer);
				}
				else
				{
					if($this->loggedInUser && $this->loggedInUser->dealer_id != null)
					{
						if($this->loggedInUser->dealer->distributor_id != null)
						{
							return $this->item(Distributor::find($line->purchase_order->blanket_order->distributor_id),new DistributorCompactTransformer);
						}
					} 
					else
					{
						if($line->purchase_order->relatedOrder)
						{
							if($line->purchase_order->relatedOrder->distributor_id)
								return $this->item(Distributor::find($line->purchase_order->relatedOrder->distributor_id),new DistributorCompactTransformer);
							$d = SuperStockist::find($line->purchase_order->relatedOrder->super_stockist_id);
							return $this->item($d,new SuperStockistCompactTransformer);
						}
					}
				}
			}
			else
			{
				if($line->purchase_order->blanket_order->distributor)
				{
					return $this->item($line->purchase_order->blanket_order->distributor,new DistributorCompactTransformer);
				}
				
				$d = SuperStockist::find($line->purchase_order->blanket_order->super_stockist_id);
				return $this->item($d,new SuperStockistCompactTransformer);
			}
		}
	}
	public function includeProduct(PurchaseLine $line)
	{
		$region = $line->purchase_order->orderable->company->region;
		if(!$line->product)
			return null;
		return $this->item($line->product,new ItemCartProductTransformer($region, $this->user_type));
	}
	public function includeReturnItems(PurchaseLine $line)
	{
		return $this->collection($line->returnItems,new ReturnItemTransformer);
	}
}