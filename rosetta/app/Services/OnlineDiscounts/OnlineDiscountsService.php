<?php
namespace Rosetta\Services\OnlineDiscounts;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rosetta\Exceptions\OnlineDiscounts\DiscountExistsException;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Cart\CartItem;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\OnlineDiscount;
use Rosetta\Models\Product\Product;
use Rosetta\Models\User;
use Rosetta\Services\OnlineDiscounts\OnlineDiscountsServiceInterface;

class OnlineDiscountsService implements OnlineDiscountsServiceInterface
{
    public function getAllForCompany(Company $company, Array $params = [])
    {
        $query = $company->onlineDiscounts();
        if(array_has($params,'category'))
    	{
    		$query->where('category_code','=',$params['category']);
    	}
    	if(array_has($params,'collection'))
    	{
    		$query->where('collection_code','=',$params['collection']);
    	}
    	if(array_has($params,'colour'))
    	{
    		$query->where('colour_code','=',$params['colour']);
        }
        if(array_has($params,'page'))
		{
			return $query->paginate($params['per_page'],['*'],'',$params['page']);
        }
        return $query->get();
	}
	
	public function delete(User $user, Company $company,int $discountID)
	{
		$discount = $company->onlineDiscounts()->find($discountID);
		$disc = $discount;
		if(!$discount)
		{
			throw new ModelNotFoundException();
		}
		$discount->delete();
		if($disc->category_code)
			$params['category'] = $disc->category_code;
		if($disc->collection_code)
			$params['collection'] = $disc->collection_code;
		if($disc->colour_code)
			$params['colour'] = $disc->colour_code;
		$this->resetCartBlockedItemDiscount($params, $company);
	}

	public function create(User $user, Company $company,Array $params)
	{
		$query = $company->onlineDiscounts();
		if(array_has($params,'category'))
		{
			$query->where('category_code','=',$params['category']);
			if(array_has($params,'collection'))
			{
				$query->where('collection_code','=',$params['collection']);
				if(array_has($params,'colour'))
				{
					$query->where('colour_code','=',$params['colour']);
				}
				else
				{
					$query->whereNull('colour_code');
				}
			}
			else
			{
				$query->whereNull('collection_code');
			}
		}
		else
		{
			$query->whereNull('category_code');
		}

		if($query->first())
		{
			throw new DiscountExistsException();
		}

		$discount = new OnlineDiscount;
		$discount->company_id = $company->id;
		$discount->discount = (float)$params['discount'];
		if(array_has($params,'category'))
		{
			$discount->category_code = $params['category'];
		}

		if(array_has($params,'collection'))
		{
			$discount->collection_code = $params['collection'];
		}

		if(array_has($params,'colour'))
		{
			$discount->colour_code = $params['colour'];
		}
		$company->onlineDiscounts()->save($discount);
		if($discount)//#729
		{
			$this->resetCartBlockedItemDiscount($params, $company);
		}
		return $discount;
	}
	public function update(User $user, Company $company,int $discountID,Array $params)
	{
		$discount = $company->onlineDiscounts()->find($discountID);
		if(!$discount)
		{
			throw new ModelNotFoundException();
		}
	/*	$query = $company->onlineDiscounts()
			->where('id','<>',$discount->id);
		if(array_has($params,'category'))
		{
			$query->where('category_code','=',$params['category']);
			if(array_has($params,'collection'))
			{
				$query->where('collection_code','=',$params['collection']);
				if(array_has($params,'colour'))
				{
					$query->where('colour_code','=',$params['colour']);
				}
				else
				{
					$query->whereNull('colour_code');
				}
			}
			else
			{
				$query->whereNull('collection_code');
			}
		}
		else
		{
			$query->whereNull('category_code');
		}
		if($query->first())
		{
			throw new DiscountExistsException();
		}*/
		$discount->discount = (float)$params['discount'];
		/*if(array_has($params,'category'))
		{
			$discount->category_code = $params['category'];
		}

		if(array_has($params,'collection'))
		{
			$discount->collection_code = $params['collection'];
		}

		if(array_has($params,'colour'))
		{
			$discount->colour_code = $params['colour'];
		}*/
		$discount->save();
		if($discount)//#729
		{
			$this->resetCartBlockedItemDiscount($params, $company);
		}
		return $discount;
	}

	public function getDiscount(Company $company, Product $product): float
	{
		$discount = 
			(
				OnlineDiscount::where('company_id', '=', $company->id)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->where('colour_code', '=', $product->colour_code)
					->limit(1)
			)->union(
				OnlineDiscount::where('company_id', '=', $company->id)
				->where('category_code', '=', $product->category_code)
				->where('collection_code', '=', $product->collection_code)
				->whereNull('colour_code')
				->limit(1)
			)->union(
				OnlineDiscount::where('company_id', '=', $company->id)
				->where('category_code', '=', $product->category_code)
				->whereNull('colour_code')
				->whereNull('collection_code')
				->limit(1)
			)->union(
				OnlineDiscount::where('company_id', '=', $company->id)
				->whereNull('category_code')
				->whereNull('collection_code')
				->whereNull('colour_code')
				->limit(1)
			);
		if($discount->first())
			return $discount->first()->discount;
		return 0;
	}
	public function resetCartBlockedItemDiscount(Array $params, Company $company)//#686
	{
		$products = [];
		if(array_has($params, 'category') && $params['category'] != null)
		{
			$productsQuery = Product::where('category_code','=',$params['category']);
			if(array_has($params, 'collection') && $params['collection'] != null)
			{
				$productsQuery->where('collection_code','=',$params['collection']);
				if(array_has($params, 'colour') && $params['colour'] != null)
				{
					$productsQuery->where('colour_code','=',$params['colour']);
				}
			}
			$products = $productsQuery->get();
			foreach($products as $product)
			{
				$valid_discount = $this->getDiscount($company, $product);
				$cart_items = CartItem::where('product_id', '=', $product->id)->whereNotNull('stock_point_id')->get();//items purchased from Company Stock
				foreach($cart_items as $cartItem)
				{
					if(get_class($cartItem->itemable) == Distributor::class)
					{
						$cartItem->onlineDiscount = $valid_discount;
						$cartItem->save();
					}
					if(get_class($cartItem->itemable) == Dealer::class)
					{
						if($cartItem->dealer->distributor_id == null)//not DUD
						{
							$cartItem->onlineDiscount = $valid_discount;
							$cartItem->save();
						}
					}
				}

				$blocked_items = BlockedItem::where('product_id', '=', $product->id)->whereNotNull('stock_point_id')->active()->get();//items blocked from Company Stock
				foreach($blocked_items as $blockedItem)
				{
					if(get_class($blockedItem->blockable) == Distributor::class)
					{
						$blockedItem->onlineDiscount = $valid_discount;
						$blockedItem->save();
					}
					if(get_class($blockedItem->blockable) == Dealer::class)
					{
						if($blockedItem->dealer->distributor_id == null)//not DUD
						{
							$blockedItem->onlineDiscount = $valid_discount;
							$blockedItem->save();
						}
					}
				}

			}
		}
	}
}