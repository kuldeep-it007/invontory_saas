<?php

namespace Rosetta\Http\Controllers;

use Auth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Rosetta\Exceptions\Order\DeleteLineException;
use Rosetta\Exceptions\Order\InvalidLinesPresentException;
use Rosetta\Exceptions\Order\UpdateLineException;
use Rosetta\Exceptions\Order\UpdateLineSoapException;
use Rosetta\Exceptions\Order\ViewOrderPermissionDeniedException;
use Rosetta\Exceptions\RedisException;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Notifications\SMS\PurchaseOrderCancelledNotification;
use Rosetta\Notifications\SMS\PurchaseOrderCreatedNotification;
use Rosetta\Services\BlanketOrders\BlanketOrdersServiceInterface;
use Rosetta\Services\PurchaseOrders\PurchaseOrdersServiceInterface;
use Rosetta\Services\SSPurchaseOrders\SSPurchaseOrdersServiceInterface;
use Rosetta\Transformers\BlanketOrders\IndexTransformer as BlanketOrdersIndexTransformer;
use Rosetta\Transformers\PurchaseOrder\BlanketOrderListTransformer;
use Rosetta\Transformers\PurchaseOrder\BlanketOrderTransformer;
use Rosetta\Transformers\PurchaseOrder\IndexTransformer;
use Rosetta\Transformers\PurchaseOrder\PurchaseOrderListTransformer;
use Rosetta\Transformers\PurchaseOrder\PurchaseOrderTransformer;

