<?php
namespace Rosetta\Services\Companies;

use Carbon\Carbon;
use Rosetta\Models\User;
use Rosetta\Models\Dealer;
use Rosetta\Models\Company;
use Rosetta\Models\Setting;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Stock;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Rosetta\NAV\WS\Customer\ReadMultiple;
use Rosetta\Services\NAV\CustomerService;
use Rosetta\Models\Company\BillingLocation;
use Rosetta\Models\AdvanceOrder\AdvanceItem;
use Rosetta\NAV\WS\Customer\Customer_Card_Fields;
use Rosetta\NAV\WS\Customer\Customer_Card_Filter;
use Rosetta\NAV\WS\Customer\Customer_Card_Service;
use Rosetta\Services\Region\RegionServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Services\NAV\ItemCardIntransitServiceInterface;
use Rosetta\Services\NAV\ItemCardServiceInterface;

class CompanyService implements CompanyServiceInterface
{
	protected $regionService;
	protected $itemCardIntransitService;
	protected $itemCardService;
	public function __construct(RegionServiceInterface $regionService,ItemCardIntransitServiceInterface $itemCardIntransitService, ItemCardServiceInterface $itemCardService)
	{
		$this->regionService = $regionService;
		$this->itemCardIntransitService = $itemCardIntransitService;
		$this->itemCardService = $itemCardService;
	}
	public function create(User $user, Array $params)
	{
		$company = new Company;
		$company->name = $params['name'];
		$company->database= $params['database'];
		$region = $this->regionService->find($params['region']);
		$company->region()->associate($region);
		$company->author()->associate($user);
		$company->save();
		return $company;
	}
	public function find(int $id)
	{
		$company = Company::find($id);
		if(!$company)
		{
			throw new ModelNotFoundException;
		}
		return $company;
	}
	public function update(User $user,int $id ,Array $params)
	{
		$company = $this->find($id);
		$company->name = $params['name'];
		if(array_has($params, 'database'))
			$company->database = $params['database'];
		$region = $this->regionService->find($params['region']);
		$company->region()->associate($region);
		$company->save();
		return $company;
	}
	public function all(Array $params = [])
	{
		if(array_has($params,'page'))
		{
			return Company::paginate($params['per_page'],['*'],'',$params['page']);
		}
		return Company::all();
	}
	public function createBillingLocation(User $user, Array $params, $companyId)
	{
		$billingLocation = new BillingLocation;
		$billingLocation->code = $params['code'];
		$billingLocation->name= $params['name'];
		$billingLocation->company_id= $companyId;
		$billingLocation->save();
		return $billingLocation;
	}
	public function getInventory(User $user, $companyId,Array $params = [], $customer)
	{
		$company = $this->find($companyId);
		$itemCards = $this->itemCardIntransitService->getInventory($company,$params);
		//$itemCards = $this->itemCardService->getInventory($company,$params);
		//$categories = Category::where('active', '=', 1)->get();
		//$collections = Collection::where('active', '=', 1)->get();
		//$colours = Colour::where('active', '=', 1)->get();
		$itemCardList = [];
		if(!$customer)
			$customer = $company;
		foreach ($itemCards as $itemCard)
		{
			$activeCategory = //$customer->activeProductCategories()->
			ActiveProductAttributes::where('category_code','=', $itemCard->getItem_Category_Code())
								->first();
								
			if($activeCategory)
			{
				$activeCollection = //$customer->activeProductCollections()->
				ActiveProductAttributes::where('collection_code', '=', $itemCard->getProduct_Group_Code())
									->where('category_code', '=', $itemCard->getItem_Category_Code())
									->first();
					

				if($activeCollection)
				{
					if($itemCard->getColour())
					{
						$colourCnt = //$customer->activeProductColours()->
						ActiveProductAttributes::where('category_code', '=', $itemCard->getItem_Category_Code())->where('collection_code', '=', $itemCard->getProduct_Group_Code())->count();

						if(!$colourCnt)
						{
							$itemCardList[] = $itemCard;
							continue;
						}
						$activeColour = //$customer->activeProductColours()->
						ActiveProductAttributes::where('colour_code', '=', $itemCard->getColour())
										->where('collection_code', '=', $itemCard->getProduct_Group_Code())
										->where('category_code', '=', $itemCard->getItem_Category_Code())
										->first();
						if($activeColour)
						{
							$itemCardList[] = $itemCard;
						}
					}
					else
					{
						$itemCardList[] = $itemCard;
						continue;
					}
				}
			}
		}
		return $itemCardList;
	}
	public function validate($database)
	{
		$customerCardService = new Customer_Card_Service(["location" => config('nav.host').rawurlencode($database).'/' .CustomerService::LOCATION],CustomerService::WSDL);
		$customerCardFilter = new Customer_Card_Filter(Customer_Card_Fields::Customer_Posting_Group,'DISTRIBUTO','DISTRIBUTO');
		$readMultiple = new ReadMultiple($customerCardFilter,null,1);
		$distributors = $customerCardService->ReadMultiple($readMultiple);
	}
	public function distributorStock($companyID,$params, $user_type)
	{
		$filters = $params['params']['filters'];
		$activeCategoryCodes = [];
		$activeCollectionCodes = [];
		$activeColourCodes = [];
		$activeCategories =  $user_type->activeProductCategories()->get();
		foreach ($activeCategories as $activeCategory) {
			$activeCategoryCodes[] = $activeCategory->category_code;
		}
		$activeCollections =  $user_type->activeProductCollections()->get();
		foreach ($activeCollections as $activeCollection) {
			$activeCollectionCodes[] = $activeCollection->collection_code;
		}
		$activeColours =  $user_type->activeProductColours()->get();
		foreach ($activeColours as $activeColour) {
			$activeColourCodes[] = $activeColour->colour_code;
		}
		$query = Stock::distributors()->whereHas('distributor',function ($q) use ($companyID,$filters){
			$q->where('company_id','=',$companyID);
			if(array_has($filters,'state'))
			{
				$q->where('state_code','=',$filters['state']);
			}
		});
		$query->whereHas('product',function($q1) use($activeCategoryCodes, $activeCollectionCodes, $activeColourCodes){
				$q1->whereIn('category_code',$activeCategoryCodes);
		//		$q1->whereIn('collection_code',$activeCollectionCodes);
		//		$q1->whereIn('colour_code',$activeColourCodes);
			});
	/*	$filters = $params['params']['filters'];
		$query = Stock::distributors()->whereHas('distributor',function ($q) use ($companyID,$filters){
			$q->where('company_id','=',$companyID);
			if(array_has($filters,'state'))
			{
				$q->where('state_code','=',$filters['state']);
			}
		});*/
		if(array_has($filters,'category'))
		{
			$query->whereHas('product',function($q) use($filters){
				$q->where('category_code','=',$filters['category']);
				if(array_has($filters,'collection'))
				{
					$q->where('collection_code','=',$filters['collection']);
				}
				if(array_has($filters,'colour'))
				{
					$q->where('colour_code','=',$filters['colour']);
				}
			});
		}
		if(array_has($filters,'quantity'))
		{
			preg_match('/([=<>])(\d+)/',urldecode($filters['quantity']),$matches);
			$operator = $matches[1];
			$quantity = $matches[2];
			$sum = $query->sum('quantity');
			
			if($operator == '>')
			{
				if($sum < $quantity)
				{
					/*
						available quantity is less than required, bump required quntity so that query will return empty result.
					 */
					$query->where('quantity',$operator,$quantity+100000);
				}
			}
			else
			{
				$query->where('quantity',$operator,$quantity);
			}
			
		}

		return $query->paginate($params['params']['per_page'],['*'],'',$params['params']['page']);
	}

