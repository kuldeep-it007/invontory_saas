<?php
namespace Rosetta\Services\Product;

use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Predis\Connection\ConnectionException;
use Rosetta\Events\Notifications;
use Rosetta\Exceptions\RedisException;
use Rosetta\Mail\MUPSet;
use Rosetta\Models\AdvanceOrder\AdvanceItem;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Cart\CartItem;
use Rosetta\Models\Company;
use Rosetta\Models\Discount;
use Rosetta\Models\Log;
use Rosetta\Models\Notification;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\Product\Price;
use Rosetta\Models\Product\Product;
use Rosetta\Models\Product\setItemCardIntransit;
use Rosetta\Models\Region;
use Rosetta\Models\User;
use Rosetta\Services\CMS\CMSServiceInterface;
use Rosetta\Services\NAV\ItemCardIntransitServiceInterface;
use Rosetta\Services\NAV\ItemPictureServiceInterface;
use Rosetta\Services\NAV\SalesPriceServiceInterface;
use Rosetta\Services\Product\ProductsServiceInterface;
use SoapFault;
use Tymon\JWTAuth\Providers\config;
use stdClass;
class ProductsService implements ProductsServiceInterface
{
	protected $itemCardIntransitService;
	protected $cmsService;
	protected $salesPriceService;
	protected $pictureService;
	public function __construct(
		ItemCardIntransitServiceInterface $itemCardIntransitService,
		CMSServiceInterface $cmsService,
		SalesPriceServiceInterface $salesPriceService,
		ItemPictureServiceInterface $pictureService
	)
	{
		$this->itemCardIntransitService = $itemCardIntransitService;
		$this->cmsService = $cmsService;
		$this->salesPriceService = $salesPriceService;
		$this->pictureService = $pictureService;
	}
    public function getProducts(Array $params = [])
    {
    	$query = null;
    	if(array_has($params,'nav_id'))
    	{
    		$query = Product::withoutGlobalScopes()->where('nav_id','=',$params['nav_id']);
    		if(array_has($params,'page'))
			{
				if($query)
				{
					return $query->paginate($params['per_page'],['*'],'',$params['page']);	
				}
				return Product::withoutGlobalScopes()->paginate($params['per_page'],['*'],'',$params['page']);
			}
			if($query)
			{
				return $query->get();
			}
    	}
    	if(array_has($params,'category'))
    	{
    		$query = Product::withoutGlobalScopes()->where('category_code','=',$params['category']);
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
			if($query)
			{
				return $query->paginate($params['per_page'],['*'],'',$params['page']);	
			}
			return Product::withoutGlobalScopes()->paginate($params['per_page'],['*'],'',$params['page']);
		}
		if($query)
		{
			return $query->get();
		}
		return Product::all();
    }
    public function getActiveProducts(Array $params = [], $company)
    {
    	$query = null;
    	if(array_has($params,'nav_id'))
    	{
    		$query = Product::withoutGlobalScopes()->where('nav_id','=',$params['nav_id']);
    		$product = $query->first();
    		$activeResult = $company->activeProductAttributes()->where('category_code', '=', $product->category->code)
    								->where('collection_code', '=', $product->collection->code)
    								->where('colour_code', '=', $product->colour->code)
    								->first();
    		if($activeResult)
    		{
    			if(array_has($params,'page'))
				{
					if($query)
					{
						return $query->paginate($params['per_page'],['*'],'',$params['page']);
					}
					return Product::withoutGlobalScopes()->paginate($params['per_page'],['*'],'',$params['page']);
				}
				if($query)
				{
					return $query->get();
				}
    		}
    		else
    			return null;
    	}
    	$activeCategoryCodes = $company->activeProductCategories()
							->pluck('category_code')->toArray();
		if($activeCategoryCodes)
		{
			$activeCollectionCodes = $company->activeProductCollections()
								->whereIn('category_code', $activeCategoryCodes)
								->pluck('collection_code')->toArray();//if active Category exists then active collection has to exist

			$activeColourCodes = $company->activeProductColours()
								->whereIn('category_code', $activeCategoryCodes)
								->whereIn('collection_code', $activeCollectionCodes)
								->pluck('colour_code')->toArray();
		}
    	if(array_has($params,'category'))
    	{
    		$query = Product::where('category_code','=',$params['category']);
    		if(array_has($params,'collection'))
	    	{
	    		$query->where('collection_code','=',$params['collection']);
	    		if(array_has($params,'colour'))
		    	{
		    		$query->where('colour_code','=',$params['colour']);
		    	}
		    	else
		    	{
		    		$activeColourCodes = $company->activeProductColours()
								->where('category_code', '=', $params['category'])
								->where('collection_code', '=', $params['collection'])
								->pluck('colour_code')->toArray();

					$query->whereIn('colour_code', $activeColourCodes);
		    	}
	    	}
	    	else
	    	{
	    		$activeCollectionCodes = $company->activeProductCollections()
								->where('category_code', $params['category'])
								->pluck('collection_code')->toArray();

				$query->whereIn('collection_code', $activeCollectionCodes);

				$activeColourCodes = $company->activeProductColours()
								->where('category_code', '=', $params['category'])
								->whereIn('collection_code', $activeCollectionCodes)
								->pluck('colour_code')->toArray();

				$query->whereIn('colour_code', $activeColourCodes);
	    	}
    	}
    	else
    	{
    		$query = Product::withoutGlobalScopes()->whereIn('category_code',$activeCategoryCodes)
						->whereIn('collection_code', $activeCollectionCodes)
						->whereIn('colour_code', $activeColourCodes);	
    	}
    	if(array_has($params,'page'))
		{
			if($query)
			{
				return $query->paginate($params['per_page'],['*'],'',$params['page']);	
			}
			return Product::withoutGlobalScopes()->paginate($params['per_page'],['*'],'',$params['page']);
		}
		if($query)
		{
			return $query->get();
		}
		return Product::all();
    }
    public function getUserActiveProducts(Array $params = [], $company, $customer)
    {
    	$query = null;
    	if($customer)
    	{
    		$activeCategory = $customer->activeProductCategories()
							->where('category_code','=', $params['category'])
							->first();
			if($activeCategory)
			{
				$activeCollectionCodes = $customer->activeProductCollections()
									->where('category_code', '=', $params['category'])
									->pluck('collection_code')->toArray();//if active Category exists then active collection has to exist
			}
		}
    	if(array_has($params,'category'))
    	{
    		$query = Product::where('category_code','=',$params['category']);
    	}
    	if(array_has($params,'collection'))
    	{
    		$query->where('collection_code','=',$params['collection']);
    	}
    	else
    	{
			$query->whereIn('collection_code',$activeCollectionCodes);
    	}
    	if(array_has($params,'colour'))
    	{
    		$query->where('colour_code','=',$params['colour']);
    	}
    	else
    	{
    		if($customer)
    		{
	    		if(array_has($params,'collection'))
	    		{
	    			$colourCnt = $customer->activeProductColours()
							->where('category_code', '=',  $params['category'])
							->where('collection_code', '=', $params['collection'])
							->count();
					if(!$colourCnt)
					{
						$customersCompany = $customer->company;
						$activeColourCodes = $customersCompany->activeProductColours()
										->where('category_code', '=', $params['category'])
										->where('collection_code', '=', $params['collection'])
										->pluck('colour_code')->toArray();
					}
					else
					{
						$activeColourCodes = $customer->activeProductColours()
										->where('category_code', '=', $params['category'])
										->where('collection_code', '=', $params['collection'])
										->pluck('colour_code')->toArray();
					}
	    		}
	    		else
	    		{
	    			$activeColourCodes1;
	    			$activeColourCodes2;
	    			$activeColourCodes;
	    			foreach ($activeCollectionCodes as $activeCollectionCode)
	    			{
	    				$colourCnt = $customer->activeProductColours()
							->where('category_code', '=',  $params['category'])
							->where('collection_code', '=', $activeCollectionCode)
							->count();
						
						if(!$colourCnt)
						{
							$customersCompany = $customer->company;
							$activeColourCodes1 = $customersCompany->activeProductColours()
											->where('category_code', '=', $params['category'])
											->where('collection_code', '=', $activeCollectionCode)
											->pluck('colour_code')->toArray();
						}
						else
						{
							$activeColourCodes2 = $customer->activeProductColours()
											->where('category_code', '=', $params['category'])
											->where('collection_code', '=', $activeCollectionCode)
											->pluck('colour_code')->toArray();
						}
	    			}
	    			$activeColourCodes = array_merge($activeColourCodes2, $activeColourCodes1);
	    		}
    		}
			$query->whereIn('colour_code',$activeColourCodes);
    	}
    	if(array_has($params,'page'))
		{
			if($query)
			{
				return $query->paginate($params['per_page'],['*'],'',$params['page']);	
			}
		}
		if($query)
		{
			return $query->get();
		}
    }
    public function find(int $id)
	{
		$product = Product::find($id);
		if(!$product)
		{
			throw new ModelNotFoundException;
		}
		return $product;
	}
	public function create(Array $params)
	{
		$techspecs = collect($params['techspecs']);
		$region_uoms = [];
		$techspecs_str = strtoupper($techspecs->reduce(function($carry,$item){
			return $carry . "{$item['title']} : {$item['value']}\r\n";
		}));
		$companies = Company::all();
		$companies = $companies->filter(function($item){return $item->id != 2;});//#782
		$createdProducts = [];
		$newProduct = null;
		$category = Category::find($params['category']);
		$collection = Collection::find($params['collection']);
		$colour = Colour::find($params['colour']);
		$result = new stdClass;
		$result->product = null;
		$result->cms = false;
		$result->companies = [];
		foreach($companies as $company)
		{
			$companyResult = new stdClass;
			$companyResult->company = $company;

			if(!array_has($region_uoms,(String)$company->region->id))
			{
				$uoms = collect(DB::table('uom_mappings')->select('type','from','to')->where('region_id','=',$company->region->id)->get());
				$region_uoms[(String)$company->region->id] = $uoms;
			}
			$region_uom = $region_uoms[(String)$company->region->id];

			$region_uom_sales = $region_uom->where('type','sales_unit_of_measure')
				->where('from',$params['sales_unit_of_measure']);

			$region_uom_base = $region_uom->where('type','base_unit_of_measure')
				->where('from',$params['base_unit_of_measure']);

			$region_uom_purchase = $region_uom->where('type','purchase_unit_of_measure')
				->where('from',$params['purchase_unit_of_measure']);

			
			$itemCardParams = [
				"name" => $params['name'],
				"description" => $params['description'],
				"search_description" => $params['search_description'],
				"category" => $params['category'],
				"collection" => $params['collection'],
				"techspecs" => $techspecs_str,
				"base_unit_of_measure" => $region_uom_base->isNotEmpty() ? $region_uom_base->first()->to : $params['base_unit_of_measure'],
				"sales_unit_of_measure" => $region_uom_sales->isNotEmpty() ? $region_uom_sales->first()->to : $params['sales_unit_of_measure'],
				"purchase_unit_of_measure" => $region_uom_purchase->isNotEmpty() ? $region_uom_purchase->first()->to : $params['purchase_unit_of_measure'],
				"tracking_code" => 'LOTALL',
			];
			//#736
			if(array_has($params,'gen_prod_posting_group'))
			{
				$itemCardParams['gen_prod_posting_group'] = $params["gen_prod_posting_group"];
			}
			if(array_has($params,'vat_prod_posting_group'))
			{
				$itemCardParams['vat_prod_posting_group'] = $params["vat_prod_posting_group"];
			}
			if(array_has($params,'inv_posting_group'))
			{
				$itemCardParams['inv_posting_group'] = $params["inv_posting_group"];
			}//End of #736
			if(array_has($params,'colour'))
			{
				$itemCardParams['colour'] = $params["colour"];
			}
			$navProduct = null;
			try
			{
				$navProduct = $this->itemCardIntransitService->create($company, $itemCardParams);
			}
			catch(SoapFault $fault)
			{
				$companyResult->itemCard = null;
				$companyResult->error = $fault->getMessage();
			}
			if(!$navProduct)
			{
				$result->companies[] = $companyResult;
				continue;
			}
			else
			{
				if(array_has($params,'image'))
				{
					try{
						$this->pictureService->setImage($company,$navProduct->getNo(),$params['image']);
					}
					catch(SoapFault $fault)
					{

					}
				}
			}
			$companyResult->itemCard = $navProduct;
			$companyResult->error = null;
			$result->companies[] = $companyResult;
			if(!$newProduct)
			{
				$newProduct = new Product;
				$newProduct->name = $params['name'];
				$newProduct->base_unit_of_measure = $params['base_unit_of_measure'];
				$newProduct->sales_unit_of_measure = $params['sales_unit_of_measure'];
				$newProduct->purchase_unit_of_measure = $params['purchase_unit_of_measure'];
				$newProduct->category()->associate($category);
				$newProduct->collection()->associate($collection);
				$newProduct->colour()->associate($colour);
				$newProduct->nav_id = $navProduct->getNO();
				$newProduct->save();
			}
		}
		$result->cms = false;
		if($newProduct)
		{
			$this->cmsService->createProduct($newProduct->nav_id,$params);
			$result->cms = true;
			$result->product = $newProduct;
		}
		return $result;
	}
	public function getProductPrice($companyId,$categoryCode,$collectionCode,$colourCode)
	{
		$product = Product::where('category_code', '=', $categoryCode)
							->where('collection_code', '=', $collectionCode)
							->where('colour_code', '=', $colourCode)
							->first();
		if($product)
		{
			$price = $product->prices()->where('company_id','=',$companyId)->first();
			if($price)
           		return $price->price;
		}
		return null;
	}
	public function addProductPrice($company,Array $params)
	{
		$product_price = $company->productPrices()->where('product_id', '=', $params['productId'])->first();
		$product = Product::where('id', '=', $params['productId'])->first();
		if($product_price)
		{
			$product_price->price = $params['price'];
			$product_price->save();
			$cart_items = CartItem::where('product_id', '=', $product->id)->get();
			foreach($cart_items as $cartItem)
			{
				$cartItem->unit_price = $params['price'];
				$cartItem->save();
			}
			$blocked_items = BlockedItem::where('product_id', '=', $product->id)->get();
			foreach($blocked_items as $blockedItem)
			{
				$blockedItem->unit_price = $params['price'];
				$blockedItem->save();
			}
			$advance_items = AdvanceItem::where('product_id', '=', $product->id)->get();
			foreach($advance_items as $advanceItem)
			{
				$advanceItem->unit_price = $params['price'];
				$new_discounted_price = $params['price'] - ($params['price'] * $advanceItem->discount / 100);
				$advanceItem->discounted_price = $new_discounted_price;
				$new_total_price = $new_discounted_price * $advanceItem->order_quantity;
				$advanceItem->total_price = $new_total_price;
				$newTaxAmount = ($new_discounted_price * $advanceItem->tax_percent/100) * $advanceItem->order_quantity;
				$advanceItem->tax_amount = $newTaxAmount;
				$new_net_amount = $new_total_price + $newTaxAmount;
				$advanceItem->net_amount = $new_net_amount;
				$advanceItem->save();
			}
		}
		else
		{
			$product_price = new Price;
			$product_price->price = $params['price'];
			$product_price->product_id = $product->id;
			$company->productPrices()->save($product_price);			
		}
		$sender = Auth::user();
		$query = User::where('company_id','=',$company->id);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','companyAdmin');
			});
		$users = $query->get();

		foreach ($users as $user) {
			$recipients[] = $user;
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
		}//add Company Admin Email Ids

		$superAdminEmailQuery = User::where('distributor_id','=',null)
					->where('dealer_id','=',null)
					->where('super_stockist_id','=',null)
					->where('company_id','=',null);
		$superAdminEmailQuery->whereHas('roles', function($q){
				$q->where('name', '=', 'superadmin');
			});
		$superAdminEmailIds = $superAdminEmailQuery->get();
		foreach ($superAdminEmailIds as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		}//add Super Admin Email Ids

		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		$Qmessage = (new MUPSet($product->name, $params['price']))->onQueue($queue);
		try
	    {
	    	if(!empty($toEmailIds))
        		Mail::to($toEmailIds)->queue($Qmessage);
       	}
       	catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
	    foreach ($recipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'Product Price set!';
			$notification->text = 'Price for the product ' .$product->name. ' has been set to '. $params['price'];
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification));
		}
		$log = new Log;
		$log->action = 'Product Price set';
		$log->author()->associate(Auth::user());
		$log->ip_address = \Request::ip();
		$log->comment = 'Price for the product ' .$product->name. ' has been set to '. $params['price'];
		$product_price->logs()->save($log);
		return $product_price;
	}
	public function update(int $id ,Array $params)
	{
		$techspecs = collect($params['techspecs']);
		$region_uoms = [];
		$techspecs_str = strtoupper($techspecs->reduce(function($carry,$item){
			return $carry . "{$item['title']} : {$item['value']}\r\n";
		}));
		$companies = Company::all();
		$category = Category::find($params['category']);
		$collection = Collection::find($params['collection']);
		$colour = Colour::find($params['colour']);
		$result = new stdClass;
		$result->product = null;
		$result->cms = false;
		$result->companies = [];
		foreach($companies as $company)
		{
			$companyResult = new stdClass;
			$companyResult->company = $company;

			if(!array_has($region_uoms,(String)$company->region->id))
			{
				$uoms = collect(DB::table('uom_mappings')->select('type','from','to')->where('region_id','=',$company->region->id)->get());
				$region_uoms[(String)$company->region->id] = $uoms;
			}
			$region_uom = $region_uoms[(String)$company->region->id];

			$region_uom_sales = $region_uom->where('type','sales_unit_of_measure')
				->where('from',$params['sales_unit_of_measure']);

			$region_uom_base = $region_uom->where('type','base_unit_of_measure')
				->where('from',$params['base_unit_of_measure']);

			$region_uom_purchase = $region_uom->where('type','purchase_unit_of_measure')
				->where('from',$params['purchase_unit_of_measure']);

			$itemCardParams = [
				"name" => $params['name'],
				"nav_id" => $params['nav_id'],
				"description" => $params['description'],
				"search_description" => $params['search_description'],
				"category" => $params['category'],
				"collection" => $params['collection'],
				"techspecs" => $techspecs_str,
				"base_unit_of_measure" => $region_uom_base->isNotEmpty() ? $region_uom_base->first()->to : $params['base_unit_of_measure'],
				"sales_unit_of_measure" => $region_uom_sales->isNotEmpty() ? $region_uom_sales->first()->to : $params['sales_unit_of_measure'],
				"purchase_unit_of_measure" => $region_uom_purchase->isNotEmpty() ? $region_uom_purchase->first()->to : $params['purchase_unit_of_measure'],
			];
			if(array_has($params,'colour'))
			{
				$itemCardParams['colour'] = $params["colour"];
			}
			$itemCardUpdated = false;
			try{
				$navProduct = $this->itemCardIntransitService->update($company, $itemCardParams);
				$itemCardUpdated = true;
			}
			catch(SoapFault $fault)
			{
				$companyResult->itemCard = null;
				$companyResult->error = $fault->getMessage();
			}
			if(!$itemCardUpdated)
			{
				$result->companies[] = $companyResult;
				continue;
			}
			$companyResult->itemCard = $navProduct;
			$companyResult->error = null;
			$result->companies[] = $companyResult;

			if(array_has($params,'image'))
			{
				try{
					$this->pictureService->setImage($company,$params['nav_id'],$params['image']);
				}
				catch(SoapFault $fault)
				{

				}
			}
		}
		$this->cmsService->updateProduct($params['nav_id'],$params);
		$result->cms = true;

		$rosProduct = Product::find($id);
		$rosProduct->name = $params['name'];
		$rosProduct->base_unit_of_measure = $params['base_unit_of_measure'];
		$rosProduct->sales_unit_of_measure = $params['sales_unit_of_measure'];
		$rosProduct->purchase_unit_of_measure = $params['purchase_unit_of_measure'];
		$rosProduct->category()->associate($category);
		$rosProduct->collection()->associate($collection);
		$rosProduct->colour()->associate($colour);
		$rosProduct->save();
		$result->product = $rosProduct;
		return $result;
		/*$companies = Company::all();
		foreach ($companies as $company)
		{
			$region = Region::find($company->region_id);
			$company_region = $region->id;
			if(array_has($params,'suggest_selling_price'))
			{
				for($i = 0; $i < count($params['suggest_selling_price']); $i++)
				{
					if($params['suggest_selling_price'][$i]['region'] == $company_region)
					{
						$params['unit_price'] = $params['suggest_selling_price'][$i]['amount'];
					}
				}
			}
			$navProduct = $this->itemCardIntransitService->update($company, $params);
		}
		$product = $this->find($id);
		$product->name = $params['name'];
		$product->category_code = $params['category_code'];
		$product->base_unit_of_measure = $params['base_unit_of_measure'];
		$product->purchase_unit_of_measure = $params['purchase_unit_of_measure'];
		$product->sales_unit_of_measure = $params['sales_unit_of_measure'];
		if(array_has($params, 'collection_code'))
			$product->collection_code = $params['collection_code'];
		if(array_has($params, 'colour'))
			$product->colour_code = $params['colour'];
		$product->save();
		$product->setItemCardIntransit($navProduct);
		return $product;*/
	}
	public function addCollectionPrice($company, $category_code, $collection_code, $price)//#734 when price is set at collection level, it should be updated for all products with that collection
	{
		$products = Product::where('category_code','=',$category_code)->where('collection_code','=',$collection_code)->get();
		foreach($products as $product)
		{
			//$product_price = Price::where('product_id', '=', $product->id)->first();
			$product_price = $company->productPrices()->where('product_id', '=', $product->id)->first();
			if($product_price)
			{
				$product_price->price = $price;
				$product_price->save();
				$cart_items = CartItem::where('product_id', '=', $product->id)->get();
				foreach($cart_items as $cartItem)
				{
					$cartItem->unit_price = $price;
					$cartItem->save();
				}
				$blocked_items = BlockedItem::where('product_id', '=', $product->id)->get();
				foreach($blocked_items as $blockedItem)
				{
					$blockedItem->unit_price = $price;
					$blockedItem->save();
				}
				$advance_items = AdvanceItem::where('product_id', '=', $product->id)->get();
				foreach($advance_items as $advanceItem)
				{
					$advanceItem->unit_price = $price;
					$new_discounted_price = $price - ($price * $advanceItem->discount / 100);
					$advanceItem->discounted_price = $new_discounted_price;
					$new_total_price = $new_discounted_price * $advanceItem->order_quantity;
					$advanceItem->total_price = $new_total_price;
					$newTaxAmount = ($new_discounted_price * $advanceItem->tax_percent/100) * $advanceItem->order_quantity;
					$advanceItem->tax_amount = $newTaxAmount;
					$new_net_amount = $new_total_price + $newTaxAmount;
					$advanceItem->net_amount = $new_net_amount;
					$advanceItem->save();
				}
			}
			else
			{
				$product_price = new Price;
				$product_price->price = $price;
				$product_price->product_id = $product->id;
				$company->productPrices()->save($product_price);			
			}
		}
		$log = new Log;
		$log->action = 'Products Price set';
		$log->author()->associate(Auth::user());
		$log->ip_address = \Request::ip();
		$log->comment = 'Price for the Products with Category: ' . $category_code . ' and Collection: ' . $collection_code . ' has been set to '. $price;
		$product_price->logs()->save($log);
		$sender = Auth::user();
		$query = User::where('company_id','=',$company->id);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','companyAdmin');
			});
		$users = $query->get();

		foreach ($users as $user) {
			$recipients[] = $user;
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
		}//add Company Admin Email Ids
		$superAdminEmailQuery = User::where('distributor_id','=',null)
					->where('dealer_id','=',null)
					->where('super_stockist_id','=',null)
					->where('company_id','=',null);
		$superAdminEmailQuery->whereHas('roles', function($q){
				$q->where('name', '=', 'superadmin');
			});
		$superAdminEmailIds = $superAdminEmailQuery->get();
		foreach ($superAdminEmailIds as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		}//add Super Admin Email Ids

		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		$product_name = $category_code . ' -> ' . $collection_code;
		$Qmessage = (new MUPSet($product_name, $price))->onQueue($queue);
		try
	    {
	    	if(!empty($toEmailIds))
        		Mail::to($toEmailIds)->queue($Qmessage);
       	}
       	catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
	    foreach ($recipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'Product Price set!';
			$notification->text = 'Price for the Products with Category: ' . $category_code . ' and Collection: ' . $collection_code . ' has been set to '. $price;
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification));
		}
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
				->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
				->where('region_id', '=', $region_id)
				->where('category_code', '=', $product->category_code)
				->where('collection_code', '=', $product->collection_code)
				->whereNull('customer_id')
				->whereNull('colour_code')
				->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
				->where('region_id', '=', $region_id)
				->where('category_code', '=', $product->category_code)
				->whereNull('customer_id')
				->whereNull('collection_code')
				->whereNull('colour_code')
				->limit(1)
			)->union(
				Discount::where('customer_type', '=', $customer_type)
				->where('region_id', '=', $region_id)
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

	public function updateStatus(int $id ,Array $params)
	{

		$companies = Company::all();
		$result = new stdClass;
		$result->product = null;
		$result->cms = false;
		$result->companies = [];

		// $this->cmsService->updateProduct($params['nav_id'],$params);
		$result->cms = true;

		$rosProduct = Product::withoutGlobalScopes()->find($id);
		$rosProduct->status = ($rosProduct->status==1)?0:1;
		$rosProduct->save();
		$result->product = $rosProduct;
		return $result;
	}	
}