<?php

namespace Rosetta\Services\Discount;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Rosetta\Models\AdvanceOrder\AdvanceItem;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Cart\CartItem;
use Rosetta\Models\Dealer;
use Rosetta\Models\Discount;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
use Rosetta\Models\Region;

class DiscountsService implements DiscountsServiceInterface
{
	protected $customerDiscountService;
	public function __construct()
	{
		$this->customerDiscountService = resolve('Rosetta\Services\CustomerDiscount\CustomerDiscountServiceInterface');
	}
	public function create(array $params)
	{
		$region = Region::find($params['region_id']);
		$query = $region->discounts()
			->where('customer_type', '=', $params['customer_type']);
		if (array_has($params, 'customer_id'))
			$query->where('customer_id', '=', $params['customer_id']);
		else
			$query->whereNull('customer_id');
		if (array_has($params, 'state_code'))
			$query->where('state_code', '=', $params['state_code']);
		else
			$query->whereNull('state_code');
		if (array_has($params, 'city_code'))
			$query->where('city_code', '=', $params['city_code']);
		else
			$query->whereNull('city_code');
		if (array_has($params, 'post_code'))
			$query->where('post_code', '=', $params['post_code']);
		else
			$query->whereNull('post_code');

		if (array_has($params, 'category_code')) {
			$query->where('category_code', '=', $params['category_code']);
			if (array_has($params, 'collection_code')) {
				$query->where('collection_code', '=', $params['collection_code']);
				if (array_has($params, 'colour_code')) {
					$query->where('colour_code', '=', $params['colour_code']);
				} else {
					$query->whereNull('colour_code');
				}
			} else {
				$query->whereNull('collection_code');
			}
		} else {
			$query->whereNull('category_code');
		}
		$discount = $query->first();
		if ($discount) {
			$discount->discount = (float)$params['discount'];
		} else {
			$discount = new Discount;
			$discount->region_id = $params['region_id'];
			$discount->discount = $params['discount'];
			$discount->customer_type = $params['customer_type'];
			if (array_has($params, 'customer_id'))
				$discount->customer_id = $params['customer_id'];
			if (array_has($params, 'category_code'))
				$discount->category_code = $params['category_code'];
			if (array_has($params, 'collection_code'))
				$discount->collection_code = $params['collection_code'];
			if (array_has($params, 'colour_code'))
				$discount->colour_code = $params['colour_code'];
			if (array_has($params, 'state_code'))
				$discount->state_code = $params['state_code'];
			if (array_has($params, 'city_code'))
				$discount->city_code = $params['city_code'];
			if (array_has($params, 'post_code'))
				$discount->post_code = $params['post_code'];
		}
		if ($params['customer_type'] == "SUPER_STOCKIST_DISTRIBUTOR" && array_has($params, 'customer_id')) {
			$distributor = Distributor::find($params['customer_id']);
			$discount->super_stockist_id = (!empty($distributor->super_stockist_id)) ? $distributor->super_stockist_id : null;
		}

		if ($params['customer_type'] == "SUPER_STOCKIST_DEALER" && array_has($params, 'customer_id')) {
			$dealer = Dealer::find($params['customer_id']);
			$discount->distributor_id = (!empty($dealer->distributor->id)) ? $dealer->distributor->id : null;
			$discount->super_stockist_id = (!empty($dealer->distributor->superStockist->id)) ? $dealer->distributor->superStockist->id : null;
		}
		$discount->save();
		if ($discount) //#686
		{
			$this->resetCartItemDiscount($params, $discount->discount);
			$this->resetBlockedItemDiscount($params, $discount->discount);
			$this->resetAdvanceItemDiscount($params, $discount->discount);
		}
		return $discount;
	}
	public function resetCartItemDiscount(array $params, $discount) //#686
	{
		$products = [];
		if (array_has($params, 'category_code')) {
			$productsQuery = Product::where('category_code', '=', $params['category_code']);
			if (array_has($params, 'collection_code')) {
				$productsQuery->where('collection_code', '=', $params['collection_code']);
				if (array_has($params, 'colour_code')) {
					$productsQuery->where('colour_code', '=', $params['colour_code']);
				}
			}
			$products = $productsQuery->get();
			foreach ($products as $product) {
				$cart_items = CartItem::where('product_id', '=', $product->id)->whereNotNull('stock_point_id')->get();
				foreach ($cart_items as $cartItem) {
					if ($params['customer_type'] == 'DISTRIBUTO') {
						if ((!empty($cartItem->itemable)) && get_class($cartItem->itemable) == Distributor::class) {
							if (array_has($params, 'customer_id')) //customer id always gets preference
							{
								if ($params['customer_id'] == $cartItem->distributor->id) {
									$customer = Distributor::find($params['customer_id']);
									$this->updateCartItem($cartItem, $params['customer_type'], $customer, $product);
								}
							} else //customer id not specified
							{
								$customer = $cartItem->distributor;
								$this->updateCartItem($cartItem, $params['customer_type'], $customer, $product);
							}
						}
					} elseif ($params['customer_type'] == 'DEALER') {
						if (!empty($cartItem->itemable) && get_class($cartItem->itemable) == Dealer::class) {
							if ($cartItem->dealer->distributor_id == null) {
								if (array_has($params, 'customer_id')) {
									if ($params['customer_id'] == $cartItem->dealer->id) {
										$customer = Dealer::find($params['customer_id']);
										$this->updateCartItem($cartItem, $params['customer_type'], $customer, $product);
									}
								} else {
									$customer = $cartItem->dealer;
									$this->updateCartItem($cartItem, $params['customer_type'], $customer, $product);
								}
							} elseif ($cartItem->dealer->distributor_id != null) //#803
							{
								$customer = $cartItem->dealer;
								if ($cartItem->discount == 0) {
									$this->updateCartItem($cartItem, $params['customer_type'], $customer, $product);
								} else {
									$customer_discount = round($this->customerDiscountService->getCustomerDiscount($params['customer_type'], $cartItem->dealer, $product, $cartItem->dealer->distributor_id), 2);
									if ($customer_discount == 0) {
										$this->updateCartItem($cartItem, $params['customer_type'], $customer, $product);
									} //#803
								}
							}
						}
					}
				}
			}
		} else //Category not provided so Disc applicable to all prods in cart where Cust params match 
		{
			$cart_items = CartItem::whereNotNull('stock_point_id')->get();
			foreach ($cart_items as $cartItem) {
				$product = Product::find($cartItem->product_id);
				if ($params['customer_type'] == 'DISTRIBUTO') {
					if (!empty($cartItem->itemable) && get_class($cartItem->itemable) == Distributor::class) {
						if (array_has($params, 'customer_id')) {
							if ($params['customer_id'] == $cartItem->distributor->id) {
								$customer = Distributor::find($params['customer_id']);
								$this->updateCartItem($cartItem, $params['customer_type'], $customer, $product);
							}
						} else {
							$customer = $cartItem->distributor;
							$this->updateCartItem($cartItem, $params['customer_type'], $customer, $product);
						}
					}
				} elseif ($params['customer_type'] == 'DEALER') {
					if (!empty($cartItem->itemable) && get_class($cartItem->itemable) == Dealer::class) //if($cartItem->dealer)
					{
						if ($cartItem->dealer->distributor_id == null) //not DUD
						{
							if (array_has($params, 'customer_id')) {
								if ($params['customer_id'] == $cartItem->dealer->id) {
									$customer = Dealer::find($params['customer_id']);
									$this->updateCartItem($cartItem, $params['customer_type'], $customer, $product);
								}
							} else {
								$customer = $cartItem->dealer;
								$this->updateCartItem($cartItem, $params['customer_type'], $customer, $product);
							}
						} elseif ($cartItem->dealer->distributor_id != null) //#803
						{
							$customer = $cartItem->dealer;
							if ($cartItem->discount == 0) {
								$this->updateCartItem($cartItem, $params['customer_type'], $customer, $product);
							} else {
								$customer_discount = round($this->customerDiscountService->getCustomerDiscount($params['customer_type'], $cartItem->dealer, $product, $cartItem->dealer->distributor_id), 2);
								if ($customer_discount == 0) {
									$this->updateCartItem($cartItem, $params['customer_type'], $customer, $product);
								} //#803
							}
						}
					}
				}
			}
		}
	}
	public function resetBlockedItemDiscount(array $params, $discount) //#686
	{
		$products = [];
		if (array_has($params, 'category_code')) {
			$productsQuery = Product::where('category_code', '=', $params['category_code']);
			if (array_has($params, 'collection_code')) {
				$productsQuery->where('collection_code', '=', $params['collection_code']);
				if (array_has($params, 'colour_code')) {
					$productsQuery->where('colour_code', '=', $params['colour_code']);
				}
			}
			$products = $productsQuery->get();
			foreach ($products as $product) {
				$blocked_items = BlockedItem::where('product_id', '=', $product->id)->whereNotNull('stock_point_id')->active()->get();
				foreach ($blocked_items as $blockedItem) {
					if ($params['customer_type'] == 'DISTRIBUTO') {
						if (get_class($blockedItem->blockable) == Distributor::class) {
							if (array_has($params, 'customer_id')) //customer id always gets preference
							{
								if ($params['customer_id'] == $blockedItem->distributor->id) {
									$customer = Distributor::find($params['customer_id']);
									$this->updateBlockedItem($blockedItem, $params['customer_type'], $customer, $product);
								}
							} else //customer id not specified
							{
								$customer = $blockedItem->distributor;
								$this->updateBlockedItem($blockedItem, $params['customer_type'], $customer, $product);
							}
						}
					} elseif ($params['customer_type'] == 'DEALER') {
						if (get_class($blockedItem->blockable) == Dealer::class) {
							if ($blockedItem->dealer->distributor_id == null) {
								if (array_has($params, 'customer_id')) {
									if ($params['customer_id'] == $blockedItem->dealer->id) {
										$customer = Dealer::find($params['customer_id']);
										$this->updateBlockedItem($blockedItem, $params['customer_type'], $customer, $product);
									}
								} else {
									$customer = $blockedItem->dealer;
									$this->updateBlockedItem($blockedItem, $params['customer_type'], $customer, $product);
								}
							} elseif ($blockedItem->dealer->distributor_id != null) //#803
							{
								$customer = $blockedItem->dealer;
								if ($blockedItem->discount == 0) {
									$this->updateBlockedItem($blockedItem, $params['customer_type'], $customer, $product);
								} else {
									$customer_discount = round($this->customerDiscountService->getCustomerDiscount($params['customer_type'], $blockedItem->dealer, $product, $blockedItem->dealer->distributor_id), 2);
									if ($customer_discount == 0) {
										$this->updateBlockedItem($blockedItem, $params['customer_type'], $customer, $product);
									} //#803
								}
							}
						}
					}
				}
			}
		} else //Category not provided so Disc applicable to all prods in cart where Cust params match 
		{
			$blocked_items = BlockedItem::whereNotNull('stock_point_id')->active()->get();
			foreach ($blocked_items as $blockedItem) {
				$product = Product::find($blockedItem->product_id);
				if ($params['customer_type'] == 'DISTRIBUTO') {
					if (get_class($blockedItem->blockable) == Distributor::class) {
						if (array_has($params, 'customer_id')) {
							if ($params['customer_id'] == $blockedItem->distributor->id) {
								$customer = Distributor::find($params['customer_id']);
								$this->updateBlockedItem($blockedItem, $params['customer_type'], $customer, $product);
							}
						} else {
							$customer = $blockedItem->distributor;
							$this->updateBlockedItem($blockedItem, $params['customer_type'], $customer, $product);
						}
					}
				} elseif ($params['customer_type'] == 'DEALER') {
					if (get_class($blockedItem->blockable) == Dealer::class) //if($blockedItem->dealer)
					{
						if ($blockedItem->dealer->distributor_id == null) //not DUD
						{
							if (array_has($params, 'customer_id')) {
								if ($params['customer_id'] == $blockedItem->dealer->id) {
									$customer = Dealer::find($params['customer_id']);
									$this->updateBlockedItem($blockedItem, $params['customer_type'], $customer, $product);
								}
							} else {
								$customer = $blockedItem->dealer;
								$this->updateBlockedItem($blockedItem, $params['customer_type'], $customer, $product);
							}
						} elseif ($blockedItem->dealer->distributor_id != null) //#803
						{
							$customer = $blockedItem->dealer;
							if ($blockedItem->discount == 0) {
								$this->updateBlockedItem($blockedItem, $params['customer_type'], $customer, $product);
							} else {
								$customer_discount = round($this->customerDiscountService->getCustomerDiscount($params['customer_type'], $blockedItem->dealer, $product, $blockedItem->dealer->distributor_id), 2);
								if ($customer_discount == 0) {
									$this->updateBlockedItem($blockedItem, $params['customer_type'], $customer, $product);
								} //#803
							}
						}
					}
				}
			}
		}
	}
	public function resetAdvanceItemDiscount(array $params, $discount) //#686
	{
		$products = [];
		if (array_has($params, 'category_code')) {
			$productsQuery = Product::where('category_code', '=', $params['category_code']);
			if (array_has($params, 'collection_code')) {
				$productsQuery->where('collection_code', '=', $params['collection_code']);
				if (array_has($params, 'colour_code')) {
					$productsQuery->where('colour_code', '=', $params['colour_code']);
				}
			}
			$products = $productsQuery->get();
			foreach ($products as $product) {
				$advance_items = AdvanceItem::where('product_id', '=', $product->id)->get();
				foreach ($advance_items as $advanceItem) {
					if ($params['customer_type'] == 'DISTRIBUTO') {
						if (get_class($advanceItem->advanceable) == Distributor::class) {
							if (array_has($params, 'customer_id')) //customer id always gets preference
							{
								if ($params['customer_id'] == $advanceItem->distributor->id) {
									$customer = Distributor::find($params['customer_id']);
									$this->updateAdvanceItem($advanceItem, $params['customer_type'], $customer, $product);
								}
							} else //customer id not specified
							{
								$customer = $advanceItem->distributor;
								$this->updateAdvanceItem($advanceItem, $params['customer_type'], $customer, $product);
							}
						}
					}
				}
			}
		} else //Category not provided so Disc applicable to all prods in cart where Cust params match 
		{
			$advance_items = AdvanceItem::all();
			foreach ($advance_items as $advanceItem) {
				$product = Product::find($advanceItem->product_id);
				if ($params['customer_type'] == 'DISTRIBUTO') {
					if (get_class($advanceItem->advanceable) == Distributor::class) {
						if (array_has($params, 'customer_id')) {
							if ($params['customer_id'] == $advanceItem->distributor->id) {
								$customer = Distributor::find($params['customer_id']);
								$this->updateAdvanceItem($advanceItem, $params['customer_type'], $customer, $product);
							}
						} else {
							$customer = $advanceItem->distributor;
							$this->updateAdvanceItem($advanceItem, $params['customer_type'], $customer, $product);
						}
					}
				}
			}
		}
	} //803 not applicable here as AO is not allowed for DUD
	public function updateAdvanceItem($advanceItem, $customer_type, $customer, $product)
	{
		$valid_discount = $this->getDiscount($customer_type, $customer, $product);
		$advanceItem->discount = $valid_discount;
		$new_discounted_price = $advanceItem->unit_price - ($advanceItem->unit_price * $valid_discount / 100);
		$advanceItem->discounted_price = $new_discounted_price;
		$new_total_price = $new_discounted_price * $advanceItem->order_quantity;
		$advanceItem->total_price = $new_total_price;
		$newTaxAmount = ($new_discounted_price * $advanceItem->tax_percent / 100) * $advanceItem->order_quantity;
		$advanceItem->tax_amount = $newTaxAmount;
		$advanceItem->net_amount = $new_total_price + $newTaxAmount;
		$advanceItem->save();
	}
	public function updateBlockedItem($blockedItem, $customer_type, $customer, $product)
	{
		$valid_discount = $this->getDiscount($customer_type, $customer, $product);
		$blockedItem->discount = $valid_discount;
		$blockedItem->save();
	}
	public function updateCartItem($cartItem, $customer_type, $customer, $product)
	{
		$valid_discount = $this->getDiscount($customer_type, $customer, $product);
		$cartItem->discount = $valid_discount;
		$cartItem->save();
	}
	public function delete($id)
	{
		$discount = Discount::find($id);
		$disc = $discount;
		if (!$discount)
			throw new ModelNotFoundException;
		$discount->delete();
		if ($disc->category_code)
			$params['category'] = $disc->category_code;
		if ($disc->collection_code)
			$params['collection'] = $disc->collection_code;
		if ($disc->colour_code)
			$params['colour'] = $disc->colour_code;
		if ($disc->customer_type)
			$params['customer_type'] = $disc->customer_type;
		if ($disc->customer_id)
			$params['customer_id'] = $disc->customer_id;
		$this->resetCartItemDiscount($params, $disc->discount); //#730
		$this->resetBlockedItemDiscount($params, $disc->discount); //#730
		$this->resetAdvanceItemDiscount($params, $disc->discount); //#730
	}
	public function update(array $params, int $id)
	{
		//not being used, since edit discount nnot provided for superadmin UI, udpate is done to add modal itself.
		$discount = Discount::find($id);
		if (!$discount)
			throw new ModelNotFoundException;
		$discount->discount = $params['discount'];
		$discount->save();
		return $discount;
	}
	public function listDiscounts(array $params)
	{
		if (array_has($params, 'page')) {
			$discounts = Discount::when($params, function ($query) use ($params) {
				if (array_has($params, 'region')) {
					$query->where('region_id', '=', $params['region']);
				}
				if (array_has($params, 'customer_type')) {
					$query->where('customer_type', '=', $params['customer_type']);
				}
				if (array_has($params, 'customer_id')) {
					$query->where('customer_id', '=', $params['customer_id']);
				}
				if (array_has($params, 'category')) {
					$query->where('category_code', '=', $params['category']);
				}
				if (array_has($params, 'collection')) {
					$query->where('collection_code', '=', $params['collection']);
				}
				if (array_has($params, 'colour')) {
					$query->where('colour_code', '=', $params['colour']);
				}
				if (array_has($params, 'state')) {
					$query->where('state_code', '=', $params['state']);
				}
				if (array_has($params, 'city')) {
					$query->where('city_code', '=', $params['city']);
				}
				if (array_has($params, 'post_code')) {
					$query->where('post_code', '=', $params['post_code']);
				}
			})->orderBy('id', 'desc')->paginate($params['per_page']);
		} else {
			$discounts = Discount::when($params, function ($query) use ($params) {
				if (array_has($params, 'region_id')) {
					$query->where('region_id', '=', $params['region_id']);
				}
				if (array_has($params, 'customer_type')) {
					$query->where('customer_type', '=', $params['customer_type']);
				}
				if (array_has($params, 'customer_id')) {
					$query->where('customer_id', '=', $params['customer_id']);
				}
				if (array_has($params, 'category_code')) {
					$query->where('category_code', '=', $params['category_code']);
				}
				if (array_has($params, 'collection_code')) {
					$query->where('collection_code', '=', $params['collection_code']);
				}
				if (array_has($params, 'colour_code')) {
					$query->where('colour_code', '=', $params['colour_code']);
				}
				if (array_has($params, 'state_code')) {
					$query->where('state_code', '=', $params['state_code']);
				}
				if (array_has($params, 'city_code')) {
					$query->where('city_code', '=', $params['city_code']);
				}
				if (array_has($params, 'post_code')) {
					$query->where('post_code', '=', $params['post_code']);
				}
			})->orderBy('id', 'desc')->get();
		}
		return $discounts;
	}
	public function getDiscount($customer_type, $customer, $product)
	{
		$region_id = $customer->company()->get()->first()->region_id;
		$discount =
			(
				Discount::where('customer_type', '=', $customer_type)
				->where('customer_id', '=', $customer->id)
				->where('category_code', '=', $product->category_code)
				->where('collection_code', '=', $product->collection_code)
				->where('colour_code', '=', $product->colour_code)
				->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('customer_id', '=', $customer->id)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->whereNull('colour_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('customer_id', '=', $customer->id)
					->where('category_code', '=', $product->category_code)
					->whereNull('colour_code')
					->whereNull('collection_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('customer_id', '=', $customer->id)
					->whereNull('category_code')
					->whereNull('collection_code')
					->whereNull('colour_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('post_code', '=', $customer->post_code)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->where('colour_code', '=', $product->colour_code)
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('post_code', '=', $customer->post_code)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->whereNull('colour_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('post_code', '=', $customer->post_code)
					->where('category_code', '=', $product->category_code)
					->whereNull('collection_code')
					->whereNull('colour_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('post_code', '=', $customer->post_code)
					->whereNull('category_code')
					->whereNull('collection_code')
					->whereNull('colour_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('city_code', '=', $customer->city_code)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->where('colour_code', '=', $product->colour_code)
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('city_code', '=', $customer->city_code)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->whereNull('colour_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('city_code', '=', $customer->city_code)
					->where('category_code', '=', $product->category_code)
					->whereNull('collection_code')
					->whereNull('colour_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('city_code', '=', $customer->city_code)
					->whereNull('category_code')
					->whereNull('collection_code')
					->whereNull('colour_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('state_code', '=', $customer->state_code)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->where('colour_code', '=', $product->colour_code)
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('state_code', '=', $customer->state_code)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->whereNull('colour_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('state_code', '=', $customer->state_code)
					->where('category_code', '=', $product->category_code)
					->whereNull('collection_code')
					->whereNull('colour_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('state_code', '=', $customer->state_code)
					->whereNull('category_code')
					->whereNull('collection_code')
					->whereNull('colour_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('region_id', '=', $region_id)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->where('colour_code', '=', $product->colour_code)
					->whereNull('customer_id')
					->whereNull('state_code')
					->whereNull('city_code')
					->whereNull('post_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('region_id', '=', $region_id)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->whereNull('customer_id')
					->whereNull('colour_code')
					->whereNull('state_code')
					->whereNull('city_code')
					->whereNull('post_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('region_id', '=', $region_id)
					->where('category_code', '=', $product->category_code)
					->whereNull('customer_id')
					->whereNull('collection_code')
					->whereNull('colour_code')
					->whereNull('state_code')
					->whereNull('city_code')
					->whereNull('post_code')
					->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
					->where('region_id', '=', $region_id)
					->whereNull('customer_id')
					->whereNull('category_code')
					->whereNull('collection_code')
					->whereNull('colour_code')
					->whereNull('state_code')
					->whereNull('city_code')
					->whereNull('post_code')
					->limit(1)
			)->limit(1)->get();
		if ($discount->first())
			return $discount->first()->discount;
		return 0;
	}
}
