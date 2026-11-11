<?php
namespace Rosetta\Transformers\CartItem;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Cart\CartItem;
use Rosetta\Models\Product\Product;
use Rosetta\Transformers\ProductTax\ProductTaxTransformer;
use Rosetta\Transformers\Product\ItemCartProductTransformer;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\Distributor;

class CartItemTransformer extends TransformerAbstract
{
	protected $defaultIncludes = ['product','tax'];
	protected $availableIncludes = ['category','collection','colour','tax'];
	protected $user_type;
	public function __construct($user_type = null)
	{
		$this->user_type = $user_type;
	}
	public function transform(CartItem $cartItem)
	{
		$data = [
			"id" => $cartItem->id,
			"product_id" => $cartItem->product_id,
			"batch_no" => $cartItem->batch_no,
			"order_quantity" => $cartItem->order_quantity,
			//"stock" => $cartItem->stock,
			"rate_type" => $cartItem->rate_type,
			"blocked" => $cartItem->blockedQuantity,
			//"available_quantity" => $cartItem->stock - 	$cartItem->blockedQuantity,
			"unit_price" => $cartItem->unit_price,
			"discount" => $cartItem->discount,
			"onlineDiscount" => $cartItem->onlineDiscount,
			"stock_point_id" => $cartItem->stock_point_id,
		//	"stock_point_name" => $cartItem->stock_point->name,
			"distributor_id" => $cartItem->distributor_id,
			"super_stockist_id" => $cartItem->super_stockist_id,
		];

		
		if($cartItem->stock_point){
			$data['stock_point_name'] = $cartItem->stock_point->name;
		}elseif($cartItem->super_stockist_id>0){
			$stockPointData = SuperStockist::find($cartItem->super_stockist_id)->load('stock_points');
			$data['stock_point_name'] = (!empty($stockPointData->display_name))?$stockPointData->display_name:$stockPointData->name;
		}elseif($cartItem->distributor_id>0){
			$stockPointData = Distributor::find($cartItem->distributor_id)->load('stock_points');
			$data['stock_point_name'] = (!empty($stockPointData->display_name))?$stockPointData->display_name:$stockPointData->name;
		}else{
			$data['stock_point_name'] = null;
		}			
		return $data;
	}
	public function includeProduct(CartItem $cartItem)
	{

		$region = $cartItem->itemable->company->region;
		return $this->item($cartItem->product, new ItemCartProductTransformer($region, $this->user_type));
	}
	public function includeTax(CartItem $cartItem)
	{
		$region = $cartItem->itemable->company->region;
		$category = $cartItem->product->category;
		$collection = $cartItem->product->collection;
		if($category)
		{
			$tax = $region->taxes()->where('category_code','=',$category->code)->where('collection_code','=',$collection->code)->first();
			if(!$tax)
			{
				$tax = $region->taxes()->where('category_code','=',$category->code)->first();
			}
			return $this->item($tax, new ProductTaxTransformer());
		}
	}
}