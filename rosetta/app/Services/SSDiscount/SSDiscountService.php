<?php
namespace Rosetta\Services\SSDiscount;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Rosetta\Exceptions\ActiveProductAttributes\RecordAlreadyExistsException;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Cart\CartItem;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
use Rosetta\Models\SSDiscount;
use Rosetta\Services\SSDiscount\SSDiscountServiceInterface;

class SSDiscountService implements SSDiscountServiceInterface
{
	public function create(Array $params)
	{
		$discount = new SSDiscount;
		$discount->discount = $params['discount'];
		$discount->customer_type = 'DISTRIBUTO';
		$discount->super_stockist_id = $params['super_stockist_id'];

		if(array_has($params, 'category_code'))
			$category = $params['category_code'];
		else
			$category = null;
		if(array_has($params, 'collection_code'))
			$collection = $params['collection_code'];
		else
			$collection = null;
		if(array_has($params, 'colour_code'))
			$colour = $params['colour_code'];
		else
			$colour = null;
		if(array_has($params, 'customer_id'))
		{
			$query = SSDiscount::where('category_code', '=', $category)
						->where('collection_code', '=', $collection)
						->where('colour_code', '=', $colour)
						->where('super_stockist_id', '=', $params['super_stockist_id'])
						->where('customer_id', '=', $params['customer_id'])
						->first();
			if($query == null)
			{
				$discount->customer_id = $params['customer_id'];
				$discount->category_code = $category;
				$discount->collection_code = $collection;
				$discount->colour_code = $colour;
				$discount->save();
				if($discount)//#731
				{
					$this->resetCartBlockedItemDiscount($discount);
				}
				return $discount;
			}
			else
	        {
	            throw new RecordAlreadyExistsException;
	        }
		}
		else
		{
			$query = SSDiscount::where('category_code', '=', $category)
						->where('collection_code', '=', $collection)
						->where('colour_code', '=', $colour)
						->where('super_stockist_id', '=', $params['super_stockist_id'])
						->whereNull('customer_id')
						->first();
			if($query == null)
			{
				$discount->category_code = $category;
				$discount->collection_code = $collection;
				$discount->colour_code = $colour;
				$discount->save();
				if($discount)//#731
				{
					$this->resetCartBlockedItemDiscount($discount);
				}
				return $discount;
			}
			else
	        {
	            throw new RecordAlreadyExistsException;
	        }
		}
	}
	public function delete($id)
	{
		$discount = SSDiscount::find($id);
		$disc = $discount;
		if(!$discount)
			throw New ModelNotFoundException;
		$discount->delete();
		$this->resetCartBlockedItemDiscount($disc);
	}
	public function update(Array $params, int $id)
	{
		$discount = SSDiscount::find($id);
		if(!$discount)
			throw New ModelNotFoundException;
		$discount->discount = $params['discount'];
		$discount->save();
		if($discount)//#731
		{
			$this->resetCartBlockedItemDiscount($discount);
		}
		return $discount;
	}
	public function listSSDiscount(Array $params)
	{
		if(array_has($params,'page'))
		{
			$discount = SSDiscount::where('super_stockist_id', '=', $params['super_stockist_id'])->when($params, function ($query) use ($params){
				if(array_has($params, 'customer_type'))
				{
					$query->where('customer_type', '=', $params['customer_type']);
				}
				if(array_has($params, 'customer_id'))
				{
					$query->where('customer_id', '=', $params['customer_id']);
				}
				if(array_has($params, 'category'))
				{
					$query->where('category_code', '=', $params['category']);
				}
				if(array_has($params, 'collection'))
				{
					$query->where('collection_code', '=', $params['collection']);
				}
				if(array_has($params, 'colour'))
				{
					$query->where('colour_code', '=', $params['colour']);
				}
			})->orderBy('customer_id','asc')->orderBy('category_code','asc')->orderBy('collection_code','asc')
			->orderBy('colour_code','asc')->orderBy('discount','asc')->paginate($params['per_page']);
		}
		else
		{
			$discount = SSDiscount::where('super_stockist_id', '=', $params['super_stockist_id'])->when($params, function ($query) use ($params){
				if(array_has($params, 'customer_type'))
				{
					$query->where('customer_type', '=', $params['customer_type']);
				}
				if(array_has($params, 'customer_id'))
				{
					$query->where('customer_id', '=', $params['customer_id']);
				}
				if(array_has($params, 'category_code'))
				{
					$query->where('category_code', '=', $params['category_code']);
				}
				if(array_has($params, 'collection_code'))
				{
					$query->where('collection_code', '=', $params['collection_code']);
				}
				if(array_has($params, 'colour_code'))
				{
					$query->where('colour_code', '=', $params['colour_code']);
				}
			})->orderBy('customer_id','asc')->orderBy('category_code','asc')->orderBy('collection_code','asc')
			->orderBy('colour_code','asc')->orderBy('discount','asc')->get();
		}
		return $discount;
	}
	public function getSSDiscount($customer_type, $customer, $product, $super_stockist_id)
	{
		$discount = 
			(
				SSDiscount::where('super_stockist_id', '=', $super_stockist_id)
					->where('customer_type', '=', $customer_type)
					->where('customer_id', '=', $customer->id)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->where('colour_code', '=', $product->colour_code)
					->limit(1)
			)->union(
				SSDiscount::where('super_stockist_id', '=', $super_stockist_id)
				->where('customer_type', '=', $customer_type)
				->where('customer_id', '=', $customer->id)
				->where('category_code', '=', $product->category_code)
				->where('collection_code', '=', $product->collection_code)
				->whereNull('colour_code')
				->limit(1)
			)->union(
				SSDiscount::where('super_stockist_id', '=', $super_stockist_id)
				->where('customer_type', '=', $customer_type)
				->where('customer_id', '=', $customer->id)
				->where('category_code', '=', $product->category_code)
				->whereNull('colour_code')
				->whereNull('collection_code')
				->limit(1)
			)->union(
				SSDiscount::where('super_stockist_id', '=', $super_stockist_id)
				->where('customer_type', '=', $customer_type)
				->where('customer_id', '=', $customer->id)
				->whereNull('category_code')
				->whereNull('collection_code')
				->whereNull('colour_code')
				->limit(1)
			)->union(
				SSDiscount::where('super_stockist_id', '=', $super_stockist_id)
				->where('customer_type', '=', $customer_type)
				->whereNull('customer_id')
				->where('category_code', '=', $product->category_code)
				->where('collection_code', '=', $product->collection_code)
				->where('colour_code', '=', $product->colour_code)
				->limit(1)
			)->union(
				SSDiscount::where('super_stockist_id', '=', $super_stockist_id)
				->where('customer_type', '=', $customer_type)
				->whereNull('customer_id')
				->where('category_code', '=', $product->category_code)
				->where('collection_code', '=', $product->collection_code)
				->whereNull('colour_code')
				->limit(1)
			)->union(
				SSDiscount::where('super_stockist_id', '=', $super_stockist_id)
				->where('customer_type', '=', $customer_type)
				->whereNull('customer_id')
				->where('category_code', '=', $product->category_code)
				->whereNull('collection_code')
				->whereNull('colour_code')
				->limit(1)
			)->union(
				SSDiscount::where('super_stockist_id', '=', $super_stockist_id)
				->where('customer_type', '=', $customer_type)
				->whereNull('customer_id')
				->whereNull('category_code')
				->whereNull('collection_code')
				->whereNull('colour_code')
				->limit(1)
			)->limit(1)->get();
		if($discount->first())
			return $discount->first()->discount;
		return 0;
	}
	public function resetCartBlockedItemDiscount($discount)//#686 Dist blocking SS stock - replenish purpose
	{
		$discountService = resolve('Rosetta\Services\Discount\DiscountsServiceInterface');
		
		$products = [];
		if($discount->category_code != null)
		{
			$productsQuery = Product::where('category_code','=',$discount->category_code);
			if($discount->collection_code != null)
			{
				$productsQuery->where('collection_code','=',$discount->collection_code);
				if($discount->colour_code != null)
				{
					$productsQuery->where('colour_code','=',$discount->colour_code);
				}
			}
			$products = $productsQuery->get();
			foreach($products as $product)
			{
				$cart_items = CartItem::where('product_id', '=', $product->id)->where('itemable_type','=','Rosetta\Models\Distributor')->get();//all items irrespective of Stock Point
				foreach($cart_items as $cartItem)
				{
					if($cartItem->distributor->super_stockist_id == $discount->super_stockist_id)
					{
						$valid_discount = $this->getSSDiscount('DISTRIBUTO', $cartItem->distributor, $product,$cartItem->distributor->super_stockist_id);
					//	var_dump($cartItem->dealer->name, $valid_discount, $cartItem->dealer->distributor_id);
						if($valid_discount == 0)
						{
							$valid_discount = round($discountService->getDiscount('DISTRIBUTO',$cartItem->distributor,$product),2);
						}//#803
						$cartItem->discount = $valid_discount;
						$cartItem->save();
					}
				}

				$blocked_items = BlockedItem::where('product_id', '=', $product->id)->where('blockable_type','=','Rosetta\Models\Distributor')->active()->get();//all items irrespective of Stock Point
				foreach($blocked_items as $blockedItem)
				{
					if($blockedItem->distributor->super_stockist_id == $discount->super_stockist_id)
					{
						$valid_discount = $this->getSSDiscount('DEALER', $blockedItem->distributor, $product,$blockedItem->distributor->super_stockist_id);
						if($valid_discount == 0)
						{
							$valid_discount = round($discountService->getDiscount('DISTRIBUTO',$blockedItem->distributor,$product),2);
						}//#803
						$blockedItem->discount = $valid_discount;
						$blockedItem->save();
					}
				}
			}
		}
		else
		{
			$cart_items = CartItem::where('itemable_type','=','Rosetta\Models\Distributor')->get();
			foreach($cart_items as $cartItem)
			{
				if($cartItem->distributor->super_stockist_id == $discount->super_stockist_id)
				{
					$product = Product::find($cartItem->product_id);
					$valid_discount = $this->getSSDiscount('DISTRIBUTO', $cartItem->distributor, $product,$cartItem->distributor->super_stockist_id);
					//var_dump($cartItem->dealer->name, $valid_discount, $cartItem->dealer->distributor_id);
					if($valid_discount == 0)
					{
						$valid_discount = round($discountService->getDiscount('DEALER',$cartItem->distributor,$product),2);
					}//#803
					$cartItem->discount = $valid_discount;
					$cartItem->save();
				}
			}
			$blocked_items = BlockedItem::where('blockable_type','=','Rosetta\Models\Dealer')->active()->get();//all items irrespective of Stock Point
			foreach($blocked_items as $blockedItem)
			{
				if($blockedItem->dealer->distributor_id == $discount->distributor_id)
				{
					$product = Product::find($blockedItem->product_id);
					$valid_discount = $this->getSSDiscount('DEALER', $blockedItem->dealer, $product,$blockedItem->dealer->distributor_id);
					if($valid_discount == 0)
					{
						$valid_discount = round($discountService->getDiscount('DEALER',$blockedItem->dealer,$product),2);
					}//#803
					$blockedItem->discount = $valid_discount;
					$blockedItem->save();
				}
			}
		}
	}
}