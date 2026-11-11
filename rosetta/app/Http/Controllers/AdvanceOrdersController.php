<?php 
namespace Rosetta\Http\Controllers;

use Auth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Rosetta\Exceptions\Order\BlockItemDeniedException;
use Rosetta\Exceptions\Order\InsufficientQuantityToBlockException;
use Rosetta\Exceptions\Order\ViewOrderPermissionDeniedException;
use Rosetta\Exceptions\RedisException;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Notifications\SMS\AdvanceOrderCancelledNotification;
use Rosetta\Services\AdvanceBlanketOrders\AdvanceBlanketOrdersServiceInterface;
use Rosetta\Services\AdvanceOrders\AdvanceOrdersServiceInterface;
use Rosetta\Transformers\AdvanceOrder\AdvanceItemMessagesTransformer;
use Rosetta\Transformers\AdvanceOrder\AdvanceLineMessagesTransformer;
use Rosetta\Transformers\AdvanceOrder\AdvanceLineTransformer;
use Rosetta\Transformers\AdvanceOrder\AdvanceOrderListTransformer;
use Rosetta\Transformers\AdvanceOrder\AdvanceOrderTransformer;
use Rosetta\Transformers\AdvanceOrder\BlanketOrderListTransformer;
use Rosetta\Transformers\AdvanceOrder\BlanketOrderTransformer;
use Rosetta\Transformers\AdvanceOrder\BlanketOrdersIndexTransformer;
use Rosetta\Transformers\AdvanceOrder\BlockedAdvanceItemTransformer;
use Rosetta\Transformers\AdvanceOrder\IndexTransformer;
use Rosetta\Transformers\Company\AdvanceItemTransformer;
use Rosetta\Transformers\Message\MessagesTransformer;
class AdvanceOrdersController extends Controller
{
	protected $advanceOrdersService, $advanceBlanketOrdersService;
	public function __construct(AdvanceOrdersServiceInterface $advanceOrdersService, AdvanceBlanketOrdersServiceInterface $advanceBlanketOrdersService)
	{
		$this->advanceOrdersService = $advanceOrdersService;
        $this->advanceBlanketOrdersService = $advanceBlanketOrdersService;
	}
	public function create(Request $request,$companyID, $customerType,$customerID)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
		$validator = Validator::make($request->all(), [
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $advanceOrder = $this->advanceOrdersService->create($company, $customer, $request->all(), Auth::user());
        return fractal()
            ->item($advanceOrder)
            ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
            ->transformWith(new AdvanceOrderTransformer())
            ->toArray(); 
    }
    public function createIntransitOrder(Request $request,$companyID, $customerType,$customerID)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        elseif($customerType == "super_stockists")
            $customer = SuperStockist::find($customerID);
        $company = Company::find($companyID);
        $validator = Validator::make($request->all(), [
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        //#722
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->superStockist;
        //$customer is the Dealer or Distributor for whom order is placed
        if($customerType == "distributors")//order being placed for Distributor
        {
            $customer = Distributor::find($customerID);
            if($loggedInUsersDist)//Distributor logged in and placing order for himself
            {
                if($customer->id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permission to place order for another Distributor"],401);
                }
            }
            if($loggedInUsersDlr)//Dealer is not allowed to place order for Distributor
            {
                return response()->json(["error" =>"You do not have permission to place order for Distributor"],401);
            }
        }
        elseif($customerType == "super_stockists")//order being placed for Distributor
        {
            $customer = SuperStockist::find($customerID);
            if($loggedInUsersSS)//Super Stockist logged in and placing order for himself
            {
                if($customer->id != $loggedInUsersSS->id)
                {
                    return response()->json(["error" =>"You do not have permission to place order for another Distributor"],401);
                }
            }
            if($loggedInUsersDist)//Distributor is not allowed to place order for Super Stockist
            {
                return response()->json(["error" =>"You do not have permission to place order for Distributor"],401);
            }
        }
        elseif($customerType == "dealers")//order being placed for either Dealer or DUD
        {
            $customer = Dealer::find($customerID);
            if($loggedInUsersDlr)//DUC or DUD logged in and placing order for himself
            {
                if($loggedInUsersDlr->id != $customer->id)
                {
                    return response()->json(["error" =>"You do not have permission to place order for Dealer"],412);
                }
            }
            if($loggedInUsersDist)//Distributor logged in and placing order for DUD(app)
            {
                if($customer->distributor_id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permission to place order for this Dealer"],412);
                }
            }
        }
        //End of #722
        try{
            list($advanceBlanketOrder,$invalidLines, $message) = $this->advanceOrdersService->createAdvanceBlanketOrder($company, $customer, $request->all(), $loggedInUser, $customerType);
        }
        catch (Exception $ex) { 
            return response()->json([ 'ao' => null, 'invalid' => null, 'error_text' => $ex->getMessage()], 500);
        }
        $advanceBlanketOrderData = null;
        if($advanceBlanketOrder)
        {
            $advanceBlanketOrderData = fractal()
            ->item($advanceBlanketOrder)
            ->parseIncludes(['customer','advance_orders','advance_lines', 'customer','address'])
            ->transformWith(new BlanketOrderTransformer())
            ->toArray();

            $notifiable = (object)[
                "order_number" => $advanceBlanketOrder->order_number,
                "number" => $loggedInUser->phone,
                "customer" => $customer->name
            ];
     //       Notification::send($notifiable,new AdvanceOrderCreatedNotification($company));//#595 Notify Customer
        }
        if($message != null)
        {
             return response()->json([ 'ao' => $advanceBlanketOrderData, 'invalid' => $invalidLines, 'error_text' => $message], 412);
        }
        return ['ao' => $advanceBlanketOrderData,'invalid' => $invalidLines];
    }
    public function updateInTransitOrder(Request $request,$companyID, $customerType,$customerID, $orderId)
    {
        $loggedInUser = Auth::user();
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);

        list($blanketOrder,$alert) = $this->advanceOrdersService->findInTransitOrder(Auth::user(),$orderId);
        $blanketOrderData = null;
        if($blanketOrder)
        {
            $blanketOrderData = fractal()
            ->item($blanketOrder)
            ->parseIncludes(['customer','advance_lines', 'advance_lines.product','advance_lines.product.category','advance_lines.product.collection', 'advance_lines.product.colour','advance_lines.stock_point'])
            ->transformWith(new BlanketOrderTransformer($loggedInUser))
            ->toArray();
        }
        return ['ao' => $blanketOrderData,'alert' => $alert];
    }
    public function update(Request $request,$companyID, $customerType,$customerID, $orderId)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
/*        list($advanceOrder,$alert) = $this->advanceOrdersService->update($company, $customer, $orderId);
        return fractal()
            ->item($advanceOrder)
            ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
            ->transformWith(new AdvanceOrderTransformer())
            ->toArray();
*/
        list($advanceOrder,$alert) = $this->advanceOrdersService->update($company, $customer, $orderId);
        $advanceOrderData = null;
        if($advanceOrder)
        {
            $advanceOrderData = fractal()
            ->item($advanceOrder)
            ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
            ->transformWith(new AdvanceOrderTransformer())
            ->toArray();
        }
        return ['co' => $advanceOrderData,'alert' => $alert];
    }
    public function updateLine(Request $request,$companyID, $customerType,$customerID, $orderId, $lineId)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
        $advanceLine = $this->advanceOrdersService->updateLine($company, $customer, $customerType, $orderId, $lineId, Auth::user(), $request->all());
        return fractal()
            ->item($advanceLine)
            ->transformWith(new AdvanceLineTransformer())
            ->toArray();
    }
    public function updateLineQtyDisc(Request $request,$companyID, $customerType,$customerID, $orderId, $lineId)
    {
        if(!Gate::allows('updateAdvanceLines'))
        {
            return response()->json("", 403);
        }
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        elseif($customerType == "super_stockists")
            $customer = SuperStockist::find($customerID);
        $company = Company::find($companyID);
        $advanceOrder = $customer->advanceOrders()->find($orderId);
        $advanceLine = $advanceOrder->advance_lines()->find($lineId);
        list($updatedAdvanceOrder,$alert) = $this->advanceOrdersService->update($company, $customer, $orderId);
        $updatedAdvanceLine = $updatedAdvanceOrder->advance_lines()->find($lineId);
        if($updatedAdvanceLine->nav_po_no != null)
        {
            $blanketOrder = $updatedAdvanceOrder->advance_blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','advance_lines', 'advance_lines.product','advance_lines.product.category','advance_lines.product.collection', 'advance_lines.product.colour','advance_lines.stock_point','advance_lines.returnItems'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Line could not be updated as order is already in progress for this Line."], 412);
        }
        try
        {
            list($advanceOrder,$alert) = $this->advanceOrdersService->updateLineQtyDisc($company, $customerType, $customer, $updatedAdvanceOrder, $updatedAdvanceLine, $request->all(), Auth::user());
                $blanketOrderData = null;
                if($advanceOrder)
                {
                    $blanketOrder = $advanceOrder->advance_blanket_order;
                    $advanceOrderData = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer','advance_lines', 'advance_lines.product','advance_lines.product.category','advance_lines.product.collection', 'advance_lines.product.colour','advance_lines.stock_point','advance_lines.returnItems'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray();
                }
                return ['ao' => $advanceOrderData,'alert' => $alert];
        }
        catch(RedisException $e)
        {
            $blanketOrder = $advanceOrder->advance_blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','advance_lines', 'advance_lines.product','advance_lines.product.category','advance_lines.product.collection', 'advance_lines.product.colour','advance_lines.stock_point','advance_lines.returnItems'])
                    ->transformWith(new BlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Line updated, however Email was not sent to Customer as Email Queue Server is down."], 503);
        }
        catch(\Exception $e)
        {
            $blanketOrder = $advanceOrder->advance_blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','advance_lines', 'advance_lines.product','advance_lines.product.category','advance_lines.product.collection', 'advance_lines.product.colour','advance_lines.stock_point','advance_lines.returnItems'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Internal server error."], 412);
        }
    }
    public function refresh($UUId)
    {
        $advanceOrder = $this->advanceOrdersService->refresh($UUId);
    }
    public function listCompanyAOs(Request $request, $companyId)
    {    
        if(!Gate::allows('viewAdvanceOrders'))
        {
            return response()->json("", 403);
        }
        $advanceOrders = $this->advanceOrdersService->listCompanyAOs($companyId,$request->all());
        $advanceOrdersArray = fractal()
            ->collection($advanceOrders)
            ->parseIncludes(['customer'])
            ->transformWith(new AdvanceOrderListTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $advanceOrders->total(),
                "page"  => $advanceOrders->currentPage(),
                "advanceOrders" => $advanceOrdersArray
            ];
        }
        else
            return $advanceOrdersArray;
    }
    public function index(Request $request, $companyID, $customerType,$customerID)
    {
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        if($customerType == "distributors")
        {
            $customer = Distributor::find($customerID);
            if($loggedInUsersDist)//Distributor logged in and placing order for himself
            {
                if($customer->id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this data"],412);
                }
            }
            if($loggedInUsersDlr)//Dealer is not allowed to place order for Distributor
            {
                return response()->json(["error" =>"You do not have permission to view this data"],412);
            }
        }
        elseif($customerType == "dealers")
        {
            $customer = Dealer::find($customerID);
            if($loggedInUsersDlr)//DUC or DUD logged in and placing order for himself
            {
                if($loggedInUsersDlr->id != $customer->id)
                {
                    return response()->json(["error" =>"You do not have permission to place order for Dealer"],412);
                }
            }
            if($loggedInUsersDist)//Distributor logged in and placing order for DUD(app)
            {
                if($customer->distributor_id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permission to place order for this Dealer"],412);
                }
            }
        }
        $company = Company::find($companyID);

        $advanceOrders = $this->advanceOrdersService->all($company, $customer, $request->all());

        $advanceOrdersArray = fractal()
            ->collection($advanceOrders)
            ->transformWith(new IndexTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $advanceOrders->total(),
                "page"  => $advanceOrders->currentPage(),
                "advanceOrders" => $advanceOrdersArray,
            ];
        }
        else
            return $advanceOrdersArray;
    }
    public function getAdvanceOrder(Request $request,$orderId)
    {
        try
        {
            list($advanceOrder,$alert) = $this->advanceOrdersService->findOrder(Auth::user(),$orderId);
            $advanceOrderData = null;
            $customer = $advanceOrder->orderable;
        }
        catch(ViewOrderPermissionDeniedException $e)
        {
            return response()->json(["error" =>"You do not have permission to view this order"],412);
        }
      /*  return fractal()
            ->item($advanceOrder)
            ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
            ->transformWith(new AdvanceOrderTransformer())
            ->toArray(); */  
        if($advanceOrder)
        {
            $advanceOrderData = fractal()
            ->item($advanceOrder)
            ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
            ->transformWith(new AdvanceOrderTransformer())
            ->toArray();
        }
        return ['co' => $advanceOrderData,'alert' => $alert];  
    }
    public function listCompanyBlanketOrders(Request $request, $companyId)
    {    
        if(!Gate::allows('viewAdvanceOrders'))
        {
            return response()->json("", 403);
        }
        $advanceOrders = $this->advanceBlanketOrdersService->listCompanyBlanketOrders($companyId,$request->all());
        $advanceOrdersArray = fractal()
            ->collection($advanceOrders)
            ->parseIncludes(['customer','so_numbers','si_numbers','stock_points'])
            ->transformWith(new BlanketOrderListTransformer())
            ->toArray();
        if($request->get('page'))
        {
            return [
                "total" => $advanceOrders->total(),
                "page"  => $advanceOrders->currentPage(),
                "advanceOrders" => $advanceOrdersArray
            ];
        }
        else
            return $advanceOrdersArray;
    }
    public function blanketOrders(Request $request, $companyID, $customerType,$customerID)
    {
        //#724
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->superStockist;
        //$customer is the Dealer or Distributor for whom order is placed
        if($customerType == "distributors")//order being placed for Distributor
        {
            $customer = Distributor::find($customerID);
            if($loggedInUsersDist)//Distributor logged in and placing order for himself
            {
                if($customer->id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this data"],401);
                }
            }
            if($loggedInUsersDlr)//Dealer is not allowed to place order for Distributor
            {
                return response()->json(["error" =>"You do not have permission to view this data"],401);
            }
        }
        elseif($customerType == "super_stockists")//order being placed for Distributor
        {
            $customer = SuperStockist::find($customerID);
            if($loggedInUsersSS)//Super Stockist logged in and placing order for himself
            {
                if($customer->id != $loggedInUsersSS->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this data"],401);
                }
            }
            if($loggedInUsersDist)//Distributor is not allowed to place order for Super Stockist
            {
                return response()->json(["error" =>"You do not have permission to view this data"],401);
            }
        }
        elseif($customerType == "dealers")//order being placed for either Dealer or DUD
        {
            $customer = Dealer::find($customerID);
            if($loggedInUsersDlr)//DUC or DUD logged in and placing order for himself
            {
                if($loggedInUsersDlr->id != $customer->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this data"],401);
                }
            }
            if($loggedInUsersDist)//Distributor logged in and placing order for DUD(app)
            {
                if($customer->distributor_id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this data"],401);
                }
            }
        }
        //End of #724
        $company = Company::find($companyID);
        $advanceOrders = $this->advanceBlanketOrdersService->all($company, $customer, $request->all());


        $advanceOrdersArray = fractal()
            ->collection($advanceOrders)
            ->parseIncludes(['so_numbers','stock_points','si_numbers'])//#765
            ->transformWith(new BlanketOrdersIndexTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $advanceOrders->total(),
                "page"  => $advanceOrders->currentPage(),
                "advanceOrders" => $advanceOrdersArray,
            ];
        }
        else
            return $advanceOrdersArray;
    }
    public function getInTransitOrder(Request $request,$orderId)
    {
        try
        {
            list($blanketOrder,$alert) = $this->advanceOrdersService->findInTransitOrder(Auth::user(),$orderId);
            $blanketOrderData = null;
            $customer = $blanketOrder->orderable;
        }
        catch(ViewOrderPermissionDeniedException $e)
        {
            return response()->json(["error" =>"You do not have permission to view this order"],412);
        }
        $loggedInUser = Auth::user();
        if($blanketOrder)
        {
            $blanketOrderData = fractal()
            ->item($blanketOrder)
            ->parseIncludes(['customer','advance_lines', 'advance_lines.product','advance_lines.product.category','advance_lines.product.collection', 'advance_lines.product.colour','advance_lines.stock_point','advance_lines.returnItems'])
            ->transformWith(new BlanketOrderTransformer($loggedInUser))
            ->toArray();
        }
        return ['ao' => $blanketOrderData,'alert' => $alert];  
    }
    public function getAdvanceItems($companyID, $customerType, $customerID)
    {
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->SuperStockist;
        if($customerType == "distributors")
        {
            $customer = Distributor::find($customerID);
            if($loggedInUsersDist)//Distributor logged in and placing order for himself
            {
                if($customer->id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this data"],412);
                }
            }
            if($loggedInUsersDlr)//Dealer is not allowed to place order for Distributor
            {
                return response()->json(["error" =>"You do not have permission to view this data"],412);
            }
        }
        elseif($customerType == "dealers")
        {
            $customer = Dealer::find($customerID);
            if($loggedInUsersDlr)//DUC or DUD logged in and placing order for himself
            {
                if($loggedInUsersDlr->id != $customer->id)
                {
                    return response()->json(["error" =>"You do not have permission to place order for Dealer"],412);
                }
            }
            if($loggedInUsersDist)//Distributor logged in and placing order for DUD(app)
            {
                if($customer->distributor_id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permission to place order for this Dealer"],412);
                }
            }
        }
        else if($customerType == "super_stockists")
        {
            $customer = SuperStockist::find($customerID);
            if($loggedInUsersSS)//SS logged in and placing order for himself
            {
                if($customer->id != $loggedInUsersSS->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this data"],412);
                }
            }
            if($loggedInUsersDist)//Distributor is not allowed to place order for Dealer
            {
                return response()->json(["error" =>"You do not have permission to view this data"],412);
            }
        }
        $company = Company::find($companyID);
        $advanceItems = $this->advanceOrdersService->getAdvanceItems($customer);

       return fractal()
            ->collection($advanceItems)
            ->parseIncludes(['product','product.category','product.collection','product.colour'])
            ->transformWith(new AdvanceItemTransformer())
            ->toArray();
    }
  /*  public function getAdvanceItems($companyID, $customerType, $customerID, Request $request)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
        $advanceItems = $this->advanceOrdersService->getAdvanceItems($customer, $request->all());

        $advanceItemsArray =  fractal()
            ->collection($advanceItems)
            ->transformWith(new AdvanceItemTransformer())
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->toArray();

            return [
                "total" => $advanceItems->total(),
                "page"  => $advanceItems->currentPage(),
                "per_page"  => $advanceItems->PerPage(),
                "items" => $advanceItemsArray,
            ];
    }*/
    public function deleteAdvanceItems(Request $request, $companyId, $customerType, $customerID)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "super_stockists")
            $customer = SuperStockist::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyId);
        $deletedAdvanceItem = $this->advanceOrdersService->deleteAdvanceItems($request->all(), $customer, Auth::user(), $customerType);
    }
    public function deleteCustomerAdvanceItems(Request $request, $companyId, $customerType)
    {
        $company = Company::find($companyId);
        $deletedAdvanceItem = $this->advanceOrdersService->deleteCustomerAdvanceItems($company, $customerType, $request->all(), Auth::user());
    }
    public function addIntransitToAdvanceCart(Request $request, $companyId, $customerType, $customerID)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "super_stockists")
            $customer = SuperStockist::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyId);
        $advanceCartItems = $this->advanceOrdersService->addIntransitToAdvanceCart($customer, $company, $request->all());

        return fractal()
            ->collection($advanceCartItems)
            ->parseIncludes(['product','product.category','product.collection','product.colour'])
            ->transformWith(new AdvanceItemTransformer())
            ->toArray();
    }
    public function addToAdvanceCart(Request $request, $companyId, $customerType, $customerID)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyId);
        $advanceCartItems = $this->advanceOrdersService->addToAdvanceCart($customer, $company, $request->all());

        return fractal()
            ->collection($advanceCartItems)
            ->parseIncludes(['product','product.category','product.collection','product.colour'])
            ->transformWith(new AdvanceItemTransformer())
            ->toArray();
    }
    public function blockAdvanceItems(Request $request, $companyId, $customerType, $customerID)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "super_stockists")
            $customer = SuperStockist::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyId);
        try
        {
            $blockedAdvanceItems = $this->advanceOrdersService->blockAdvanceItems($customer, $company, $request->all());
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
            ->collection($blockedAdvanceItems)
            ->parseIncludes(['product','product.category','product.collection','product.colour'])
            ->transformWith(new BlockedAdvanceItemTransformer())
            ->toArray();
    }
    public function getBlockedAdvanceItems($companyId, $customerType, $customerID)
    {
    /*    if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);*/
        $company = Company::find($companyId);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->superStockist;

     /*   if($loggedInUsersDist)
        {
            if($customer->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to view blocked items of this Distributor"],401);
            }
        }
        if($loggedInUsersDlr)
        {
            return response()->json(["error" =>"You do not have permission to view blocked items of Distributor"],401);
        }
        //End of #720  */
        
        if($customerType == "distributors")
        {
            $customer = Distributor::find($customerID);
            if($loggedInUsersDist)//Distributor logged in and placing order for himself
            {
                if($customer->id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this data"],412);
                }
            }
            if($loggedInUsersDlr)//Dealer is not allowed to place order for Distributor
            {
                return response()->json(["error" =>"You do not have permission to view this data"],412);
            }
        }
        elseif($customerType == "dealers")
        {
            $customer = Dealer::find($customerID);
            if($loggedInUsersDlr)//DUC or DUD logged in and placing order for himself
            {
                if($loggedInUsersDlr->id != $customer->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this data"],412);
                }
            }
            if($loggedInUsersDist)//Distributor logged in and placing order for DUD(app)
            {
                if($customer->distributor_id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this data"],412);
                }
            }
        }
        else if($customerType == "super_stockists")
        {
            $customer = SuperStockist::find($customerID);
            if($loggedInUsersSS)//SS logged in and placing order for himself
            {
                if($customer->id != $loggedInUsersSS->id)
                {
                    return response()->json(["error" =>"You do not have permission to view this data"],412);
                }
            }
            if($loggedInUsersDist)//Distributor is not allowed to place order for Dealer
            {
                return response()->json(["error" =>"You do not have permission to view this data"],412);
            }
        }

        $blockedItems = $this->advanceOrdersService->getBlockedAdvanceItems($customer);

        return fractal()
            ->collection($blockedItems)
            ->parseIncludes(['product.category','product.collection','product.colour'])
            ->transformWith(new BlockedAdvanceItemTransformer($customer))
            ->toArray();
    }
    public function unBlockAdvanceItems(Request $request, $companyId, $customerType, $customerId)
    {
     /*   if($customerType == "distributors")
            $customer = Distributor::find($customerId);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerId);*/
        $company = Company::find($companyId);
        //#720
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->superStockist;

     /*   if($loggedInUsersDist)
        {
            if($customer->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permission to unblock items for this Distributor"],401);
            }
        }
        if($loggedInUsersDlr)
        {
            return response()->json(["error" =>"You do not have permission to unblock items for Distributor"],401);
        }
        //End of #720  */
        
        if($customerType == "distributors")
        {
            $customer = Distributor::find($customerId);
            if($loggedInUsersDist)//Distributor logged in and placing order for himself
            {
                if($customer->id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permission to unblock items for this Distributor"],412);
                }
            }
            if($loggedInUsersDlr)//Dealer is not allowed to place order for Distributor
            {
                return response()->json(["error" =>"You do not have permission to unblock items"],412);
            }
        }
        elseif($customerType == "dealers")
        {
            $customer = Dealer::find($customerId);
            if($loggedInUsersDlr)//DUC or DUD logged in and placing order for himself
            {
                if($loggedInUsersDlr->id != $customer->id)
                {
                    return response()->json(["error" =>"You do not have permission to unblock items for Distributor"],412);
                }
            }
            if($loggedInUsersDist)//Distributor logged in and placing order for DUD(app)
            {
                if($customer->distributor_id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permission to vunblock items"],412);
                }
            }
        }
        elseif($customerType == "super_stockists")
        {
            $customer = SuperStockist::find($customerId);
            if($loggedInUsersSS)//Super Stockist logged in and placing order for himself
            {
                if($customer->id != $loggedInUsersSS->id)
                {
                    return response()->json(["error" =>"You do not have permission to unblock items for this Super Stockist"],412);
                }
            }
            if($loggedInUsersDist)//Dealer is not allowed to place order for Super Stockist
            {
                return response()->json(["error" =>"You do not have permission to unblock items"],412);
            }
        }
        $ublockedItems = $this->advanceOrdersService->unBlockAdvanceItems($request->all(), $customer);
    }
    public function addMessage(Request $request, $companyID, $customerType, $customerID, $itemId)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        elseif($customerType == "super_stockists")
            $customer = SuperStockist::find($customerID);
        $company = Company::find($companyID);
        $message = $this->advanceOrdersService->addMessage($company, $customer, $customerType, $itemId, $request->all(), Auth::user());
        return fractal()
            ->item($message)
         //   ->parseIncludes(['backOrderLine'])
            ->transformWith(new MessagesTransformer())
            ->toArray();
    }
    public function getMessages(Request $request, $companyID, $customerType, $customerID, $itemId)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        elseif($customerType == "super_stockists")
            $customer = SuperStockist::find($customerID);
        $company = Company::find($companyID);
        $item = $this->advanceOrdersService->getMessages($company, $customer, $itemId, Auth::user());

        return fractal()
            ->item($item)
            ->parseIncludes(['messages'])
            ->transformWith(new AdvanceItemMessagesTransformer())
            ->toArray();
    }
    public function addLineMessage(Request $request, $companyID, $customerType, $customerID, $orderId, $lineId)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        elseif($customerType == "super_stockists")
            $customer = SuperStockist::find($customerID);
        $company = Company::find($companyID);
        $message = $this->advanceOrdersService->addLineMessage($company, $customer, $customerType, $orderId, $lineId, $request->all(), Auth::user());
        return fractal()
            ->item($message)
            ->transformWith(new MessagesTransformer())
            ->toArray();
    }
    public function getLineMessages(Request $request, $companyID, $customerType, $customerID, $orderId, $lineId)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
        $line = $this->advanceOrdersService->getLineMessages($orderId, $lineId);

        return fractal()
            ->item($line)
            ->parseIncludes(['messages'])
            ->transformWith(new AdvanceLineMessagesTransformer())
            ->toArray();
    }
    public function updateAdvanceCartItem(Request $request,$companyID, $customerType,$customerID, $itemId)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
        $item = $this->advanceOrdersService->updateAdvanceCartItem($company, $customer, $customerType, $itemId, $request->all(), Auth::user());
        return fractal()
            ->item($item)
            ->parseIncludes(['product','product.category','product.collection','product.colour'])
            ->transformWith(new AdvanceItemTransformer())
            ->toArray();
    }
    public function updateShipment(Request $request,$companyID,$customerType,$customerID,$orderId)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
        $advanceOrder = $this->advanceOrdersService->updateShipment($company,$customer,$orderId,$request->all());
        return fractal()
            ->item($advanceOrder)
            ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
            ->transformWith(new AdvanceOrderTransformer())
            ->toArray();
    }
     public function deleteInTransitOrder(Request $request,$companyID, $customerType,$customerID, $orderId)
    {
        if($customerType == "distributors")
        {
            $customer = Distributor::find($customerID);
            $customer_user = User::where('distributor_id','=',$customerID)->whereHas('roles',function($q) {
                    $q->where('name','=','distributorAdmin');
                })->first();
        }
        elseif($customerType == "dealers")
        {
            $customer = Dealer::find($customerID);
            $customer_user = User::where('dealer_id','=',$customerID)->whereHas('roles',function($q) {
                    $q->where('name','=','dealerAdmin');
                })->first();
        }
        elseif($customerType == "super_stockists")
        {
            $customer = SuperStockist::find($customerID);
            $customer_user = User::where('super_stockist_id','=',$customerID)->whereHas('roles',function($q) {
                    $q->where('name','=','superStockistAdmin');
                })->first();
        }
        $company = Company::find($companyID);
        $blanketOrder = AdvanceBlanketOrder::find($orderId);
        $advanceOrders = $blanketOrder->advance_orders;
        foreach ($advanceOrders as $advanceOrder)
        {
            list($updatedAdvanceOrder,$alert) = $this->advanceOrdersService->update($company, $customer, $advanceOrder->id);
        }
        $blanketOrder->fresh();
        if($blanketOrder->status == 'Partially Shipped' || $blanketOrder->status == 'Shipped' || $blanketOrder->status == 'Complete')
        {
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Order could not be deleted as Order Status has changed."], 412);
        }
        try 
        {
            $loggedInUser = Auth::user();
            list($blanketOrder, $message) = $this->advanceOrdersService->deleteInTransitOrder($company, $orderId, $request['cancelation_reason'], $loggedInUser, $customerID, $customerType);


            if($message != null)
            {
                $tempOrder = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray(); 
                return response()->json([ 'order' => $tempOrder,'error_text' => "Order could not be deleted as Order Status has changed."], 412);
            }
            else
            {
                $notifiable = (object)[
                    "order_number" => $blanketOrder->order_number,
                    "number" => $customer_user->phone,
                    "customer" => $customer->name
                ];
                Notification::send($notifiable,new AdvanceOrderCancelledNotification($company));//#595
                return fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray();      
            }               
        }
        catch(RedisException $e)
        {
            $advanceOrder = $customer->orders()->find($orderId);
            $blanketOrder = $advanceOrder->advance_blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Order deleted, however Email was not sent to Customer."], 503);
        }
    }
    public function delete(Request $request,$companyID, $customerType,$customerID, $orderId)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
        list($advanceOrder,$alert) = $this->advanceOrdersService->update($company, $customer, $orderId);
        if($advanceOrder->status == 'Open' || $advanceOrder->status == 'Approved' || $advanceOrder->status == 'Pending_Approval')
        {
            try 
            {
                list($advanceOrder, $message) = $this->advanceOrdersService->delete($company, $orderId, $request['cancelation_reason'], Auth::user(), $customerID, $customerType);
                if($message != null)
                {
                    $tempOrder = fractal()
                        ->item($advanceOrder)
                        ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
                        ->transformWith(new AdvanceOrderTransformer())
                        ->toArray(); 
                    return response()->json([ 'order' => $tempOrder,'error_text' => "Order could not be deleted as it is already in progress."], 412);
                }
                else
                {
                    return fractal()
                        ->item($advanceOrder)
                        ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
                        ->transformWith(new AdvanceOrderTransformer())
                        ->toArray();   
                }    
            }
            catch(RedisException $e)
            {
                $advanceOrder = $customer->advanceOrders()->find($orderId);
                $tempOrder = fractal()
                    ->item($advanceOrder)
                    ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
                    ->transformWith(new AdvanceOrderTransformer())
                    ->toArray(); 
                return response()->json([ 'order' => $tempOrder,'error_text' => "Order deleted, however Email was not sent to Customer as Email Queue Server is down."], 503);
            }
            catch(\Exception $e)
            {
                $advanceOrder = $customer->advanceOrders()->find($orderId);
                $tempOrder = fractal()
                    ->item($advanceOrder)
                    ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
                    ->transformWith(new AdvanceOrderTransformer())
                    ->toArray(); 
                return response()->json([ 'order' => $tempOrder,'error_text' => "Internal server error."], 412);
            }
        }
        else
        {
            $tempOrder = fractal()
                ->item($advanceOrder)
                ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
                ->transformWith(new AdvanceOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Order could not be deleted as Order Status has changed."], 412);
        }
    }
    public function deleteInTransitLine(Request $request,$companyID, $customerType,$customerID, $orderId, $lineId)
    {
        if(!Gate::allows('deleteAdvanceLines'))
        {
            return response()->json("", 403);
        }
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        elseif($customerType == "super_stockists")
            $customer = SuperStockist::find($customerID);
        $company = Company::find($companyID);

     /*   $blanketOrder = AdvanceBlanketOrder::find($orderId);
        $advanceOrders = $blanketOrder->advance_orders;
        foreach ($advanceOrders as $advanceOrder)
        {
            list($updatedAdvanceOrder,$alert) = $this->advanceOrdersService->update($company, $customer, $advanceOrder->id);
        }
        $blanketOrder->fresh();*/


        list($advanceOrder,$alert) = $this->advanceOrdersService->update($company, $customer, $orderId);
        $advanceLine = $advanceOrder->advance_lines()->find($lineId);

        try
        {
            if($advanceLine->shipped_quantity > 0)
            {
                $blanketOrder = $advanceOrder->advance_blanket_order;
                $tempOrder = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray(); 
                return response()->json([ 'order' => $tempOrder,'error_text' => "Line could not be deleted as Shipped Quantity has changed."], 412);
            }
            elseif($advanceLine->nav_po_no != null)
            {
                $blanketOrder = $advanceOrder->advance_blanket_order;
                $tempOrder = fractal()
                    ->item($blanketOrder)
                     ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray(); 
                return response()->json([ 'order' => $tempOrder,'error_text' => "Line could not be deleted as order is already in progress."], 412);
            }
            else
            {
                $blanketOrder = $this->advanceOrdersService->deleteInTransitLine($company, $customerType, $customer, $advanceOrder, $advanceLine, Auth::user());
                $blanketOrderData = null;
                if($blanketOrder)
                {
                 //   $blanketOrder = $purchaseOrder->blanket_order;
                    $blanketOrderData = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray();
                }
                return ['ao' => $blanketOrderData];
            }
        }
        catch(RedisException $e)
        {
            $blanketOrder = $advanceOrder->advance_blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Line deleted, however Email was not sent to Customer."], 503);
        }
        catch(\Exception $e)
        {
            throw $e;
            $advanceOrder = $customer->advanceOrders()->find($orderId);
            $blanketOrder = $advanceOrder->advance_blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Internal server error."], 412);
        }
    }
    public function deleteLine(Request $request,$companyID, $customerType,$customerID, $orderId, $lineId)
    {
        if(!Gate::allows('deleteAdvanceLines'))
        {
            return response()->json("", 403);
        }
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
        list($advanceOrder,$alert) = $this->advanceOrdersService->update($company, $customer, $orderId);
        $advanceLine = $advanceOrder->advance_lines()->find($lineId);
        try
        {
            if($advanceLine->shipped_quantity > 0)
            {
                $tempOrder = fractal()
                    ->item($advanceOrder)
                    ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
                    ->transformWith(new AdvanceOrderTransformer())
                    ->toArray(); 
                return response()->json([ 'order' => $tempOrder,'error_text' => "Line could not be deleted as Shipped Quantity has changed."], 412);
            }
            elseif($advanceLine->nav_po_no != null)
            {
                $tempOrder = fractal()
                    ->item($advanceOrder)
                    ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
                    ->transformWith(new AdvanceOrderTransformer())
                    ->toArray(); 
                return response()->json([ 'order' => $tempOrder,'error_text' => "Line could not be deleted as order is already in progress."], 412);
            }
            else
            {
                list($advanceOrder,$alert) = $this->advanceOrdersService->deleteLine($company, $customerType, $customer, $advanceOrder, $advanceLine, Auth::user());
                $advanceOrderData = null;
                if($advanceOrder)
                {
                    $advanceOrderData = fractal()
                    ->item($advanceOrder)
                    ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
                    ->transformWith(new AdvanceOrderTransformer())
                    ->toArray();
                }
                return ['co' => $advanceOrderData,'alert' => $alert];
            }
        }
        catch(RedisException $e)
        {
            $tempOrder = fractal()
                ->item($advanceOrder)
                ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
                ->transformWith(new AdvanceOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Line deleted, however Email was not sent to Customer as Email Queue Server is down."], 503);
        }
        catch(\Exception $e)
        {
            $advanceOrder = $customer->advanceOrders()->find($orderId);
            $tempOrder = fractal()
                ->item($advanceOrder)
                ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour'])
                ->transformWith(new AdvanceOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Internal server error."], 412);
        }
    }
}