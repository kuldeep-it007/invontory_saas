<?php
namespace Rosetta\Services\Distributor;

use Carbon\Carbon;
use Rosetta\Models\User;
use Rosetta\Models\Dealer;
use Rosetta\Models\stocks;
use Rosetta\Models\Company;
use Rosetta\Models\ReturnItem;
use Rosetta\Models\Distributor;
use Rosetta\Models\Notification;
use Illuminate\Http\UploadedFile;
use Rosetta\Events\Notifications;
use Rosetta\Models\Cart\CartItem;
use Rosetta\Models\Product\Stock;
use Illuminate\Support\Facades\DB;
use Rosetta\Models\Product\Product;
use Illuminate\Support\Facades\Mail;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Exceptions\RedisException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Predis\Connection\ConnectionException;
use Rosetta\Services\NAV\STAServiceInterface;
use Rosetta\Mail\SuperStockist\AccountCreated;
use Rosetta\Models\PurchaseOrder\PurchaseLine;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Services\NAV\CustomerServiceInterface;
use Rosetta\Services\Engineer\EngineerServiceInterface;
use Rosetta\Exceptions\FileImport\FileNotFoundException;
use Rosetta\Notifications\SMS\AccountCreatedNotification;
use Rosetta\Services\Distributor\DistributorServiceInterface;
use Illuminate\Support\Facades\Notification as SMSNotification;
use Rosetta\Services\SuperStockist\SuperStockistServiceInterface;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DistributorService implements DistributorServiceInterface
{

	protected $customerService;
	protected $staService, $superStockistService, $activeProductAttributesService;
	public function __construct(CustomerServiceInterface $customerService,STAServiceInterface $staService, EngineerServiceInterface $engineerService, SuperStockistServiceInterface $superStockistService,ActiveProductAttributesServiceInterface $activeProductAttributesService)
	{
		$this->customerService = $customerService;
		$this->staService = $staService;
		$this->engineerService = $engineerService;
		$this->superStockistService = $superStockistService;
		$this->activeProductAttributesService = $activeProductAttributesService;
	}
	/*#894
	public function getDistributors(Company $company,Array $params= [])
	{
		if(array_has($params,'page'))
		{
			$distributors = $company->distributors()->when($params, function ($query) use ($params){
				if(array_has($params, 'customer_name'))
				{
					$query->where('name', 'like', '%' . $params['customer_name'] . '%');
				}
				if(array_has($params,'customer_id'))
				{
					$query->where('nav_id', '=', $params['customer_id']);
				}
			})->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$distributors = $company->distributors;
		}
		return $distributors;
	}*/
	public function getDistributors(Company $company, Array $params, SuperStockist $superStockist=null)
	{ 
		if($superStockist == null)
		{
			if(array_has($params,'page'))
			{
				$distributors = $company->distributors()->when($params, function ($query) use ($params){
					if(array_has($params, 'customer_name'))
					{
						$query->where('name', 'like', '%' . $params['customer_name'] . '%');
					}
					if(array_has($params,'customer_id'))
					{
						$query->where('nav_id', '=', $params['customer_id']);
					}
					if(array_has($params,'isDistrubutorUnderSs') && $params['isDistrubutorUnderSs']==true){
						$query->whereNotNull('super_stockist_id');
					}
				})->orderBy('created_at','desc')->paginate($params['per_page']);
			}
			else
			{
				if(array_has($params,'onlyCompany'))
					$distributors = $company->distributors()->whereNull('super_stockist_id')->get();
				else
				$distributors = $company->distributors()->when($params, function ($query) use ($params){
					
					if(array_has($params,'isDistrubutorUnderSs') && $params['isDistrubutorUnderSs']==true){
						$query->whereNotNull('super_stockist_id');
					}
				})->get();
			}
			return $distributors;
		}
		else
		{
			if(array_has($params,'page'))
			{
				$distributors = $superStockist->distributors()->when($params, function ($query) use ($params){
					if(array_has($params, 'customer_name'))
					{
						$query->where('name', 'like', '%' . $params['customer_name'] . '%');
					}
					if(array_has($params,'customer_id'))
					{
						$query->where('nav_id', '=', $params['customer_id']);
					}
				})->orderBy('created_at','desc')->paginate($params['per_page']);
			}
			else
			{
				if(array_has($params,'withOrders'))//#750
				{
					$distributors = $superStockist->distributors()->whereHas('orders')->get();
				}
				elseif(array_has($params,'withCartItems'))//#749
				{
					$distributors = $superStockist->distributors()->whereHas('cartItems')->get();
				}
				elseif(array_has($params,'withBlockedItems'))//#749
				{
					$distributors = $superStockist->distributors()->whereHas('blockedItems')->get();
				}
				else
					$distributors = $superStockist->distributors;
			}
			return $distributors;
		}
	}
	public function create(User $user,Company $company,Array $params, SuperStockist $superStockist = null)
	{
		$navDistributor = $this->customerService->createDistributor($company, $params);
		$distributor = new Distributor;
		$distributor->name = $params['name'];
		if(array_has($params, 'contact'))
			$distributor->contact = $params['contact'];
		if(array_has($params, 'phone'))
			$distributor->phone = $params['phone'];
		if(array_has($params, 'email'))
			$distributor->email = $params['email'];
		$distributor->post_code = $params['zipcode'];
		$distributor->state_code = $params['state'];
		$distributor->city_code = $params['city'];
		if(array_has($params, 'address'))
			$distributor->address = $params['address'];
		if(array_has($params, 'address2'))
			$distributor->address_2 = $params['address2'];
		$distributor->nav_id = $navDistributor->getNo();
		$distributor->company()->associate($company);
		$distributor->author()->associate($user);
		if($superStockist != null)
			$distributor->superStockist()->associate($superStockist);
		$distributor->save();
		$distributor->setCustomerCard($navDistributor);

		$this->staService->createAddress($company,$distributor->nav_id,$params);//#842

		return $distributor;
	}
	public function resetPermissions(User $user, Company $company, Distributor $distributor, Array $params)
	{
		if(array_has($params, 'deny_all'))
		{
			$p1 = Permission::where('name','=','deny_add_to_cart')->first();
			$p2 = Permission::where('name','=','deny_block')->first();
			//$p3 = Permission::where('name','=','deny_place_order')->first();
			$p4 = Permission::where('name','=','deny_advance_ordering')->first();
			if($params['deny_all'] == true)
				$distributor->givePermissionTo([$p1,$p2,$p3,$p4]);
			else
				$distributor->revokePermissionTo([$p1,$p2,$p3,$p4]);
		}
		else
		{
			if(array_has($params, 'deny_add_to_cart'))
			{
				$p1 = Permission::where('name','=','deny_add_to_cart')->first();
				if($params['deny_add_to_cart'] == true)
					$distributor->givePermissionTo($p1);
				else
					$distributor->revokePermissionTo($p1);
			}
			if(array_has($params, 'deny_block'))
			{
				$p2 = Permission::where('name','=','deny_block')->first();
				if($params['deny_block'] == true)
					$distributor->givePermissionTo($p2);
				else
					$distributor->revokePermissionTo($p2);
			}
		/*	if(array_has($params, 'deny_place_order'))
			{
				$p3 = Permission::where('name','=','deny_place_order')->first();
				if($params['deny_place_order'] == true)
					$distributor->givePermissionTo($p3);
				else
					$distributor->revokePermissionTo($p3);
			}*/
			if(array_has($params, 'deny_advance_ordering'))
			{
				$p4 = Permission::where('name','=','deny_advance_ordering')->first();
				if($params['deny_advance_ordering'] == true)
					$distributor->givePermissionTo($p4);
				else
					$distributor->revokePermissionTo($p4);
			}
		}
		return $distributor;
	}
	public function update(User $user, Company $company, Distributor $distributor, Array $params)
	{
		if($distributor->super_stockist_id)
			$superStockist = $distributor->superStockist;
		else
			$superStockist = null;
		if(array_has($params, 'newSuperStockistId'))//#827
		{
			if($params['newSuperStockistId'] != null)
			{
				$superStockist = SuperStockist::find($params['newSuperStockistId']);
				$distributor->super_stockist_id = $params['newSuperStockistId'];
			}
			else
			{
				$superStockist = null;
				$distributor->super_stockist_id = null;
			}
			
		}
		$navDistributor = $this->customerService->updateDistributor($company, $distributor,$params, $superStockist);
		if(array_has($params, 'name'))
			$distributor->name = $params['name'];
		if(array_has($params, 'display_name'))
			$distributor->display_name = $params['display_name'];
		if(array_has($params, 'address'))
			$distributor->address = $params['address'];
		if(array_has($params, 'address2'))
			$distributor->address_2 = $params['address2'];
		if(array_has($params, 'state'))
			$distributor->state_code = $params['state'];
		if(array_has($params, 'city'))
			$distributor->city_code = $params['city'];
		if(array_has($params, 'zipcode'))
			$distributor->post_code = $params['zipcode'];
		if(array_has($params, 'credit_limit'))
			$distributor->credit_limit = $params['credit_limit'];
		$distributor->save();
		$distributor->setCustomerCard($navDistributor);
		return $distributor;
	}
	public function find(int $id)
	{
		$distributor = Distributor::find($id);
		if(!$distributor)
		{
			throw new ModelNotFoundException;
		}
		return $distributor;
	}
	public function findForCompany(int $companyId,int $distributorId)
	{
		$company = Company::find($companyId);
		$distributor = $company->distributors()->find($distributorId);
		return $distributor;
	}
	public function findForSuperStockist(User $user,$companyID,$superStockistId,$distributorId)
	{
		$company = Company::find($companyID);
		$superStockist = $company->super_stockists()->find($superStockistId);
		$distributor = $superStockist->distributors()->find($distributorId);
		return $distributor;	
	}
	public function getShippingAddresses(int $companyId,int $distributorId)
	{
		$company = Company::find($companyId);
		$distributor = $company->distributors()->find($distributorId);
		return $this->staService->getAllAddresses($company,$distributor->nav_id);
	}
	public function createShippingAddresses(int $companyId,int $distributorId,Array $params)
	{
		$company = Company::find($companyId);
		$distributor = $company->distributors()->find($distributorId);
		return $this->staService->createAddress($company,$distributor->nav_id,$params);
	}
	public function updateShippingAddress(int $companyID, int $distributorID, $addressCode, Array $params)
	{
		$company = Company::find($companyID);
		$distributor = $company->distributors()->find($distributorID);
		return $this->staService->updateAddress($company,$distributor->nav_id, $addressCode, $params);
	}
	public function deleteAddress(int $companyID, int $distributorID, $addressCode)
	{
		$company = Company::find($companyID);
		$distributor = $company->distributors()->find($distributorID);
		return $this->staService->deleteAddress($company,$distributor->nav_id, $addressCode);
	}
	public function getInventory(int $distributorID, Array $params)
	{
		$distributor = $this->find($distributorID);
		$category = null;
		$collection = null;
		$colour = null;
		$query = $distributor->stocks()->where('quantity', '<>', 0);
		if(array_has($params['params'],'filters') && count($params['params']['filters']))
		{
			$filters = $params['params']['filters'];
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

				$query->where('quantity',$operator,$quantity);
			}
		}
		return $query->paginate($params['params']['per_page'],['*'],'',$params['params']['page']);
	}
	public function importStock($distributor, UploadedFile $file)
	{
		$validated = [
			'valid' => [],
			'invalid' => [],
		];
	    $spreadsheet = IOFactory::load($file->getRealPath());
        if(!file_exists($file))
        {
        	throw new FileNotFoundException;
        }
        $worksheet = $spreadsheet->getActiveSheet();
        $maxRow = $worksheet->getHighestRow();
        $distributorStock = $distributor->stocks();
        if($distributorStock)
        	$distributorStock->delete();
        for($i=2;$i<=$maxRow;$i++)
        {
            $nav_id = $worksheet->getCell('A'.$i)->getValue();
			if(empty($nav_id)){
				continue;
			}
            $quantity = $worksheet->getCell('E'.$i)->getCalculatedValue();
            $batch_no = $worksheet->getCell('F'.$i)->getValue();
            $product = Product::where('nav_id','=',$nav_id)->first();
            if(!$product)
            {
            	$validated['invalid'][] = [
            		'row' => $i,
            		'nav_id' => $nav_id,
            		'category' => $worksheet->getCell('B'.$i)->getValue(),
            		'collection' => $worksheet->getCell('C'.$i)->getValue(),
            		'colour' => $worksheet->getCell('D'.$i)->getValue(),
            		'batch_no' => $batch_no,
            		'quantity' => $quantity,
            		'reason' => 'Invalid Product'
            	];
            	continue;
            }
            /*find existing stock if any */
            $stock = $distributor->stocks()->where('product_id', '=', $product->id)
                        ->where('batch_no', '=', $batch_no)->first();

            if(!$stock)
            {
                if($quantity <= 0 || $quantity == '' || $quantity == null)
                {
                	$validated['invalid'][] = [
	            		'row' => $i,
	            		'nav_id' => $nav_id,
	            		'category' => $worksheet->getCell('B'.$i)->getValue(),
	            		'collection' => $worksheet->getCell('C'.$i)->getValue(),
	            		'colour' => $worksheet->getCell('D'.$i)->getValue(),
	            		'batch_no' => $batch_no,
	            		'quantity' => $quantity,
	            		'reason' => 'Invalid Quantity'
	            	];
	                continue;
                }
                else
                {
                    $stock = new Stock;
                    $stock->product_id = $product->id;
                    $stock->batch_no = $batch_no;
                    if($quantity == null)
                        $stock->quantity = 0;
                    else
                        $stock->quantity = $quantity;
                    $distributor->stocks()->save($stock);
                }
            }
            else
            {
            	$validated['invalid'][] = [
            		'row' => $i,
            		'nav_id' => $nav_id,
            		'category' => $worksheet->getCell('B'.$i)->getValue(),
            		'collection' => $worksheet->getCell('C'.$i)->getValue(),
            		'colour' => $worksheet->getCell('D'.$i)->getValue(),
            		'batch_no' => $batch_no,
            		'quantity' => $quantity,
            		'reason' => 'Stock for this batch already present'
            	];
                continue;
                //$stock->quantity += $quantity;
                //$stock->save();
            }
        }
        return $validated;
	}
	public function exportStock(int $distributorId, Array $params)
	{
		$distributor = $this->find($distributorId);
		$category = null;
		$collection = null;
		$colour = null;
		$query = $distributor->stocks()->where('quantity', '<>', 0);
		if(array_has($params['params'],'filters') && count($params['params']['filters']))
		{
			$filters = $params['params']['filters'];
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

				$query->where('quantity',$operator,$quantity);
			}
		}
		$inventory = $query->get();

		if($inventory)
		{
			$spreadsheet = new Spreadsheet();
	        $activeSheet = $spreadsheet->getActiveSheet()->setTitle("My Stock");;
	        $activeSheet->setCellValue('A1' , 'NAV ID')->getStyle('B1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('B1' , 'Category')->getStyle('C1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('C1' , 'Collection')->getStyle('D1')->getFont()->setBold(true);   
	        $activeSheet->setCellValue('D1' , 'Colour')->getStyle('E1')->getFont()->setBold(true);   
	        $activeSheet->setCellValue('E1' , 'Batch No')->getStyle('F1')->getFont()->setBold(true);   
	        $activeSheet->setCellValue('F1' , 'Quantity')->getStyle('G1')->getFont()->setBold(true);   
	        $row = 2;
			foreach ($inventory as $stock)
	        {
	            $activeSheet->setCellValue('A'.$row, $stock->product->nav_id);
	            $activeSheet->setCellValue('B'.$row, $stock->product->category->code);
	            $activeSheet->setCellValue('C'.$row, $stock->product->collection->code);
	            if($stock->product->colour)
	            	$activeSheet->setCellValue('D'.$row, $stock->product->colour->code);
	            $activeSheet->setCellValue('E'.$row, $stock->batch_no);
	            $activeSheet->setCellValue('F'.$row, $stock->quantity);
	            $row++;
	        }
	        for($col = 'A'; $col !== 'F'; $col++) 
	        {
	            $activeSheet->getColumnDimension($col)->setAutoSize(true);
	        }
	        $writer = new Xlsx($spreadsheet);
	        $file = storage_path() . '/app/DistributorStock_'.$distributorId.'.xlsx';
	        $writer->save($file);
	        return $file;
		}
	}
	protected function getIndividualDistributorInventory(Distributor $distributor, $customer,Array $params)
	{
		
		$category = null;
		$collection = null;
		$colour = null;
		$query = $distributor->stocks();
		$distributorID = $distributor->id;
		if(array_has($params,'category'))
		{
			$query->whereHas('product',function($q) use($params){
				$q->where('category_code','=',urldecode($params['category']));
				if(array_has($params,'collection'))
				{
					$q->where('collection_code','=',urldecode($params['collection']));
				}
				if(array_has($params,'colour'))
				{
					$q->where('colour_code','=',urldecode($params['colour']));
				}
			});
		}
	/*	if(array_has($params,'quantity'))
		{
			preg_match('/([=<>])(\d+)/',urldecode($params['quantity']),$matches);
			$operator = $matches[1];
			$quantity = $matches[2];
			$query->where('quantity',$operator,$quantity);
		}*/
		$inventories = $query->get();
		$uniqueProducts=[];

		foreach ($inventories as $inventory)
		{
			if(array_has($uniqueProducts,$inventory->product_id))
			{
				$tempInventory = $uniqueProducts[$inventory->product_id];
				$inventory->quantity += $tempInventory->quantity;
			}
			$uniqueProducts[$inventory->product_id] = $inventory;
		}
		foreach ($uniqueProducts as $uniqueProduct)
		{
			$blocked_total = BlockedItem::active()->where('distributor_id', '=', $distributorID)
									->where('product_id', '=', $uniqueProduct->product_id)
									->where('stock_point_id', '=', NULL)
									->sum('order_quantity');

			//$uniqueProduct->quantity -= $blocked_total;
			$uniqueProduct->setBlocked($blocked_total);
			$cart_total = $customer->cartItems->where('distributor_id', '=', $distributorID)
									->where('product_id', '=', $uniqueProduct->product_id)
									->where('stock_point_id', '=', NULL)
									->sum('order_quantity');
			$uniqueProduct->quantity -= $cart_total;	//should be commented				
		}
		return $uniqueProducts;
	}
	public function getDistributorInventory(int $distributorID, Array $params)
	{
		$distributor = $this->find($distributorID);
		$dealer = Dealer::find($params['dealer']);
		$uniqueProducts = $this->getIndividualDistributorInventory($distributor,$dealer,$params);
		//return collect(array_values($uniqueProducts))->where('quantity','>',1);
		/* Get Co distributor stock */
		if($distributor->coDistributors->isNotEmpty())
		{
			foreach($distributor->coDistributors as $coDistributor)
			{
				$u = $this->getIndividualDistributorInventory($coDistributor,$dealer,$params);
				foreach($u as $key  => $stock)
				{
					if(array_has($uniqueProducts,$key))
					{
						$uniqueProducts[$key]->quantity += $stock->quantity;
						$uniqueProducts[$key]->setBlocked($uniqueProducts[$key]->getBlocked() + $stock->getBlocked());
					}
					else
					{
						$uniqueProducts[$key] = $stock;
					}
				}
			}
		}
		return collect(array_values($uniqueProducts))->where('quantity','>',1);
	}
	protected function getIndividualDistributorInventoryDetails($distributor,$customer, $productID, Array $params)
	{
		$distributorID = $distributor->id;
		$inventories = $distributor->stocks->where('product_id', '=', $productID)
												->where('quantity', '>', 1);

		foreach ($inventories as $inventory)
		{
			$blocked_total = BlockedItem::active()->where('distributor_id', '=', $distributorID)
									->where('product_id', '=', $inventory->product_id)
									->where('batch_no', '=', $inventory->batch_no)
									->sum('order_quantity');
			$inventory->quantity -= $blocked_total;		
				
			$cart_total = $customer->cartItems->where('distributor_id', '=', $distributorID)
									->where('product_id', '=', $inventory->product_id)
									->where('batch_no', '=', $inventory->batch_no)
									->sum('order_quantity');
			$inventory->quantity -= $cart_total;
		}
		return $inventories;
	}
	public function getDistributorInventoryDetails($distributorID, $productID, Array $params)
	{
		$distributor = $this->find($distributorID);

		if (array_has($params, 'dealer')) {
			$customer = Dealer::find($params['dealer']);
		}

		if (array_has($params, 'cust_type') && ($params['cust_type'] == "DISTRIBUTO")) {
			$customer = Distributor::find($params['distributor']);
		}

		$inventories = $this->getIndividualDistributorInventoryDetails($distributor,$customer, $productID,$params);
		if($distributor->coDistributors->isNotEmpty())
		{
			foreach($distributor->coDistributors as $coDistributor)
			{
				$coDistributorInventory = $this->getIndividualDistributorInventoryDetails($coDistributor,$customer, $productID,$params);
				$inventories = $inventories->merge($coDistributorInventory);
			}
		}
	//	collect($inventories)->where('quantity','>',1);
		return $inventories->where('quantity','>',1);
	}
	public function getInventoryDetails(int $distributorID, int $productID)
	{
		$distributor = $this->find($distributorID);
		return $inventory = $distributor->stocks->where('product_id', '=', $productID);
	}
	public function getMyBlockedStock($distributor, Array $params)
	{
		$query = BlockedItem::active()->where('distributor_id', '=', $distributor->id)->where('order_quantity','>',0);
		if(array_has($params,'page'))
		{
			return $query->paginate($params['per_page'],['*'],'',$params['page']);
		}
		return $query->get();
	}
	public function adjustInventory(int $distributorID,int $inventoryID,string $operation,float $quantity)
	{
		$distributor = $this->find($distributorID);
		$inventory = $distributor->stocks()->find($inventoryID);
		if($operation == '=')
		{
			$inventory->quantity = $quantity;
		}
		if($operation == '-')
		{
			$inventory->quantity -= $quantity;
		}
		if($operation == '+')
		{
			$inventory->quantity += $quantity;
		}
		$inventory->save();
		return $inventory;
	}
	public function sync(User $user,Company $company, $nav_id)
	{
		$navDistributor = $this->customerService->get($company, $nav_id);
        $distributor = new Distributor;
        $distributor->company()->associate($company);
        $distributor->nav_id = $navDistributor->getNo();
        $distributor->name = $navDistributor->getName();
        if($navDistributor->getDistributor_Code())
        {
        	$superStockist_nav_id = $navDistributor->getDistributor_Code();
       		$superStockist = SuperStockist::where('nav_id', '=', $superStockist_nav_id)->first();
       		if($superStockist)
        		$distributor->super_stockist_id = $superStockist->id;
        	else
        		return null;
        }
        $distributor->city_code = $navDistributor->getCity();
        $distributor->state_code = $navDistributor->getState_Code();
        $distributor->post_code = $navDistributor->getPost_Code();
        $distributor->author()->associate($user);
        $distributor->save();
        $distributor->setCustomerCard($navDistributor);
		return $distributor;
	}
	public function getEngineers($distributorID)
	{
		return $this->engineerService->getAllEngineers($distributorID);
	}
	public function unBlockMyStock($itemId, $distributorID)
	{
		$blockedItem = BlockedItem::where('id','=', $itemId)->delete();
	/*	$blockedItem = BlockedItem::where('id','=', $itemId)->first();
		$distributor = Distributor::find($distributorID);

		$productId = $blockedItem->product_id;
		$batch_no = $blockedItem->batch_no;
		$quantity = $blockedItem->order_quantity;

		$blockedItem->delete();
		$distributorStock = $distributor->stocks
							->where('product_id', '=', $productId)
							->where('batch_no', '=', $batch_no)
							->first();

		if(!$distributorStock)
		{
			$stock = new Stock;
			$stock->product_id = $productId;
			$stock->batch_no = $batch_no;
			$stock->quantity = $quantity;
			$distributor->stocks()->save($stock);
		}
		else
		{
			$distributorStock->quantity += $quantity;
			$distributorStock->save();
		}*/
	}
	public function addStock($companyId, $distributorId, Array $params)
	{
		$distributor = Distributor::find($distributorId);
		$query = Product::where('category_code', '=', $params['category_code'])
							->where('collection_code', '=', $params['collection_code']);

    	if(array_has($params, 'colour_code'))
    	{
    		$query->where('colour_code','=',$params['colour_code']);
    	}
    	$product = $query->first();
    	if($product)
		{
			$distributorStock = $distributor->stocks
							->where('product_id', '=', $product->id)
							->where('batch_no', '=', $params['batch_no'])
							->first();

			if(!$distributorStock)
			{
				$stock = new Stock;
				$stock->product_id = $product->id;
				$stock->batch_no = $params['batch_no'];
				$stock->quantity = $params['stock'];
				$distributor->stocks()->save($stock);
				return $stock;
			}
			else
			{
				$distributorStock->quantity += $params['stock'];
				$distributorStock->save();
				return $distributorStock;
			}
		}
	}
	public function deleteStock($distributorId, $stockId)
	{
		$distributor = Distributor::find($distributorId);
		$distributorStock = $distributor->stocks->where('id', '=', $stockId)->first();
		if($distributorStock)
			$distributorStock->delete();
	}
	public function setPaymentType($distributor, Array $params)
	{
		$distributor->payment_type = $params['payment_type'];
		$distributor->save();
		return $distributor;
	}
	public function exportDistributors($company, $SuperStockist, $SuperStockists)
	{
		$company_distributors = true;
		$SuperStockist_ids = [];
		if($SuperStockist == null)
		{
			$distributors = $company->distributors()->get();
		}	
		else
		{
			if($SuperStockists != null)//#826
			{	
				foreach($SuperStockists as $d)
				{
					$SuperStockist_ids[] = $d['id'];
				}
				$distributors = Distributor::whereIn('super_stockist_id', $SuperStockist_ids)->whereNull('deleted_at')->get();	
			}
			else
			{
				$distributors = $SuperStockist->distributors;
			}
			$company_distributors = false;
		}

		if($distributors)
		{
			$spreadsheet = new Spreadsheet();
			if($company_distributors == false)
				$activeSheet = $spreadsheet->getActiveSheet()->setTitle("Distributors_Under_SS");
			else
				$activeSheet = $spreadsheet->getActiveSheet()->setTitle("company_distributors");
	        $activeSheet->setCellValue('A1' , 'NAV ID')->getStyle('A1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('B1' , 'Name')->getStyle('B1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('C1' , 'GSTIN')->getStyle('C1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('D1' , 'Address')->getStyle('D1')->getFont()->setBold(true);  
	        $activeSheet->setCellValue('E1' , 'Address_2')->getStyle('E1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('F1' , 'City')->getStyle('F1')->getFont()->setBold(true);  
	        $activeSheet->setCellValue('G1' , 'State')->getStyle('G1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('H1' , 'Pincode')->getStyle('H1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('I1' , 'Email')->getStyle('I1')->getFont()->setBold(true);   
	        $activeSheet->setCellValue('J1' , 'Phone #')->getStyle('J1')->getFont()->setBold(true);   
	        $activeSheet->setCellValue('K1' , 'Customer Type')->getStyle('K1')->getFont()->setBold(true);
	    	$activeSheet->setCellValue('L1' , 'Super Stockist NAV ID')->getStyle('L1')->getFont()->setBold(true);  
	        $activeSheet->setCellValue('M1' , 'Super Stockist Name')->getStyle('M1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('N1' , 'Created Date')->getStyle('N1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('O1' , 'Created Time')->getStyle('O1')->getFont()->setBold(true);
	    	        
	        $row = 2;
			foreach ($distributors as $distributor)
	        {
            	$activeSheet->setCellValue('A'.$row, $distributor->nav_id);
            	$activeSheet->setCellValue('B'.$row, $distributor->name);
            	$activeSheet->setCellValue('C'.$row, $distributor->gstin);
            	$activeSheet->setCellValue('D'.$row, $distributor->address);
            	$activeSheet->setCellValue('E'.$row, $distributor->address_2);
            	$activeSheet->setCellValue('F'.$row, $distributor->city_code);
            	$activeSheet->setCellValue('G'.$row, $distributor->state_code);
            	$activeSheet->setCellValue('H'.$row, $distributor->post_code);
            	$user = $distributor->users->first();
            	if($user)
            	{
                   	$activeSheet->setCellValue('I'.$row, $user->email);
            		$activeSheet->setCellValue('J'.$row, $user->phone);
            	}
            	$activeSheet->setCellValue('K'.$row, $distributor->type);
            	if($distributor->SuperStockist)
	        	{
	            	$activeSheet->setCellValue('L'.$row, $distributor->SuperStockist->nav_id);
	            	$activeSheet->setCellValue('M'.$row, $distributor->SuperStockist->name);
	            }
	            $timezone = config('rosetta.time_zone');
	            $date = Carbon::createFromTimestamp($distributor->created_at->format('U'))->setTimezone($timezone);//#840

	           	$activeSheet->setCellValue('N'.$row, $date->format('d M Y'));//'Y-m-d'
	            $activeSheet->setCellValue('O'.$row, $date->format('h:i a'));//'Y-m-d'
           		$row++;
	        }
	        for($col = 'A'; $col !== 'O'; $col++) 
	        {
	            $activeSheet->getColumnDimension($col)->setAutoSize(true);
	        }
	        $writer = new Xlsx($spreadsheet);
	        if($company_distributors == false)
	        {
	        	if($SuperStockist_ids != null)
	        	{
	        		$SuperStockist_ids_1 = implode(',', $SuperStockist_ids);
	        		$file = storage_path() . '/app/Distributors_Under_SS'.$SuperStockist_ids_1.'.xlsx';
	        	}
	        	else
	        		$file = storage_path() . '/app/Distributors_Under_SS'.$SuperStockist->id.'.xlsx';
	        }
	        else
	        {	
	       		$file = storage_path() . '/app/Company_Distributors_'.$company->id.'.xlsx';
	        }
	        $writer->save($file);
	        return $file;
		}
	}//#813
	public function createDistributorUnderSuperStockist(User $user, $companyId, $superStockistId, Array $params)//#894
	{
		$toDistributorEmailId = [];
		$company = Company::find($companyId);
		$superStockist = $this->superStockistService->find($superStockistId);
		$navSuperStockist = $this->customerService->get($company, $superStockist->nav_id);
		$params["territory"] = $navSuperStockist->getTerritory_Code();
		$params["billingLocationCode"] = $navSuperStockist->getLocation_Code();
		$params["copySellFrom"] = "Company";
		$params["generalBusinessPostingGroup"] = "NATIONAL";
		$params["paymentApplicationMethod"] = "Manual";
		$params["paymentMethod"] = "AS AGREED";
		$params["shippingAdvice"] = "Complete";
		$params["shippingReserve"] = "Optional";
		$params["shippingMethod"] = "AS AGREED";

		if(array_has($params,'tax_information'))
		{
			$taxInformation = $params['tax_information'];
			if(array_has($taxInformation,'gst'))
			{
				$params["structure"] = "GST SERVIC";
			}
			if(array_has($taxInformation,'vat'))
			{
				$params["structure"] = "SALES UAE";	//#652 changed from VAT (EX) to SALES UAE as confirmed by NAV and Rosetta team for TRF/UAE this is the value that appears on Invoice generated in NAV
			}
		}
		$distributor = $this->create($user,$company,$params, $superStockist);
	
		$newUser = new User;
		$newUser->email = $params['user']['email'];
		$newUser->communication_email = $params['user']['email'];
		$newUser->name = $distributor->name;
		$newUser->phone = $params['user']['phone'];
		$newUser->password = bcrypt($params['user']['password']);//#742
		$newUser->assignRole('distributorAdmin');
		$newUser->distributor()->associate($distributor);
		$newUser->change_password = true;
		$newUser->save();
	
        $this->activeProductAttributesService->load($company->id, 'distributor', $distributor->id, null, $superStockistId);

        $notifiable = (object)[
            "customer" => $distributor->name,
            "number" => $newUser->phone
        ];
        SMSNotification::send($notifiable,new AccountCreatedNotification($company));//#595 Notify Customer

        $recipients = [];
        $toEmailIds = [];
		$sender = $user;
		$brand = config('brand.brand_short');

		$query = User::where('super_stockist_id','=',$superStockistId);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','superStockistAdmin');
			});
		$users = $query->get();
		
		foreach ($users as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		}//add Super Stockist Admin Email Ids

		$companyAdminQuery = User::where('company_id','=',$company->id);
		$companyAdminQuery->whereHas('roles',function($q) {
				$q->where('name','=','companyAdmin');
			});
		$companyAdmins = $companyAdminQuery->get();

		foreach ($companyAdmins as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		}//add Company Admin Email Ids

		$superAdminEmailQuery = User::where('distributor_id','=',null)
					->where('dealer_id','=',null)
					->where('super_stockist_id','=',null)
					->where('company_id','=',null);
		$superAdminEmailQuery->whereHas('roles', function($q){
				$q->where('name', '=', 'superadmin');
			});
		$superAdmins = $superAdminEmailQuery->get();
		foreach ($superAdmins as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		}//add Super Admin Email Ids

		if($newUser->communication_email)
			$toDistributorEmailId[] = $newUser->communication_email;//add  New User Email Id

		foreach ($recipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'New Distributor Created!';
			$notification->text = 'Distributor '. $distributor->name. ' has been created. Please check your email for '. $brand. ' credentials of the Distributor.';
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification));
		}
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
        try
	    {
        	$Qmessage = (new AccountCreated($distributor, $superStockist,$params['user']['email'], $params['user']['password']))->onQueue($queue);
	    	if(!empty($toDistributorEmailId))
        		Mail::to($toDistributorEmailId)->queue($Qmessage);
       	}
       	catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
		return $distributor;
	}

	public function getAllDistributorInventory(array $params)
	{
		$uniqueProducts = [];		
		if(request()->user()->distributor_id>0){
			$distributors = Distributor::whereHas('stocks')->where('id', '!=',request()->user()->distributor_id)->get();
		}else{
			$distributors = Distributor::whereHas('stocks')->get();
		}

		if ($distributors->isNotEmpty()) {
			foreach ($distributors as $distributor) {

				if (array_has($params, 'dealer')) {
					$dealer = Dealer::find($params['dealer']);
					$uniqueProducts[$distributor->id] = $this->getIndividualDistributorInventory($distributor, $dealer, $params);
				}

				if (array_has($params, 'cust_type') && ($params['cust_type'] == "DISTRIBUTO")) {
					$orderByDistributor = Distributor::find($params['distributor']);
					$uniqueProducts[$distributor->id] = $this->getIndividualDistributorInventory($distributor, $orderByDistributor, $params);
				}

				//$uniqueProducts = $this->getIndividualDistributorInventory($distributor, $dealer, $params);
				//return collect(array_values($uniqueProducts))->where('quantity','>',1);

				/* Get Co distributor stock */
				if ($distributor->coDistributors->isNotEmpty()) {
					foreach ($distributor->coDistributors as $coDistributor) {
						if (array_has($params, 'dealer')) {
							$dealer = Dealer::find($params['dealer']);
							$u = $this->getIndividualDistributorInventory($coDistributor, $dealer, $params);
							foreach ($u as $key  => $stock) {
								if (array_has($uniqueProducts, $key)) {
									$uniqueProducts[$key]->quantity += $stock->quantity;
									$uniqueProducts[$key]->setBlocked($uniqueProducts[$key]->getBlocked() + $stock->getBlocked());
								} else {
									$uniqueProducts[$key] = $stock;
								}
							}
						}
					}
				}
			}
		}
		if($uniqueProducts == null)
			return collect([]);
		$flattenedArray = [];
			array_walk_recursive($uniqueProducts, function ($value) use (&$flattenedArray) {
				$flattenedArray[] = $value;
			});
		return collect(array_values($flattenedArray))->where('quantity', '>', 1);
	}	
}