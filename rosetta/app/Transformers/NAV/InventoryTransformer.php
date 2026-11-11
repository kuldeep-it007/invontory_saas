<?php
namespace Rosetta\Transformers\NAV;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\Product\Product;
use Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit;
use Rosetta\Transformers\Inventory\LocationSpecificStockTransformer;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;
use Rosetta\Transformers\Product\DistributorPriceTransformer;
use Rosetta\Transformers\Product\PriceTransformer;
class InventoryTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['category',"collection","colour","price",'stock_points'];
	protected $defaultIncludes = ['category',"collection","colour",'stock_points'];
	protected $product;
	protected $company, $customerType, $customer;
	public function __construct(Company $company = null, $customerType=null, $customer=null)
	{
		$this->company = $company;
		$this->customerType = $customerType;
		$this->customer = $customer;
	}
	public function transform(Item_Card_Intransit $item)
	{
		$this->product = Product::where('nav_id','=',$item->getNo())->first();
		$this->product->setItemCardIntransit($item);
	//	$blockedQuntity = BlockedItem::active()->where('product_id','=',$this->product->id)->where('company_id','=',$this->company->id)->sum('order_quantity');
		return [
			"id"                   => $this->product->id,
			"nav_id"               => $this->product->nav->getNo(),
			"name"                 => $this->product->name,
			"description_2"        => $this->product->nav->getDescription_2(),
			"base_unit_of_measure" => $this->product->nav->getBase_Unit_of_Measure(),
		//	"inventory"            => round((float)$this->product->nav->getInventory()),//total inventory across stock points//multiple stock points
			"inventory"            => (float)$this->product->nav->getInventory(),//total inventory across stock points
		//	"blocked"			   => (float)$blockedQuntity,
			"blocked"			   => (float)$this->product->nav->getBlocked(),
			"qty_on_purch_order"   => (float)$this->product->nav->getQty_on_Purch_Order(),
			"in_transit_blocked"   => (float)$this->product->nav->getIntransitBlocked(),
			"image_url"            => (!empty($this->product->image['url']))?$this->product->image['url']:'',
		];	
	}
	public function includeCategory(Item_Card_Intransit $item)
	{
		$region = null;
		if($this->company)
			$region = $this->company->region;
		if($this->product->category)
			return $this->item($this->product->category, new CategoryTransformer($region,$this->product->sales_unit_of_measure, $this->customer));
		return null;
	}
	public function includeCollection(Item_Card_Intransit $item)
	{
		$region = null;
		if($this->company)
			$region = $this->company->region;
		if($this->product->collection)
			return $this->item($this->product->collection, new CollectionTransformer($region,$this->product->sales_unit_of_measure, $this->customer));
		return null;
	}
	public function includeColour(Item_Card_Intransit $item)
	{
		if($this->product->colour)
		{
			$colour = Colour::where('code','=',$this->product->colour_code)
				->where('collection_code','=',$this->product->collection_code)
				->first();
			$region = null;
			if($this->company)
				$region = $this->company->region;
			if(!$colour)
				return null;
			return $this->item($colour, new ColourTransformer($region,$this->product->sales_unit_of_measure, $this->customer, $this->product->category->code));
		}
		return null;
	}
	public function includePrice()
	{
		if($this->customerType == 'DEALER' && $this->customer->distributor_id != null)
			return $this->item($this->product,new DistributorPriceTransformer($this->customer,$this->customerType));
		else
			return $this->item($this->product,new PriceTransformer($this->company,$this->customer,$this->customerType));	
	}
	public function includeStockPoints(Item_Card_Intransit $item)
	{
		$getLocationSpecificStock = collect($item->getLocationSpecificStock())->filter(function($stock) {
			return $stock->inventory >= 1;
		});
		return $this->collection($getLocationSpecificStock,new LocationSpecificStockTransformer);

		//return $this->collection($item->getLocationSpecificStock(),new LocationSpecificStockTransformer);
	}

}