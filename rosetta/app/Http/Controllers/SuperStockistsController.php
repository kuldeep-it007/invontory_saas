<?php

namespace Rosetta\Http\Controllers;

use Carbon\Carbon;
use Rosetta\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Services\Users\UserServiceInterface;
use Rosetta\Transformers\NAV\AddressTransformer;
use Rosetta\Exceptions\Order\ErrorUnblockingStock;
use Rosetta\Exceptions\Order\BlockItemDeniedException;
use Rosetta\Transformers\CartItem\CartItemTransformer;
use Rosetta\Services\Companies\CompanyServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rosetta\Notifications\SMS\AccountCreatedNotification;
use Rosetta\Services\CartItems\CartItemsServiceInterface;
use Rosetta\Transformers\CartItem\BlockedItemTransformer;
use Rosetta\Transformers\SuperStockists\CompactTransformer;
use Rosetta\Transformers\SuperStockistStock\StockTransformer;
use Illuminate\Support\Facades\Notification as SMSNotification;
use Rosetta\Services\SuperStockist\SuperStockistServiceInterface;
use Rosetta\Transformers\SuperStockists\SuperStockistTransformer;
use Rosetta\Exceptions\Order\InsufficientQuantityToBlockException;
use Rosetta\Transformers\SuperStockists\SearchResponseTransformer;
use Rosetta\Transformers\SuperStockists\SuperStockistBlockedStockTransformer;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;


