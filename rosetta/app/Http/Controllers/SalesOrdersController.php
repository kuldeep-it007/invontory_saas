<?php

namespace Rosetta\Http\Controllers;

use Auth;
use Exception;
use Illuminate\Http\Request;
use Rosetta\Exceptions\Order\ViewOrderPermissionDeniedException;
use Rosetta\Exceptions\RedisException;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Distributor;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Services\BlanketOrders\BlanketOrdersServiceInterface;
use Rosetta\Services\PurchaseOrders\PurchaseOrdersServiceInterface;
use Rosetta\Services\SalesOrders\SalesOrdersServiceInterface;
use Rosetta\Transformers\Payment\PaymentTransformer;
use Rosetta\Transformers\PurchaseOrder\BlanketOrderListTransformer;
use Rosetta\Transformers\PurchaseOrder\BlanketOrderTransformer;
use Rosetta\Transformers\PurchaseOrder\IndexTransformer;
use Rosetta\Transformers\PurchaseOrder\PurchaseOrderTransformer;
use Rosetta\Transformers\ReturnItem\ReturnItemTransformer;

class SalesOrdersController extends Controller
{
    protected $salesOrdersService;
    protected $blanketOrdersService, $purchaseOrdersService;
    public function __construct(SalesOrdersServiceInterface $salesOrdersService, BlanketOrdersServiceInterface $blanketOrdersService, PurchaseOrdersServiceInterface $purchaseOrdersService)
    {
        $this->salesOrdersService = $salesOrdersService;
        $this->blanketOrdersService = $blanketOrdersService;
        $this->purchaseOrdersService = $purchaseOrdersService;
    }
    public function index(Request $request, $companyID, $distributorId)
    {
        $distributor = Distributor::find($distributorId);
        //#723
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        if ($loggedInUsersDist) {
            if ($distributor->id != $loggedInUsersDist->id) {
                return response()->json(["error" => "You do not have permissions to view this data"], 401);
            }
        } //End of #723
        // $company = Company::find($companyID);
        $blanketOrders = $this->blanketOrdersService->distributorBlanketOrders($distributorId, $request->all());

        $getLoginUserData = false;
        if($request->is('api/companies/*/distributors/*/sales_orders')){
            $getLoginUserData = true;
        }
        
        if(($loggedInUser->hasRole('superStockistAdmin') || $loggedInUser->hasRole('distributorAdmin')) && ($getLoginUserData)){
            $loggedInUser['filter_purchase_orders'] = true;
        }

        $blanketOrdersArray = fractal()
            ->collection($blanketOrders)
            ->transformWith(new BlanketOrderListTransformer($loggedInUser))
            ->toArray();
        //dd($blanketOrders);
        if ($request->get('page')) {
            return [
                "total" => $blanketOrders->total(),
                "page"  => $blanketOrders->currentPage(),
                "salesOrders" => $blanketOrdersArray,
            ];
        } else
            return $blanketOrdersArray;
    }
    public function superStockistIndex(Request $request, $companyID, $superstockist_id)
    {
        $superStockist = SuperStockist::find($superstockist_id);
        //#723
        $loggedInUser = Auth::user();
        $loggedInUsersSS = $loggedInUser->superStockist;
        if ($loggedInUsersSS) {
            if ($superStockist->id != $loggedInUsersSS->id) {
                return response()->json(["error" => "You do not have permissions to view this data"], 401);
            }
        } //End of #723
        // $company = Company::find($companyID);
        $blanketOrders = $this->blanketOrdersService->superStockistBlanketOrders($superstockist_id, $request->all());

        $getLoginUserData = false;
        if($request->is('api/companies/*/superstockists/*/sales_orders')){
            $getLoginUserData = true;
        }
        
        if(($loggedInUser->hasRole('superStockistAdmin') || $loggedInUser->hasRole('distributorAdmin')) && ($getLoginUserData)){
            $loggedInUser['filter_purchase_orders'] = true;
        }

        $blanketOrdersArray = fractal()
            ->collection($blanketOrders)
            ->transformWith(new BlanketOrderListTransformer($loggedInUser))
            ->toArray();
        //dd($blanketOrders);
        if ($request->get('page')) {
            return [
                "total" => $blanketOrders->total(),
                "page"  => $blanketOrders->currentPage(),
                "salesOrders" => $blanketOrdersArray,
            ];
        } else
            return $blanketOrdersArray;
    }



