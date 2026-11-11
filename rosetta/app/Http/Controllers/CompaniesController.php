<?php

namespace Rosetta\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Rosetta\Exceptions\CLRate\CLRateExistsException;
use Rosetta\Exceptions\ProductPrice\ProductPriceExistsException;
use Rosetta\Exceptions\StockPoint\StockPointExistsException;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Services\AdvanceOrders\AdvanceOrdersServiceInterface;
use Rosetta\Services\CLRate\CLRateServiceInterface;
use Rosetta\Services\CartItems\CartItemsServiceInterface;
use Rosetta\Services\Companies\CompanyServiceInterface;
use Rosetta\Services\Inventory\InventoryServiceInterface;
use Rosetta\Services\Product\ProductsServiceInterface;
use Rosetta\Services\StockPoints\StockPointServiceInterface;
use Rosetta\Services\Users\UserServiceInterface;
use Rosetta\Transformers\CLRate\CLRateTransformer;
use Rosetta\Transformers\Company\AdvanceItemTransformer;
use Rosetta\Transformers\Company\BillingLocationTransformer;
use Rosetta\Transformers\Company\BlockedAdvanceItemTransformer;
use Rosetta\Transformers\Company\BlockedItemTransformer;
use Rosetta\Transformers\Company\CompanyTransformer;
use Rosetta\Transformers\Company\DashboardTransformer;
use Rosetta\Transformers\Inventory\LedgerEntryTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\NAV\InventoryTransformer;
use Rosetta\Transformers\ProductPrice\ProductPriceTransformer;
use Rosetta\Transformers\Product\IndexTransformer;
use Rosetta\Transformers\StockPoint\CompanyIndexTransformer as StockPointListTransformer;
use Rosetta\Transformers\StockPoint\CustomerTransformer;
use Rosetta\Transformers\Stock\StockTransformer;
use Rosetta\Transformers\StockPoint\IndexTransformer as StockPointIndexTransformer;
use Rosetta\Transformers\TaxTypeTransformer;
use SoapFault;
use Rosetta\Services\SuperStockist\SuperStockistServiceInterface;
use Rosetta\NAV\WS\ILE\ILE;
use Rosetta\Services\Distributor\DistributorServiceInterface;