class PurchaseOrdersController extends Controller
{
    protected $purchaseOrdersService, $SSPurchaseOrdersService;
    protected $blanketOrdersService;
    public function __construct(PurchaseOrdersServiceInterface $purchaseOrdersService, BlanketOrdersServiceInterface $blanketOrdersService, SSPurchaseOrdersServiceInterface $SSPurchaseOrdersService)
    {
        $this->purchaseOrdersService = $purchaseOrdersService;
        $this->blanketOrdersService = $blanketOrdersService;
        $this->SSPurchaseOrdersService = $SSPurchaseOrdersService;
    }
    public function create(Request $request, $companyID, $customerType, $customerID)
    {
        $validator = Validator::make($request->all(), [
            "shipping_method"  => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }
        $company = Company::find($companyID);
        //#722
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->superStockist;
        //$customer is the Dealer or Distributor for whom order is placed
        if ($customerType == "distributors") //order being placed for Distributor
        {
            $customer = Distributor::find($customerID);
            if ($loggedInUsersDist) //Distributor logged in and placing order for himself
            {
                if ($customer->id != $loggedInUsersDist->id) {
                    return response()->json(["error" => "You do not have permission to place order for another Distributor"], 401);
                }
            }
            if ($loggedInUsersDlr) //Dealer is not allowed to place order for Distributor
            {
                return response()->json(["error" => "You do not have permission to place order for Distributor"], 401);
            }
        } elseif ($customerType == "dealers") //order being placed for either Dealer or DUD
        {
            $customer = Dealer::find($customerID);
            if ($loggedInUsersDlr) //DUC or DUD logged in and placing order for himself
            {
                if (data_get($loggedInUsersDlr, 'id') != data_get($customer, 'id')) {
                    return response()->json(["error" => "You do not have permission to place order for Dealer"], 412);
                }
            }
            if ($loggedInUsersDist) //Distributor logged in and placing order for DUD(app)
            {
                if ($customer->distributor_id != $loggedInUsersDist->id) {
                    return response()->json(["error" => "You do not have permission to place order for this Dealer"], 412);
                }
            }
        } else if ($customerType == "super_stockists") //order being placed for Distributor
        {
            $customer = SuperStockist::find($customerID);
            if ($loggedInUsersSS) //Super Stockist logged in and placing order for himself
            {
                if ($customer->id != $loggedInUsersSS->id) {
                    return response()->json(["error" => "You do not have permission to place order for another Super Stockist"], 401);
                }
            }
            if ($loggedInUsersDist) //Distributor is not allowed to place order for Super Stockist
            {
                return response()->json(["error" => "You do not have permission to place order for Super Stockist"], 401);
            }
        }
        
        //End of #722
        if (!$request->get('online', false)) {
            try {
                if ($customerType == "dealers") {
                    $params = $request->all();
                    $collection = collect($params['purchase_lines']);
                    $groupedBySuperStockistStockPoint = $collection->groupBy('super_stockist_id');
                    if ($customer->distributor) //DUD
                    {
                        if ($customer->distributor->super_stockist_id != null) //DLR's DIST Under SS
                        {
                            list($blanketOrder, $invalidLines, $message) = $this->SSPurchaseOrdersService->createBlanketOrder($company, $customer, $request->all(), $loggedInUser, $customerType);
                        } else //DLR's DIST Under Comp
                        {
                            list($blanketOrder, $invalidLines, $message) = $this->purchaseOrdersService->createBlanketOrder($company, $customer, $request->all(), $loggedInUser, $customerType);
                        }
                    } else //DUC
                    {
                        list($blanketOrder, $invalidLines, $message) = $this->purchaseOrdersService->createBlanketOrder($company, $customer, $request->all(), $loggedInUser, $customerType);
                    }
                } elseif ($customerType == "distributors") {
                    // $params = $request->all();
                    //$collection = collect($params['purchase_lines']);
                    //$groupedBySuperStockistStockPoint = $collection->groupBy('super_stockist_id');
                    //foreach($groupedBySuperStockistStockPoint as $stock_point_id => $purchase_lines){
                    if ((get_class($customer) == Distributor::class) /* && ($stock_point_id != "" || $stock_point_id != null) */) //DUSSS
                    {
                        if ($customer->super_stockist_id != null) //DIST Under SS
                        {
                            list($blanketOrder, $invalidLines, $message) = $this->SSPurchaseOrdersService->createBlanketOrderForDistributor($company, $customer, $request->all(), $loggedInUser, $customerType);
                        } else //DIST Under Comp
                        {
                            list($blanketOrder, $invalidLines, $message) = $this->purchaseOrdersService->createBlanketOrder($company, $customer, $request->all(), $loggedInUser, $customerType);
                        }
                    } else //DUC
                    {
                        list($blanketOrder, $invalidLines, $message) = $this->purchaseOrdersService->createBlanketOrder($company, $customer, $request->all(), $loggedInUser, $customerType);
                    }
                    //}
                } else if ($customerType == "super_stockists") //order being placed for Distributor
                {
                    $params = $request->all();
                    $collection = collect($params['purchase_lines']);
                    $groupedBySuperStockistStockPoint = $collection->groupBy('super_stockist_id');
                    if ($groupedBySuperStockistStockPoint != null) //SS Under Company order being placed for SS stockpoint
                    {
                        list($blanketOrder, $invalidLines, $message) = $this->SSPurchaseOrdersService->createBlanketOrderForDistributor($company, $customer, $request->all(), $loggedInUser, $customerType);
                    }
                } else {
                    list($blanketOrder, $invalidLines, $message) = $this->purchaseOrdersService->createBlanketOrder($company, $customer, $request->all(), $loggedInUser, $customerType);
                }
            } catch (Exception $ex) {
                return response()->json(['po' => null, 'invalid' => null, 'error_text' => $ex->getMessage()], 500);
            }
        } else {
            try {
                $response = $this->purchaseOrdersService->startOnlinePaymentOrder($loggedInUser, $company, $customer, $request->all());
            } catch (InvalidLinesPresentException $e) {
                return response()->json(['po' => null, 'invalid' => $e->getInvalid(), 'error_text' => null]);
            }
            return response()->json(Arr::only($response->all(), ['url']), 202);
            exit(0);
        }
        /*   if($customer->distributor_id != null)
        {
            list($purchaseOrder,$invalidLines, $message) = $this->purchaseOrdersService->createDudOrder($company, $customer, $request->all(), Auth::user());
        }
        else
        {
             list($purchaseOrder,$invalidLines, $message) = $this->purchaseOrdersService->create($company, $customer, $request->all(), Auth::user());
        }*/
        $blanketOrderData = null;
        if ($blanketOrder) {
            $blanketOrderData = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_orders'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();

            $notifiable = (object)[
                "order_number" => $blanketOrder->order_number,
                "number" => $loggedInUser->phone,
                "customer" => $customer->name
            ];
            Notification::send($notifiable, new PurchaseOrderCreatedNotification($company)); //#595 Notify Customer

            
        }
        if ($message != null) {
            return response()->json(['po' => $blanketOrderData, 'invalid' => $invalidLines, 'error_text' => $message], 412);
        }
        return ['po' => $blanketOrderData, 'invalid' => $invalidLines];
    }
    public function refresh($UUId)
    {
        $purchaseOrder = $this->purchaseOrdersService->refresh($UUId);
    }
    public function print_dc($orderId)
    {
        try {
            $pdf = $this->purchaseOrdersService->print_dc($orderId);
            list($blanketOrder, $alert) = $this->purchaseOrdersService->findOrder(Auth::user(), $orderId);
            $blanketOrderData = null;
        } catch (ViewOrderPermissionDeniedException $e) {
            return response()->json(["error" => "You do not have permission to view this order"], 412);
        }
        $loggedInUser = Auth::user();
        if ($blanketOrder) {
            $blanketOrderData = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point', 'purchase_lines.returnItems'])
                ->transformWith(new BlanketOrderTransformer($loggedInUser))
                ->toArray();
        }
        if ($pdf == null)
            return ['po' => $blanketOrderData, 'alert' => $alert, 'pdf' => null];
        return ['po' => $blanketOrderData, 'alert' => $alert, 'pdf' => base64_encode($pdf->output('file.pdf', 'S'))];
    }
    public function update(Request $request, $companyID, $customerType, $customerID, $orderId)
    {
        $loggedInUser = Auth::user();
        if ($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif ($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);

        list($blanketOrder, $alert) = $this->purchaseOrdersService->findOrder(Auth::user(), $orderId);
        $blanketOrderData = null;
        if ($blanketOrder) {
            $blanketOrderData = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer($loggedInUser))
                ->toArray();
        }
        return ['po' => $blanketOrderData, 'alert' => $alert];
        //    $customer = $blanketOrder->orderable;

        /*  list($purchaseOrder,$alert) = $this->purchaseOrdersService->update($company, $customer, $orderId);
        $purchaseOrderData = null;
        if($purchaseOrder)
        {
            $purchaseOrderData = fractal()
            ->item($purchaseOrder)
            ->parseIncludes(['customer','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour'])
            ->transformWith(new PurchaseOrderTransformer())
            ->toArray();
        }
        return ['po' => $purchaseOrderData,'alert' => $alert];*/
    }
    public function index(Request $request, $companyID, $customerType, $customerID)
    {
        if ($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif ($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
        $purchaseOrders = $this->purchaseOrdersService->all($company, $customer, $request->all());

        $purchaseOrdersArray = fractal()
            ->collection($purchaseOrders)
            ->transformWith(new IndexTransformer())
            ->toArray();

        if ($request->get('page')) {
            return [
                "total" => $purchaseOrders->total(),
                "page"  => $purchaseOrders->currentPage(),
                "purchaseOrders" => $purchaseOrdersArray,
            ];
        } else
            return $purchaseOrdersArray;
    }

    public function blanketOrders(Request $request, $companyID, $customerType, $customerID)
    {
        //#724
        $loggedInUser = Auth::user();
        $loggedInUsersDlr = $loggedInUser->dealer;
        $loggedInUsersDist = $loggedInUser->distributor;
        $loggedInUsersSS = $loggedInUser->superStockist;
        //$customer is the Dealer or Distributor for whom order is placed
        if ($customerType == "distributors") //order being placed for Distributor
        {
            $customer = Distributor::find($customerID);
            if ($loggedInUsersDist) //Distributor logged in and placing order for himself
            {
                if ($customer->id != $loggedInUsersDist->id) {
                    return response()->json(["error" => "You do not have permission to view this data"], 401);
                }
            }
            if ($loggedInUsersDlr) //Dealer is not allowed to place order for Distributor
            {
                return response()->json(["error" => "You do not have permission to view this data"], 401);
            }
        } elseif ($customerType == "dealers") //order being placed for either Dealer or DUD
        {
            $customer = Dealer::find($customerID);
            if ($loggedInUsersDlr) //DUC or DUD logged in and placing order for himself
            {
                if ($loggedInUsersDlr->id != $customer->id) {
                    return response()->json(["error" => "You do not have permission to view this data"], 401);
                }
            }
            if ($loggedInUsersDist) //Distributor logged in and placing order for DUD(app)
            {
                if ($customer->distributor_id != $loggedInUsersDist->id) {
                    return response()->json(["error" => "You do not have permission to view this data"], 401);
                }
            }
        } elseif ($customerType == "super_stockists") //order being placed for Distributor
        {
            $customer = SuperStockist::find($customerID);
            if ($loggedInUsersSS) //Super Stockist logged in and placing order for himself
            {
                if ($customer->id != $loggedInUsersSS->id) {
                    return response()->json(["error" => "You do not have permission to view this data"], 401);
                }
            }
            if ($loggedInUsersDist) //Distributor is not allowed to place order for Super Stockist
            {
                return response()->json(["error" => "You do not have permission to view this data"], 401);
            }
        }
        //End of #724
        $company = Company::find($companyID);
        $purchaseOrders = $this->blanketOrdersService->all($company, $customer, $request->all());

        $purchaseOrdersArray = fractal()
            ->collection($purchaseOrders)
            ->parseIncludes(['so_numbers', 'stock_points', 'si_numbers']) //#765
            ->transformWith(new BlanketOrdersIndexTransformer())
            ->toArray();

        if ($request->get('page')) {
            return [
                "total" => $purchaseOrders->total(),
                "page"  => $purchaseOrders->currentPage(),
                "purchaseOrders" => $purchaseOrdersArray,
            ];
        } else
            return $purchaseOrdersArray;
    }
    public function listCompanyPOs(Request $request, $companyId)
    {
        if (!Gate::allows('viewPurchaseOrders')) {
            return response()->json("", 403);
        }
        $purchaseOrders = $this->purchaseOrdersService->listCompanyPOs($companyId, $request->all());
        $purchaseOrdersArray = fractal()
            ->collection($purchaseOrders)
            ->parseIncludes(['customer'])
            ->transformWith(new PurchaseOrderListTransformer())
            ->toArray();

        if ($request->get('page')) {
            return [
                "total" => $purchaseOrders->total(),
                "page"  => $purchaseOrders->currentPage(),
                "purchaseOrders" => $purchaseOrdersArray
            ];
        } else
            return $purchaseOrdersArray;
    }
    public function listCompanyBlanketOrders(Request $request, $companyId)
    {
        if (!Gate::allows('viewPurchaseOrders')) {
            return response()->json("", 403);
        }
        
        $purchaseOrders = $this->blanketOrdersService->listCompanyBlanketOrders($companyId, $request->all());
        $purchaseOrdersArray = fractal()
            ->collection($purchaseOrders)
            ->parseIncludes(['customer', 'so_numbers', 'si_numbers', 'stock_points'])
            ->transformWith(new BlanketOrderListTransformer())
            ->toArray();
        if ($request->get('page')) {
            return [
                "total" => $purchaseOrders->total(),
                "page"  => $purchaseOrders->currentPage(),
                "purchaseOrders" => $purchaseOrdersArray
            ];
        } else
            return $purchaseOrdersArray;
    }
    public function listCoDistributorBlanketOrders(Request $request, $companyId)
    {
        if (!Gate::allows('viewPurchaseOrders')) {
            return response()->json("", 403);
        }
        $purchaseOrders = $this->blanketOrdersService->listCoDistributorBlanketOrders($companyId, $request->all());
        $purchaseOrdersArray = fractal()
            ->collection($purchaseOrders)
            ->parseIncludes(['customer', 'so_numbers'])
            ->transformWith(new BlanketOrderListTransformer())
            ->toArray();
        if ($request->get('page')) {
            return [
                "total" => $purchaseOrders->total(),
                "page"  => $purchaseOrders->currentPage(),
                "purchaseOrders" => $purchaseOrdersArray
            ];
        } else
            return $purchaseOrdersArray;
    }
    public function delete(Request $request, $companyID, $customerType, $customerID, $orderId)
    {
        if ($customerType == "distributors") {
            $customer = Distributor::find($customerID);
            $customer_user = User::where('distributor_id', '=', $customerID)->whereHas('roles', function ($q) {
                $q->where('name', '=', 'distributorAdmin');
            })->first();
        } elseif ($customerType == "dealers") {
            $customer = Dealer::find($customerID);
            $customer_user = User::where('dealer_id', '=', $customerID)->whereHas('roles', function ($q) {
                $q->where('name', '=', 'dealerAdmin');
            })->first();
        } elseif ($customerType == "super_stockists") {
            $customer = SuperStockist::find($customerID);
            $customer_user = User::where('super_stockist_id', '=', $customerID)->whereHas('roles', function ($q) {
                $q->where('name', '=', 'superStockistAdmin');
            })->first();
        }
        $company = Company::find($companyID);
        $blanketOrder = BlanketOrder::find($orderId);
        $purchaseOrders = $blanketOrder->purchase_orders;
        foreach ($purchaseOrders as $purchaseOrder) {
            list($updatedPurchaseOrder, $alert) = $this->purchaseOrdersService->update($company, $customer, $purchaseOrder->id);
        }
        $blanketOrder->fresh();
        if ($blanketOrder->status == 'Partially Shipped' || $blanketOrder->status == 'Shipped' || $blanketOrder->status == 'Complete') {
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Order could not be deleted as Order Status has changed."], 412);
        }
        try {
            $loggedInUser = Auth::user();
            $blanketOrder = $this->purchaseOrdersService->delete($company, $orderId, $request['cancelation_reason'], $loggedInUser, $customerID, $customerType);
            $notifiable = (object)[
                "order_number" => $blanketOrder->order_number,
                "number" => $customer_user->phone,
                "customer" => $customer->name
            ];
            Notification::send($notifiable, new PurchaseOrderCancelledNotification($company)); //#595
            return fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
        } catch (RedisException $e) {
            $purchaseOrder = $customer->orders()->find($orderId);
            $blanketOrder = $purchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Order deleted, however Email was not sent to Customer."], 503);
        }
    }
    public function get(Request $request, $companyID, $customerType, $customerID, $orderId)
    {
        if ($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif ($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
        $order = $this->purchaseOrdersService->find($company, $customer, $orderId);
        return fractal()
            ->item($order)
            ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour'])
            ->transformWith(new PurchaseOrderTransformer())
            ->toArray();
    }
    public function getOrder(Request $request, $orderId)
    {
        try {
            list($blanketOrder, $alert) = $this->purchaseOrdersService->findOrder(Auth::user(), $orderId);
            $blanketOrderData = null;
            $customer = $blanketOrder->orderable;
        } catch (ViewOrderPermissionDeniedException $e) {
            return response()->json(["error" => "You do not have permission to view this order"], 412);
        }
        $loggedInUser = Auth::user();
        if ($blanketOrder) {
            //update read
            $blanketOrder->is_read = 1;
            $blanketOrder->save();

            $blanketOrderData = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point', 'purchase_lines.returnItems'])
                ->transformWith(new BlanketOrderTransformer($loggedInUser))
                ->toArray();
        }
        return ['po' => $blanketOrderData, 'alert' => $alert];
    }
    public function updateShipment(Request $request, $companyID, $customerType, $customerID, $orderId)
    {
        if ($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif ($customerType == "dealers")
            $customer = Dealer::find($customerID);
        elseif ($customerType == "super_stockists")
            $customer = SuperStockist::find($customerID);
        $company = Company::find($companyID);
        $blanketOrder = $this->purchaseOrdersService->updateShipment($company, $customer, $orderId, $request->all());
        return fractal()
            ->item($blanketOrder)
            ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
            ->transformWith(new BlanketOrderTransformer())
            ->toArray();
    }
    public function deleteLine(Request $request, $companyID, $customerType, $customerID, $orderId, $lineId)
    {
        if (!Gate::allows('deletePurchaseLines')) {
            return response()->json("", 403);
        }
        if ($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif ($customerType == "dealers")
            $customer = Dealer::find($customerID);
        elseif ($customerType == "super_stockists")
            $customer = SuperStockist::find($customerID);
        $company = Company::find($companyID);
        list($purchaseOrder, $alert) = $this->purchaseOrdersService->update($company, $customer, $orderId);
        $purchaseLine = $purchaseOrder->purchase_lines()->find($lineId);
        try {
            if ($purchaseLine->shipped_quantity > 0) {
                $blanketOrder = $purchaseOrder->blanket_order;
                $tempOrder = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray();
                return response()->json(['order' => $tempOrder, 'error_text' => "Line could not be deleted as Shipped Quantity has changed."], 412);
            } else {
                $blanketOrder = $this->purchaseOrdersService->deleteLine($company, $customerType, $customer, $purchaseOrder, $purchaseLine, Auth::user());
                $blanketOrderData = null;
                if ($blanketOrder) {
                    //   $blanketOrder = $purchaseOrder->blanket_order;
                    $blanketOrderData = fractal()
                        ->item($blanketOrder)
                        ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                        ->transformWith(new BlanketOrderTransformer())
                        ->toArray();
                }
                return ['po' => $blanketOrderData];
            }
        } catch (RedisException $e) {
            $blanketOrder = $purchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Line deleted, however Email was not sent to Customer."], 503);
        } catch (\Exception $e) {
            $purchaseOrder = $customer->orders()->find($orderId);
            $blanketOrder = $purchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Internal server error."], 412);
        }
    }
    public function updateLine(Request $request, $companyID, $customerType, $customerID, $orderId, $lineId)
    {
        if (!Gate::allows('updatePurchaseLines')) {
            return response()->json("", 403);
        }
        if ($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif ($customerType == "dealers")
            $customer = Dealer::find($customerID);
        elseif ($customerType == "super_stockists")
            $customer = SuperStockist::find($customerID);
        $company = Company::find($companyID);
        $purchaseOrder = $customer->orders()->find($orderId);
        $purchaseLine = $purchaseOrder->purchase_lines()->find($lineId);

        list($updatedPurchaseOrder, $alert) = $this->purchaseOrdersService->update($company, $customer, $orderId);
        $updatedPurchaseLine = $purchaseOrder->purchase_lines()->find($lineId);
        if ($updatedPurchaseLine->shipped_quantity != $purchaseLine->shipped_quantity) {
            $blanketOrder = $updatedPurchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Line could not be updated as Shipped Quantity has changed, please try again."], 412);
        }

        try {
            list($purchaseOrder, $alert) = $this->purchaseOrdersService->updateLine($company, $customerType, $customer, $updatedPurchaseOrder, $updatedPurchaseLine, $request->all(), Auth::user());
            $blanketOrderData = null;
            if ($purchaseOrder) {
                $blanketOrder = $purchaseOrder->blanket_order;
                $blanketOrderData = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray();
            }
            return ['po' => $blanketOrderData, 'alert' => $alert];
        } catch (RedisException $e) {
            //   $purchaseOrder = $customer->orders()->find($orderId);
            $blanketOrder = $purchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Line updated, however Email was not sent to Customer."], 503);
        } catch (\Exception $e) {
            // $purchaseOrder = $customer->orders()->find($orderId);
            $blanketOrder = $purchaseOrder->blanket_order;

            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Internal server error."], 412);
        }
    }

    public function calculatePayment(Request $request, $companyID, $customerType, $customerID)
    {
        $customer = null;
        if ($customerType == 'distributors') {
            $customer = Distributor::find($customerID);
        } elseif ($customerType == 'super_stockists') {
            $customer = SuperStockist::find($customerID);
        } elseif ($customerType == 'dealers') {
            $customer = Dealer::find($customerID);
        }
        return $this->purchaseOrdersService->calculatePayment($customer, (float)$request->get('amount'));
    }

    public function shipAndInvoice(Request $request, $companyID, $customerType, $customerID, $orderId)
    {
        $loggedInUser = Auth::user();
        list($blanketOrder, $alert) = $this->purchaseOrdersService->findOrder($loggedInUser, $orderId);
        $blanketOrderData = null;
        if ($blanketOrder) {
            $blanketOrderData = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer($loggedInUser))
                ->toArray();
        }
        return ['po' => $blanketOrderData, 'alert' => $alert];
    }    
}
