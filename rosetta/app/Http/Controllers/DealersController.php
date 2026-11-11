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
use Rosetta\Exceptions\Order\BlockItemDeniedException;
use Rosetta\Exceptions\Order\InsufficientQuantityToBlockException;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\TempDealer;
use Rosetta\Notifications\SMS\AccountCreatedNotification;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;
use Rosetta\Services\CartItems\CartItemsServiceInterface;
use Rosetta\Services\Companies\CompanyServiceInterface;
use Rosetta\Services\Dealer\DealerServiceInterface;
use Rosetta\Services\Distributor\DistributorServiceInterface;
use Rosetta\Services\Users\UserServiceInterface;
use Rosetta\Transformers\CartItem\BlockedItemTransformer;
use Rosetta\Transformers\CartItem\CartItemTransformer;
use Rosetta\Transformers\CustomerTransformer;
use Rosetta\Transformers\Dealers\CompactTransformer;
use Rosetta\Transformers\Dealers\DealerTransformer;
use Rosetta\Transformers\Dealers\IndexTransformer;
use Rosetta\Transformers\Dealers\ProfileTransformer;
use Rosetta\Transformers\Dealers\SearchResponseTransformer;
use Rosetta\Transformers\Dealers\TempDealerTransformer;
use Rosetta\Transformers\Dealers\TempDealersTransformer;
use Rosetta\Transformers\EngineerTransformer;
use Rosetta\Transformers\NAV\AddressTransformer;
use Rosetta\Transformers\Stock\RemainingStockTransformer;
use Rosetta\Transformers\Stock\StockTransformer;