		public function blockedItems($companyID,Array $params)
	{
		$company = $this->find($companyID);
		$customer = null;
		if(array_has($params, 'selectedCustomerType') && array_has($params, 'selectedCustomerId'))
		{
			if($params['selectedCustomerType'] == "Distributor")
	            $customer = Distributor::find($params['selectedCustomerId']);
	        elseif($params['selectedCustomerType'] == "Dealer")
	            $customer = Dealer::find($params['selectedCustomerId']);
		}
		if($customer)
		{
			if($customer->distributor_id == null)
			{
				$queryMain = $customer->blockedItems()/*->whereNull('distributor_id')->whereNull('super_stockist_id')*/->active();
			}
			else
			{
				$queryMain = $customer->blockedItems()->where('blockable_type','=',Dealer::class)
				/*->whereNull('distributor_id')->whereNull('super_stockist_id')*/->active();
			}			
		}
		else
		{
			if(array_has($params, 'selectedCustomerTypeCode'))
			{
				if($params['selectedCustomerTypeCode'] == "DISTRIBUTO")
		         	$queryMain = $company->blockedItems()->where('blockable_type','=',Distributor::class)/*->whereNull('distributor_id')->whereNull('super_stockist_id')*/->active();
		        elseif($params['selectedCustomerTypeCode'] == "DEALER")
		           $queryMain = $company->blockedItems()->where('blockable_type','=',Dealer::class)/*->whereNull('distributor_id')->whereNull('super_stockist_id')*/->active();
		        else
		        	$queryMain = $company->blockedItems()/*->whereNull('distributor_id')->whereNull('super_stockist_id')*/->active();
			}
			else
				$queryMain = $company->blockedItems()/*->whereNull('distributor_id')->whereNull('super_stockist_id')*/->active();
		}
		if(array_has($params,'page'))
		{
			$blockedItems = $queryMain->when($params, function ($query) use ($params){
				if(array_has($params, 'category'))
				{
					//$query->where('category_code', '=', $params['category']);
					$query->whereHas('product',function($q) use($params){
						$q->where('category_code','=',$params['category']);
						if(array_has($params,'collection'))
						{
							$q->where('collection_code','=',$params['collection']);
						}
						if(array_has($params,'colour'))
						{
							$q->where('colour_code','=',$params['colour']);
						}
					});
				}
			})->orderBy('created_at','desc')->paginate($params['per_page']);
		//	$blockedItems = $queryMain->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$blockedItems = $queryMain->when($params, function ($query) use ($params){
		/*		if(array_has($params, 'status'))
				{
					$query->where('order_status', '=', $params['status']);
				}*/
			})->orderBy('created_at','desc')->get();
		}
		return $blockedItems;

		/*
		$query = $company->blockedItems()->active()->whereNull('distributor_id');
		if(array_has($params,'page'))
		{
			return $query->paginate($params['per_page'],['*'],'',$params['page']);
		}
		return $query->get();	*/
	}
	
	public function blockedItems_bck_10_AUG_2025($companyID,Array $params)
	{
		$company = $this->find($companyID);
		$customer = null;
		if(array_has($params, 'selectedCustomerType') && array_has($params, 'selectedCustomerId'))
		{
			if($params['selectedCustomerType'] == "Distributor")
	            $customer = Distributor::find($params['selectedCustomerId']);
	        elseif($params['selectedCustomerType'] == "Dealer")
	            $customer = Dealer::find($params['selectedCustomerId']);
		}
		if($customer)
		{
			if($customer->distributor_id == null)
			{
				$queryMain = $customer->blockedItems()->whereNull('distributor_id')->active();
			}
			else
			{
				$queryMain = $customer->blockedItems()->where('blockable_type','=',Dealer::class)
				->whereNull('distributor_id')->active();
			}			
		}
		else
		{
			if(array_has($params, 'selectedCustomerTypeCode'))
			{
				if($params['selectedCustomerTypeCode'] == "DISTRIBUTO")
		         	$queryMain = $company->blockedItems()->where('blockable_type','=',Distributor::class)->whereNull('distributor_id')->active();
		        elseif($params['selectedCustomerTypeCode'] == "DEALER")
		           $queryMain = $company->blockedItems()->where('blockable_type','=',Dealer::class)->whereNull('distributor_id')->active();
		        else
		        	$queryMain = $company->blockedItems()->whereNull('distributor_id')->active();
			}
			else
				$queryMain = $company->blockedItems()->whereNull('distributor_id')->active();
		}
		if(array_has($params,'page'))
		{
			$blockedItems = $queryMain->when($params, function ($query) use ($params){
				if(array_has($params, 'category'))
				{
					//$query->where('category_code', '=', $params['category']);
					$query->whereHas('product',function($q) use($params){
						$q->where('category_code','=',$params['category']);
						if(array_has($params,'collection'))
						{
							$q->where('collection_code','=',$params['collection']);
						}
						if(array_has($params,'colour'))
						{
							$q->where('colour_code','=',$params['colour']);
						}
					});
				}
			})->orderBy('created_at','desc')->paginate($params['per_page']);
		//	$blockedItems = $queryMain->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$blockedItems = $queryMain->when($params, function ($query) use ($params){
		/*		if(array_has($params, 'status'))
				{
					$query->where('order_status', '=', $params['status']);
				}*/
			})->orderBy('created_at','desc')->get();
		}
		return $blockedItems;

		/*
		$query = $company->blockedItems()->active()->whereNull('distributor_id');
		if(array_has($params,'page'))
		{
			return $query->paginate($params['per_page'],['*'],'',$params['page']);
		}
		return $query->get();	*/
	}
	public function blockedAdvanceItems($companyID,Array $params)
	{
		$company = $this->find($companyID);
		$customer = null;
		if(array_has($params, 'selectedCustomerType') && array_has($params, 'selectedCustomerId'))
		{
			if($params['selectedCustomerType'] == "Distributor")
	            $customer = Distributor::find($params['selectedCustomerId']);
	        elseif($params['selectedCustomerType'] == "Dealer")
	            $customer = Dealer::find($params['selectedCustomerId']);
		}
		if($customer)
		{
			if($customer->distributor_id == null)
			{
				$queryMain = $customer->blockedAdvanceItems()->whereNull('distributor_id')->active();
			}
			else
			{
				$queryMain = $customer->blockedAdvanceItems()->where('blockable_type','=',Dealer::class)
				->whereNull('distributor_id')->active();
			}			
		}
		else
		{
			if(array_has($params, 'selectedCustomerTypeCode'))
			{
				if($params['selectedCustomerTypeCode'] == "DISTRIBUTO")
		         	$queryMain = $company->blockedAdvanceItems()->where('blockable_type','=',Distributor::class)->whereNull('distributor_id')->active();
		        elseif($params['selectedCustomerTypeCode'] == "DEALER")
		           $queryMain = $company->blockedAdvanceItems()->where('blockable_type','=',Dealer::class)->whereNull('distributor_id')->active();
		        else
		        	$queryMain = $company->blockedAdvanceItems()->whereNull('distributor_id')->active();
			}
			else
				$queryMain = $company->blockedAdvanceItems()->whereNull('distributor_id')->active();
		}
		if(array_has($params,'page'))
		{
			$blockedItems = $queryMain->when($params, function ($query) use ($params){
				if(array_has($params, 'category'))
				{
					//$query->where('category_code', '=', $params['category']);
					$query->whereHas('product',function($q) use($params){
						$q->where('category_code','=',$params['category']);
						if(array_has($params,'collection'))
						{
							$q->where('collection_code','=',$params['collection']);
						}
						if(array_has($params,'colour'))
						{
							$q->where('colour_code','=',$params['colour']);
						}
					});
				}
			})->orderBy('created_at','desc')->paginate($params['per_page']);
		//	$blockedItems = $queryMain->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$blockedItems = $queryMain->when($params, function ($query) use ($params){
		/*		if(array_has($params, 'status'))
				{
					$query->where('order_status', '=', $params['status']);
				}*/
			})->orderBy('created_at','desc')->get();
		}
		return $blockedItems;

		/*
		$query = $company->blockedItems()->active()->whereNull('distributor_id');
		if(array_has($params,'page'))
		{
			return $query->paginate($params['per_page'],['*'],'',$params['page']);
		}
		return $query->get();	*/
	}
	public function dudBlockedCompanyStock($companyId,$distributor,Array $params)
	{
		$company = $this->find($companyId);
		$dealerIds = $distributor->dealers()->pluck('id')->toArray();
		$query = $company->blockedItems()->active()->whereNull('distributor_id')->where('blockable_type','=', Dealer::class)->whereIn('blockable_id',$dealerIds);
		if(array_has($params,'page'))
		{
			return $query->paginate($params['per_page'],['*'],'',$params['page']);
		}
		return $query->get();	
	}
	public function getAdvanceItems(Array $params = [])
	{
		$query = null;
    	if(array_has($params,'customer_id'))
    	{
    		$query = AdvanceItem::where('advanceable_id','=',$params['customer_id']);
    	}
    	if(array_has($params,'customer_type'))
    	{
    		if($params['customer_type'] == 'DISTRIBUTO')
    			$customer_type = Distributor::class;
    		if($params['customer_type'] == 'DEALER')
    			$customer_type = Dealer::class;
    		if($query)
    			$query->where('advanceable_type','=',$customer_type);
    		else
    			$query = AdvanceItem::where('advanceable_type','=',$customer_type);
    	}
    	if(array_has($params,'page'))
		{
			if($query)
			{
				return $query->paginate($params['per_page'],['*'],'',$params['page']);	
			}
			return AdvanceItem::paginate($params['per_page'],['*'],'',$params['page']);
		}
		if($query)
		{
			return $query->get();
		}
		return AdvanceItem::all();
	}
	public function exportOrders(int $companyId, Array $params)
	{
		$company = Company::find($companyId);
		$customer = null;
		if(array_has($params, 'selectedCustomerType') && array_has($params, 'selectedCustomerId'))
		{
			if($params['selectedCustomerType'] == "Distributor")
	            $customer = Distributor::find($params['selectedCustomerId']);
	        elseif($params['selectedCustomerType'] == "Dealer")
	            $customer = Dealer::find($params['selectedCustomerId']);
		}
		if($customer)
		{
			if($customer->distributor_id == null)
				$queryMain = $customer->blanketOrders()->concrete();//->whereNull('distributor_id'); #422
			else
				$queryMain = $company->blanketOrders()->where('orderable_type','=',Dealer::class)
				->whereNull('distributor_id')->concrete();
		}
		else
		{
			if(array_has($params, 'selectedCustomerTypeCode'))
			{
				if($params['selectedCustomerTypeCode'] == "DISTRIBUTO")
		         	$queryMain = $company->blanketOrders()->where('orderable_type','=',Distributor::class)->concrete();
		        elseif($params['selectedCustomerTypeCode'] == "DEALER")
		           $queryMain = $company->blanketOrders()->where('orderable_type','=',Dealer::class)->concrete();
		        else
		        	$queryMain = $company->blanketOrders()->concrete();
			}
			else
				$queryMain = $company->blanketOrders()->concrete();
		}
		$blanketOrders = $queryMain->when($params, function ($query) use ($params){
			if(array_has($params, 'status'))
			{
				$query->where('order_status', '=', $params['status']);
			}
			if(array_has($params,'so'))
			{
				//$query->where('navision_order_number', '=', $params['so']);
				$query->whereHas('purchase_orders',function($q) use($params){
					$q->where('navision_order_number','=',$params['so']);
				});
			}
			if(array_has($params,'si'))//#704
			{
				$query->whereHas('purchase_orders',function($q) use($params){
					$q->where('nav_invoice_number','=',$params['si']);
				});
			}
			if(array_has($params,'stock_point_id'))//#762
			{
				$query->whereHas('purchase_orders',function($q) use($params){
					$q->where('stock_point_id','=',$params['stock_point_id']);
				});
			}
			if(array_has($params,'start_date'))
			{
				$query->where('created_at', '>=', $params['start_date']);
				$query->where('created_at', '<=', $params['end_date']);
			}
			$query->where('order_status', '<>', 'Cancelled');
		})->orderBy('created_at','desc')->get();
		if($blanketOrders)
		{
			$spreadsheet = new Spreadsheet();
	        $activeSheet = $spreadsheet->getActiveSheet()->setTitle("Company Orders");;
	        $activeSheet->setCellValue('A1' , 'PO #')->getStyle('A1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('B1' , 'SO #')->getStyle('B1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('C1' , 'SI #')->getStyle('C1')->getFont()->setBold(true);  
	        $activeSheet->setCellValue('D1' , 'Stock Point')->getStyle('D1')->getFont()->setBold(true);  
	        $activeSheet->setCellValue('E1' , 'Customer NAV ID')->getStyle('E1')->getFont()->setBold(true);  
	        $activeSheet->setCellValue('F1' , 'Customer')->getStyle('F1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('G1' , 'Dealer NAV ID')->getStyle('G1')->getFont()->setBold(true);  
	        $activeSheet->setCellValue('H1' , 'Dealer')->getStyle('H1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('I1' , 'Dealer Type')->getStyle('I1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('J1' , 'Item #')->getStyle('J1')->getFont()->setBold(true);   
	        $activeSheet->setCellValue('K1' , 'Item Name')->getStyle('K1')->getFont()->setBold(true);   
	        $activeSheet->setCellValue('L1' , 'Quantity')->getStyle('L1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('M1' , 'Net Item Amount')->getStyle('M1')->getFont()->setBold(true);  
	        $activeSheet->setCellValue('N1' , 'Total Item Amount')->getStyle('N1')->getFont()->setBold(true);  
	        $activeSheet->setCellValue('O1' , 'Net Order Amount')->getStyle('O1')->getFont()->setBold(true);   
	        $activeSheet->setCellValue('P1' , 'Total Order Amount')->getStyle('P1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('Q1' , 'Order Amount Paid')->getStyle('Q1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('R1' , 'Order Date')->getStyle('R1')->getFont()->setBold(true); 
	        $activeSheet->setCellValue('S1' , 'Order Source')->getStyle('S1')->getFont()->setBold(true);   
	        
	        $row = 2;
			foreach ($blanketOrders as $blanketOrder)
	        {
	            $purchase_lines = $blanketOrder->purchase_lines;
	            foreach($purchase_lines as $line)
	            {
	            	$activeSheet->setCellValue('A'.$row, $blanketOrder->order_number);
	            	$activeSheet->setCellValue('B'.$row, $line->purchase_order->navision_order_number);
	            	$activeSheet->setCellValue('C'.$row, $line->purchase_order->nav_invoice_number);
	            	$activeSheet->setCellValue('D'.$row, $line->purchase_order->stock_point->name);
	            	$activeSheet->setCellValue('E'.$row, $blanketOrder->orderable->nav_id);
	            	$activeSheet->setCellValue('F'.$row, $blanketOrder->orderable->name);
	            	if($blanketOrder->relatedOrder)
					{	
	            		$activeSheet->setCellValue('G'.$row, $blanketOrder->relatedOrder->orderable->nav_id);
	            		$activeSheet->setCellValue('H'.$row, $blanketOrder->relatedOrder->orderable->name);
	            		$activeSheet->setCellValue('I'.$row, $blanketOrder->relatedOrder->orderable->type);
					}
	            	$activeSheet->setCellValue('J'.$row, $line->product->nav_id);
	            	$activeSheet->setCellValue('K'.$row, $line->product->name);
	            	$activeSheet->setCellValue('L'.$row, $line->quantity);
	            	$activeSheet->setCellValue('M'.$row, $line->amount);
	            	$activeSheet->setCellValue('N'.$row, $line->net_amount);
	            	$activeSheet->setCellValue('O'.$row, $blanketOrder->net_amount());
	            	$activeSheet->setCellValue('P'.$row, $blanketOrder->amount);
	            	$activeSheet->setCellValue('Q'.$row, $blanketOrder->paid_amount());
	            	$activeSheet->setCellValue('R'.$row, $blanketOrder->created_at);
	            	if($blanketOrder->source != null)
	            		$activeSheet->setCellValue('S'.$row, $blanketOrder->source);
	            	else
	            		$activeSheet->setCellValue('S'.$row, 'ROS');
	           		$row++;
	           	}
	        }
	        for($col = 'A'; $col !== 'Q'; $col++) 
	        {
	            $activeSheet->getColumnDimension($col)->setAutoSize(true);
	        }
	        $writer = new Xlsx($spreadsheet);
	        $file = storage_path() . '/app/CompanyOrders_'.$companyId.'.xlsx';
	        $writer->save($file);
	        return $file;
		}
	}//#814

	public function getCompanyTenants(int $companyId)
	{
		$setting = Setting::where('key','threshold_companies')->first();
		if($setting)
		{
			$data = json_decode($setting->value, true);
			$tenants = array_get($data,"{$companyId}");
			return array_keys($tenants);
		}
		return [];
	}
	public function getCompanyTenantStockpoints(int $companyId, String $tenant)
	{
		$setting = Setting::where('key','threshold_companies')->first();
		if(!$setting)
		{

		}
		$data = json_decode($setting->value, true);
		$stockpoints = array_get($data,"{$companyId}.{$tenant}");
		return StockPoint::where('company_id',$companyId)->whereIn('code',$stockpoints)->get();
	}
}