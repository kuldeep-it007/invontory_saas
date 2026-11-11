<?php

namespace Rosetta\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification as SMSNotification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Rosetta\Exceptions\FileImport\FileNotFoundException;
use Rosetta\Exceptions\Order\BlockItemDeniedException;
use Rosetta\Exceptions\Order\ErrorUnblockingStock;
use Rosetta\Exceptions\Order\InsufficientQuantityToBlockException;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\NAV\WS\ILE\ILE;
use Rosetta\Notifications\SMS\AccountCreatedNotification;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;
use Rosetta\Services\CLRate\CLRateServiceInterface;
use Rosetta\Services\CartItems\CartItemsServiceInterface;
use Rosetta\Services\Companies\CompanyServiceInterface;
use Rosetta\Services\Distributor\DistributorServiceInterface;
use Rosetta\Services\Inventory\InventoryServiceInterface;
use Rosetta\Services\SuperStockist\SuperStockistServiceInterface;
use Rosetta\Services\Users\UserServiceInterface;
use Rosetta\Transformers\CLRate\CLRateTransformer;
use Rosetta\Transformers\CartItem\BlockedItemTransformer;
use Rosetta\Transformers\CartItem\CartItemTransformer;
use Rosetta\Transformers\Distributors\CompactTransformer;
use Rosetta\Transformers\Distributors\DistributorBlockedStockTransformer;
use Rosetta\Transformers\Distributors\DistributorTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer;
use Rosetta\Transformers\Distributors\ProfileTransformer;
use Rosetta\Transformers\Distributors\SearchResponseTransformer;
use Rosetta\Transformers\EngineerTransformer;
use Rosetta\Transformers\Inventory\LedgerEntryTransformer;
use Rosetta\Transformers\NAV\AddressTransformer;
use Rosetta\Transformers\NAV\InventoryTransformer;
use Rosetta\Transformers\ReturnItem\ReturnItemTransformer;
use Rosetta\Transformers\Stock\DistributorRemainingStockTransformer;
use Rosetta\Transformers\Stock\DistributorStockTransformer;
use Rosetta\Transformers\Stock\RemainingStockTransformer;
use Rosetta\Transformers\Stock\StockTransformer;