class SuperStockistsController extends Controller
{
	protected $companyService, $superStockistService, $userService, $activeProductAttributesService, $cartItemsService;
	public function __construct(SuperStockistServiceInterface $superStockistService, CompanyServiceInterface $companyService, UserServiceInterface $userService, ActiveProductAttributesServiceInterface $activeProductAttributesService, CartItemsServiceInterface $cartItemsService)
	{
        $this->companyService = $companyService;
		$this->superStockistService = $superStockistService;
        $this->userService = $userService;
        $this->activeProductAttributesService = $activeProductAttributesService;
        $this->cartItemsService = $cartItemsService;
	}
    public function index(Request $request,$companyID)
    {
    	if(!Gate::allows('viewSuperStockists'))
        {
            return response()->json("", 403);
        }
        $company = $this->companyService->find($companyID);
    	$superStockists = $this->superStockistService->getSuperStockists($company,$request->all());
        $superStockistsArray =  fractal()
            ->collection($superStockists)
            ->transformWith(new CompactTransformer())
            ->parseIncludes(['abilities'])
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $superStockists->total(),
                "page"  => $superStockists->currentPage(),
                "super_stockists" => $superStockistsArray,
            ];
        }
        else
            return $superStockistsArray;
    }
    public function sync(Request $request, $companyId)
    {
        if(!Gate::allows('createSuperStockists'))
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
        $superStockist = $company->super_stockists()->where('nav_id', '=', $request->get('nav_id'))->first();
        if($superStockist)
            return response()->json([],412);
        $superStockist = $this->superStockistService->sync(Auth::user(),$company, $request->get('nav_id'));
        $this->userService->create(Auth::user(), $request->all(), 'superStockist', $superStockist);
        $this->activeProductAttributesService->load($companyId, 'superStockist', $superStockist->id,null,null);
        return fractal()
            ->item($superStockist)
            ->transformWith(new SuperStockistTransformer())
            ->toArray();
    }
    public function create(Request $request,$companyID)
    {
        if(!Gate::allows('createSuperStockists'))
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
        $company = $this->companyService->find($companyID);
        $superStockist = $this->superStockistService->create(Auth::user(),$company,$request->all());
        $user = $this->userService->create(Auth::user(), $request['user'], 'superStockist', $superStockist);
        $this->activeProductAttributesService->load($companyID, 'superStockist', $superStockist->id);
        $notifiable = (object)[
            "customer" => $superStockist->name,
            "number" => $user->phone
        ];
        SMSNotification::send($notifiable,new AccountCreatedNotification($company));//#595 Notify Customer

        return fractal()
            ->item($superStockist)
            ->transformWith(new SuperStockistTransformer())
            ->toArray();
    }
    public function search(Request $request, $companyId)//#894
    {
        if(!Gate::allows('viewSuperStockists'))
        {
            return response()->json("", 403);
        }
        $company = $this->companyService->find($companyId);
        $superStockists = $this->superStockistService->getSuperStockists($company, $request->all());
        
        $superStockistsArray =  fractal()
            ->collection($superStockists)
            ->transformWith(new SearchResponseTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $superStockists->total(),
                "page"  => $superStockists->currentPage(),
                "superStockists" => $superStockistsArray,
            ];
        }
        else
            return $superStockistsArray;
    }
    public function getCart($companyId, $superStockistId)
    {
        $superStockist = SuperStockist::find($superStockistId);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->superStockist;

        if($loggedInUsersSS)
        {
            if($superStockist->id != $loggedInUsersSS->id)
            {
                return response()->json(["error" =>"You do not have permission to view Cart of this Super Stockist"],401);
            }
        }
        if($loggedInUsersDist)
        {
            return response()->json(["error" =>"You do not have permission to view Cart of Super Stockist"],401);
        }
        //End of #720  
        $cartItems = $this->cartItemsService->get($superStockist);

        return fractal()
            ->collection($cartItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new CartItemTransformer($superStockist))
            ->toArray();
    }
    public function addToCart(Request $request, $companyId, $superStockistId)
    {
        $superStockist = SuperStockist::find($superStockistId);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->superStockist;

        if($loggedInUsersSS)
        {
            if($superStockist->id != $loggedInUsersSS->id)
            {
                return response()->json(["error" =>"You do not have permission to add items to this Super Stockist's Cart"],401);
            }
        }
        if($loggedInUsersDist)
        {
            return response()->json(["error" =>"You do not have permission to add items to Super Stockist's Cart"],401);
        }
        //End of #720
        $cartItems = $this->cartItemsService->create($request->all(), $superStockist);

        return fractal()
            ->collection($cartItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new CartItemTransformer($superStockist))
            ->toArray();
    }
    public function deleteFromCart(Request $request, $companyId, $superStockistId)
    {
        $superStockist = SuperStockist::find($superStockistId);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->superStockist;

        if($loggedInUsersSS)
        {
            if($superStockist->id != $loggedInUsersSS->id)
            {
                return response()->json(["error" =>"You do not have permission to delete items from this Super Stockist's cart"],401);
            }
        }
        if($loggedInUsersDist)
        {
            return response()->json(["error" =>"You do not have permission to delete items from Super Stockist's cart"],401);
        }
        //End of #720  
        $this->cartItemsService->deleteFromCart($request->all(), $superStockist);
    }
    public function getBlockedItems($companyId, $superStockistId)
    {
        $superStockist = SuperStockist::find($superStockistId);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->superStockist;

        if($loggedInUsersSS)
        {
            if($superStockist->id != $loggedInUsersSS->id)
            {
                return response()->json(["error" =>"You do not have permission to view blocked items of this Super Stockist"],401);
            }
        }
        if($loggedInUsersDist)
        {
            return response()->json(["error" =>"You do not have permission to view blocked items of Super Stockist"],401);
        }
        //End of #720  
        $blockedItems = $this->cartItemsService->getBlockedItems($superStockist);

        return fractal()
            ->collection($blockedItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new BlockedItemTransformer($superStockist))
            ->toArray();
    }
    public function blockItems(Request $request, $companyId, $superStockistId)
    {
        $superStockist = SuperStockist::find($superStockistId);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->superStockist;

        if($loggedInUsersSS)
        {
            if($superStockist->id != $loggedInUsersSS->id)
            {
                return response()->json(["error" =>"You do not have permission to block items for this Super Stockist"],401);
            }
        }
        if($loggedInUsersDist)
        {
            return response()->json(["error" =>"You do not have permission to block items for Super Stockist"],401);
        }
        //End of #720  
        try
        {
            $blockedItems = $this->cartItemsService->block($request->all(), $superStockist);
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
            ->transformWith(new BlockedItemTransformer($superStockist))
            ->toArray();
    }
    public function unBlockItems(Request $request, $companyId, $superStockistId)
    {
        $superStockist = SuperStockist::find($superStockistId);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->superStockist;

        if($loggedInUsersSS)
        {
            if($superStockist->id != $loggedInUsersSS->id)
            {
                return response()->json(["error" =>"You do not have permission to unblock items for this Super Stockist"],401);
            }
        }
        if($loggedInUsersDist)
        {
            return response()->json(["error" =>"You do not have permission to unblock items for Super Stockist"],401);
        }
        //End of #720  
        $ublockedItems = $this->cartItemsService->unBlock($request->all(), $superStockist);
    }
    public function getShippingAddresses(Request $request,$companyID,$superStockistId)
    {
         $addresses = $this->superStockistService->getShippingAddresses($companyID,$superStockistId);
         return fractal()
            ->collection($addresses)
            ->transformWith(new AddressTransformer())
            ->toArray();
    }
    public function createShippingAddresses(Request $request,$companyID,$superStockistId)
    {
        $address = $this->superStockistService->createShippingAddresses($companyID,$superStockistId,$request->all());
        return fractal()
            ->item($address)
            ->transformWith(new AddressTransformer())
            ->toArray();
    }
    public function updateShippingAddress(Request $request, $companyID, $superStockistId, $addressCode)
    {
        if(!Gate::allows('updateSuperStockists'))
        {
            return response()->json("", 403);
        }
        $address = $this->superStockistService->updateShippingAddress($companyID, $superStockistId, $addressCode, $request->all());
        return fractal()
            ->item($address)
            ->transformWith(new AddressTransformer())
            ->toArray();
    }
    public function deleteShippingAddress($companyID, $superStockistId, $addressCode)
    {
        if(!Gate::allows('deleteSuperStockists'))
        {
            return response()->json("", 403);
        }
        $this->superStockistService->deleteAddress($companyID, $superStockistId, $addressCode);
    }
    public function inventory(Request $request, $superStockistId)
    {

        $superStockist = $this->superStockistService->find($superStockistId);
        //gets total stock for a Super Stockist
         //#723
        $loggedInUser = Auth::user();
        $loggedInUsersSS = $loggedInUser->superStockist;
        if($loggedInUsersSS)
        {
            if($superStockist->id != $loggedInUsersSS->id)
            {
                return response()->json(["error" =>"You do not have permission to view this inventory"],401);
            }
        }//End of #723
        $inventories = $this->superStockistService->getInventory($superStockistId, $request->all());
        $inventoriesArray =  fractal()
            ->collection($inventories)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new StockTransformer($superStockist))
            ->toArray();

        return [
            "total" => $inventories->total(),
            "page"  => $inventories->currentPage(),
            "per_page"  => $inventories->PerPage(),
            "inventory" => $inventoriesArray,
        ];
    }
    public function inventoryAdjust(Request $request,$superStockistId,$inventoryID)
    {
        $superStockist = $this->superStockistService->find($superStockistId);
        $inventory = $this->superStockistService->adjustInventory($superStockistId,$inventoryID,$request->get('operation'),$request->get('quantity'));
        return fractal()
            ->item($inventory)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new StockTransformer($superStockist))
            ->toArray();

    }
    public function addStock(Request $request, $companyId, $superStockistId)
    {
        $superStockist = SuperStockist::find($superStockistId);
        $inventory = $this->superStockistService->addStock($companyId, $superStockistId, $request->all());
        if(is_null($inventory))
        {
            return response()->json([
                            "error" => "Product not found. Please add product and try again."
            ])->setStatusCode(409);
        }
        return fractal()
            ->item($inventory)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new StockTransformer($superStockist))
            ->toArray();
    }
    public function deleteStock($companyId, $superStockistId, $stockId)
    {
        $inventory = $this->superStockistService->deleteStock($superStockistId, $stockId);
    }
    public function importStock(Request $request, $superStockistId)
    {
        try
        {
            $superStockist = SuperStockist::find($superStockistId);
            $invalidRows = $this->superStockistService->importStock($superStockist, $request->file('file'));
        }
        catch(FileNotFoundException $e)
        {
            return response()->json(["error" =>"File does not exist."],404);
        }
        return $invalidRows;
    }
    public function exportStock(Request $request, $superStockistId)
    {
        $pathToFile = $this->superStockistService->exportStock($superStockistId, $request->all());
        return response()->download($pathToFile,'StockReport '.Carbon::now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend(true);
    }
    public function getMyBlockedStock(Request $request, $companyID, $superStockistId)
    {
        $superStockist = SuperStockist::find($superStockistId);
        $loggedInUser = Auth::user();
        $loggedInUsersSuperStockist = $loggedInUser->superStockist;
       
        if($loggedInUsersSuperStockist)
        {
            if($superStockist->id != $loggedInUsersSuperStockist->id)
            {
                return response()->json(["error" =>"You do not have permission to view blocked items of this Distributor"],401);
            }
        }
        $blockedStock = $this->superStockistService->getMyBlockedStock($superStockist, $request->all());
        //End of #720  
        $blockedStockArray =  fractal()
            ->collection($blockedStock)
            ->transformWith(new SuperStockistBlockedStockTransformer())
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->toArray();

        return [
            "total" => $blockedStock->total(),
            "page"  => $blockedStock->currentPage(),
            "per_page"  => $blockedStock->PerPage(),
            "items" => $blockedStockArray,
        ];
    }

    public function unBlockMyStock($companyId, $superStockistId, $itemId)
    {
        $superStockist = SuperStockist::find($superStockistId);
        $loggedInUser = Auth::user();
        $loggedInUsersSuperStockist = $loggedInUser->superStockist;

        if($loggedInUsersSuperStockist)
        {
            if($superStockist->id != $loggedInUsersSuperStockist->id)
            {
                return response()->json(["error" =>"You do not have permission to unblock items for this Distributor"],401);
            }
        }
         
        try
        {
            $this->superStockistService->unBlockMyStock($itemId, $superStockistId);
        }
        catch(ErrorUnblockingStock $e)
        {
            return response()->json(["error" =>"Sorry! Product coud not be Unblocked."],412);
        }
    }

    public function getForCompany(Request $request, $compnayID, $distributorID)
    {
        $superStockist = $this->superStockistService->findForCompany($compnayID,$distributorID);
        return fractal()
            ->item($superStockist)
            ->transformWith(new SuperStockistTransformer())
            ->parseIncludes(['tax_information','state','superStockist'])
            ->toArray();
    }

    public function updateCompanySuperStockist(Request $request, $companyId, $superStockistId)
    {
        if(!Gate::allows('updateSuperStockists'))
        {
            return response()->json("", 403);
        }

        $company = $this->companyService->find($companyId);
        $superStockistData = $company->super_stockists()->find($superStockistId);

        $superStockist = $this->superStockistService->update(Auth::user(),$company,$superStockistData,$request->all());
        return fractal()
            ->item($superStockist)
            //->parseIncludes(['superStockist'])
            ->transformWith(new SuperStockistTransformer())
            ->toArray();
    }    
}