    /* public function blanketOrders(Request $request, $companyID, $customerType,$customerID)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
        $purchaseOrders = $this->blanketOrdersService->all($company, $customer, $request->all());

        $purchaseOrdersArray = fractal()
            ->collection($purchaseOrders)
            ->transformWith(new BlanketOrdersIndexTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $purchaseOrders->total(),
                "page"  => $purchaseOrders->currentPage(),
                "purchaseOrders" => $purchaseOrdersArray,
            ];
        }
        else
            return $purchaseOrdersArray;
    }*/
    public function getOrder(Request $request, $orderId)
    {
        try {
            $blanketOrder = $this->salesOrdersService->findOrder(Auth::user(), $orderId);
            $blanketOrderData = null;
            $customer = $blanketOrder->orderable;
        } catch (ViewOrderPermissionDeniedException $e) {
            return response()->json(["error" => "You do not have permission to view this order"], 412);
        }

        $getLoginUserData = false;
        if($request->is('api/sales_orders/*')){
            $getLoginUserData = true;
        }

        $loggedInUser = Auth::user();
        if(($loggedInUser->hasRole('superStockistAdmin') || $loggedInUser->hasRole('distributorAdmin')) && ($getLoginUserData)){
            $loggedInUser['filter_purchase_lines'] = true;
        }
        
        //update read
        $blanketOrder->is_read = 1;
        $blanketOrder->save();
        
        if ($blanketOrder) {
            $blanketOrderData = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer(($loggedInUser)))
                ->toArray();
        }
        return ['po' => $blanketOrderData];
    }
    public function delete(Request $request, $companyID, $distributorId, $orderId)
    {
        $distributor = Distributor::find($distributorId);
        //#723
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        if ($loggedInUsersDist) {
            if ($distributor->id != $loggedInUsersDist->id) {
                return response()->json(["error" => "You do not have permissions to cancel order"], 401);
            }
        } //End of #723
        $company = Company::find($companyID);
        $blanketOrder = BlanketOrder::find($orderId);
        $purchaseOrders = $blanketOrder->purchase_orders;
        foreach ($purchaseOrders as $purchaseOrder) {
            if ($purchaseOrder->navision_order_number) {
                list($updatedPurchaseOrder, $alert) = $this->purchaseOrdersService->update($company, $purchaseOrder->orderable, $purchaseOrder->id);
            }
        }
        $blanketOrder->fresh();
        $loggedInUser = Auth::user();
        if ($blanketOrder->status == 'Partially Shipped' || $blanketOrder->status == 'Shipped') {
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer($loggedInUser))
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Order could not be deleted as Order Status has changed."], 412);
        }
        try {
            $blanketOrder = $this->salesOrdersService->delete($distributorId, $orderId, $request['cancelation_reason'], Auth::user());
            return fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer($loggedInUser))
                ->toArray();
        } catch (RedisException $e) {
            $blanketOrder = $this->salesOrdersService->findOrder(Auth::user(), $orderId);
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer($loggedInUser))
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Order deleted, however Email was not sent to Customer."], 503);
        }
    }
    public function deleteLine(Request $request, $companyID, $distributorId, $orderId, $lineId)
    {
        $loggedInUser = Auth::user();
        $purchaseOrder = PurchaseOrder::find($orderId);
        $purchaseLine = $purchaseOrder->purchase_lines()->find($lineId);
        $nav_order_no = $purchaseOrder->navision_order_number;
        $company = Company::find($companyID);
        if ($nav_order_no != null) {
            list($purchaseOrder, $alert) = $this->purchaseOrdersService->update($company, $purchaseOrder->orderable, $orderId);
            $purchaseLine = $purchaseOrder->purchase_lines()->find($lineId);

            if ($purchaseLine->shipped_quantity > 0) {
                $blanketOrder = $purchaseOrder->blanket_order;
                $tempOrder = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray();
                return response()->json(['order' => $tempOrder, 'error_text' => "Line could not be deleted as Shipped Quantity has changed, please try again."], 412);
            }
        } else {
            if ($purchaseLine->shipped_quantity > 0) {
                $blanketOrder = $purchaseOrder->blanket_order;
                $tempOrder = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray();
                return response()->json(['order' => $tempOrder, 'error_text' => "Line could not be deleted as Shipped Quantity has changed, please try again."], 412);
            }
        }
        try {
            $blanketOrder = $this->salesOrdersService->deleteLine($distributorId, $orderId, $lineId, Auth::user());
            $blanketOrderData = null;
            if ($blanketOrder) {
                $blanketOrderData = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer($loggedInUser))
                    ->toArray();
            }
            return ['po' => $blanketOrderData];
        } catch (RedisException $e) {
            $blanketOrder = $this->salesOrdersService->findOrder(Auth::user(), $orderId);
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Line deleted, however Email was not sent to Customer."], 503);
        } catch (\Exception $e) {
            // dd($e);
            $blanketOrder = $purchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Internal server error."], 412);
        }
    }
    public function updateLine(Request $request, $companyID, $distributorId, $orderId, $lineId)
    {
        $purchaseOrder = PurchaseOrder::find($orderId);
        $purchaseLine = $purchaseOrder->purchase_lines()->find($lineId);
        $company = Company::find($companyID);
        $customer = $purchaseOrder->customer;
        $loggedInUser = Auth::user();
        if ($request['nav_order_no'] != null) {
            $blanketOrder = $purchaseOrder->blanket_order;
            $relatedBO = BlanketOrder::where('related_po_id', '=', $purchaseOrder->id)->first();
            $relatedBOsPO = $relatedBO->purchase_orders()->first();

            if ($relatedBO) {
                $superRelatedBO = BlanketOrder::where('related_po_id', '=', $relatedBOsPO->id)->first(); //SS BO
                if ($superRelatedBO) {
                    $superRelatedPO = $superRelatedBO->purchase_orders()->first();
                    list($superRelatedPO, $alert) = $this->purchaseOrdersService->update($company, $customer, $superRelatedPO->id);
                } else {
                    list($relatedBOsPO, $alert) = $this->purchaseOrdersService->update($company, $customer, $relatedBOsPO->id);
                }
            }
            $updatedPurchaseLine = $purchaseOrder->purchase_lines()->find($lineId);
            if ($updatedPurchaseLine->shipped_quantity != $purchaseLine->shipped_quantity) {
                $tempOrder = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray();
                return response()->json(['order' => $tempOrder, 'error_text' => "Line could not be updated as Shipped Quantity has changed, please try again."], 412);
            }
        } else {
            if ($request['quantity'] < $purchaseLine->shipped_quantity) {
                // $blanketOrder = $purchaseOrder->blanket_order;
                $tempOrder = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray();
                return response()->json(['order' => $tempOrder, 'error_text' => "Line could not be updated as Shipped Quantity has changed, please try again."], 412);
            }
        }
        try {
            list($updatedPurchaseOrder, $alert) = $this->salesOrdersService->updateLine($distributorId, $orderId, $lineId, $request->all(), Auth::user());
            $blanketOrderData = null;
            if ($updatedPurchaseOrder) {
                $blanketOrder = $purchaseOrder->blanket_order;
                $blanketOrderData = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer($loggedInUser))
                    ->toArray();
            }
            return ['po' => $blanketOrderData, 'alert' => $alert];
        } catch (RedisException $e) {
            $blanketOrder = $purchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Line updated, however Email was not sent to Customer."], 503);
        } catch (\Exception $e) {
            //  dd($e);
            $blanketOrder = $purchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Internal server error."], 412);
        }
    }
    public function payment(Request $request, $companyId, $distributorId, $orderId)
    {
        $distributor = Distributor::find($distributorId);
        //#723
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        if ($loggedInUsersDist) {
            if ($distributor->id != $loggedInUsersDist->id) {
                return response()->json(["error" => "You do not have permissions to perform this operation"], 401);
            }
        } //End of #723
        $payment = $this->salesOrdersService->addPayment($companyId, $distributorId, $orderId, $request->all(), Auth::user());
    }
    public function ship(Request $request, $companyId, $distributorId, $orderId)
    {
        $blanketOrder = $this->salesOrdersService->ship($companyId, $distributorId, $orderId, $request->all(), Auth::user());
        $blanketOrderData = null;
        if ($blanketOrder) {
            $blanketOrderData = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
        }
        return ['po' => $blanketOrderData];
    }
    public function addInvoice(Request $request, $companyId, $distributorId, $orderId)
    {
        $blanketOrder = $this->salesOrdersService->addInvoice($companyId, $distributorId, $orderId, $request->all(), Auth::user());
        $blanketOrderData = null;
        $loggedInUser = Auth::user();
        if ($blanketOrder) {
            $blanketOrderData = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer($loggedInUser))
                ->toArray();
        }
        return ['po' => $blanketOrderData];
    }
    public function addReturns(Request $request, $distributorId)
    {
        $return_item = $this->salesOrdersService->addReturns($distributorId, $request->get('customerId'), $request->get('lineId'), $request->get('quantity'), Auth::user());
        return fractal()
            ->item($return_item)
            ->parseIncludes(['customer', 'product', 'product.category', 'product.collection', 'product.colour'])
            ->transformWith(new ReturnItemTransformer())
            ->toArray();
    }
    public function getReturns(Request $request, $distributorId)
    {
        $returnItems = $this->salesOrdersService->getReturns($distributorId, $request->all());

        $returnItemsArray = fractal()
            ->collection($returnItems)
            ->parseIncludes(['customer', 'product', 'product.category', 'product.collection', 'product.colour'])
            ->transformWith(new ReturnItemTransformer())
            ->toArray();

        if ($request->get('page')) {
            return [
                "total" => $returnItems->total(),
                "page"  => $returnItems->currentPage(),
                "return_items" => $returnItemsArray,
            ];
        } else
            return $returnItemsArray;
    }
    public function manageReturns(Request $request, $distributorId, $returnItemId)
    {
        $return_item = $this->salesOrdersService->manageReturns($distributorId, $returnItemId, $request->all(), Auth::user());
        return fractal()
            ->item($return_item)
            ->parseIncludes(['customer', 'product', 'product.category', 'product.collection', 'product.colour'])
            ->transformWith(new ReturnItemTransformer())
            ->toArray();
    }
    public function updateLineForSuperstockists(Request $request, $companyID, $superstockist_id, $orderId, $lineId)
    {

        $purchaseOrder = PurchaseOrder::find($orderId);
        $purchaseLine = $purchaseOrder->purchase_lines()->find($lineId);
        $company = Company::find($companyID);
        $customer = $purchaseOrder->customer;
        $loggedInUser = Auth::user();
        if ($request['nav_order_no'] != null) {
            $blanketOrder = $purchaseOrder->blanket_order;
            $relatedBO = BlanketOrder::where('related_po_id', '=', $purchaseOrder->id)->first();
            $relatedBOsPO = (!empty($relatedBO))?$relatedBO->purchase_orders()->first():'';

            if ($relatedBO) {
                $superRelatedBO = BlanketOrder::where('related_po_id', '=', $relatedBOsPO->id)->first(); //SS BO
                if ($superRelatedBO) {
                    $superRelatedPO = $superRelatedBO->purchase_orders()->first();
                    list($superRelatedPO, $alert) = $this->purchaseOrdersService->update($company, $customer, $superRelatedPO->id);
                } else {
                    list($relatedBOsPO, $alert) = $this->purchaseOrdersService->update($company, $customer, $relatedBOsPO->id);
                }
            }
            $updatedPurchaseLine = $purchaseOrder->purchase_lines()->find($lineId);
            if ($updatedPurchaseLine->shipped_quantity != $purchaseLine->shipped_quantity) {
                $tempOrder = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray();
                return response()->json(['order' => $tempOrder, 'error_text' => "Line could not be updated as Shipped Quantity has changed, please try again."], 412);
            }
        } else {
            if ($request['quantity'] < $purchaseLine->shipped_quantity) {
                $blanketOrder = $purchaseOrder->blanket_order;
                $tempOrder = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer())
                    ->toArray();
                return response()->json(['order' => $tempOrder, 'error_text' => "Line could not be updated as Shipped Quantity has changed, please try again."], 412);
            }
        }
        try {
            list($updatedPurchaseOrder, $alert) = $this->salesOrdersService->updateLineForSuperStockist($superstockist_id, $orderId, $lineId, $request->all(), Auth::user(), $request->ip());
            $blanketOrderData = null;
            if ($updatedPurchaseOrder) {
                $blanketOrder = $purchaseOrder->blanket_order;
                $blanketOrderData = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                    ->transformWith(new BlanketOrderTransformer($loggedInUser))
                    ->toArray();
            }
            return ['po' => $blanketOrderData, 'alert' => $alert];
        } catch (RedisException $e) {
            $blanketOrder = $purchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Line updated, however Email was not sent to Customer."], 503);
        } catch (\Exception $e) {
            // dd($e);
            $blanketOrder = $purchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.category', 'purchase_lines.product.collection', 'purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray();
            return response()->json(['order' => $tempOrder, 'error_text' => "Internal server error."], 412);
        }
    }