class CompaniesController extends Controller
{
    protected $companyService;
    protected $inventoryService, $userService;
    protected $stockPointService;
    protected $clRateService, $productsService, $cartItemsService, $advanceOrdersService, $superStockistService, $distributorService;
    public function __construct(CompanyServiceInterface $companyService,InventoryServiceInterface $inventoryService, UserServiceInterface $userService,StockPointServiceInterface $stockPointService,CLRateServiceInterface $clRateService, ProductsServiceInterface $productsService, CartItemsServiceInterface $cartItemsService, AdvanceOrdersServiceInterface $advanceOrdersService, SuperStockistServiceInterface $superStockistService, DistributorServiceInterface $distributorService)
    {
        $this->companyService = $companyService;
        $this->inventoryService = $inventoryService;
        $this->userService = $userService;
        $this->stockPointService = $stockPointService;
        $this->clRateService = $clRateService;
        $this->productsService = $productsService;
        $this->cartItemsService = $cartItemsService;
        $this->advanceOrdersService = $advanceOrdersService;
        $this->superStockistService = $superStockistService;
        $this->distributorService = $distributorService;
    }
    public function create(Request $request)
    {
        if(!Gate::allows('companies'))
        {
            return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
            "name"    => 'required',
            "database"    => 'required',
            "region"      => 'required',
            "user.email" => 'unique:users,email'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $company = $this->companyService->create(Auth::user(),$request->all());
        $this->userService->create(Auth::user(), $request['user'], 'company', $company);
        return fractal()
            ->item($company)
            ->transformWith(new CompanyTransformer())
            ->parseIncludes(['region'])
            ->toArray();
    }
    public function update(Request $request,$id)
    {
        if(!Gate::allows('companies'))
        {
            return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
            "name"    => 'required',
            "region"  => 'required'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $company = $this->companyService->update(Auth::user(),$id,$request->all());
        return fractal()
            ->item($company)
            ->transformWith(new CompanyTransformer())
            ->parseIncludes(['region'])
            ->toArray();
    }
    public function get(Request $request,$id)
    {
   /*   if(!Gate::allows('companies'))
        {
            return response()->json("", 403);
        }*/
        
        $company = $this->companyService->find($id);
        return fractal()
            ->item($company)
            ->transformWith(new CompanyTransformer())
            ->parseIncludes(['region'])
            ->toArray();
    }

    public function index(Request $request)
    {
        if(!Gate::allows('companies'))
        {
            return response()->json("",403);
        }

        $companies = $this->companyService->all($request->all());
        $companiesArray =  fractal()
            ->collection($companies)
            ->transformWith(new CompanyTransformer())
            ->parseIncludes(['region'])
            ->toArray();


        if($request->get('page'))
        {
            return [
                "total" => $companies->total(),
                "page"  => $companies->currentPage(),
                "companies" => $companiesArray,
            ];
        }
        else
            return $companiesArray;
    }
    public function taxes(Request $request, $companyId)
    {
        $company = $this->companyService->find((int)$companyId);
        return fractal()
            ->collection($company->region->taxTypes)
            ->transformWith(new TaxTypeTransformer)
            ->toArray();
    }
    public function states(Request $request, $companyId)
    {
        $company = $this->companyService->find((int)$companyId);
        return fractal()
            ->collection($company->region->country->states)
            ->transformWith(new StateTransformer)
            ->toArray();
    }
    public function billingLocations(Request $request, $companyId)
    {
        $company = $this->companyService->find((int)$companyId);
        return fractal()
            ->collection($company->billing_locations)
            ->transformWith(new BillingLocationTransformer)
            ->toArray();
    }
    public function createBillingLocation(Request $request, $companyId)
    {
        $validator = Validator::make($request->all(), [
            "code"    => 'required',
            "name"    => 'required'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $company = Company::find($companyId);
        if(!$companyId)
            return response()->json("Invalid Company", 405);
        $billingLocation = $this->companyService->createBillingLocation(Auth::user(),$request->all(), $companyId);
        return fractal()
            ->item($billingLocation)
            ->transformWith(new BillingLocationTransformer())
            ->toArray();
    }
    public function dashboard(Request $request, $companyId)
    {
        $company = $this->companyService->find((int)$companyId);
        return fractal()
            ->item($company)
            ->transformWith(new DashboardTransformer())
            ->parseIncludes(['region'])
            ->toArray();
    }
    public function inventory(Request $request,$companyId)
    {
        $params = $request->all();
        
        $company = $this->companyService->find($companyId);
        $customer_type = $request->get('cust_type');
        $customer = null;
        $superStockistInventory = $this->superStockistService->getAllSSInventory($params);
        
        if($customer_type)
        {
            //#711
            $loggedInUser = Auth::user();
            if($customer_type == 'DISTRIBUTO')
            {
                $distributorsInventory  = $this->distributorService->getAllDistributorInventory($params);
                $distributor = $customer = Distributor::find($request->get('distributor'));
                if($loggedInUser->distributor_id != $customer->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this inventory"],412);
                }

                $companyInventory = $this->companyService->getInventory(Auth::user(),$companyId,$params, $customer);
                if($distributor->super_stockist_id != null)
                {
                    //$superStockistInventory = $this->superStockistService->getSSInventory($distributor->super_stockist_id, $params);
                    //$superStockistInventory = $this->superStockistService->getAllSSInventory($params);
                    $inventory = $this->inventoryService->calculateStock($companyInventory,$distributor,$params['quantity'], $companyId,$params['min_price'],$params['max_price'],array_values($superStockistInventory->all()),array_values($distributorsInventory->all()));
                }
                else
                {
                    $inventory = $this->inventoryService->calculateStock($companyInventory,$distributor,$params['quantity'], $companyId,$params['min_price'],$params['max_price'],array_values($superStockistInventory->all()),array_values($distributorsInventory->all()));
                }
            }
            if($customer_type == 'SUPER_STOCKIST')
            {
                $customer = SuperStockist::find($request->get('superStockist'));
                if($loggedInUser->super_stockist_id != $customer->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this inventory"],412);
                }

                $companyInventory = $this->companyService->getInventory(Auth::user(),$companyId,$params, $customer);
                $inventory = $this->inventoryService->calculateStock($companyInventory,$customer,$params['quantity'], $companyId, $params['min_price'], $params['max_price'],array_values($superStockistInventory->all())/*,array_values($distributorsInventory->all()) */);
            }
            if($customer_type == 'DEALER')
            {
                $distributorsInventory  = $this->distributorService->getAllDistributorInventory($params);
                $customer = Dealer::find($request->get('dealer'));
                if(data_get($loggedInUser, 'dealer_id') != data_get($customer,'id'))
                {
                    return response()->json(["error" =>"You do not have permission to view this inventory"],412);
                }
                $companyInventory = $this->companyService->getInventory(Auth::user(),$companyId,$params, $customer);
                $inventory = $this->inventoryService->calculateStock($companyInventory,$customer,$params['quantity'], $companyId, $params['min_price'], $params['max_price'],array_values($superStockistInventory->all()),array_values($distributorsInventory->all()));
            }
            //End of #711
            $inventory = collect($inventory);
            $inventory = $inventory->filter(function($item){
                return $item->getInventory() > 0;
            });
            if($customer_type=='DISTRIBUTO' && $customer->super_stockist_id>0){
                $customer_type = "SUPER_STOCKIST_DISTRIBUTOR";
            }
            
            $fractal = fractal()
                ->Collection($inventory)
                ->transformWith(new InventoryTransformer($company,$customer_type,$customer))   
                ->parseIncludes(['price']);
        }
        else
        {
            $inventory = $this->companyService->getInventory(Auth::user(),$companyId,$params, $customer);
            $inventory = $this->inventoryService->calculateCompanyStock($inventory,$customer,$params['quantity'], $companyId, $params['min_price'], $params['max_price']);

            $inventory = collect($inventory);
            $inventory = $inventory->filter(function($item){
                return $item->getInventory() > 0;
            });

            $fractal = fractal()
                ->Collection($inventory)
                ->transformWith(new InventoryTransformer($company, null, $company))
                ->parseIncludes(['price']);
        }
        $inventoryArray =  $fractal->toArray();     
        $inventoryArray = array_filter($inventoryArray, function($item) {
            return $item['inventory'] > 0;
        });
        $inventoryArray = array_values($inventoryArray);
        return [
                "total" => 0,
                "page"  =>0,
                "inventory" => $inventoryArray,
            ];
    }
    public function inventoryDetails(Request $request, $companyId,$itemID)
    {
        $customer_type = $request->get('cust_type');
        $requested_stock_point_source = $request->get('stock_point_source');
        $customer = null;
        $location_codes = null;
        $company = Company::find($companyId);
        if($customer_type)
        {
            if($customer_type == 'DISTRIBUTO')
            {
                $customer = Distributor::find($request->get('distributor'));
            }
            if($customer_type == 'DEALER')
            {
                $customer = Dealer::find($request->get('dealer'));
            }
        }
        $requested_stock_point_id = $request->get('stock_point_id');
        
        if($requested_stock_point_id == null)
        {
            if($customer)
                $customer_stock_points = $customer->stock_points;
            else
                $customer_stock_points = $company->stock_points;
            foreach ($customer_stock_points as $stock_point)
            {
                $location_codes .= $stock_point->stock_point->code;
                $location_codes .= '|';
            }
            $location_codes = substr($location_codes,0,-1);
        }
        else
        {
            $locations = StockPoint::where('id', '=', $requested_stock_point_id)->first();
            $location_codes = (!empty($locations))?$locations->code:'';
        }

        if($requested_stock_point_id != null && $requested_stock_point_source == 'SuperStockist')
        {
                $superStockist = SuperStockist::find($requested_stock_point_id);
                $superStockistInventory = $this->superStockistService->getSSInventoryDetails($superStockist, $itemID, $request->all());
                foreach($superStockistInventory as $stock)
                {
                    $ile = new ILE;
                    $ssName = (!empty($superStockist->display_name))?$superStockist->display_name:$superStockist->name;
                    $ile->setLot_no($stock->batch_no);
                    $ile->setRemaining_Quantity($stock->quantity);
                    $ile->setLocation_Code($ssName);
                    $ile->setLocation_Name($ssName);
                    $ile->setLocation_Source('Super Stockist');
                    $ile->setLocation_SourceId($superStockist->id);
                    if($stock->superStockist->id != $superStockist->id)
                    {
                        $ile->setCoLocation($stock->superStockist);
                    }
                    $ledgerEntries[] = $ile;
                }
            //if stock point is not sepcified (select all) or Super Stockist stock point is selected*/
        }elseif($requested_stock_point_id == null || $requested_stock_point_source == 'SuperStockist')
        {
            if($customer->super_stockist_id != null)
            {
                $superStockist = SuperStockist::find($customer->super_stockist_id);
                $superStockistInventory = $this->superStockistService->getSSInventoryDetails($superStockist, $itemID, $request->all());
                foreach($superStockistInventory as $stock)
                {
                    $ile = new ILE;
                    $ssName = (!empty($superStockist->display_name))?$superStockist->display_name:$superStockist->name;
                    $ile->setLot_no($stock->batch_no);
                    $ile->setRemaining_Quantity($stock->quantity);
                    $ile->setLocation_Code($ssName);
                    $ile->setLocation_Name($ssName);
                    $ile->setLocation_Source('Super Stockist');
                    $ile->setLocation_SourceId($superStockist->id);
                    if($stock->superStockist->id != $superStockist->id)
                    {
                        $ile->setCoLocation($stock->superStockist);
                    }
                    $ledgerEntries[] = $ile;
                }
            }
            //if stock point is not sepcified (select all) or Super Stockist stock point is selected*/
        }elseif($requested_stock_point_id != null && $requested_stock_point_source == 'Distributor')
        {
                $distributor = Distributor::find($requested_stock_point_id);
                $distributorInventory = $this->distributorService->getDistributorInventoryDetails($requested_stock_point_id, $itemID, $request->all());
                foreach($distributorInventory as $stock)
                {
                    $ile = new ILE;
                    $distName = (!empty($distributor->display_name))?$distributor->display_name:$distributor->name;
                    $ile->setLot_no($stock->batch_no);
                    $ile->setRemaining_Quantity($stock->quantity);
                    $ile->setLocation_Code($distName);
                    $ile->setLocation_Name($distName);
                    $ile->setLocation_Source('Distributor');
                    $ile->setLocation_SourceId($distributor->id);
                    if($stock->distributor->id != $distributor->id)
                    {
                        $ile->setCoLocation($stock->distributor);
                    }
                    $ledgerEntries[] = $ile;
                }
            //if stock point is not sepcified (select all) or Super Stockist stock point is selected*/
        }else if($location_codes)
        {
            $ledgerEntries = $this->inventoryService->getLedgerEntries(Auth::user(),$companyId,$itemID, $location_codes);
            
  //          $pendingPOEntries = $this->inventoryService->getPendingPurchaseOrders(Auth::user(),$companyId,$itemID, $location_codes);
    //        $mergedLedgerEntries = $this->inventoryService->mergeLedgerEntries($ledgerEntries, $pendingPOEntries);
         //   dd($mergedLedgerEntries);
            //$ledgerEntries = $this->inventoryService->calculateStockAgainstBatch($itemID,$mergedLedgerEntries,$customer, $companyId);
            $ledgerEntries = $this->inventoryService->calculateStockAgainstBatch($itemID,$ledgerEntries,$customer, $companyId);
        }

            return fractal()
                ->Collection($ledgerEntries)
                ->transformWith(new LedgerEntryTransformer())
                ->toArray();
    }
    public function intransitInventoryDetails(Request $request, $companyId,$itemID)
    {
        $customer_type = $request->get('cust_type');
        $customer = null;
        $location_codes = null;
        $company = Company::find($companyId);
        if($customer_type)
        {
            if($customer_type == 'DISTRIBUTO')
            {
                $customer = Distributor::find($request->get('distributor'));
            }
            if($customer_type == 'DEALER')
            {
                $customer = Dealer::find($request->get('dealer'));
            }
        }
        $requested_stock_point_id = $request->get('stock_point_id');
        if($requested_stock_point_id == null)
        {
            if($customer)
                $customer_stock_points = $customer->stock_points;
            else
                $customer_stock_points = $company->stock_points;
            foreach ($customer_stock_points as $stock_point)
            {
                $location_codes .= $stock_point->stock_point->code;
                $location_codes .= '|';
            }
            $location_codes = substr($location_codes,0,-1);
        }
        else
        {
            $locations = StockPoint::where('id', '=', $requested_stock_point_id)->first();
            $location_codes = $locations->code;
        }
        if($location_codes)
        {
            $pendingPOEntries = $this->inventoryService->getPendingPurchaseOrders(Auth::user(),$companyId,$itemID, $location_codes);
            $ledgerEntries = $this->inventoryService->formattedLedgerEntries($pendingPOEntries);
            $ledgerEntries = $this->inventoryService->calculateIntransitStockAgainstBatch($itemID,$ledgerEntries,$customer, $companyId);
            return fractal()
                ->Collection($ledgerEntries)
                ->transformWith(new LedgerEntryTransformer())
                ->toArray();
        }
    }
    public function validateNav(Request $request)
    {
        $database = $request->get('database');
        try{
            $this->companyService->validate($database);
        }
        catch(SoapFault $e)
        {
            return response()->json([])->setStatusCode(404);
        }
    }
    public function stock(Request $request,$companyID)
    {
        $user = Auth::user();
        $customer = null;
        $company = null;
        if($user->distributor)
            $user_type = $user->distributor;
        if($user->dealer)
            $user_type = $user->dealer;
        if($user->company)
            $user_type = $user->company;
        $stocks = $this->companyService->distributorStock($companyID,$request->all(), $user_type);
        $stocksArray =  fractal()
            ->collection($stocks)
            ->parseIncludes(['product.category','product.collection','product.colour','location'])
            ->transformWith(new StockTransformer($user_type))
            ->toArray();

            return [
                "total" => $stocks->total(),
                "page"  => $stocks->currentPage(),
                "per_page"  => $stocks->PerPage(),
                "inventory" => $stocksArray,
            ];
    }
    public function blockedIndex(Request $request,$companyID)
    {
        $blockedItems = $this->companyService->blockedItems($companyID,$request->all());
        $blockedItemsArray =  fractal()
            ->collection($blockedItems)
            ->transformWith(new BlockedItemTransformer())
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->toArray();

            return [
                "total" => $blockedItems->total(),
                "page"  => $blockedItems->currentPage(),
                "per_page"  => $blockedItems->PerPage(),
                "items" => $blockedItemsArray,
            ];
    }
    public function blockedAdvanceIndex(Request $request,$companyID)
    {
        $blockedItems = $this->companyService->blockedAdvanceItems($companyID,$request->all());
        $blockedItemsArray =  fractal()
            ->collection($blockedItems)
            ->transformWith(new BlockedAdvanceItemTransformer())
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->toArray();

            return [
                "total" => $blockedItems->total(),
                "page"  => $blockedItems->currentPage(),
                "per_page"  => $blockedItems->PerPage(),
                "items" => $blockedItemsArray,
            ];
    }
    public function dudBlockedCompanyStock($companyId, $distributorId, Request $request)//When Distributor views Company Items blocked by DUD 
    {
        $distributor = Distributor::find($distributorId);
         //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;

        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permissions to view this data"],401);
            }
        }
        //End of #720  
        $blockedItems = $this->companyService->dudBlockedCompanyStock($companyId,$distributor,$request->all());
        
        $blockedItemsArray =  fractal()
            ->collection($blockedItems)
            ->transformWith(new BlockedItemTransformer($distributor))
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->toArray();

            return [
                "total" => $blockedItems->total(),
                "page"  => $blockedItems->currentPage(),
                "per_page"  => $blockedItems->PerPage(),
                "items" => $blockedItemsArray,
            ];
    }
    public function customersAdvanceItems(Request $request)
    {
        $advanceItems = $this->companyService->getAdvanceItems($request->all());
        $advanceItemsArray =  fractal()
            ->collection($advanceItems)
            ->transformWith(new AdvanceItemTransformer())
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $advanceItems->total(),
                "page"  => $advanceItems->currentPage(),
                "per_page"  => $advanceItems->PerPage(),
                "items" => $advanceItemsArray,
            ];
        }
        else
            return $advanceItemsArray;
    }
    public function stockpoints(Request $request, $companyID)
    {
        if(!Gate::allows('viewStockPoints'))
        {
            return response()->json("", 403);
        }
        $stockPoints = $this->stockPointService->all(Auth::user(),$companyID);
        return fractal()
                ->Collection($stockPoints)
                ->transformWith(new StockPointListTransformer())
                ->toArray();        
    }
    public function getStockpointCustomers(Request $request,$companyID,$stockPointID)
    {
        if(!Gate::allows('viewStockPointCustomers'))
        {
            return response()->json("", 403);
        }
        $customers = $this->stockPointService->getCustomers(Auth::user(),$companyID,$stockPointID,$request->all());
        
        $customersArray =  fractal()
            ->collection($customers)
            ->transformWith(new CustomerTransformer())
            ->toArray();
        if($request->get('page'))
        {
            return [
                "total" => $customers->total(),
                "page"  => $customers->currentPage(),
                "per_page"  => $customers->PerPage(),
                "customers" => $customersArray,
            ];
        }
        else
            return $customersArray;
    }
    public function createStockpoint(Request $request,$companyID)
    {
        if(!Gate::allows('addStockPoint'))
        {
            return response()->json("", 403);
        }
        try{
            $stockPoint = $this->stockPointService->create(Auth::user(),$companyID,$request->all());
        }
        catch(StockPointExistsException $e)
        {
            return response()->json([])->setStatusCode(409);
        }
        return fractal()
                ->item($stockPoint)
                ->transformWith(new StockPointListTransformer())
                ->toArray(); 
    }
    public function deleteCustomerStockPoint(Request $request,$companyID,$stockPointID,$customerType,$customerID)
    {
        if(!Gate::allows('deleteStockPointCustomers'))
        {
            return response()->json("", 403);
        }
        try{
            $this->stockPointService->deleteCustomer(Auth::user(),$companyID,$stockPointID,$customerType,$customerID);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json([])->setStatusCode(409);
        }
        return response()->json([]);
    }
    public function addCustomerToStockPoint(Request $request,$companyID,$stockPointID,$customerType)
    {
        if(!Gate::allows('addStockPointCustomers'))
        {
            return response()->json("", 403);
        }
        try{
            $stockPoint = $this->stockPointService->addCustomerToStockPoint(Auth::user(),$companyID,$stockPointID,$customerType,$request->get('customer'));
        }
        catch(StockPointExistsException $e)
        {
            return response()->json([])->setStatusCode(409);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json([])->setStatusCode(404);
        }
        return fractal()
                ->item($stockPoint)
                ->transformWith(new CustomerTransformer())
                ->toArray(); 
    }

