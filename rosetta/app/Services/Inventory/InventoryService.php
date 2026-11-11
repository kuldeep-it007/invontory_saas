<?php
namespace Rosetta\Services\Inventory;

use Rosetta\Models\AdvanceOrder\BlockedAdvanceItem;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\NAV\WS\ILE\ILE;
use Rosetta\Services\Companies\CompanyServiceInterface;
use Rosetta\Services\Inventory\InventoryServiceInterface;
use Rosetta\Services\NAV\ILEServiceInterface;
use Rosetta\Services\NAV\ItemCardIntransitServiceInterface;
use Rosetta\Services\NAV\PendingPurchaseOrdersServiceInterface;
class InventoryService implements InventoryServiceInterface 
{
	protected $ILESevice;
	protected $pendingPurchaseOrdersService;
	protected $companyService;
	protected $itemCardIntransitService;

	public function __construct(ILEServiceInterface $ILESevice,CompanyServiceInterface $companyService, ItemCardIntransitServiceInterface $itemCardIntransitService, PendingPurchaseOrdersServiceInterface $pendingPurchaseOrdersService)
	{
		$this->ILESevice = $ILESevice;
		$this->pendingPurchaseOrdersService = $pendingPurchaseOrdersService;
		$this->companyService = $companyService;
		$this->itemCardIntransitService = $itemCardIntransitService;
	}
	public function getLedgerEntries(User $user, $companyID, $productID, $location_codes)
	{
		$company = $this->companyService->find($companyID);
		$product = Product::find($productID);
		return $this->ILESevice->getLedgerEntries($company,$product->nav_id, $location_codes);
	}
	public function getPendingPurchaseOrders(User $user, $companyID, $productID, $location_codes)
	{
		$company = $this->companyService->find($companyID);
		$product = Product::find($productID);
		return $this->pendingPurchaseOrdersService->getPendingPurchaseOrders($company,$product->nav_id,$location_codes);
	}
	/*public function calculateStock(Array $itemCards,$customer,$requestedQuantity, $companyId, $min_price, $max_price,Array $distributorStock = [])
	{
		$resultingInventory = [];
		$company = $this->companyService->find($companyId);
		//preg_match('/([=<>])(\d+)/',urldecode($requestedQuantity),$matches);
		preg_match('/([=<>]|>=)(\d+)/',urldecode($requestedQuantity),$matches); //#754 & 752
		$operator = $matches[1];
		$quantity = $matches[2];
		$distributorStockCollection = collect($distributorStock);
		if($customer->distributor_id != null)
		{
			$distributor = Distributor::find($customer->distributor_id);
			$customer_stock_points = $distributor->stock_points;
		}
		else
			$customer_stock_points = $customer->stock_points;
		$distributor = null;
		if(count($distributorStock))
		{
			if($customer->distributor)
			{
				$distributor = $customer->distributor;//$distributorStock[0]->distributor;
			}
			else
			{
				$distributor = $distributorStock[0]->distributor;	
			}
		}
		$distributorProductsProcessed = [];
		//var_dump($customer_stock_points);
		//exit(0);
		if(count($itemCards) > 0)
		{
			foreach($itemCards as $itemCard)
			{
		//		$product = Product::where('nav_id','=',$itemCard->getNo())->first();
				//#688
				$query = Product::where('nav_id','=',$itemCard->getNo());
				if($min_price == null)
					$min_price = 0;
				if($max_price == null)
				{
					$query->whereHas('prices',function($q) use ($min_price,$max_price,$companyId) {
						$q->where('price','>=',$min_price)->where('company_id','=',$companyId);
					});
				}
				else
				{
					$query->whereHas('prices',function($q) use ($min_price,$max_price,$companyId) {
						$q->where('price','>=',$min_price)->where('price','<',$max_price)->where('company_id','=',$companyId);
					});
				}
				$product = $query->first();


				if(!$product)
					continue;
				$distributorStockForProduct = $distributorStockCollection->where('product_id','=',$product->id)->first();
				$inventory = 0;
				$SO_Qty = 0;
				$SO_Qty_Adv = 0;
				$intransit = 0;
				$intransit_SO_Qty = 0;
				$Cart_Qty = 0;
				$blocked_Qty = 0;
				$intransit_blocked_Qty = 0;
				$intransit_cart_Qty = 0;
				foreach ($customer_stock_points as $stock_point)
				{
					//$SO_Qty_Adv = 0;
					$location_code = $stock_point->stock_point->code;
					$inventory_method = 'get'.$location_code.'_Inventory';
					$inventory += (float)$itemCard->$inventory_method();
					$SO_Qty_method = 'get'.$location_code.'_SO_Qty';
					$SO_Qty += (float)$itemCard->$SO_Qty_method();
					$intransit_method = 'get'.$location_code.'_PO_Qty';
					$intransit += (float)$itemCard->$intransit_method();
					$SO_Qty_Adv_method = 'get'.$location_code.'_SO_Qty_Adv';
					$SO_Qty_Adv += (float)$itemCard->$SO_Qty_Adv_method();
					$stock_point_inventory = (float)$itemCard->$inventory_method() - (float)$itemCard->$SO_Qty_method();
					$stock_point_intransit = (float)$itemCard->$intransit_method() - (float)$itemCard->$SO_Qty_Adv_method();
					$stockPointBlockedQty = 0;
					$stockPointIntransitBlockedQty = 0;
					if((float)$itemCard->$inventory_method())
					{
						
						$stockPointBlockedQty = BlockedItem::active()->where('product_id','=',$product->id)->where('company_id','=',$companyId)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stockPointCartQuantity = $customer->cartItems()->where('product_id','=',$product->id)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stock_point_inventory = $stock_point_inventory - $stockPointBlockedQty - $stockPointCartQuantity;

						$stockPointIntransitBlockedQty = BlockedAdvanceItem::active()->where('product_id','=',$product->id)->where('company_id','=',$companyId)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stockPointIntransitCartQuantity = $customer->advanceItems()->active()->where('product_id','=',$product->id)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stock_point_intransit = $stock_point_intransit - $stockPointIntransitBlockedQty - $stockPointIntransitCartQuantity;

						$itemCard->setLocationSpecificStock($stock_point->stock_point,$stock_point_inventory,$stockPointBlockedQty, $stock_point_intransit, $stockPointIntransitBlockedQty);
						
						$itemCard->setBlocked($itemCard->getBlocked() + $stockPointBlockedQty);
						$itemCard->setIntransitBlocked($itemCard->getIntransitBlocked() + $stockPointIntransitBlockedQty);
						$Cart_Qty += $stockPointCartQuantity;
						$blocked_Qty += $stockPointBlockedQty;
						$intransit_cart_Qty += $stockPointIntransitCartQuantity;
						$intransit_blocked_Qty += $stockPointIntransitBlockedQty;

					//	$intransit_SO_Qty += $SO_Qty_Adv;
					}
					elseif((float)$stock_point_intransit)
					{
						$stockPointBlockedQty = BlockedItem::active()->where('product_id','=',$product->id)->where('company_id','=',$companyId)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stockPointCartQuantity = $customer->cartItems()->where('product_id','=',$product->id)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stock_point_inventory = $stock_point_inventory - $stockPointBlockedQty - $stockPointCartQuantity;

						$stockPointIntransitBlockedQty = BlockedAdvanceItem::active()->where('product_id','=',$product->id)->where('company_id','=',$companyId)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stockPointIntransitCartQuantity = $customer->advanceItems()->active()->where('product_id','=',$product->id)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stock_point_intransit = $stock_point_intransit - $stockPointIntransitBlockedQty - $stockPointIntransitCartQuantity;
						$itemCard->setLocationSpecificStock($stock_point->stock_point,$stock_point_inventory,$stockPointBlockedQty, $stock_point_intransit, $stockPointIntransitBlockedQty);
						$itemCard->setBlocked($itemCard->getBlocked() + $stockPointBlockedQty);
						$itemCard->setIntransitBlocked($itemCard->getIntransitBlocked() + $stockPointIntransitBlockedQty);
						$Cart_Qty += $stockPointCartQuantity;
						$blocked_Qty += $stockPointBlockedQty;
						$intransit_cart_Qty += $stockPointIntransitCartQuantity;
						$intransit_blocked_Qty += $stockPointIntransitBlockedQty;
					}
				}
				if($distributorStockForProduct)
				{
					$distributorInventory = $distributorStockForProduct->quantity - $distributorStockForProduct->getBlocked();
					$itemCard->setLocationSpecificStock($distributor,$distributorInventory,$distributorStockForProduct->getBlocked(),0,0);

					$inventory +=  $distributorInventory;
					$itemCard->setBlocked($itemCard->getBlocked() + $distributorStockForProduct->getBlocked());
					$distributorProductsProcessed[] = $product->id;
				}
				$itemCard->setInventory($inventory - $SO_Qty - $Cart_Qty - $blocked_Qty);
				$itemCard->setQty_on_Purch_Order($intransit - $SO_Qty_Adv - $intransit_cart_Qty - $intransit_blocked_Qty);
				//if($operator == '>')
				if($operator == '>' || $operator == '>=') //#754 & 752
				{
					if(($itemCard->getInventory() + $itemCard->getBlocked()) >= $quantity)
					{
						if($itemCard->getInventory() < 0)
							$itemCard->setInventory(0);
						$resultingInventory[] = $itemCard;
					}
				}
				else
				{
					if(($itemCard->getInventory() + $itemCard->getBlocked()) <= $quantity && ($itemCard->getInventory() + $itemCard->getBlocked()) > 0)
					{
						if($itemCard->getInventory() < 0)
							$itemCard->setInventory(0);
						$resultingInventory[] = $itemCard;
					}
				}
			}
			$notProcessed = $distributorStockCollection->filter(function ($value,$key)  use ($distributorProductsProcessed) {
				return !in_array($value->product_id,$distributorProductsProcessed);
			});
		}
		else
		{
			$notProcessed = $distributorStockCollection;
		}
		if($notProcessed->isNotEmpty())
		{
			foreach($notProcessed as $item)
			{
			//	$product = Product::where('id','=',$item->product_id)->first();
				//#688
				$query = Product::where('id','=',$item->product_id);
				if($min_price == null)
					$min_price = 0;
				if($max_price == null)
				{
					$query->whereHas('prices',function($q) use ($min_price,$max_price,$companyId) {
						$q->where('price','>=',$min_price)->where('company_id','=',$companyId);
					});
				}
				else
				{
					$query->whereHas('prices',function($q) use ($min_price,$max_price,$companyId) {
						$q->where('price','>=',$min_price)->where('price','<',$max_price)->where('company_id','=',$companyId);
					});
				}
				$product = $query->first();

				if($product)
				{
					$iCard = $this->itemCardIntransitService->get($company,$product->nav_id);
					$stockPointBlockedQty = BlockedItem::active()->where('product_id','=',$product->id)->where('company_id','=',$companyId)->where('stock_point_id', '=', NULL)->sum('order_quantity');
					$stockPointIntransitBlockedQty = 0;//distributor intransit stock - not in scope
					$stockPointInventory = $item->quantity - $stockPointBlockedQty;//cart qty already deducted in DistributorService
					$intransit = 0;//distributor intransit stock - not in scope
				
					$iCard->setLocationSpecificStock($distributor,$stockPointInventory,$stockPointBlockedQty);
					$iCard->setInventory($stockPointInventory);
					$iCard->setBlocked($stockPointBlockedQty);
					$iCard->setQty_on_Purch_Order($intransit);
					$iCard->setIntransitBlocked($stockPointIntransitBlockedQty);
					
					if($operator == '>')
					{
						if(($iCard->getInventory() + $iCard->getBlocked()) >= $quantity)
						{
							if($iCard->getInventory() < 0)
								$iCard->setInventory(0);
							$resultingInventory[] = $iCard;
						}
					}
					else
					{
						if(($iCard->getInventory() + $iCard->getBlocked()) <= $quantity && ($iCard->getInventory() + $iCard->getBlocked()) > 0)
						{
							if($iCard->getInventory() < 0)
								$iCard->setInventory(0);
							$resultingInventory[] = $iCard;
						}
					}
				}
			}
		}
		return $resultingInventory;
	}*///commented as part of #898
	public function calculateStock(Array $itemCards,$customer,$requestedQuantity, $companyId, $min_price, $max_price,Array $superStockistStock = [], $disrtibutorsStock=[])
	{
		$resultingInventory = [];
		$company = $this->companyService->find($companyId);
		//preg_match('/([=<>])(\d+)/',urldecode($requestedQuantity),$matches);
		preg_match('/([=<>]|>=)(\d+)/',urldecode($requestedQuantity),$matches); //#754 & 752
		$operator = $matches[1];
		$quantity = $matches[2];
		$superStockistStockCollection = collect($superStockistStock);
		$disrtibutorStockCollection = collect($disrtibutorsStock);
		$distributor = '';
		if(!empty($customer->distributor))
		{
			if($customer->distributor->super_stockist_id != null)
			{
				$distributor = $customer->distributor;
				$superStockist = $customer->distributor->superStockist;
				$customer_stock_points = $superStockist->stock_points;
			}else{
				$distributor = $customer->distributor;
				$customer_stock_points = $customer->distributor->stock_points;
			}
		} elseif (get_class($customer) == Distributor::class/*   && $customer->super_stockist_id == null */) {
			$distributor = $customer;
			$customer_stock_points = $customer->stock_points;
		}
		else
		{
			$customer_stock_points = $customer->stock_points;	
		}
		
		$superStockist = null;
		if(count($superStockistStock))
		{
			if(get_class($customer)==Distributor::class){
				$superStockist = $customer->superStockist;
			}else if(!empty($customer->distributor->superStockist))
			{
				$superStockist = $customer->distributor->superStockist;//$superStockistStock[0]->distributor;
			}
			else
			{
				$superStockist = $superStockistStock[0]->superStockist;	
			}
		}
		$superStockistProductsProcessed = [];
		//var_dump($customer_stock_points);
		//exit(0);
		if(count($itemCards) > 0)
		{
			foreach($itemCards as $itemCard)
			{
				/*$product = Product::where('nav_id','=',$itemCard->getNo())->first();
				//#688*/
				$query = Product::where('nav_id','=',$itemCard->getNo());
				if($min_price == null)
					$min_price = 0;
				if($max_price == null)
				{
					$query->whereHas('prices',function($q) use ($min_price,$max_price,$companyId) {
						$q->where('price','>=',$min_price)->where('company_id','=',$companyId);
					});
				}
				else
				{
					$query->whereHas('prices',function($q) use ($min_price,$max_price,$companyId) {
						$q->where('price','>=',$min_price)->where('price','<',$max_price)->where('company_id','=',$companyId);
					});
				}
				$product = $query->first();


				if(!$product)
					continue;
				$superStockistStockForProduct = $superStockistStockCollection->where('product_id','=',$product->id);

				$disrtibutorStockForProducts = $disrtibutorStockCollection->where('product_id','=',$product->id);//->first();

				$inventory = 0;
				$SO_Qty = 0;
				$SO_Qty_Adv = 0;
				$intransit = 0;
				$intransit_SO_Qty = 0;
				$Cart_Qty = 0;
				$blocked_Qty = 0;
				$intransit_blocked_Qty = 0;
				$intransit_cart_Qty = 0;
				foreach ($customer_stock_points as $stock_point)
				{
					/*$SO_Qty_Adv = 0;*/
					$location_code = $stock_point->stock_point->code;
					$inventory_method = 'get'.$location_code.'_Inventory';
					$inventory += (float)$itemCard->$inventory_method();
					$SO_Qty_method = 'get'.$location_code.'_SO_Qty';
					$SO_Qty += (float)$itemCard->$SO_Qty_method();
					$intransit_method = 'get'.$location_code.'_PO_Qty';
					$intransit += (float)$itemCard->$intransit_method();
					$SO_Qty_Adv_method = 'get'.$location_code.'_SO_Qty_Adv';
					$SO_Qty_Adv += (float)$itemCard->$SO_Qty_Adv_method();
					$stock_point_inventory = (float)$itemCard->$inventory_method() - (float)$itemCard->$SO_Qty_method();
					$stock_point_intransit = (float)$itemCard->$intransit_method() - (float)$itemCard->$SO_Qty_Adv_method();
					$stockPointBlockedQty = 0;
					$stockPointIntransitBlockedQty = 0;
					if((float)$itemCard->$inventory_method())
					{
						
						$stockPointBlockedQty = BlockedItem::active()->where('product_id','=',$product->id)->where('company_id','=',$companyId)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stockPointCartQuantity = $customer->cartItems()->where('product_id','=',$product->id)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stock_point_inventory = $stock_point_inventory - $stockPointBlockedQty - $stockPointCartQuantity;

						$stockPointIntransitBlockedQty = BlockedAdvanceItem::active()->where('product_id','=',$product->id)->where('company_id','=',$companyId)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stockPointIntransitCartQuantity = $customer->advanceItems()->active()->where('product_id','=',$product->id)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stock_point_intransit = $stock_point_intransit - $stockPointIntransitBlockedQty - $stockPointIntransitCartQuantity;

						$itemCard->setLocationSpecificStock($stock_point->stock_point,$stock_point_inventory,$stockPointBlockedQty, $stock_point_intransit, $stockPointIntransitBlockedQty);
						
						$itemCard->setBlocked($itemCard->getBlocked() + $stockPointBlockedQty);
						$itemCard->setIntransitBlocked($itemCard->getIntransitBlocked() + $stockPointIntransitBlockedQty);
						$Cart_Qty += $stockPointCartQuantity;
						$blocked_Qty += $stockPointBlockedQty;
						$intransit_cart_Qty += $stockPointIntransitCartQuantity;
						$intransit_blocked_Qty += $stockPointIntransitBlockedQty;

					//	$intransit_SO_Qty += $SO_Qty_Adv;
					}
					elseif((float)$stock_point_intransit)
					{
						$stockPointBlockedQty = BlockedItem::active()->where('product_id','=',$product->id)->where('company_id','=',$companyId)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stockPointCartQuantity = $customer->cartItems()->where('product_id','=',$product->id)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stock_point_inventory = $stock_point_inventory - $stockPointBlockedQty - $stockPointCartQuantity;

						$stockPointIntransitBlockedQty = BlockedAdvanceItem::active()->where('product_id','=',$product->id)->where('company_id','=',$companyId)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stockPointIntransitCartQuantity = $customer->advanceItems()->active()->where('product_id','=',$product->id)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stock_point_intransit = $stock_point_intransit - $stockPointIntransitBlockedQty - $stockPointIntransitCartQuantity;
						$itemCard->setLocationSpecificStock($stock_point->stock_point,$stock_point_inventory,$stockPointBlockedQty, $stock_point_intransit, $stockPointIntransitBlockedQty);
						$itemCard->setBlocked($itemCard->getBlocked() + $stockPointBlockedQty);
						$itemCard->setIntransitBlocked($itemCard->getIntransitBlocked() + $stockPointIntransitBlockedQty);
						$Cart_Qty += $stockPointCartQuantity;
						$blocked_Qty += $stockPointBlockedQty;
						$intransit_cart_Qty += $stockPointIntransitCartQuantity;
						$intransit_blocked_Qty += $stockPointIntransitBlockedQty;
					}
				}

				if ($superStockistStockForProduct) {
					foreach ($superStockistStockForProduct as $superStockistStockistInventory) {
						$getSSWhoHasInventory = $superStockistStockistInventory->superStockist;
						$superStockistInventory = $superStockistStockistInventory->quantity - $superStockistStockistInventory->getBlocked();
						$itemCard->setLocationSpecificStock($getSSWhoHasInventory, $superStockistInventory, $superStockistStockistInventory->getBlocked(), 0, 0);

						$inventory +=  $superStockistInventory;
						$itemCard->setBlocked($itemCard->getBlocked() + $superStockistStockistInventory->getBlocked());
						$superStockistProductsProcessed[] = $product->id;
					}
				}

				if ($disrtibutorStockForProducts->isNotEmpty()) {
					foreach ($disrtibutorStockForProducts as $disrtibutorStockForProduct) {
						$getDistWhoHasInventory = $disrtibutorStockForProduct->distributor;
						$disrtibutorStockistInventory = $disrtibutorStockForProduct->quantity - $disrtibutorStockForProduct->getBlocked();
						$itemCard->setLocationSpecificStock($getDistWhoHasInventory, $disrtibutorStockistInventory, $disrtibutorStockForProduct->getBlocked(), 0, 0);

						$inventory +=  $disrtibutorStockistInventory;
						$itemCard->setBlocked($itemCard->getBlocked() + $disrtibutorStockForProduct->getBlocked());
						$distributorProductsProcessed[] = $product->id;
					}
				}

				$itemCard->setInventory($inventory - $SO_Qty - $Cart_Qty - $blocked_Qty);
				$itemCard->setQty_on_Purch_Order($intransit - $SO_Qty_Adv - $intransit_cart_Qty - $intransit_blocked_Qty);
				//if($operator == '>')
				if($operator == '>' || $operator == '>=') //#754 & 752
				{
					if(($itemCard->getInventory() + $itemCard->getBlocked()) >= $quantity)
					{
						if($itemCard->getInventory() < 0)
							$itemCard->setInventory(0);
						$resultingInventory[] = $itemCard;
					}
				}
				else
				{
					if(($itemCard->getInventory() + $itemCard->getBlocked()) <= $quantity && ($itemCard->getInventory() + $itemCard->getBlocked()) > 0)
					{
						if($itemCard->getInventory() < 0)
							$itemCard->setInventory(0);
						$resultingInventory[] = $itemCard;
					}
				}
			}
			$notProcessed = $superStockistStockCollection->filter(function ($value,$key)  use ($superStockistProductsProcessed) {
				return !in_array($value->product_id,$superStockistProductsProcessed);
			});
		}
		else
		{
			$notProcessed = $superStockistStockCollection;
		}
		
		if($notProcessed->isNotEmpty())
		{
			foreach($notProcessed as $item)
			{
			//	$product = Product::where('id','=',$item->product_id)->first();
				//#688
				$query = Product::where('id','=',$item->product_id);
				if($min_price == null)
					$min_price = 0;
				if($max_price == null)
				{
					$query->whereHas('prices',function($q) use ($min_price,$max_price,$companyId) {
						$q->where('price','>=',$min_price)->where('company_id','=',$companyId);
					});
				}
				else
				{
					$query->whereHas('prices',function($q) use ($min_price,$max_price,$companyId) {
						$q->where('price','>=',$min_price)->where('price','<',$max_price)->where('company_id','=',$companyId);
					});
				}
				$product = $query->first();

				if($product)
				{
					$iCard = $this->itemCardIntransitService->get($company,$product->nav_id);
					$stockPointBlockedQty = BlockedItem::active()->where('product_id','=',$product->id)->where('company_id','=',$companyId)->where('stock_point_id', '=', NULL)->sum('order_quantity');
					$stockPointIntransitBlockedQty = 0;//distributor intransit stock - not in scope
					$stockPointInventory = $item->quantity - $stockPointBlockedQty;//cart qty already deducted in DistributorService
					$intransit = 0;//distributor intransit stock - not in scope
					if(!empty($superStockist)){
						// $iCard->setLocationSpecificStock($superStockist,$stockPointInventory,$stockPointBlockedQty,0,0);
					}
					if(!empty($distributor)){
						// $iCard->setLocationSpecificStock($distributor,$stockPointInventory,$stockPointBlockedQty,0,0);
					}
					
					$iCard->setInventory($stockPointInventory);
					$iCard->setBlocked($stockPointBlockedQty);
					$iCard->setQty_on_Purch_Order($intransit);
					$iCard->setIntransitBlocked($stockPointIntransitBlockedQty);

					$superStockistStockForProduct = $superStockistStockCollection->where('product_id','=',$product->id);

					$disrtibutorStockForProducts = $disrtibutorStockCollection->where('product_id','=',$product->id);//->first();

					if ($superStockistStockForProduct) {
						foreach ($superStockistStockForProduct as $superStockistStockistInventory) {
							$getSSWhoHasInventory = $superStockistStockistInventory->superStockist;
							$superStockistInventory = $superStockistStockistInventory->quantity - $superStockistStockistInventory->getBlocked();
							$iCard->setLocationSpecificStock($getSSWhoHasInventory, $superStockistInventory, $superStockistStockistInventory->getBlocked(), 0, 0);
	
							// $inventory +=  $superStockistInventory;
							$iCard->setBlocked($iCard->getBlocked() + $superStockistStockistInventory->getBlocked());
							$superStockistProductsProcessed[] = $product->id;
						}
					}
	
					if ($disrtibutorStockForProducts->isNotEmpty()) {
						foreach ($disrtibutorStockForProducts as $disrtibutorStockForProduct) {
							$getDistWhoHasInventory = $disrtibutorStockForProduct->distributor;
							$disrtibutorStockistInventory = $disrtibutorStockForProduct->quantity - $disrtibutorStockForProduct->getBlocked();
							$iCard->setLocationSpecificStock($getDistWhoHasInventory, $disrtibutorStockistInventory, $disrtibutorStockForProduct->getBlocked(), 0, 0);
	
							// $inventory +=  $disrtibutorStockistInventory;
							$iCard->setBlocked($iCard->getBlocked() + $disrtibutorStockForProduct->getBlocked());
							$distributorProductsProcessed[] = $product->id;
						}
					}
					
					if($operator == '>')
					{
						if(($iCard->getInventory() + $iCard->getBlocked()) >= $quantity)
						{
							if($iCard->getInventory() < 0)
								$iCard->setInventory(0);
							$resultingInventory[] = $iCard;
						}
					}
					else
					{
						if(($iCard->getInventory() + $iCard->getBlocked()) <= $quantity && ($iCard->getInventory() + $iCard->getBlocked()) > 0)
						{
							if($iCard->getInventory() < 0)
								$iCard->setInventory(0);
							$resultingInventory[] = $iCard;
						}
					}
				}
			}
		}
		return $resultingInventory;
	}
	public function calculateCompanyStock(Array $itemCards,$customer,$requestedQuantity, $companyId, $min_price, $max_price)
	{
		$resultingInventory = [];
		$company = $this->companyService->find($companyId);
		//preg_match('/([=<>])(\d+)/',urldecode($requestedQuantity),$matches);
		preg_match('/([=<>]|>=)(\d+)/',urldecode($requestedQuantity),$matches); //#754 & 752
		$operator = $matches[1];
		$quantity = $matches[2];
		$company_stock_points = $company->stock_points;
		if($itemCards)
		{
			foreach($itemCards as $itemCard)
			{
				//$product = Product::where('nav_id','=',$itemCard->getNo())->first();
				//#688
				$query = Product::where('nav_id','=',$itemCard->getNo());
				if($min_price == null)
					$min_price = 0;
				if($max_price == null)
				{
					$query->whereHas('prices',function($q) use ($min_price,$max_price,$companyId) {
						$q->where('price','>=',$min_price)->where('company_id','=',$companyId);
					});
				}
				else
				{
					$query->whereHas('prices',function($q) use ($min_price,$max_price,$companyId) {
						$q->where('price','>=',$min_price)->where('price','<',$max_price)->where('company_id','=',$companyId);
					});
				}
				$product = $query->first();

				if(!$product)
					continue;
				
				$inventory = 0;
				$SO_Qty = 0;
				$blocked_Qty = 0;
				foreach ($company_stock_points as $stock_point)
				{
					$location_code = $stock_point->stock_point->code;
					$inventory_method = 'get'.$location_code.'_Inventory';
					$inventory += (float)$itemCard->$inventory_method();
					$SO_Qty_method = 'get'.$location_code.'_SO_Qty';
					$SO_Qty += (float)$itemCard->$SO_Qty_method();
					$stock_point_inventory = (float)$itemCard->$inventory_method() - (float)$itemCard->$SO_Qty_method();
					$stockPointBlockedQty = 0;
					if((float)$itemCard->$inventory_method())
					{
						$stockPointBlockedQty = BlockedItem::active()->where('product_id','=',$product->id)->where('company_id','=',$companyId)->where('stock_point_id','=',$stock_point->stock_point->id)->sum('order_quantity');
						$stock_point_inventory = $stock_point_inventory - $stockPointBlockedQty;
						$itemCard->setLocationSpecificStock($stock_point->stock_point,$stock_point_inventory,$stockPointBlockedQty,0,0);
					}
					$itemCard->setBlocked($itemCard->getBlocked() + $stockPointBlockedQty);
					$blocked_Qty += $stockPointBlockedQty;
				}
				$itemCard->setInventory($inventory - $SO_Qty - $blocked_Qty);
				if($operator == '>')
				{
					if(($itemCard->getInventory() + $itemCard->getBlocked()) >= $quantity)
					{
						if($itemCard->getInventory() < 0)
							$itemCard->setInventory(0);
						$resultingInventory[] = $itemCard;
					}
				}
				else
				{
					if(($itemCard->getInventory() + $itemCard->getBlocked()) <= $quantity && ($itemCard->getInventory() + $itemCard->getBlocked()) > 0)
					{
						if($itemCard->getInventory() < 0)
							$itemCard->setInventory(0);
						$resultingInventory[] = $itemCard;
					}
				}
			}
		}
		return $resultingInventory;
	}
	public function mergeLedgerEntries($ledgerEntries, $pendingPOEntries)
	{
		$resultingLedgerEntries = [];
		foreach($ledgerEntries as $entry)
		{
			$resultingLedgerEntries[] = $entry;
		}
		foreach($pendingPOEntries as $pendingPOEntry)
		{
			$ledgerEntry = new ILE;
			$ledgerEntry->setLot_No($pendingPOEntry->getDocument_No());
			$ledgerEntry->setRemaining_Quantity($pendingPOEntry->getOutstanding_Qty());
			$ledgerEntry->setLocation_Source('StockPoint');
			$ledgerEntry->setLocation_Code($pendingPOEntry->getLocation_Code());
			$ledgerEntry->setEntry_Type('In-Transit');
			$ledgerEntry->setPosting_Date($pendingPOEntry->getExpected_Receipt_Date());
			$ledgerEntry->setReserved_Qty($pendingPOEntry->getReserved_Qty());
			$resultingLedgerEntries[] = $ledgerEntry;
		}
		return $resultingLedgerEntries;
	}
	public function formattedLedgerEntries($pendingPOEntries)
	{
		$resultingLedgerEntries = [];
		foreach($pendingPOEntries as $pendingPOEntry)
		{
			$ledgerEntry = new ILE;
			$ledgerEntry->setLot_No($pendingPOEntry->getDocument_No());
			$ledgerEntry->setRemaining_Quantity($pendingPOEntry->getOutstanding_Qty());
			$ledgerEntry->setLocation_Source('StockPoint');
			$ledgerEntry->setLocation_Code($pendingPOEntry->getLocation_Code());
			$ledgerEntry->setEntry_Type('In-Transit');
			$ledgerEntry->setPosting_Date($pendingPOEntry->getExpected_Receipt_Date());
			$ledgerEntry->setReserved_Qty($pendingPOEntry->getReserved_Qty());
			$resultingLedgerEntries[] = $ledgerEntry;
		}
		return $resultingLedgerEntries;
	}
	public function calculateStockAgainstBatch($productID,Array $ledgerEntries,$customer, $companyId, $blockFrom = null)
	{
		$resultingLedgerEntries = [];
		$uniqueLots=[];
		foreach ($ledgerEntries as $entry)
		{
			$uniqueCode = $entry->getLot_No().$entry->getLocation_Code();
			if(array_has($uniqueLots,$uniqueCode))
			{
				$entry->setRemaining_Quantity($uniqueLots[$uniqueCode]->getRemaining_Quantity() + $entry->getRemaining_Quantity());
			}
			$uniqueLots[$uniqueCode] = $entry;
		}
		foreach($uniqueLots as $lot => $ledgerEntry)
		{
			$ledgerEntry->setRemaining_Quantity(((float)$ledgerEntry->getRemaining_Quantity()) + ((float)$ledgerEntry->getReserved_Qty()));
			$cartQuantity = 0;
			$location_code = $ledgerEntry->getLocation_Code();
			$stock_point = StockPoint::where('code', '=', $location_code)->first();
			if($customer)
			{
				$blockedQuntity = BlockedItem::active()->where('product_id','=',$productID)
					->where('company_id','=',$customer->company_id)
					->where('batch_no','=',$ledgerEntry->getLot_No())
					->where('stock_point_id', '=', $stock_point->id)
					->sum('order_quantity');
				if($blockFrom != "BlockFromCart")
				{
					$cartQuantity = $customer->cartItems()->where('product_id','=',$productID)
					->where('batch_no','=',$ledgerEntry->getLot_No())
					->where('stock_point_id', '=', $stock_point->id)
					->sum('order_quantity');
				}
			}
			else
				$blockedQuntity = BlockedItem::active()->where('product_id','=',$productID)
					->where('company_id','=',$companyId)
					->where('batch_no','=',$ledgerEntry->getLot_No())
					->where('stock_point_id', '=', $stock_point->id)
					->sum('order_quantity');
			$cartQuantity += $blockedQuntity;
			$resultingQuantity = ((float)$ledgerEntry->getRemaining_Quantity()) - $cartQuantity;
			if($resultingQuantity >= 1 && $blockFrom != "BlockFromCart")//details button on search and order products from company stock //#752 changed filter to >= from >
			{
				$ledgerEntry->setRemaining_Quantity($resultingQuantity);
				$ledgerEntry->setLocation_Source('StockPoint');
				$ledgerEntry->setLocation_name($stock_point->name);
				$resultingLedgerEntries[] = $ledgerEntry;
			}
			if($blockFrom == "BlockFromCart")
			{
				$ledgerEntry->setRemaining_Quantity($resultingQuantity);
				$ledgerEntry->setLocation_Source('StockPoint');
				$ledgerEntry->setLocation_name($stock_point->name);
				$resultingLedgerEntries[] = $ledgerEntry;
			}
		}
		return $resultingLedgerEntries;
	}
	public function calculateIntransitStockAgainstBatch($productID,Array $ledgerEntries,$customer, $companyId, $blockFrom = null)
	{
		$resultingLedgerEntries = [];
		$uniqueLots=[];
		foreach ($ledgerEntries as $entry)
		{
			$uniqueCode = $entry->getLot_No().$entry->getLocation_Code();
			if(array_has($uniqueLots,$uniqueCode))
			{
				$entry->setRemaining_Quantity($uniqueLots[$uniqueCode]->getRemaining_Quantity() + $entry->getRemaining_Quantity());
			}
			$uniqueLots[$uniqueCode] = $entry;
		}
		foreach($uniqueLots as $lot => $ledgerEntry)
		{
			$ledgerEntry->setRemaining_Quantity(((float)$ledgerEntry->getRemaining_Quantity()) - ((float)$ledgerEntry->getReserved_Qty()));
			$advanceCartQuantity = 0;
			$location_code = $ledgerEntry->getLocation_Code();
			$stock_point = StockPoint::where('code', '=', $location_code)->first();
			if($customer)
			{
				$blockedQuntity = BlockedAdvanceItem::active()->where('product_id','=',$productID)
					->where('company_id','=',$customer->company_id)
					->where('nav_intransit_po','=',$ledgerEntry->getLot_No())
					->where('stock_point_id', '=', $stock_point->id)
					->sum('order_quantity');
				if($blockFrom != "BlockFromCart")
				{
					$advanceCartQuantity = $customer->advanceItems()->active()->where('product_id','=',$productID)
					->where('nav_intransit_po','=',$ledgerEntry->getLot_No())
					->sum('order_quantity');
				}
			}
			else
				$blockedQuntity = BlockedAdvanceItem::active()->where('product_id','=',$productID)
					->where('company_id','=',$companyId)
					->where('nav_intransit_po','=',$ledgerEntry->getLot_No())
					->where('stock_point_id', '=', $stock_point->id)
					->sum('order_quantity');
			$advanceCartQuantity += $blockedQuntity;
			$resultingQuantity = ((float)$ledgerEntry->getRemaining_Quantity()) - $advanceCartQuantity;
			if($resultingQuantity >= 1 && $blockFrom != "BlockFromCart")//details button on search and order products from company stock //#752 changed filter to >= from >
			{
				$ledgerEntry->setRemaining_Quantity($resultingQuantity);
				$ledgerEntry->setLocation_Source('StockPoint');
				$ledgerEntry->setLocation_name($stock_point->name);
				$resultingLedgerEntries[] = $ledgerEntry;
			}
			if($blockFrom == "BlockFromCart")
			{
				$ledgerEntry->setRemaining_Quantity($resultingQuantity);
				$ledgerEntry->setLocation_Source('StockPoint');
				$ledgerEntry->setLocation_name($stock_point->name);
				$resultingLedgerEntries[] = $ledgerEntry;
			}
		}
		return $resultingLedgerEntries;
	}
}