public function superStockistIndexReports(Request $request, $companyID, $superstockist_id)
    {
        ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
        $superStockist = SuperStockist::find($superstockist_id);
        //#723
        $loggedInUser = Auth::user();
        $loggedInUsersSS = $loggedInUser->superStockist;
        if ($loggedInUsersSS) {
            if ($superStockist->id != $loggedInUsersSS->id) {
                return response()->json(["error" => "You do not have permissions to view this data"], 401);
            }
        } //End of #723
        // $company = Company::find($companyID);
        $blanketOrders = $this->blanketOrdersService->superStockistBlanketOrders($superstockist_id, $request->all());

        $getLoginUserData = false;
        if($request->is('api/companies/*/superstockists/*/sales_orders')){
            $getLoginUserData = true;
        }
        
        if(($loggedInUser->hasRole('superStockistAdmin') || $loggedInUser->hasRole('distributorAdmin')) && ($getLoginUserData)){
            $loggedInUser['filter_purchase_orders'] = true;
        }
// dd('--', $blanketOrders);
        $blanketOrdersArray = fractal()
            ->collection($blanketOrders)
            ->transformWith(new BlanketOrderListTransformer($loggedInUser))
            ->toArray();
        // dd($blanketOrdersArray);

        if ($request->get('down') == 'excel') {


            if ($blanketOrdersArray) {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $activeSheet = $spreadsheet->getActiveSheet()->setTitle("My Stock");;
                $activeSheet->setCellValue('A1', 'Month')->getStyle('B1')->getFont()->setBold(true);
                $activeSheet->setCellValue('B1', 'Date')->getStyle('C1')->getFont()->setBold(true);
                $activeSheet->setCellValue('C1', 'StockPoint')->getStyle('D1')->getFont()->setBold(true);
                $activeSheet->setCellValue('D1', 'BilledBy')->getStyle('E1')->getFont()->setBold(true);
                $activeSheet->setCellValue('E1', 'BilledTo')->getStyle('F1')->getFont()->setBold(true);
                $activeSheet->setCellValue('F1', 'Category')->getStyle('G1')->getFont()->setBold(true);
                $activeSheet->setCellValue('G1', 'Collection')->getStyle('H1')->getFont()->setBold(true);
                $activeSheet->setCellValue('H1', 'Colour')->getStyle('I1')->getFont()->setBold(true);
                $activeSheet->setCellValue('I1', 'Qty')->getStyle('J1')->getFont()->setBold(true);

                $activeSheet->setCellValue('J1', 'Sale-Sqft')->getStyle('K1')->getFont()->setBold(true);
                // $activeSheet->setCellValue('K1', 'TaxPercent')->getStyle('L1')->getFont()->setBold(true);
                // $activeSheet->setCellValue('L1', 'TaxAmount')->getStyle('M1')->getFont()->setBold(true);
                $activeSheet->setCellValue('K1', 'NetAmount')->getStyle('L1')->getFont()->setBold(true);
                $activeSheet->setCellValue('L1', 'OrderID')->getStyle('M1')->getFont()->setBold(true);
                $row = 2;
                $activeSheet->setCellValue('M1', 'OrderStatus')->getStyle('N1')->getFont()->setBold(true);
                $row = 2;
                
                foreach ($blanketOrdersArray as $orders) {
                    if($orders['status'] == "Cancelled"){
                        continue;
                    }
                    $stockPoint = trim(implode(',', $orders['stock_points']), ',');
                    $netAmount = 0;
                    $category = [];
                    $collection = [];
                    $colour = [];
                    $quantity = 0;
                    $unitPrice = 0;
                    // $TaxPercent = 0;
                    // $TaxAmount = 0;

                    foreach ($orders['purchase_lines'] as $purchase_line) {                        
                        $netAmount+= $purchase_line['amount']??0;//$purchase_line['net_amount']??0;
                      
                        $quantity+= $purchase_line['quantity']??0;
                        // $unitPrice = $purchase_line['unit_price']??0;
                        $unitPrice = $purchase_line['unit_price']??0;
                        $discountPercentage = $purchase_line['discount']??0;
                        $discountAmount = ($unitPrice * $discountPercentage) / 100;
                        $finalPrice = round(($unitPrice - $discountAmount),2);
                        // $TaxPercent+= $purchase_line['tax_percent']??0;
                        //  $TaxAmount+= $purchase_line['tax_amount']??0;
                        if(!empty($purchase_line['product']['category']['code'])){
                            $category[] = $purchase_line['product']['category']['code'];
                        } 
                        if(!empty($purchase_line['product']['collection']['code'])){
                            $collection[] = $purchase_line['product']['collection']['code'];
                        }
                        if(!empty($purchase_line['product']['colour']['code'])){
                            $colour[] = $purchase_line['product']['colour']['code'];
                        }                       
                    }
                    $activeSheet->setCellValue('A' . $row, date('M', ($orders['created_at'])));
                    $activeSheet->setCellValue('B' . $row, date('d, M Y', ($orders['created_at'])));
                    $activeSheet->setCellValue('C' . $row, $stockPoint);
                    $activeSheet->setCellValue('D' . $row, $stockPoint);
                    $activeSheet->setCellValue('E' . $row, $orders['customer']['name']);
                    $activeSheet->setCellValue('F' . $row, trim(implode(',', array_unique($category)),','));
                    $activeSheet->setCellValue('G' . $row, trim(implode(',', array_unique($collection)),','));
                    $activeSheet->setCellValue('H' . $row, trim(implode(',', array_unique($colour)),','));
                    $activeSheet->setCellValue('I' . $row, $quantity);   
                    $activeSheet->setCellValue('J' . $row, $finalPrice);  
                    // $activeSheet->setCellValue('K' . $row, implode(',', $TaxPercent)); 
                    // $activeSheet->setCellValue('L' . $row, implode(',', $TaxAmount));  
                    $activeSheet->setCellValue('K' . $row, $netAmount); 
                    $activeSheet->setCellValue('L' . $row, $orders['id']); 
                    $activeSheet->setCellValue('M' . $row, $orders['status']); 
                    $row++;
                }
                
                for ($col = 'A'; $col !== 'N'; $col++) {
                    $activeSheet->getColumnDimension($col)->setAutoSize(true);
                }
                
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $file = storage_path() . '/app/SuperStockistStock_' . $superstockist_id . '.xlsx';

                $writer->save($file);
                return $file;
            }
        }

        if ($request->get('page')) {
            return [
                "total" => $blanketOrders->total(),
                "page"  => $blanketOrders->currentPage(),
                "salesOrders" => $blanketOrdersArray,
            ];
        } else
            return $blanketOrdersArray;
    }    
}
