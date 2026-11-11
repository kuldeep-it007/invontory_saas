<?php
namespace Rosetta\Services\CartItems;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Ramsey\Uuid\Uuid;
use Rosetta\Events\Notifications;
use Rosetta\Exceptions\Order\BlockItemDeniedException;
use Rosetta\Exceptions\Order\InsufficientQuantityToBlockException;
use Rosetta\Models\AdvanceOrder\AdvanceItem;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Cart\CartItem;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Distributor;
use Rosetta\Models\Notification;
use Rosetta\Models\Product\Product;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Services\AdvanceOrders\AdvanceOrdersServiceInterface;
use Rosetta\Services\CartItems\CartItemsServiceInterface;
use Rosetta\Services\Depot\DepotItemsServiceInterface;
use Rosetta\Services\Inventory\InventoryServiceInterface;
use Rosetta\Services\NAV\ILEServiceInterface;

class CartItemsService implements CartItemsServiceInterface
{
    protected $inventoryService, $ILEService, $depotService, $advanceOrdersService;
	public function __construct(InventoryServiceInterface $inventoryService, ILEServiceInterface $ILEService, DepotItemsServiceInterface $depotService, AdvanceOrdersServiceInterface $advanceOrdersService)
	{
        $this->inventoryService = $inventoryService;
        $this->ILEService = $ILEService;
        $this->depotService = $depotService;
        $this->advanceOrdersService = $advanceOrdersService;
	}
	public function create(Array $params, $customer)
	{
		$cartItems = $params['cart_items'];
		foreach ($cartItems as $cartItem)
		{
			if(!array_has($cartItem,'blocked_id'))//Add To Cart
			{
				$item = new CartItem;
				$item->product_id = $cartItem['product_id'];
				$item->batch_no = $cartItem['batch_no'];
				$item->order_quantity = $cartItem['order_quantity'];
				$item->rate_type = $cartItem['rate_type'];
				$item->unit_price = $cartItem['unit_price'];
				$item->discount = $cartItem['discount'];
				if(array_has($cartItem,'onlineDiscount'))
					$item->onlineDiscount = $cartItem['onlineDiscount'];
				if(array_has($cartItem, 'stock_point_source'))
				{
					if($cartItem['stock_point_source'] == 'StockPoint')
					{
						if(array_has($cartItem, 'stock_point_code'))
						{
							$stockPoint = StockPoint::where('code', '=', $cartItem['stock_point_code'])->first();
							$item->stock_point_id = $stockPoint->id;
						}
					}
					else
					{
						if(array_has($cartItem,'distributor_id') && ($cartItem['stock_point_source'] == 'Distributor')){
							$item->distributor_id = $cartItem['distributor_id'];
						}
						if(array_has($cartItem,'super_stockist_id') && ($cartItem['stock_point_source'] == 'Super Stockist')){
							$item->super_stockist_id = $cartItem['super_stockist_id'];
						}							
					}
				}
				$items[] = $item;
			}
			else//Move To Cart from Blocked Items
			{
				$unBlockItem = $customer->blockedItems()->where('id', '=', $cartItem['blocked_id'])->first();
				$item = new CartItem;
				$item->product_id = $unBlockItem['product_id'];
				$item->batch_no = $unBlockItem['batch_no'];
				$item->order_quantity = $unBlockItem['order_quantity'];
				$item->rate_type = $unBlockItem['rate_type'];
				$item->unit_price = $unBlockItem['unit_price'];
				$item->discount = $unBlockItem['discount'];
				$item->stock_point_id = $unBlockItem['stock_point_id'];
			//	if(array_has($unBlockItem,'distributor_id'))
			//		$item->distributor_id = $unBlockItem['distributor_id'];
				if(array_has($unBlockItem,'super_stockist_id'))
					$item->super_stockist_id = $unBlockItem['super_stockist_id'];
				if(array_has($unBlockItem,'onlineDiscount'))
					$item->onlineDiscount = $unBlockItem['onlineDiscount'];
				$items[] = $item;
				if(array_has($unBlockItem,'UUID'))
				{
					$UUId = $unBlockItem['UUID'];
					$this->depotService->unBlock($UUId, $customer->company);
				}
				$unBlockItem->delete();
			}
		}
		$customer->cartItems()->saveMany($items);

		return $customer->cartItems;
	}
	public function update(Array $params, $customer)
	{
		$cartItems = $params['cart_items'];
		foreach ($cartItems as $cartItem)
		{
			$item = $customer->cartItems()->find($cartItem['id']);
			$item->order_quantity = $cartItem['order_quantity'];
			$item->save();
		}
	}
	public function get($customer)
	{
		return $cartItems = $customer->cartItems()->get();
	}
	public function getBlockedItems($customer)
	{
		return $blockedItems = $customer->blockedItems()->active()->get();
	}
	public function block(Array $params, $customer)
	{
		$blockItems = $params['block_items'];
		//$UUId = Uuid::uuid4();
		foreach ($blockItems as $blockItem)
		{
			$UUId = Uuid::uuid4();
			if(array_has($blockItem,'cart_id'))//Items from Cart are blocked
			{
				$companyId = $customer->company->id;
				$cartItem = $customer->cartItems()->find($blockItem['cart_id']);
				$productId = $cartItem->product_id;

				$current_time = Carbon::now()->format('U');
				$already_blocked_items = $customer->blockedItems()
												  ->where('product_id','=',$productId)
												  ->where('cool_down_start_time','<',$current_time)
												  ->where('cool_down_end_time','>',$current_time)
												  ->count();

				
				if($already_blocked_items > 0)
				{
					$e = new BlockItemDeniedException;
					$e->cool_down_end_time = $customer->blockedItems()->where('product_id','=',$productId)
												  ->where('cool_down_start_time','<',$current_time)
												  ->where('cool_down_end_time','>',$current_time)
												  ->latest()->value('cool_down_end_time');
					throw $e;
				}

				if($cartItem->super_stockist_id == null)
				{
					
				/*	$ledgerEntries = $this->inventoryService->getLedgerEntries(Auth::user(),$companyId,$productId, $location_code);
			        $ledgerEntries = $this->inventoryService->calculateStockAgainstBatch($productId,$ledgerEntries,$customer, $companyId, "BlockFromCart");			        

					foreach ($ledgerEntries as $ile)//post items are added to cart and if the stock in Company changes and the Qty that was added to cart now becomes more than available quantity
					{
						$lot_no = $ile->getLot_No();
						if($lot_no ==  $cartItem->batch_no)
						{
							$remaining_quantity = (float)$ile->getRemaining_Quantity();
							if($remaining_quantity < $cartItem->order_quantity)
							{
								throw new InsufficientQuantityToBlockException; 
							}
						}
					}
				*///commented as part of #414
			
					//added as part of #414
					$stock_point_id = $cartItem->stock_point_id;
					$location_code = StockPoint::where('id', '=', $stock_point_id)->first()->code;
					$product = Product::find($productId);
					$remaining_quantity = round((float)$this->ILEService->getBatchSpecificStock($customer->company,$product->nav_id,$cartItem->batch_no,true, $location_code),2);//added as part of #414
					if($remaining_quantity < $cartItem->order_quantity)
					{
						throw new InsufficientQuantityToBlockException; 
					}
				}
				else if($cartItem->super_stockist_id != null)
				{
					$superStockist = SuperStockist::find($cartItem->super_stockist_id);

					$superStockistStock = $superStockist->stocks
											->where('product_id', '=', $cartItem->product_id)
											->where('batch_no', '=', $cartItem->batch_no)
											->first();

					if(!$superStockistStock || $superStockistStock->quantity < $cartItem->order_quantity)
					{
						throw new InsufficientQuantityToBlockException;
					}
				}
				$item = new BlockedItem;
				$item->product_id = $cartItem->product_id;//['product_id'];
				$item->batch_no = $cartItem->batch_no;
				$item->order_quantity = $cartItem->order_quantity;
				$item->rate_type = $cartItem->rate_type;
				$item->unit_price = $cartItem->unit_price;
				$item->discount = $cartItem->discount;
				$item->onlineDiscount = $cartItem->onlineDiscount;
				$item->stock_point_id = $cartItem->stock_point_id;
				$item->UUId = $UUId;
				if(array_has($cartItem, 'distributor_id') && $cartItem['distributor_id'] != null)
					$item->distributor_id = $cartItem->distributor_id;
				if(array_has($cartItem, 'super_stockist_id') && $cartItem['super_stockist_id'] != null)
					$item->super_stockist_id = $cartItem->super_stockist_id;
				$item->expires_at = Carbon::now()->addSeconds(config('rosetta.blocked_duration'));
				$item->cool_down_start_time = Carbon::now()->addSeconds(config('rosetta.cool_down_start_time'));//#820
				$item->cool_down_end_time = Carbon::now()->addSeconds(config('rosetta.cool_down_end_time'));//#820
				$item->company()->associate($customer->company);
				$items[] = $item;	
				$cartItem->delete();
			}
			else//Items from available list are blocked
			{
				$current_time = Carbon::now()->format('U');
				$already_blocked_items = $customer->blockedItems()
												  ->where('product_id','=',$blockItem['product_id'])
												  ->where('cool_down_start_time','<',$current_time)
												  ->where('cool_down_end_time','>',$current_time)
												  ->count();

				
				if($already_blocked_items > 0)
				{
					$e = new BlockItemDeniedException;
					$e->cool_down_end_time = $customer->blockedItems()
												->where('product_id','=',$blockItem['product_id'])
												->where('cool_down_start_time','<',$current_time)
												->where('cool_down_end_time','>',$current_time)
												->latest()->value('cool_down_end_time');
					throw $e;
				}
				$item = new BlockedItem;
				$item->product_id = $blockItem['product_id'];
				$item->batch_no = $blockItem['batch_no'];
				$item->order_quantity = $blockItem['order_quantity'];
				$item->rate_type = $blockItem['rate_type'];
				$item->unit_price = $blockItem['unit_price'];
				$item->discount = $blockItem['discount'];
				$item->expires_at = Carbon::now()->addSeconds(config('rosetta.blocked_duration'));
				$item->cool_down_start_time = Carbon::now()->addSeconds(config('rosetta.cool_down_start_time'));//#820
				$item->cool_down_end_time = Carbon::now()->addSeconds(config('rosetta.cool_down_end_time'));//#820
			//	var_dump($item->expires_at, $item->cool_down_till);
			//	exit(0);
				$item->company()->associate($customer->company);
				if(array_has($blockItem,'onlineDiscount'))
					$item->onlineDiscount = $blockItem['onlineDiscount'];
				if(array_has($blockItem, 'stock_point_source'))
				{
					if($blockItem['stock_point_source'] == 'StockPoint')
					{
						if(array_has($blockItem, 'stock_point_code'))
						{
							$stockPoint = StockPoint::where('code', '=', $blockItem['stock_point_code'])->first();
							$item->stock_point_id = $stockPoint->id;
							$item->UUId = $UUId;
						}
					}
					else
					{
						if(array_has($blockItem,'distributor_id') && $blockItem['distributor_id'] != null && ($blockItem['stock_point_source'] == 'Distributor')){
							$item->distributor_id = $blockItem['distributor_id'];
						}
						if(array_has($blockItem,'super_stockist_id') && $blockItem['super_stockist_id'] != null && ($blockItem['stock_point_source'] == 'Super Stockist')){
							$item->super_stockist_id = $blockItem['super_stockist_id'];
						}	
					}
				}
				$items[] = $item;
			}
		}
		$customer->blockedItems()->saveMany($items);
		
		foreach($items as $item)
		{
		//	if($item->distributor_id == null && $item->super_stockist_id == null)//if co-distributor and co-super-stockist stock is blocked no need to block on other Tenants cause Co-distributors/Co-super-stockists will not be on different Tenants
			if($item->distributor_id == null && $item->super_stockist_id == null)
			{
				$current_tenant = $customer->company->getSettingsForType('current_tenant')->get('tenant_code');
				$location_code = StockPoint::where('id', '=', $item->stock_point_id)->first()->code;
				$product = Product::find($item->product_id);
				$depotItem=[];
				$depotItem['batch_no'] = $item->batch_no;
				$depotItem['order_quantity'] = $item->order_quantity;
				$depotItem['rate_type'] = $item->rate_type;
				$depotItem['unit_price'] = $item->unit_price;
				$depotItem['discount'] = $item->discount;
				$depotItem['expires_at'] = $item->expires_at;
				$depotItem['cool_down_start_time'] = $item->cool_down_start_time;
				$depotItem['cool_down_end_time'] = $item->cool_down_end_time;
				$depotItem['product_nav_id'] = $product->nav_id;//product_id (row_id) may be different on different Tenants based on availability of Products on each Tenant so pass nav_id
				$depotItem['stock_point_code'] = $location_code;
				$depotItem['company_name'] = $customer->company->name;
			//	$depotItem['comment'] = 'Blocked on ROS';
				$depotItem['comment'] = 'Blocked on ' . $current_tenant. ' by Customer : ' . $customer->name. ' ('. $customer->nav_id. ')';


			//	$depotItem['distributor_id'] = null;//Disributor on one Tenant will/might not be present on other Tenants
				$depotItem['super_stockist_id'] = null;//Super Stockist on one Tenant will/might not be present on other Tenants
				$company = $customer->company;
				$depotItem['UUId'] = $item->UUId;
				$tenants = $customer->company->getSettingsForType('tenants')->get('tenant_codes');//
				if($tenants != null)
					$this->depotService->block($depotItem, $company);
			}
		}
		return $customer->blockedItems()->active()->get();
	}
	public function unBlock(Array $params, $customer)
	{
	//	$customer->blockedItems()->whereIn('id', $params['blocked_Ids'])->delete();
		$blockedItems = $customer->blockedItems()->whereIn('id', $params['blocked_Ids'])->get();
		foreach($blockedItems as $blockedItem)
		{
			$UUId = $blockedItem->UUID;
			$company = $customer->company;
			$blockedItem->delete();
			if($UUId != '' || $UUId != null)
			{
				$this->depotService->unBlock($UUId, $company);
			}
		}
	}
	public function unBlock_acrossTenants(Array $params, $companyId)
	{
		$company = Company::find($companyId);
		$blockedItems = $company->blockedItems()->whereIn('id', $params['blocked_Ids'])->get();
		foreach($blockedItems as $blockedItem)
		{
			$UUId = $blockedItem->UUID;
			$blockedItem->delete();
			if($UUId != '' || $UUId != null)
			{
				$this->depotService->unBlock($UUId, $company);
			}
		}
	}
	public function deleteFromCart(Array $params, $customer)
	{
		$customer->cartItems()->whereIn('id', $params['item_ids'])->delete();
	}
	public function findAndDeleteCartItems($attribute, $attributeCompany, $attributeCustomer)
	{
		$loggedInUser = Auth::user();
		$query = null;
    	if($attribute->category_code != null)
    	{
    		$query = Product::where('category_code','=',$attribute->category_code);
    	}
    	if($attribute->collection_code != null)
    	{
    		$query->where('collection_code','=',$attribute->collection_code);
    	}
    	if($attribute->colour_code != null)
    	{
    		$query->where('colour_code','=',$attribute->colour_code);
    	}
    	$products = $query->get();
    	$productIds = $query->distinct()->get(['id'])->pluck('id')->toArray();
    	if($attributeCompany != null)
    	{
			$cartItems = CartItem::whereIn('product_id', $productIds)->get();
			$blockedItems = BlockedItem::whereIn('product_id', $productIds)->get();
			$advanceItems = AdvanceItem::whereIn('product_id', $productIds)->get();
    	}
		if($attributeCustomer != null)
		{
			$cartItems = $attributeCustomer->cartItems()->whereIn('product_id', $productIds)->get();
			$blockedItems = $attributeCustomer->blockedItems()->whereIn('product_id', $productIds)->get();
			$advanceItems = $attributeCustomer->advanceItems()->whereIn('product_id', $productIds)->get();
		}
		if($cartItems->count())
		{
			foreach ($cartItems as $cartItem)
			{
				$recipients = [];
				$product = $cartItem->product->category_code;
				$customer = $cartItem->distributor;
				$customerType = get_class($cartItem->itemable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
				if($customerType == 'DISTRIBUTO')
				{
					$customer = $cartItem->distributor;
					$users = User::where('distributor_id', '=', $customer->id)->get();
					foreach ($users as $user)
					{
						$recipients[] = $user;
					}
				}
				if($customerType == 'DEALER')
				{
					$customer = $cartItem->dealer;
					$users = User::where('dealer_id', '=', $customer->id)->get();
					foreach ($users as $user)
					{
						$recipients[] = $user;
					}
				}
				$productName = $this->getProductDisplayName($customer->company, $customer, $attribute->category_code, $attribute->collection_code, $attribute->colour_code);
				$batch_no = $cartItem->batch_no;
				$cartItem->delete();
				foreach ($recipients as $recipient) 
				{
					$notification = new Notification;
					$notification->title = 'Removed Shopping Cart Item';
					$notification->text = 'Product : '. $productName. ' against Batch No: '. $batch_no.' is not available for Ordering, hence removed from your Shopping Cart.';
					$notification->sender_id = $loggedInUser->id;
					$notification->recipient_id = $recipient->id;
					$notification->author()->associate($loggedInUser);
					$notification->sender()->associate($loggedInUser);
					$notification->recipient()->associate($recipient);
					$notification->save();
					event(new Notifications($notification));
				}
			}
		}
		if($blockedItems->count())
		{
			foreach ($blockedItems as $blockedItem)
			{
				$recipients = [];
				$product = $blockedItem->product->category_code;
				$customer = $blockedItem->distributor;
				$customerType = get_class($blockedItem->blockable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
				if($customerType == 'DISTRIBUTO')
				{
					$customer = $blockedItem->distributor;
					$users = User::where('distributor_id', '=', $customer->id)->get();
					foreach ($users as $user)
					{
						$recipients[] = $user;
					}
				}
				if($customerType == 'DEALER')
				{
					$customer = $blockedItem->dealer;
					$users = User::where('dealer_id', '=', $customer->id)->get();
					foreach ($users as $user)
					{
						$recipients[] = $user;
					}
				}
				$productName = $this->getProductDisplayName($customer->company, $customer, $attribute->category_code, $attribute->collection_code, $attribute->colour_code);
				$batch_no = $blockedItem->batch_no;
				$blockedItem->delete();
				foreach ($recipients as $recipient) 
				{
					$notification = new Notification;
					$notification->title = 'Removed Blocked Item';
					$notification->text = 'Product : '. $productName. ' against Batch No: '. $batch_no.' is not available for Ordering, hence removed from your Blocked Items.';
					$notification->sender_id = $loggedInUser->id;
					$notification->recipient_id = $recipient->id;
					$notification->author()->associate($loggedInUser);
					$notification->sender()->associate($loggedInUser);
					$notification->recipient()->associate($recipient);
					$notification->save();
					event(new Notifications($notification));
				}
			}
		}
		if($advanceItems->count())
		{
			foreach ($advanceItems as $advanceItem)
			{
				$recipients = [];
				$product = $advanceItem->product->category_code;
				$customer = $advanceItem->distributor;
				$customerType = get_class($advanceItem->advanceable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
				if($customerType == 'DISTRIBUTO')
				{
					$customer = $advanceItem->distributor;
					$users = User::where('distributor_id', '=', $customer->id)->get();
					foreach ($users as $user)
					{
						$recipients[] = $user;
					}
				}
				if($customerType == 'DEALER')
				{
					$customer = $advanceItem->dealer;
					$users = User::where('dealer_id', '=', $customer->id)->get();
					foreach ($users as $user)
					{
						$recipients[] = $user;
					}
				}
				$productName = $this->getProductDisplayName($customer->company, $customer, $attribute->category_code, $attribute->collection_code, $attribute->colour_code);
				$advanceItem->delete();
				foreach ($recipients as $recipient) 
				{
					$notification = new Notification;
					$notification->title = 'Removed Advance Cart Item';
					$notification->text = 'Product : '. $productName. ' is not available for Ordering, hence removed from your Advance Cart.';
					$notification->sender_id = $loggedInUser->id;
					$notification->recipient_id = $recipient->id;
					$notification->author()->associate($loggedInUser);
					$notification->sender()->associate($loggedInUser);
					$notification->recipient()->associate($recipient);
					$notification->save();
					event(new Notifications($notification));
				}
			}
		}
	}
	public function getProductDisplayName($company, $customer, $category_code, $collection_code, $colour_code)
	{
		$activeCategory = $customer->activeProductCategories()->where('category_code', '=', $category_code)->withTrashed()->first();
		if($collection_code != null)
    	{	$activeCollection = $customer->activeProductCollections()->where('collection_code', '=', $collection_code)->withTrashed()->first();
    	}
    	if($colour_code != null)
    	{	$activeColour = $customer->activeProductColours()->where('colour_code', '=', $colour_code)->withTrashed()->first();
    		if(!$activeColour)
    			$activeColour = $company->activeProductColours()->where('colour_code', '=', $colour_code)->withTrashed()->first();
    	}
    	if($colour_code == null)
    		$product_display_name = $activeCategory->category_display. ' >> '. $activeCollection->collection_display;
    	if($colour_code != null)
    	{
    		if($activeColour)
    			$product_display_name = $activeCategory->category_display. ' >> '. $activeCollection->collection_display. ' >> '. $activeColour->colour_display;
    	}
    	return $product_display_name;
	}
}