class DealersController extends Controller
{
	protected $dealerService, $companyService, $distributorService, $cartItemsService, $userService, $activeProductAttributesService;
	public function __construct(DealerServiceInterface $dealerService, CompanyServiceInterface $companyService, DistributorServiceInterface $distributorService, CartItemsServiceInterface $cartItemsService, UserServiceInterface $userService, ActiveProductAttributesServiceInterface $activeProductAttributesService)
	{
		$this->dealerService = $dealerService;
		$this->companyService = $companyService;
		$this->distributorService = $distributorService;
        $this->cartItemsService = $cartItemsService;
        $this->userService = $userService;
        $this->activeProductAttributesService = $activeProductAttributesService;
	}
	public function create(Request $request, $companyId, $distributorId=null)
	{ 
		if(!Gate::allows('createDealers'))
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

        //Start #836
        $params = $request->all();
        if(array_has($params,'tax_information'))
        {
            $taxInformation = $params['tax_information'];
            if(array_has($taxInformation,'gst'))
            {
                $gst = $taxInformation['gst'];
                if(isset($gst['number']))
                {
                    $gstin = $gst['number'];
                    $system_gst_count = Dealer::where('gstin','=',$gstin)->count();
                    if($system_gst_count >= 1)
                    {
                      return response()->json("Duplicate GSTIN",400);
                    }   
                    if(strlen($gstin) != 15)
                    {
                        return response()->json("Invalid GSTIN",409);
                    }
                }
            }
        }//End #836
		if($distributorId != null)
		{
			$user = Auth::user();
            if($user->hasRole('distributorAdmin'))
            {
                return $dealer = $this->dealerService->createDealerUnderDistributor($user, $companyId, $distributorId, $request->all());
            }
            else
            {
                $distributor = $this->distributorService->find($distributorId);
                $company = $distributor->company;   
            }
		}
		else
		{
			$company = $this->companyService->find($companyId);
			$distributor = null;
		}

        if(array_has($params, 'newDistributorId'))//#827
		{
			if($params['newDistributorId'] != null)
			{
				$distributor = Distributor::find($params['newDistributorId']);
			}
			else
			{
				$distributor = null;
			}			
		}

		$dealer = $this->dealerService->create(Auth::user(), $request->all(), $company, $distributor);
        $user = $this->userService->create(Auth::user(), $request['user'], 'dealer', $dealer);
        if($distributor == null)
            $this->activeProductAttributesService->load($company->id, 'dealer', $dealer->id);
        else
            $this->activeProductAttributesService->load($company->id, 'dealer', $dealer->id, $distributorId);

        $notifiable = (object)[
            "customer" => $dealer->name,
            "number" => $user->phone
        ];
        SMSNotification::send($notifiable,new AccountCreatedNotification($company));//#595 Notify Customer

        return fractal()
            ->item($dealer)
            ->parseIncludes(['distributor'])
            ->transformWith(new DealerTransformer())
            ->toArray();

	}
    public function createTempDealers(Request $request)
    { 
        if(!Gate::allows('createDealers'))
        {
            return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
         //   "email" => 'unique:users,email'
            "user.email" => [
                Rule::unique('users', 'email')->where(function($query){
                    return $query->whereNull('deleted_at');
                }),
            ],
            "user.phone" => [
                Rule::unique('users', 'phone')->where(function($query){
                    return $query->whereNull('deleted_at');
                }),
            ],
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $user = Auth::user();
        $dealer = $this->dealerService->createTempDealer($user,$request->all());
        /*return fractal()
            ->item($dealer)
            ->parseIncludes(['distributor'])
            ->transformWith(new DealerTransformer())
            ->toArray();*/
    }
    public function updateCompanyDealer(Request $request, $companyId, $dealerId)
    {
        if(!Gate::allows('updateDealers'))
        {
            return response()->json("", 403);
        }
        /*$validator = Validator::make($request->all(), [
            "nav_id"  => 'required',
            "name"    => 'required',
            "cust_price_grp" => 'required',
            "cust_post_grp" => 'required',
            "copySellFrom" => 'required',
            "paymentApplicationMethod" => 'required',
            "paymentTerms" => 'required',
            "shippingReserve" => 'required',
            "shippingAdvice" => 'required',
            "address" => 'required'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }*/
        /*if($distributorId != null)
        {
            $user = Auth::user();
            if(!$user->hasRole('distributorAdmin'))
            {
                $distributor = $this->distributorService->find($distributorId);
                $company = $distributor->company;   
            }
        }
        else
        {*/
            $company = $this->companyService->find($companyId);
            $distributor = null;
        //}
            $dealer = $company->dealers()->find($dealerId);

        //Start #836
        $params = $request->all();
        if(array_has($params,'tax_information'))
        {
            $taxInformation = $params['tax_information'];
            if(array_has($taxInformation,'gst'))
            {
                $gst = $taxInformation['gst'];
                if(isset($gst['number']))
                {
                    $gstin = $gst['number'];
                    $system_gst_count = Dealer::where('gstin','=',$gstin)->count();
                    if($system_gst_count == 1)
                    {
                        $matching_dealer = Dealer::where('gstin','=',$gstin)->first();
                        if($matching_dealer->id != $dealerId)
                        {
                            return response()->json("Duplicate GSTIN",400);
                        }
                    }
                    elseif($system_gst_count > 1)
                    {
                        return response()->json("Duplicate GSTIN",400);
                    }
                    if(strlen($gstin) != 15)
                    {
                        return response()->json("Invalid GSTIN",409);
                    }
                }
            }
        }//End #836
        $dealer = $this->dealerService->update(Auth::user(),$company,$dealer,$request->all());
        return fractal()
            ->item($dealer)
            ->parseIncludes(['distributor'])
            ->transformWith(new DealerTransformer())
            ->toArray();
    }
    public function update(Request $request, $companyId, $distributorId, $dealerId)
    {
        if(!Gate::allows('updateDealers'))
        {
            return response()->json("", 403);
        }
        if($distributorId != null)
        {
            $user = Auth::user();
         //   if(!$user->hasRole('distributorAdmin'))
           // {
                $distributor = $this->distributorService->find($distributorId);
                $company = $distributor->company;   
            //}
        }
        else
        {
            $company = $this->companyService->find($companyId);
            $distributor = null;
        }
        $dealer = $distributor->dealers()->find($dealerId);
        //Start #836
        $params = $request->all();
        if(array_has($params,'tax_information'))
        {
            $taxInformation = $params['tax_information'];
            if(array_has($taxInformation,'gst'))
            {
                $gst = $taxInformation['gst'];
                if(isset($gst['number']))
                {
                    $gstin = $gst['number'];
                    $system_gst_count = Dealer::where('gstin','=',$gstin)->count();
                    if($system_gst_count == 1)
                    {
                        $matching_dealer = Dealer::where('gstin','=',$gstin)->first();
                        if($matching_dealer->id != $dealerId)
                        {
                            return response()->json("Duplicate GSTIN",400);
                        }
                    }
                    elseif($system_gst_count > 1)
                    {
                        return response()->json("Duplicate GSTIN",400);
                    }
                    if(strlen($gstin) != 15)
                    {
                        return response()->json("Invalid GSTIN",409);
                    }
                }
            }
        }//End #836
        $dealer = $this->dealerService->update(Auth::user(),$company,$dealer,$request->all());
        return fractal()
            ->item($dealer)
            ->parseIncludes(['distributor'])
            ->transformWith(new DealerTransformer())
            ->toArray();
    }
    public function profile(Request $request,$companyID,$dealerID)
    {
        if(!Gate::allows('manageProfile'))
        {
            return response()->json("", 403);
        }
        $company = $this->companyService->find($companyID);
        $dealer = Dealer::find($dealerID);
        $dealer = $this->dealerService->update(Auth::user(),$company,$dealer, $request->all());
        return fractal()
            ->item($dealer)
            ->transformWith(new ProfileTransformer())
            ->parseIncludes(['tax_information','state'])
            ->toArray();
    }
    public function search(Request $request, $companyId, $distributorId=null)
    {
        if(!Gate::allows('viewDealers'))
        {
            return response()->json("", 403);
        }
        if($distributorId != null)
        {
            $distributor = $this->distributorService->find($distributorId);
            $company = $distributor->company;
            //#723
            $loggedInUser = Auth::user();
            $loggedInUsersDist = $loggedInUser->distributor;
            if($loggedInUsersDist)
            {
                if($distributor->id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permissions to view this data"],401);
                }
            }//End of #723
        }
        else
        {
            $company = $this->companyService->find($companyId);
            $distributor = null;
        }
        $dealers = $this->dealerService->getDealers($company, $distributor, $request->all());
        
        $dealersArray =  fractal()
            ->collection($dealers)
            ->transformWith(new SearchResponseTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $dealers->total(),
                "page"  => $dealers->currentPage(),
                "dealers" => $dealersArray,
            ];
        }
        else
            return $dealersArray;
    }
	public function index(Request $request, $companyId, $distributorId=null)
	{
        
		if(!Gate::allows('viewDealers'))
        {
       		return response()->json("", 403);
        }
		if($distributorId != null)
		{
			$distributor = $this->distributorService->find($distributorId);
			$company = $distributor->company;
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
		}
		else
		{
			$company = $this->companyService->find($companyId);
			$distributor = null;
		}
		$dealers = $this->dealerService->getDealers($company, $distributor, $request->all());
    	
        $dealersArray =  fractal()
            ->collection($dealers)
            ->transformWith(new CompactTransformer())
            ->parseIncludes(['paymentTypes'])
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $dealers->total(),
                "page"  => $dealers->currentPage(),
                "dealers" => $dealersArray,
            ];
        }
        else
            return $dealersArray;
	}
    public function getTempDealers(Request $request, $companyId, $distributorId =null)
    {
        if(!Gate::allows('viewDealers'))
        {
            return response()->json("", 403);
        }
        $company = $this->companyService->find($companyId);
        $dealers = $this->dealerService->getTempDealers($company, $distributorId, $request->all());
        
        $dealersArray =  fractal()
            ->collection($dealers)
            ->transformWith(new TempDealersTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $dealers->total(),
                "page"  => $dealers->currentPage(),
                "dealers" => $dealersArray,
            ];
        }
        else
            return $dealersArray;
    }
    public function getRejectedDealers(Request $request, $companyId, $distributorId)
    {
        if(!Gate::allows('viewDealers'))
        {
            return response()->json("", 403);
        }
        $company = $this->companyService->find($companyId);
        $dealers = $this->dealerService->getRejectedDealers($company, $distributorId, $request->all());
        
        $dealersArray =  fractal()
            ->collection($dealers)
            ->transformWith(new TempDealersTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $dealers->total(),
                "page"  => $dealers->currentPage(),
                "dealers" => $dealersArray,
            ];
        }
        else
            return $dealersArray;
    }
    public function findCompTempDealer($companyId, $id)
    {
        if(!Gate::allows('viewDealers'))
        {
            return response()->json("", 403);
        }
        try
        {
            $company = $this->companyService->find($companyId);
            $dealer = $this->dealerService->findTempDealer($company, $id);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json([], 404);
        }
        return fractal()
            ->item($dealer)
            ->transformWith(new TempDealerTransformer())
            ->toArray();
    }
    public function findTempDealer($id)
    {
        if(!Gate::allows('viewDealers'))
        {
            return response()->json("", 403);
        }
        try
        {
            $dealer = $this->dealerService->findTempDealer($company=null, $id);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json([], 404);
        }
        return fractal()
            ->item($dealer)
            ->transformWith(new TempDealerTransformer())
            ->toArray();
    }
    public function approveCompTempDealer($companyId, $id)
    {
        if(!Gate::allows('approveDealers'))
        {
            return response()->json("", 403);
        }
        try
        {
            $company = $this->companyService->find($companyId);
            $this->dealerService->approveDealer(Auth::user(), $company, $id);

         /*   return fractal()
                ->item($dealer)
                ->parseIncludes(['distributor'])
                ->transformWith(new DealerTransformer())
                ->toArray();*/
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json([], 404);
        }
    }
    public function approveTempDealer($id)
    {
        if(!Gate::allows('approveDealers'))
        {
            return response()->json("", 403);
        }
        try
        {
            $dealer = $this->dealerService->approveDealer(Auth::user(), $company=null, $id);

            return fractal()
                ->item($dealer)
                ->parseIncludes(['distributor'])
                ->transformWith(new DealerTransformer())
                ->toArray();
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json([], 404);
        }
    }
    public function rejectTempDealer(Request $request, $companyId, $id)
    {
        if(!Gate::allows('rejectDealers'))
        {
            return response()->json("", 403);
        }
        try
        {
            $company = Company::find($companyId);
            $dealer = TempDealer::find($id);
            $dealer = $this->dealerService->rejectDealer(Auth::user(), $company, $dealer, $request->all());
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json([], 404);
        }
    }
    public function inventory(Request $request, $dealerID)
    {
        $dealer = $this->dealerService->find($dealerID);
        //gets total stock for a dealer
        $inventories = $this->dealerService->getInventory($dealerID, $request->all());
        $inventoriesArray =  fractal()
            ->collection($inventories)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new StockTransformer($dealer))
            ->toArray();

            return [
                "total" => $inventories->total(),
                "page"  => $inventories->currentPage(),
                "per_page"  => $inventories->PerPage(),
                "inventory" => $inventoriesArray,
            ];   
    }
    public function inventoryDetails($dealerID, $productID)
    {
        //gets product stock (with inventory details) across dealers inventories 
        $inventories = $this->dealerService->getInventoryDetails($dealerID, $productID);
        return fractal()
            ->collection($inventories)
            ->transformWith(new RemainingStockTransformer())
            ->toArray();
    }
    public function getForCompany(Request $request,$companyID, $dealerID)
    {
        $dealer = $this->dealerService->findForCompany(Auth::user(),$companyID,$dealerID);
        return fractal()
                ->item($dealer)
                ->parseIncludes(['distributor','tax_information','state', 'sales_manager'])
                ->transformWith(new DealerTransformer())
                ->toArray();
    }
    public function getForDistributor(Request $request,$companyID, $distributorID, $dealerID)
    {
        $dealer = $this->dealerService->findForDistributor(Auth::user(),$companyID,$distributorID,$dealerID);
        return fractal()
                ->item($dealer)
                ->parseIncludes(['distributor','tax_information','state'])
                ->transformWith(new DealerTransformer())
                ->toArray();
    }
    public function createShippingAddresses(Request $request,$companyID, $dealerID)
    {
        $address = $this->dealerService->createShippingAddresses($companyID,$dealerID,$request->all());
        return fractal()
            ->item($address)
            ->transformWith(new AddressTransformer())
            ->toArray();
    }
    public function updateShippingAddress(Request $request, $companyID, $dealerID, $addressCode)
    {
        if(!Gate::allows('updateDealers'))
        {
            return response()->json("", 403);
        }
        $address = $this->dealerService->updateShippingAddress($companyID, $dealerID, $addressCode, $request->all());
        return fractal()
            ->item($address)
            ->transformWith(new AddressTransformer())
            ->toArray();
    }
    public function getShippingAddresses(Request $request,$companyID,$dealerID)
    {
         $addresses = $this->dealerService->getShippingAddresses($companyID,$dealerID);
         return fractal()
            ->collection($addresses)
            ->transformWith(new AddressTransformer())
            ->toArray();
    }
    public function deleteShippingAddress($companyID, $dealerID, $addressCode)
    {
        if(!Gate::allows('deleteDealers'))
        {
            return response()->json("", 403);
        }
        $this->dealerService->deleteAddress($companyID, $dealerID, $addressCode);
    }
    public function createCustomers(Request $request,$companyID, $dealerID)
    {
        $customer = $this->dealerService->createCustomers($companyID,$dealerID,$request->all(), Auth::user());
        return fractal()
            ->item($customer)
            ->transformWith(new CustomerTransformer())
            ->toArray();
    }
    public function updateCustomer(Request $request, $companyID, $dealerID, $customerId)
    {
        if(!Gate::allows('updateDealers'))
        {
            return response()->json("", 403);
        }
        $customer = $this->dealerService->updateCustomers($dealerID, $customerId, $request->all());
        return fractal()
            ->item($customer)
            ->transformWith(new CustomerTransformer())
            ->toArray();
    }
    public function getCustomers(Request $request,$companyID,$dealerID)
    {
         $customers = $this->dealerService->getCustomers($dealerID);
         return fractal()
            ->collection($customers)
            ->transformWith(new CustomerTransformer())
            ->toArray();
    }
    public function deleteCustomer($companyID, $dealerID, $customerId)
    {
        if(!Gate::allows('deleteDealers'))
        {
            return response()->json("", 403);
        }
        $this->dealerService->deleteCustomer($dealerID, $customerId);
    }
    public function createEngineers(Request $request,$companyID, $dealerID)
    {
        $engineer = $this->dealerService->createEngineers($companyID,$dealerID,$request->all(), Auth::user());
        return fractal()
            ->item($engineer)
            ->transformWith(new EngineerTransformer())
            ->toArray();
    }
    public function getEngineers(Request $request,$companyID,$dealerID)
    {
         $engineers = $this->dealerService->getEngineers($dealerID);
         return fractal()
            ->collection($engineers)
            ->transformWith(new EngineerTransformer())
            ->toArray();
    }
    public function addToCart(Request $request, $companyId, $dealerID)
    {
        $dealer = Dealer::find($dealerID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDlr)
        {
            if($dealer->id != $loggedInUsersDlr->id)
            {
                return response()->json(["error" =>"You do not have permission to add items to this Dealer's Cart"],401);
            }
        }
        if($loggedInUsersDist)//Distributor adding to Dealer's cart (app)
        {
            if($dealer->distributor_id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to add items to this Dealer's Cart"],401);
            }
        }
        //End of #720
        $cartItems = $this->cartItemsService->create($request->all(), $dealer);
       /* $items = $request['cart_items'];
        foreach ($items as $item)
        {
            if(array_has($item,'distributor_id'))
            {
                $product_id = $item['product_id'];
                $batch_no = $item['batch_no'];
                $order_quantity = $item['order_quantity'];
                $distributorId = $item['distributor_id'];

                $distributor = Distributor::find($distributorId);
                $inventory = $distributor->stocks()->where('product_id', '=', $product_id)
                                                    ->where('batch_no', '=', $batch_no)
                                                    ->first();
                $inventory->quantity -= $order_quantity;
                if($inventory->quantity <= 0)
                    $inventory->delete();
                else
                    $inventory->save();
            }
        }*/
        return fractal()
            ->collection($cartItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new CartItemTransformer($dealer))
            ->toArray();
    }
    public function updateCart(Request $request, $companyId, $dealerID)
    {
        $dealer = Dealer::find($dealerID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDlr)
        {
            if($dealer->id != $loggedInUsersDlr->id)
            {
                return response()->json(["error" =>"You do not have permission to add items to this Dealer's Cart"],401);
            }
        }
        if($loggedInUsersDist)//Distributor adding to Dealer's cart (app)
        {
            if($dealer->distributor_id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to add items to this Dealer's Cart"],401);
            }
        }
        //End of #720
        $cartItems = $this->cartItemsService->update($request->all(), $dealer);
    }
    public function blockItems(Request $request, $companyId, $dealerID)
    {
        $dealer = Dealer::find($dealerID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDlr)
        {
            if($dealer->id != $loggedInUsersDlr->id)
            {
                return response()->json(["error" =>"You do not have permission to block items for this Dealer"],401);
            }
        }
        if($loggedInUsersDist)//Distributor adding to Dealer's cart (app)
        {
            if($dealer->distributor_id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to block items for this Dealer"],401);
            }
        }
        //End of #720
        try
        {
           // $dealer_blocked_items = $dealer->blockedItems->where('product_id','=','1334')->all();
            $blockedItems = $this->cartItemsService->block($request->all(), $dealer);
        }
        catch(InsufficientQuantityToBlockException $e)
        {
            return response()->json(["error" =>"Insufficient Quantity to Block as Stock for this Product's Batch has changed."],412);
        }
        catch(BlockItemDeniedException $e)
        {
            return response()->json([
                "key" => "cannot_block",
                "error" => "You cannot block this item till ",
                "until" => $e->cool_down_end_time->addMinutes()->format('U')],412);
              //  "error" =>'You cannot block this item till '. ($e->cool_down_end_time)->addMinutes()->toDayDateTimeString()],412);
            //return response()->json(["error" =>'You cannot block this item till '. ($e->cool_down_end_time)->addMinutes()->toDayDateTimeString()],412);
        }
        return fractal()
            ->collection($blockedItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new BlockedItemTransformer($dealer))
            ->toArray();
    }
    public function unBlockItems(Request $request, $companyId, $dealerID)
    {
        $dealer = Dealer::find($dealerID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDlr)
        {
            if($dealer->id != $loggedInUsersDlr->id)
            {
                return response()->json(["error" =>"You do not have permission to unBlock items for this Dealer"],401);
            }
        }
        if($loggedInUsersDist)//Distributor adding to Dealer's cart (app)
        {
            if($dealer->distributor_id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to unBlock items for this Dealer"],401);
            }
        }
        //End of #720
        $ublockedItems = $this->cartItemsService->unBlock($request->all(), $dealer);
    }
    public function getCart($companyID, $dealerID)
    {
        $dealer = Dealer::find($dealerID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDlr)
        {
            if($dealer->id != $loggedInUsersDlr->id)
            {
                return response()->json(["error" =>"You do not have permission to view this Dealer's cart"],401);
            }
        }
        if($loggedInUsersDist)//Distributor adding to Dealer's cart (app)
        {
            if($dealer->distributor_id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to view this Dealer's cart"],401);
            }
        }
        //End of #720
        $cartItems = $this->cartItemsService->get($dealer);

        return fractal()
            ->collection($cartItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new CartItemTransformer($dealer))
            ->toArray();
    }
    public function getBlockedItems($companyID, $dealerID)
    {
        $dealer = Dealer::find($dealerID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDlr)
        {
            if($dealer->id != $loggedInUsersDlr->id)
            {
                return response()->json(["error" =>"You do not have permission to view blocked items of this Dealer"],401);
            }
        }
        if($loggedInUsersDist)//Distributor adding to Dealer's cart (app)
        {
            if($dealer->distributor_id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to view locked items of this Dealer"],401);
            }
        }
        //End of #720
        $blockedItems = $this->cartItemsService->getBlockedItems($dealer);

        return fractal()
            ->collection($blockedItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new BlockedItemTransformer($dealer))
            ->toArray();
    }
    public function deleteFromCart(Request $request, $companyId, $dealerID)
    {
        $dealer = Dealer::find($dealerID);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDlr)
        {
            if($dealer->id != $loggedInUsersDlr->id)
            {
                return response()->json(["error" =>"You do not have permission to delete items from this Dealer's cart"],401);
            }
        }
        if($loggedInUsersDist)//Distributor adding to Dealer's cart (app)
        {
            if($dealer->distributor_id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to delete items from this Dealer's cart"],401);
            }
        }
        //End of #720
        $this->cartItemsService->deleteFromCart($request->all(), $dealer);
    }
    public function sync(Request $request, $companyId)
    {
        if(!Gate::allows('createDealers'))
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
        $dealer = $company->dealers()->where('nav_id', '=', $request->get('nav_id'))->first();
        if($dealer)
            return response()->json([],412);
        $dealer = $this->dealerService->sync(Auth::user(),$company, $request->get('nav_id'));
        if($dealer == null)
            return response()->json("Distributor not synced",409);
        $this->userService->create(Auth::user(), $request->all(), 'dealer', $dealer);
        $this->activeProductAttributesService->load($companyId, 'dealer', $dealer->id);
        return fractal()
            ->item($dealer)
            ->transformWith(new DealerTransformer())
            ->toArray();
    }
    public function setPaymentType(Request $request,$companyId,$dealerId)
    {
        $company = Company::find($companyId);
        $dealer = $company->dealers()->find($dealerId);
        $dealer = $this->dealerService->setPaymentType($dealer,$request->all());
        return fractal()
            ->item($dealer)
            ->transformWith(new IndexTransformer())
            ->parseIncludes(['paymentTypes'])
            ->toArray();
    }
    public function exportDealers(Request $request, $companyId, $distributorId=null)
    {
        $distributors = [];
        if($distributorId != null)
        {
            $distributor = $this->distributorService->find($distributorId);
            $company = $distributor->company;
        }
        else
        {
            $company = $this->companyService->find($companyId);
            $distributor = null;
            $selectedCustomers = $request['selectedCustomers'];//#826
            foreach ($selectedCustomers as $distributor) 
            {
                $distributors[] = $distributor;
            }
        }
        $pathToFile = $this->dealerService->exportDealers($company, $distributor, $distributors);
        return response()->download($pathToFile,'DealerReport_'.Carbon::now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend(false);
    }//#813

    public function updateDealerInfoFromWhatsapp(Request $request)
    {        
        // $dealerData = Dealer::where('phone', $request->mobile)->first();
        $dealerData = Dealer::where('nav_id', $request->nav_id)->first();
        if(!empty($dealerData )){        
        //Start #836
        $params = $request->all();
        if(array_has($params,'gstin'))
        {
            $gstin = $params['gstin'];
                if(!empty($gstin))
                {                    
                    $system_gst_count = Dealer::where('gstin','=',$gstin)->count();

                    if($system_gst_count == 1)
                    {
                        $matching_dealer = Dealer::where('gstin','=',$gstin)->first();
                        if($matching_dealer->id != $dealerData->id)
                        {
                            return response()->json(['success'=>false, "message" => "Duplicate GSTIN"],400);
                        }
                    }
                    elseif($system_gst_count > 1)
                    {
                        return response()->json(['success'=>false, "message" => "Duplicate GSTIN"],400);
                    }
                    if(strlen($gstin) != 15)
                    {
                        return response()->json(['success'=>false, "message" => "Invalid GSTIN"],409);
                    }
                }
        }//End #836
        $company = Company::find($dealerData->company_id);
        $dealer = $this->dealerService->updateViaWhatsApp($company,$dealerData,$request->all());
        return response()->json(['success'=>true, "message" => "Dealer Information has been updated successfully."],200);
        /*return fractal()
            ->item($dealer)
            ->parseIncludes(['distributor'])
            ->transformWith(new DealerTransformer())
            ->toArray();*/
    }else{
        return response()->json(['success'=>false, "message" => "Dealer not found"],404);
    }
    }
    
public function createDealerFromWhatsApp(Request $request, $companyId, $distributorId=null)
	{
        $url = $request->url();
        if(!Gate::allows('createDealers') && !str_contains($url, 'dealers-from-whatsapp'))
        {
            return response()->json("==", 403);
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

        //Start #836
        $params = $request->all();
        if(array_has($params,'tax_information'))
        {
            $taxInformation = $params['tax_information'];
            if(array_has($taxInformation,'gst'))
            {
                $gst = $taxInformation['gst'];
                if(isset($gst['number']))
                {
                    $gstin = $gst['number'];
                    $system_gst_count = Dealer::where('gstin','=',$gstin)->count();
                    if($system_gst_count >= 1)
                    {
                      return response()->json("Duplicate GSTIN",400);
                    }   
                    if(strlen($gstin) != 15)
                    {
                        return response()->json("Invalid GSTIN",409);
                    }
                }
            }
        }//End #836
		if($distributorId != null)
		{
			$user = Auth::user();
            if($user->hasRole('distributorAdmin'))
            {
                return $dealer = $this->dealerService->createDealerUnderDistributor($user, $companyId, $distributorId, $request->all());
            }
            else
            {
                $distributor = $this->distributorService->find($distributorId);
                $company = $distributor->company;   
            }
		}
		else
		{
			$company = $this->companyService->find($companyId);
			$distributor = null;
		}
		$dealer = $this->dealerService->createDealerFromWhatsApp($request->all(), $company, $distributor);
        $user = $this->userService->createUserFromWhatsApp($request['user'], 'dealer', $dealer);
        if($distributor == null)
            $this->activeProductAttributesService->load($company->id, 'dealer', $dealer->id);
        else
            $this->activeProductAttributesService->load($company->id, 'dealer', $dealer->id, $distributorId);

        $notifiable = (object)[
            "customer" => $dealer->name,
            "number" => $user->phone
        ];
        SMSNotification::send($notifiable,new AccountCreatedNotification($company));//#595 Notify Customer

        return fractal()
            ->item($dealer)
            ->parseIncludes(['distributor'])
            ->transformWith(new DealerTransformer())
            ->toArray();

	}    
}