    public function getCLRates(Request $request,$companyId)
    {
        $company = Company::find($companyId);
        $cl_rates = $this->clRateService->getAll($company,$request->all());

        $cl_rates_array =  fractal()
            ->collection($cl_rates)
            ->transformWith(new CLRateTransformer())
            ->toArray();


        if($request->get('page'))
        {
            return [
                "total" => $cl_rates->total(),
                "page"  => $cl_rates->currentPage(),
                "rates" => $cl_rates_array,
            ];
        }
        else
            return $cl_rates_array;
    }
    public function createCLRate(Request $request,$companyId)
    {
        $company = Company::find($companyId);
        try{
            $clRate = $this->clRateService->create($company,$request->all());
        }
        catch(CLRateExistsException $e)
        {
            return response()->json([])->setStatusCode(409);
        }
        return fractal()
            ->item($clRate)
            ->transformWith(new CLRateTransformer())
            ->toArray();
    }
    public function deleteCLRate(Request $request,$companyId,$clId)
    {
        $company = Company::find($companyId);
        try{
            $clRate = $this->clRateService->delete($company,$clId);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json([])->setStatusCode(404);
        }
    }
    public function getProductPrices(Request $request,$companyId)
    {
        $company = Company::find($companyId);
        $products = $this->productsService->getProducts($request->all(), $company);
    
        $productsArray =  fractal()
                        ->collection($products)
                        ->parseIncludes(['category','collection','colour'])
                        ->transformWith(new ProductPriceTransformer($company))
                        ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $products->total(),
                "page"  => $products->currentPage(),
                "products" => $productsArray,
            ];
        }
        else
            return $productsArray;
    }
    public function addProductPrice(Request $request,$companyId)
    {
        $company = Company::find($companyId);
        try
        {
            $productPrice = $this->productsService->addProductPrice($company,$request->all());
        }
        catch(ProductPriceExistsException $e)
        {
            return response()->json([])->setStatusCode(409);
        }
    }
    public function setCollectionPrice(Request $request, $companyId, $category_code, $collection_code)//#734
    {
        $company = Company::find($companyId);
        try
        {
            $productPrice = $this->productsService->addCollectionPrice($company, $category_code, $collection_code,$request->price);
        }
        catch(Exception $e)
        {
            return response()->json([])->setStatusCode(500);
        }
    }
    public function unblock_acrossTenants($companyId, Request $request)//unblock stock blocked on other Tenants #629
    {
      //  $distributor = Distributor::find($distributorID);
        $ublockedItems = $this->cartItemsService->unBlock_acrossTenants($request->all(), $companyId);
    }
    public function unblock_advance_items_acrossTenants($companyId, Request $request)//unblock stock blocked on other Tenants #629 #810
    {
        $ublockedItems = $this->advanceOrdersService->unBlock_acrossTenants($request->all(), $companyId);
    }
    public function exportOrders(Request $request, $companyId)
    {
        $pathToFile = $this->companyService->exportOrders($companyId, $request->all());
        return response()->download($pathToFile,'OrderReport_'.Carbon::now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend(false);
    }//#814
    public function thresholdTenants(Request $request, $companyId)
    {
        return $this->companyService->getCompanyTenants($companyId);
    }
    public function thresholdTenantStockPoints(Request $request, $companyId, $tenant)
    {
        $stockpoints = $this->companyService->getCompanyTenantStockpoints($companyId,$tenant);
        return fractal()
            ->collection($stockpoints)
            ->transformWith(new StockPointIndexTransformer)
            ->toArray();
    }
}