class DistributorsController extends Controller
{
	protected $companyService, $userService, $distributorService, $cartItemsService, $activeProductAttributesService, $inventoryService,$clRateService, $superStockistService;
	public function __construct(CompanyServiceInterface $companyService,DistributorServiceInterface $distributorService, CartItemsServiceInterface $cartItemsService, UserServiceInterface $userService, ActiveProductAttributesServiceInterface $activeProductAttributesService, InventoryServiceInterface $inventoryService,CLRateServiceInterface $clRateService, SuperStockistServiceInterface $superStockistService)
	{
		$this->companyService = $companyService;
		$this->distributorService = $distributorService;
        $this->cartItemsService = $cartItemsService;
        $this->userService = $userService;
        $this->activeProductAttributesService = $activeProductAttributesService;
        $this->inventoryService = $inventoryService;
        $this->clRateService = $clRateService;
        $this->superStockistService = $superStockistService;
	}
    public function index(Request $request,$companyId, $superStockistId = null)
    {
    	if(!Gate::allows('viewDistributors'))
        {
            return response()->json("", 403);
        }
        if($superStockistId != null)
        {
            $superStockist = $this->superStockistService->find($superStockistId);
            $company = $superStockist->company;
             //#723
            $loggedInUser = Auth::user();
            $loggedInUsersSS = $loggedInUser->superStockist;
            if($loggedInUsersSS)
            {
                if($superStockist->id != $loggedInUsersSS->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this data"],401);
                }
            }//End of #723
        }
        else
        {
            $company = $this->companyService->find($companyId);
            $superStockist = null;
        }

     //   $company = $this->companyService->find($companyID);
    //	$distributors = $this->distributorService->getDistributors($company,$request->all());
    //    $distributors = $this->distributorService->getDistributors($company, $superStockist, $request->all());
        
        $distributors = $this->distributorService->getDistributors($company, $request->all(), $superStockist);
        $distributorsArray =  fractal()
            ->collection($distributors)
            ->transformWith(new CompactTransformer())
            ->parseIncludes(['paymentTypes','abilities'])
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $distributors->total(),
                "page"  => $distributors->currentPage(),
                "distributors" => $distributorsArray,
            ];
        }
        else
            return $distributorsArray;
    }
    public function search(Request $request, $companyId, $superStockistId=null)//#894
    {
        if(!Gate::allows('viewDistributors'))
        {
            return response()->json("", 403);
        }
        if($superStockistId != null)
        {
            $superStockist = $this->superStockistService->find($superStockistId);
            $company = $superStockist->company;
            //#723
            $loggedInUser = Auth::user();
            $loggedInUsersSS = $loggedInUser->superStockist;
            if($loggedInUsersSS)
            {
                if($superStockist->id != $loggedInUsersSS->id)
                {
                    return response()->json(["error" =>"You do not have permissions to view this data"],401);
                }
            }//End of #723
        }
        else
        {
            $company = $this->companyService->find($companyId);
            $superStockist = null;
        }
        $distributors = $this->distributorService->getDistributors($company, $request->all(), $superStockist);
        
        $distributorsArray =  fractal()
            ->collection($distributors)
            ->transformWith(new SearchResponseTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $distributors->total(),
                "page"  => $distributors->currentPage(),
                "distributors" => $distributorsArray,
            ];
        }
        else
            return $distributorsArray;
    }
    /*#894
    public function search(Request $request,$companyID)
    {
        if(!Gate::allows('viewDistributors'))
        {
            return response()->json("", 403);
        }
        $company = $this->companyService->find($companyID);
        $distributors = $this->distributorService->getDistributors($company,$request->all());
        $distributorsArray =  fractal()
            ->collection($distributors)
            ->transformWith(new CompactTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $distributors->total(),
                "page"  => $distributors->currentPage(),
                "distributors" => $distributorsArray,
            ];
        }
        else
            return $distributorsArray;
    }*/
    public function create(Request $request,$companyID, $superStockistId = null)
    {
        if(!Gate::allows('createDistributors'))
        {
                return response()->json("", 403);
        }
        $phone = $request->input('user.phone');
        $email = $request->input('user.email');
        $rules = [
            "name"    => 'required',
        ];
        if($email)
        {
            $rules["user.email"] = [
                Rule::unique('users', 'email')->where(function($query){
                    return $query->whereNull('deleted_at');
                }),
            ];
        }
        if($phone)
        {
            $rules["user.phone"] = [
                Rule::unique('users', 'phone')->where(function($query){
                    return $query->whereNull('deleted_at');
                }),
            ];
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        if($superStockistId == null)
        {
            $company = $this->companyService->find($companyID);
            $superStockist = null;
        }
        $user = Auth::user();
        if($superStockistId != null)
        {
            
            if($user->hasRole('superStockistAdmin'))
            {
                $company = $this->companyService->find($companyID);
                $distributor = $this->distributorService->createDistributorUnderSuperStockist($user, $companyID, $superStockistId, $request->all());
            }
            else
            {
                $superStockist = $this->superStockistService->find($superStockistId);
                $company = $superStockist->company;
                $distributor = $this->distributorService->create(Auth::user(),$company,$request->all(), $superStockist);
                $user = $this->userService->create(Auth::user(), $request['user'], 'distributor', $distributor);
            }
        }
        else
        {
            $distributor = $this->distributorService->create(Auth::user(),$company,$request->all());
            $user = $this->userService->create(Auth::user(), $request['user'], 'distributor', $distributor);
        }

        if($superStockistId == null)
            $this->activeProductAttributesService->load($companyID, 'distributor', $distributor->id,null,null);
        else
            $this->activeProductAttributesService->load($companyID, 'distributor', $distributor->id, null, $superStockistId);

        $notifiable = (object)[
            "customer" => $distributor->name,
            "number" => $user->phone
        ];
        SMSNotification::send($notifiable,new AccountCreatedNotification($company));//#595 Notify Customer

        return fractal()
            ->item($distributor)
            ->parseIncludes(['superStockist'])
            ->transformWith(new DistributorTransformer())
            ->toArray();
    }
    public function update(Request $request, $companyID, $superStockistId, $distributorId)
    {
        if(!Gate::allows('updateDistributors'))
        {
            return response()->json("", 403);
        }
        if($superStockistId != null)
        {
            $user = Auth::user();
            $superStockist = $this->superStockistService->find($superStockistId);
            $company = $superStockist->company;
        }
        else
        {
            $company = $this->companyService->find($companyID);
            $superStockist = null;
        }
        $distributor = $superStockist->distributors()->find($distributorId);

        $distributor = $this->distributorService->update(Auth::user(),$company,$distributor,$request->all());
        return fractal()
            ->item($distributor)
             ->parseIncludes(['superStockist'])
            ->transformWith(new DistributorTransformer())
            ->toArray();
    }
    public function updateCompanyDistributor(Request $request, $companyId, $distributorId)
    {
        if(!Gate::allows('updateDistributors'))
        {
            return response()->json("", 403);
        }

        $company = $this->companyService->find($companyId);
        $superStockist = null;
        $distributor = $company->distributors()->find($distributorId);

        $distributor = $this->distributorService->update(Auth::user(),$company,$distributor,$request->all());
        return fractal()
            ->item($distributor)
            ->parseIncludes(['superStockist'])
            ->transformWith(new DistributorTransformer())
            ->toArray();
    }
    public function setPermissions(Request $request,$companyId,$distributorId)
    {
        if(!Gate::allows('updateDistributors'))
        {
            return response()->json("", 403);
        }
        $company = $this->companyService->find($companyId);
        $distributor = $company->distributors()->find($distributorId);
        $distributor = $this->distributorService->resetPermissions(Auth::user(),$company,$distributor,$request->all());
        return fractal()
            ->item($distributor)
            ->transformWith(new CompactTransformer())
            ->parseIncludes(['abilities'])
            ->toArray();
    }
    public function profile(Request $request,$companyID,$distributorID)
    {
        if(!Gate::allows('manageProfile'))
        {
            return response()->json("", 403);
        }
        $company = $this->companyService->find($companyID);
        $distributor = $company->distributors()->find($distributorID);
        $distributor = $this->distributorService->update(Auth::user(),$company,$distributor,$request->all());
        return fractal()
            ->item($distributor)
            ->transformWith(new ProfileTransformer())
            ->parseIncludes(['tax_information','state'])
            ->toArray();
    }
    public function getForCompany(Request $request, $compnayID, $distributorID)
    {
        $distributor = $this->distributorService->findForCompany($compnayID,$distributorID);
        return fractal()
            ->item($distributor)
            ->transformWith(new DistributorTransformer())
            ->parseIncludes(['tax_information','state','superStockist'])
            ->toArray();
    }
    public function getForSuperStockist(Request $request,$companyID, $superStockistId, $distributorId)
    {
        $distributor = $this->distributorService->findForSuperStockist(Auth::user(),$companyID,$superStockistId,$distributorId);
        return fractal()
                ->item($distributor)
                ->parseIncludes(['superStockist','tax_information','state'])
                ->transformWith(new DistributorTransformer())
                ->toArray();
    }
    public function getShippingAddresses(Request $request,$companyID,$distributorID)
    {
         $addresses = $this->distributorService->getShippingAddresses($companyID,$distributorID);
         return fractal()
            ->collection($addresses)
            ->transformWith(new AddressTransformer())
            ->toArray();
    }
    public function createShippingAddresses(Request $request,$companyID,$distributorID)
    {
        $address = $this->distributorService->createShippingAddresses($companyID,$distributorID,$request->all());
        return fractal()
            ->item($address)
            ->transformWith(new AddressTransformer())
            ->toArray();
    }
    public function updateShippingAddress(Request $request, $companyID, $distributorID, $addressCode)
    {
        if(!Gate::allows('updateDistributors'))
        {
            return response()->json("", 403);
        }
        $address = $this->distributorService->updateShippingAddress($companyID, $distributorID, $addressCode, $request->all());
        return fractal()
            ->item($address)
            ->transformWith(new AddressTransformer())
            ->toArray();
    }
    public function deleteShippingAddress($companyID, $distributorID, $addressCode)
    {
        if(!Gate::allows('deleteDistributors'))
        {
            return response()->json("", 403);
        }
        $this->distributorService->deleteAddress($companyID, $distributorID, $addressCode);
    }
    public function inventory(Request $request, $distributorID)
    {
        $distributor = $this->distributorService->find($distributorID);
        //gets total stock for a distributor
         //#723
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to view this inventory"],401);
            }
        }//End of #723
        $inventories = $this->distributorService->getInventory($distributorID, $request->all());
        $inventoriesArray =  fractal()
            ->collection($inventories)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new StockTransformer($distributor))
            ->toArray();
            
        return [
            "total" => $inventories->total(),
            "page"  => $inventories->currentPage(),
            "per_page"  => $inventories->PerPage(),
            "inventory" => $inventoriesArray,
        ];
    }
    public function exportStock(Request $request, $distributorId)
    {
        $pathToFile = $this->distributorService->exportStock($distributorId, $request->all());
        return response()->download($pathToFile,'StockReport '.Carbon::now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend(true);
    }
    public function importStock(Request $request, $distributorId)
    {
        try
        {
            $distributor = Distributor::find($distributorId);
            $invalidRows = $this->distributorService->importStock($distributor, $request->file('file'));
        }
        catch(FileNotFoundException $e)
        {
            return response()->json(["error" =>"File does not exist."],404);
        }
        return $invalidRows;
    }
    public function getInventory(Request $request, $companyId, $customerType, $distributorID)//DUD 
    {        
        $params = $request->all();
        $distributor = $this->distributorService->find($distributorID);
        $dealer = Dealer::find($request['dealer']);

        //#711
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;//
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDlr)
        {
            if($loggedInUsersDlr->distributor_id != $distributor->id)
            {
                return response()->json(["error" =>"You do not have permission to view this inventory"],401);
            }
        }
        if($loggedInUsersDist)
        {
            if($dealer->distributor_id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to view this inventory"],401);
            }
        }//End of #711
        //gets total stock for a distributor
        /*$distributorInventory = $this->distributorService->getDistributorInventory($distributorID, $params);
        
        $companyInventory = $this->companyService->getInventory(Auth::user(),$companyId,$params, $dealer,true);

        $inventory = $this->inventoryService->calculateStock($companyInventory,$dealer,$params['quantity'], $companyId,$params['min_price'],$params['max_price'],array_values($distributorInventory->all()));*/
        //Above code is commented as part of #898
       
     //   dd($superStockistInventory);
        $companyInventory = $this->companyService->getInventory(Auth::user(),$companyId,$params, $dealer,true);
        $superStockistInventory = $this->superStockistService->getAllSSInventory($params);
        if($distributor->super_stockist_id != null)
        {
            //$superStockistInventory = $this->superStockistService->getSSInventory($distributor->super_stockist_id, $params);           
            
            $distributorInventory = (object) [];
            if($dealer->distributor_id){
                //$distributorInventory = $this->distributorService->getDistributorInventory($dealer->distributor_id, $params)->all();
                $distributorInventory  = $this->distributorService->getAllDistributorInventory($params)->all();
            }
            
            $inventory = $this->inventoryService->calculateStock($companyInventory,$dealer,$params['quantity'], $companyId,$params['min_price'],$params['max_price'],array_values($superStockistInventory->all()), array_values($distributorInventory));
        }
        else
        {
            //dd($superStockistInventory);
            $distributorInventory = (object) [];
            if($dealer->distributor_id){
               //$distributorInventory = $this->distributorService->getDistributorInventory($dealer->distributor_id, $params)->all();
               $distributorInventory  = $this->distributorService->getAllDistributorInventory($params)->all();
            
            }
            $inventory = $this->inventoryService->calculateStock($companyInventory,$dealer,$params['quantity'], $companyId,$params['min_price'],$params['max_price'], array_values($superStockistInventory->all()), array_values($distributorInventory));
        }     
        
        $customerType = 'DEALER';
        if(!empty($dealer->distributor->superStockist)){
            $customerType = 'SUPER_STOCKIST_DEALER';
        }

        $inventoriesArray = fractal()
            ->Collection($inventory)
            ->parseIncludes(['price'])
            ->transformWith(new InventoryTransformer($dealer->company, $customerType, $dealer))//FOR DUD
            ->toArray();
            $inventoriesArray = collect($inventoriesArray);
            $inventoriesArray = $inventoriesArray->filter(function($item){
                return $item['inventory'] > 0;
            });
            $inventoriesArray = $inventoriesArray->values()->toArray();
            return [
            /*    "total" => $inventories->total(),
                "page"  => $inventories->currentPage(),
                "per_page"  => $inventories->PerPage(),*/
                "inventory" => $inventoriesArray,
            ];
    }
    public function inventoryDetails($distributorID, $productID)
    {
        //gets product stock (with inventory details) across distributors inventories 
        $inventories = $this->distributorService->getInventoryDetails($distributorID, $productID);
        return fractal()
            ->collection($inventories)
            ->transformWith(new RemainingStockTransformer())
            ->toArray();
    }
    public function getInventoryDetails(Request $request, $companyId, $distributorID, $productID)
    {
        //gets product stock (with inventory details) across distributors inventories 
        $location_codes = null;
        $dealer = Dealer::find($request['dealer']);        
        $requested_stock_point_id = $request->get('stock_point_id');
        $requested_stock_point_source = $request->get('stock_point_source');
        $distributorID = ($requested_stock_point_id!=$distributorID)?$requested_stock_point_id:$distributorID;
        $distributor = Distributor::find($distributorID);

        $ledgerEntries = [];
        if($requested_stock_point_id == null)
        {
            $superStockistId = (($requested_stock_point_id>0) && $requested_stock_point_source=="SuperStockist")?$requested_stock_point_id:$distributor->super_stockist_id;
            if($superStockistId != null)
            {
                $superStockist = SuperStockist::find($superStockistId);
                $super_stockist_stock_points = $superStockist->stock_points;
                foreach ($super_stockist_stock_points as $stock_point)
                {
                    $location_codes .= $stock_point->stock_point->code;
                    $location_codes .= '|';
                }
                $location_codes = substr($location_codes,0,-1);
            }
         //   $location_codes = implode('|',$distributor->stock_points->pluck('code'));
        }
        else
        {
            if($requested_stock_point_source != 'StockPoint')
            {
                $location_codes = null;
            }
            else
            {
                $locations = StockPoint::where('id', '=', $requested_stock_point_id)->first();
                $location_codes = $locations->code;
            }
        }
        if($location_codes)
        {
            $ledgerEntries = $this->inventoryService->getLedgerEntries(Auth::user(),$companyId,$productID,$location_codes);
            $ledgerEntries = $this->inventoryService->calculateStockAgainstBatch($productID,$ledgerEntries,$dealer, $companyId,null);
        }
        
        if($requested_stock_point_source == 'Distributor')
        {
            $distributorInventory = $this->distributorService->getDistributorInventoryDetails($distributorID, $productID, $request->all());
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
        }
        
        //if stock point is not sepcified (select all) or distributor stock point is selected
        //commented as part of #898
        if($requested_stock_point_id == null || $requested_stock_point_source == 'SuperStockist')
        {
            $superStockistId = (($requested_stock_point_id>0) && $requested_stock_point_source=="SuperStockist")?$requested_stock_point_id:$distributor->super_stockist_id;
            if( $superStockistId != null)
            {
                $superStockist = SuperStockist::find( $superStockistId);
                $superStockistInventory = $this->superStockistService->getSSInventoryDetails($superStockist, $productID, $request->all());
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
        }//if stock point is not sepcified (select all) or Super Stockist stock point is selected*/
        return fractal()
                ->Collection($ledgerEntries)
                ->transformWith(new LedgerEntryTransformer())
                ->toArray();

    /*    return fractal()
            ->collection($inventories)
            ->transformWith(new DistributorRemainingStockTransformer())
            ->toArray();*/
    }
    public function getInTransitInventoryDetails(Request $request, $companyId, $distributorID, $productID)
    {
        //gets product stock (with inventory details) across distributors inventories 
        $location_codes = null;
        $dealer = Dealer::find($request['dealer']);
        $distributor = Distributor::find($distributorID);
        $requested_stock_point_id = $request->get('stock_point_id');
        $requested_stock_point_source = $request->get('stock_point_source');
        $ledgerEntries = [];
        if($requested_stock_point_id == null)
        {
            $distributor_stock_points = $distributor->stock_points;
            foreach ($distributor_stock_points as $stock_point)
            {
                $location_codes .= $stock_point->stock_point->code;
                $location_codes .= '|';
            }
            $location_codes = substr($location_codes,0,-1);
         //   $location_codes = implode('|',$distributor->stock_points->pluck('code'));
        }
        else
        {
            if($requested_stock_point_source != 'StockPoint')
            {
                $location_codes = null;
            }
            else
            {
                $locations = StockPoint::where('id', '=', $requested_stock_point_id)->first();
                $location_codes = $locations->code;
            }
        }
        if($location_codes)
        {
            $pendingPOEntries = $this->inventoryService->getPendingPurchaseOrders(Auth::user(),$companyId,$productID,$location_codes);
            $ledgerEntries = $this->inventoryService->formattedLedgerEntries($pendingPOEntries);
            $ledgerEntries = $this->inventoryService->calculateIntransitStockAgainstBatch($productID,$ledgerEntries,$dealer, $companyId);
        }
        if($requested_stock_point_id == null || $requested_stock_point_source == 'Distributor')
        {
            $distributorInventory = $this->distributorService->getDistributorInventoryDetails($distributorID, $productID, $request->all());
            foreach($distributorInventory as $stock)
            {
                $ile = new ILE;
                $distName = (!empty($distributor->display_name))?$distributor->display_name:$distributor->name;
                $ile->setLot_no($stock->batch_no);
                $ile->setRemaining_Quantity($stock->quantity);
                $ile->setLocation_Code($distName);
                $ile->setLocation_Name($distName);
                $ile->setLocation_Source('Distributor');
                if($stock->distributor->id != $distributor->id)
                {
                    $ile->setCoLocation($stock->distributor);
                }
                $ledgerEntries[] = $ile;
            }
        }//if stock point is not sepcified (select all) or distributor stock point is selected
        return fractal()
                ->Collection($ledgerEntries)
                ->transformWith(new LedgerEntryTransformer())
                ->toArray();

    /*    return fractal()
            ->collection($inventories)
            ->transformWith(new DistributorRemainingStockTransformer())
            ->toArray();*/
    }
    public function addToCart(Request $request, $companyId, $distributorID)
    {
        $distributor = Distributor::find($distributorID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;

        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to add items to this Distributor's Cart"],401);
            }
        }
        if($loggedInUsersDlr)
        {
            return response()->json(["error" =>"You do not have permission to add items to Distributor's Cart"],401);
        }
        //End of #720
        $cartItems = $this->cartItemsService->create($request->all(), $distributor);

        return fractal()
            ->collection($cartItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new CartItemTransformer($distributor))
            ->toArray();
    }
    public function addToAdvanceCart(Request $request, $companyId, $distributorID)
    {
        $distributor = Distributor::find($distributorID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;

        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to add items to this Distributor's Cart"],401);
            }
        }
        if($loggedInUsersDlr)
        {
            return response()->json(["error" =>"You do not have permission to add items to Distributor's Cart"],401);
        }
        //End of #720
        $cartItems = $this->cartItemsService->create($request->all(), $distributor);

        return fractal()
            ->collection($cartItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new CartItemTransformer($distributor))
            ->toArray();
    }
    public function updateCart(Request $request, $companyId, $distributorID)
    {
        $distributor = Distributor::find($distributorID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;

        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to add items to this Distributor's Cart"],401);
            }
        }
        if($loggedInUsersDlr)
        {
            return response()->json(["error" =>"You do not have permission to add items to Distributor's Cart"],401);
        }
        //End of #720  
        $cartItems = $this->cartItemsService->update($request->all(), $distributor);
    }
    public function blockItems(Request $request, $companyId, $distributorID)
    {
        $distributor = Distributor::find($distributorID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;

        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to block items for this Distributor"],401);
            }
        }
        if($loggedInUsersDlr)
        {
            return response()->json(["error" =>"You do not have permission to block items for Distributor"],401);
        }
        //End of #720  
        try
        {
            $blockedItems = $this->cartItemsService->block($request->all(), $distributor);
        }
        catch(InsufficientQuantityToBlockException $e)
        {
            return response()->json(["error" =>"Insufficient Quantity to Block as Stock for this Product's Batch has changed."],412);
        }
        catch(BlockItemDeniedException $e)
        {
            //return response()->json(["error" =>'You cannot block this item till '. ($e->cool_down_end_time)->isoFormat('DD/MM/YYYY h:m A')],412);
            return response()->json([
                "key" => "cannot_block",
                "error" => "You cannot block this item till ",
                "until" => $e->cool_down_end_time->addMinutes()->format('U')],412);
        }
        return fractal()
            ->collection($blockedItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new BlockedItemTransformer($distributor))
            ->toArray();
    }
    public function unBlockItems(Request $request, $companyId, $distributorID)
    {
        $distributor = Distributor::find($distributorID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;

        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to unblock items for this Distributor"],401);
            }
        }
        if($loggedInUsersDlr)
        {
            return response()->json(["error" =>"You do not have permission to unblock items for Distributor"],401);
        }
        //End of #720  
        $ublockedItems = $this->cartItemsService->unBlock($request->all(), $distributor);
    }
    public function addStock(Request $request, $companyId, $distributorId)
    {
        $distributor = Distributor::find($distributorId);
        $inventory = $this->distributorService->addStock($companyId, $distributorId, $request->all());
        if(is_null($inventory))
        {
            return response()->json([
                            "error" => "Product not found. Please add product and try again."
            ])->setStatusCode(409);
        }
        return fractal()
            ->item($inventory)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new StockTransformer($distributor))
            ->toArray();
    }
    public function deleteStock($companyId, $distributorId, $stockId)
    {
        $inventory = $this->distributorService->deleteStock($distributorId, $stockId);
    }
    public function unBlockMyStock($companyId, $distributorID, $itemId)
    {
        $distributor = Distributor::find($distributorID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;

        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to unblock items for this Distributor"],401);
            }
        }
        if($loggedInUsersDlr)
        {
            return response()->json(["error" =>"You do not have permission to unblock items for Distributor"],401);
        }
        //End of #720  
        try
        {
            $ublockedItems = $this->distributorService->unBlockMyStock($itemId, $distributorID);
        }
        catch(ErrorUnblockingStock $e)
        {
            return response()->json(["error" =>"Sorry! Product coud not be Unblocked."],412);
        }
    }
    public function getCart($companyID, $distributorID)
    {
        $distributor = Distributor::find($distributorID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;

        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to view Cart of this Distributor"],401);
            }
        }
        if($loggedInUsersDlr)
        {
            return response()->json(["error" =>"You do not have permission to view Cart of Distributor"],401);
        }
        //End of #720  
        $cartItems = $this->cartItemsService->get($distributor);

        return fractal()
            ->collection($cartItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new CartItemTransformer($distributor))
            ->toArray();
    }
    public function getBlockedItems($companyID, $distributorID)
    {
        $distributor = Distributor::find($distributorID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;

        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to view blocked items of this Distributor"],401);
            }
        }
        if($loggedInUsersDlr)
        {
            return response()->json(["error" =>"You do not have permission to view blocked items of Distributor"],401);
        }
        //End of #720  
        $blockedItems = $this->cartItemsService->getBlockedItems($distributor);

        return fractal()
            ->collection($blockedItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new BlockedItemTransformer($distributor))
            ->toArray();
    }
    public function getMyBlockedStock(Request $request, $companyID, $distributorID)
    {
        $distributor = Distributor::find($distributorID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;

        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to view blocked items of this Distributor"],401);
            }
        }
        //End of #720  
        $blockedStock = $this->distributorService->getMyBlockedStock($distributor, $request->all());
        $blockedStockArray =  fractal()
            ->collection($blockedStock)
            ->transformWith(new DistributorBlockedStockTransformer($distributor))
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->toArray();

            return [
                "total" => $blockedStock->total(),
                "page"  => $blockedStock->currentPage(),
                "per_page"  => $blockedStock->PerPage(),
                "items" => $blockedStockArray,
            ];
    }
    public function deleteFromCart(Request $request, $companyId, $distributorID)
    {
        $distributor = Distributor::find($distributorID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;

        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to delete items from this Distributor's cart"],401);
            }
        }
        if($loggedInUsersDlr)
        {
            return response()->json(["error" =>"You do not have permission to delete items from Distributor's cart"],401);
        }
        //End of #720  
        $this->cartItemsService->deleteFromCart($request->all(), $distributor);
    }
    public function inventoryAdjust(Request $request,$distributorID,$inventoryID)
    {
        $distributor = $this->distributorService->find($distributorID);
        $inventory = $this->distributorService->adjustInventory($distributorID,$inventoryID,$request->get('operation'),$request->get('quantity'));
        return fractal()
            ->item($inventory)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new StockTransformer($distributor))
            ->toArray();

    }
    public function sync(Request $request, $companyId)
    {
        if(!Gate::allows('createDistributors'))
        {
            return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
            "password"    => 'required',
            "email" => 'unique:users,email'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $company = $this->companyService->find($companyId);
        $distributor = $company->distributors()->where('nav_id', '=', $request->get('nav_id'))->first();
        if($distributor)
            return response()->json([],412);
        $distributor = $this->distributorService->sync(Auth::user(),$company, $request->get('nav_id'));
        if($distributor == null)
            return response()->json("Super Stockist not synced",409);
        $this->userService->create(Auth::user(), $request->all(), 'distributor', $distributor);
        $this->activeProductAttributesService->load($companyId, 'distributor', $distributor->id);
        return fractal()
            ->item($distributor)
            ->transformWith(new DistributorTransformer())
            ->toArray();
    }
   /* public function syncUnderSuperStockist(Request $request, $companyId, $superStockistId)
    {
        if(!Gate::allows('createDistributors'))
        {
            return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
            "password"    => 'required',
            "email" => 'unique:users,email'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $company = $this->companyService->find($companyId);
        $distributor = $company->distributors()->where('nav_id', '=', $request->get('nav_id'))->first();
        if($distributor)
            return response()->json([],412);
        $distributor = $this->distributorService->sync(Auth::user(),$company, $request->get('nav_id'));
        if($distributor == null)
            return response()->json("Super Stockist not synced",409);
        $this->userService->create(Auth::user(), $request->all(), 'distributor', $distributor);
        $this->activeProductAttributesService->load($companyId, 'distributor', $distributor->id);
        return fractal()
            ->item($distributor)
            ->transformWith(new DistributorTransformer())
            ->toArray();
    }*/
    public function getEngineers(Request $request,$companyID,$distributorId)
    {
         $engineers = $this->distributorService->getEngineers($distributorId);
         return fractal()
            ->collection($engineers)
            ->transformWith(new EngineerTransformer())
            ->toArray();
    }
    public function getCLRates(Request $request,$companyId,$distributorId)
    {
        $company = Company::find($companyId);
        $distributor = $company->distributors()->find($distributorId);
         //#723
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to view this data"],401);
            }
        }//End of #723
        $cl_rates = $this->clRateService->getAll($distributor,$request->all());

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
    public function createCLRate(Request $request,$companyId,$distributorId)
    {
        $company = Company::find($companyId);
        $distributor = $company->distributors()->find($distributorId);
         //#723
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to add this data"],401);
            }
        }//End of #723
        try{
            $clRate = $this->clRateService->create($distributor,$request->all());
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
    public function deleteCLRate(Request $request,$companyId,$distributorId,$clId)
    {
        $company = Company::find($companyId);
        $distributor = $company->distributors()->find($distributorId);
         //#723
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to delete this data"],401);
            }
        }//End of #723
        try{
            $clRate = $this->clRateService->delete($distributor,$clId);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json([])->setStatusCode(404);
        }
    }
    public function setPaymentType(Request $request,$companyId,$distributorId)
    {
        $company = Company::find($companyId);
        $distributor = $company->distributors()->find($distributorId);
        $distributor = $this->distributorService->setPaymentType($distributor,$request->all());
        return fractal()
            ->item($distributor)
            ->transformWith(new IndexTransformer())
            ->parseIncludes(['paymentTypes'])
            ->toArray();
    }
    public function exportDistributors(Request $request, $companyId, $superStockistId=null)
    {
        $superStockists = [];
        if($superStockistId != null)
        {
            $superStockist = $this->superStockistService->find($superStockistId);
            $company = $superStockist->company;
        }
        else
        {
            $company = $this->companyService->find($companyId);
            $superStockist = null;
            $selectedCustomers = $request['selectedCustomers'];//#826
            foreach ($selectedCustomers as $superStockist) 
            {
                $superStockists[] = $superStockist;
            }
        }
        $pathToFile = $this->distributorService->exportDistributors($company, $superStockist, $superStockists);
        return response()->download($pathToFile,'DistributorReport_'.Carbon::now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend(false);
    }//#813
}
