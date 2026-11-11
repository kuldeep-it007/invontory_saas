<?php 
namespace Rosetta\Http\Controllers;

use Auth;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Rosetta\Exceptions\Invoice\ViewInvoicePermissionDeniedException;
use Rosetta\Exceptions\Order\ViewOrderPermissionDeniedException;
use Rosetta\Exceptions\RedisException;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Setting;
use Rosetta\Models\User;
use Rosetta\Services\BlanketOrders\BlanketOrdersServiceInterface;
use Rosetta\Services\Depot\DepotItemsServiceInterface;
use Rosetta\Services\Payments\PaymentsServiceInterface;
use Rosetta\Transformers\Invoice\InvoiceListTransformer;
use Rosetta\Transformers\Payment\POPaymentsTransformer;
use Rosetta\Transformers\PurchaseOrder\BlanketOrderListTransformer;
use Rosetta\Transformers\PurchaseOrder\BlanketOrderTransformer;
use Illuminate\Support\Facades\Log;

class DepotOrdersController extends Controller
{
    protected $depotService, $paymentsService;
    public function __construct(DepotItemsServiceInterface $depotService, PaymentsServiceInterface $paymentsService)
    {
        $this->depotService = $depotService;
        $this->paymentsService = $paymentsService;
    }
    public function listCurrentDepotBlanketOrders(Request $request, $companyId)
    {    
        if(!Gate::allows('viewDepotOrders'))
        {
            return response()->json("", 403);
        }
        $purchaseOrders = $this->depotService->listDepotBlanketOrders($request->all(), $companyId);
        $purchaseOrdersArray = fractal()
            ->collection($purchaseOrders)
            ->parseIncludes(['customer','so_numbers','si_numbers','stock_points'])
            ->transformWith(new BlanketOrderListTransformer())
            ->toArray();
        if($request->get('page'))
        {
            return [
                "total" => $purchaseOrders->total(),
                "page"  => $purchaseOrders->currentPage(),
                "purchaseOrders" => $purchaseOrdersArray
            ];
        }
        else
            return $purchaseOrdersArray;
    }
    public function listInterDepotBlanketOrders(Request $request, $companyId)
    {
        try{
            return $this->depotService->listInterDepotBlanketOrders($request->all(), $companyId);
        }
        catch(ClientException $e)
        {
            return response()->json(["error_text" =>"Invalid Request"],400);
        }
        catch(ServerException $e)
        {
            return response()->json(["error_text" =>"Internal Server Error"],500);
        }
    }
    public function listDepotBlanketOrders(Request $request)//this will be called from listInterDepotBlanketOrders of another tenant #776
    {    
        $company_name = $request->get('company_name');
        $company = Company::where('name','=',$company_name)->first();
        $purchaseOrders = $this->depotService->listDepotBlanketOrders($request->all(), $company->id);
        $purchaseOrdersArray = fractal()
            ->collection($purchaseOrders)
            ->parseIncludes(['customer','so_numbers','si_numbers','stock_points'])
            ->transformWith(new BlanketOrderListTransformer())
            ->toArray();
        if($request->get('page'))
        {
            return [
                "total" => $purchaseOrders->total(),
                "page"  => $purchaseOrders->currentPage(),
                "purchaseOrders" => $purchaseOrdersArray
            ];
        }
        else
            return $purchaseOrdersArray;
    }
    public function getOrder(Request $request,$orderId)//#777
    {
        $loggedInUser = Auth::user();
        if(!Gate::allows('viewDepotOrders'))
        {
            return response()->json("", 403);
        }
        $tenant = null;
        if($request->query->has('tenant'))
        {
            $setting = Setting::where('key','stock_point_tenants')->first();
            $tenant = collect(json_decode($setting->value))->where('name','=',$request->get('tenant'))->first();
        }
        try
        {
            if($tenant != null)
            {
                return $this->depotService->findInterDepotOrder($orderId, $tenant);
            }
            else
                list($blanketOrder,$alert) = $this->depotService->findCurrentDepotOrder($orderId);
            $blanketOrderData = null;
            $customer = $blanketOrder->orderable;
        }
        catch(ViewOrderPermissionDeniedException $e)
        {
            return response()->json(["error" =>"You do not have permission to view this order"],412);
        }
        if($blanketOrder)
        {
            $blanketOrderData = fractal()
            ->item($blanketOrder)
            ->parseIncludes(['customer','purchase_lines', 'purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection', 'purchase_lines.product.colour','purchase_lines.stock_point','purchase_lines.returnItems'])
            ->transformWith(new BlanketOrderTransformer($loggedInUser))
            ->toArray();
        }
        return ['po' => $blanketOrderData,'alert' => $alert];  
     //   throw new ViewOrderPermissionDeniedException;
    } 
    public function getPaymentStatus(Request $request, $companyID, $blanketOrderId)//#778
    {
        $loggedInUser = Auth::user();
        $current_tenants_company = Company::find($loggedInUser->company_id);
        $current_tenant_name = $current_tenants_company->getSettingsForType('current_tenant')->get('tenant_name');
        $order_tenant_name = $request->get('tenant_name');

        if($order_tenant_name == $current_tenant_name)
        {
            $company = Company::find($companyID);
            $blanketOrder = BlanketOrder::find($blanketOrderId);
            $this->paymentsService->getPaymentStatus($company,$blanketOrder);
            $blanketOrder->refresh();
            $purchaseOrders = $blanketOrder->purchase_orders;
            $purchaseOrdersArray = fractal()
                ->collection($purchaseOrders)
                ->transformWith(new POPaymentsTransformer())
                ->toArray();
            return $purchaseOrdersArray;
        }
        else
        {
            return $this->depotService->getInterDepotOrderPaymentStatus($order_tenant_name, $companyID, $blanketOrderId);
        }
    }
    public function listCurrentDepotInvoices(Request $request, $companyId)#795
    {
        if(!Gate::allows('viewDepotInvoices'))
        {
            return response()->json("", 403);
        }
        $company = Company::find($companyId);   
        $invoices = $this->depotService->listDepotInvoices($request->all());

        $invoicesArray = fractal()
            ->collection($invoices)
          //  ->transformWith(new InvoiceList1Transformer()) //Dealer BOs
            ->transformWith(new InvoiceListTransformer()) //Distributor BOs
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $invoices->total(),
                "page"  => $invoices->currentPage(),
                "invoices" => $invoicesArray,
            ];
        }
        else
            return $invoicesArray;
    }
    public function listInterDepotInvoices(Request $request, $companyId)#795
    {
        try{
            return $this->depotService->listInterDepotInvoices($request->all(), $companyId);
        }
        catch(ClientException $e)
        {
            return response()->json(["error_text" =>"Invalid Request"],400);
        }
        catch(ServerException $e)
        {
            return response()->json(["error_text" =>"Internal Server Error"],500);
        }
    }
    public function listDepotInvoices(Request $request)//this will be called from listInterDepotInvoices of another tenant #795
    {    
        $company_name = $request->get('company_name');
        $company = Company::where('name','=',$company_name)->first();
        $invoices = $this->depotService->listDepotInvoices($request->all());
        $invoicesArray = fractal()
            ->collection($invoices)
            ->transformWith(new InvoiceListTransformer())
            ->toArray();
        if($request->get('page'))
        {
            return [
                "total" => $invoices->total(),
                "page"  => $invoices->currentPage(),
                "invoices" => $invoicesArray
            ];
        }
        else
            return $invoicesArray;
    }
    public function getInvoice(Request $request,$dealerBOId)#795
    {
        $loggedInUser = Auth::user();
        if(!Gate::allows('viewDepotInvoices'))
        {
            return response()->json("", 403);
        }
        $tenant = null;
        if($request->query->has('tenant'))
        {
            $setting = Setting::where('key','stock_point_tenants')->first();
            $tenant = collect(json_decode($setting->value))->where('name','=',$request->get('tenant'))->first();
        }
        try
        {
            if($tenant != null)
            {
                return $this->depotService->findInterDepotInvoice($dealerBOId, $tenant);
            }
            else
                $invoice = $this->depotService->findCurrentDepotInvoice($dealerBOId);
                $invoiceData = null;
        }
        catch(ViewInvoicePermissionDeniedException $e)
        {
            return response()->json(["error" =>"You do not have permission to view this invoice"],412);
        }
        if($invoice)
        {
            $invoiceData = fractal()
            ->item($invoice)
            ->parseIncludes(['customer','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour', 'purchase_lines.stock_point'])
            ->transformWith(new BlanketOrderTransformer($loggedInUser))
            ->toArray();
        }
        return ['po' => $invoiceData];
    }
    public function mark_printed(Request $request, $orderId)#795
    {

        $loggedInUser = Auth::user();
        $tenant = null;
        if($request->query->has('tenant'))
        {
            $setting = Setting::where('key','stock_point_tenants')->first();
            $tenant = collect(json_decode($setting->value))->where('name','=',$request->get('tenant'))->first();
        }
        try
        {
            if($tenant != null)
            {
                return $this->depotService->inter_depot_mark_printed($orderId, $request->all(), Auth::user(), $tenant);
            }
            else
                $blanketOrder = $this->depotService->current_depot_mark_printed($orderId, $request->all(), Auth::user());
                $blanketOrderData = null;
        }
        catch(ViewInvoicePermissionDeniedException $e)
        {
            return response()->json(["error" =>"You do not have permission to view this invoice"],412);
        }
        if($blanketOrder)
        {
            $blanketOrderData = fractal()
            ->item($blanketOrder)
            ->parseIncludes(['customer','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour', 'purchase_lines.stock_point'])
            ->transformWith(new BlanketOrderTransformer())
            ->toArray();
        }
        return ['po' => $blanketOrderData];
    }
    public function deleteSO(Request $request,$companyID, $distributorId, $orderId)//#796 This order will not be visible to Depot Manager or Superadmin. need to check if this is required
    {
        $tenant = null;
        if($request->query->has('tenant'))
        {
            $setting = Setting::where('key','stock_point_tenants')->first();
            $tenant = collect(json_decode($setting->value))->where('name','=',$request->get('tenant'))->first();
        }
        try
        {
            return $this->depotService->CancelInterDepotOrder($request->all(), $companyID, $distributorId, $orderId, $tenant);
        }
        catch(ClientException $e)
        {
            return response()->json(["error_text" =>"Invalid Request"],400);
        }
        catch(ServerException $e)
        {
            return response()->json(["error_text" =>"Internal Server Error"],500);
        }
    }
    public function deletePO(Request $request,$companyID, $customerType,$customerID, $orderId)//#796
    {
        $tenant = null;
        if($request->query->has('tenant'))
        {
            $setting = Setting::where('key','stock_point_tenants')->first();
            $tenant = collect(json_decode($setting->value))->where('name','=',$request->get('tenant'))->first();
        }
        try
        {
            return $this->depotService->CancelInterDepotPurchaseOrder($request->all(), $companyID, $customerType,$customerID, $orderId, $tenant);
        }
        catch(ClientException $e)
        {
            return response()->json(["error_text" =>"Invalid Request"],400);
        }
        catch(ServerException $e)
        {
            return response()->json(["error_text" =>"Internal Server Error"],500);
        }
    }
    public function deleteLine(Request $request,$companyID, $customerType,$customerID, $orderId, $lineId)
    {
        $tenant = null;
        if($request->query->has('tenant'))
        {
            $setting = Setting::where('key','stock_point_tenants')->first();
            $tenant = collect(json_decode($setting->value))->where('name','=',$request->get('tenant'))->first();
        }
        try
        {
            return $this->depotService->DeleteInterDepotPurchaseLine($request->all(), $companyID, $customerType,$customerID, $orderId, $lineId, $tenant);
        }
        catch(ClientException $e)
        {
            return response()->json(["error_text" =>"Invalid Request"],400);
        }
        catch(ServerException $e)
        {
            return response()->json(["error_text" =>"Internal Server Error"],500);
        }
    }
    public function deleteSOLine(Request $request,$companyID, $distributorId, $orderId, $lineId)
    {
        $tenant = null;
        if($request->query->has('tenant'))
        {
            $setting = Setting::where('key','stock_point_tenants')->first();
            $tenant = collect(json_decode($setting->value))->where('name','=',$request->get('tenant'))->first();
        }
        try
        {
            return $this->depotService->DeleteInterDepotSalesLine($companyID, $distributorId, $orderId, $lineId, $tenant);
        }
        catch(ClientException $e)
        {
            return response()->json(["error_text" =>"Invalid Request"],400);
        }
        catch(ServerException $e)
        {
            return response()->json(["error_text" =>"Internal Server Error"],500);
        }
    }
    public function updateLine(Request $request,$companyID, $customerType,$customerID, $orderId, $lineId)
    {
        $tenant = null;
        if($request->query->has('tenant'))
        {
            $setting = Setting::where('key','stock_point_tenants')->first();
            $tenant = collect(json_decode($setting->value))->where('name','=',$request->get('tenant'))->first();
        }
        try
        {
            return $this->depotService->UpdateInterDepotPurchaseLine($request->all(), $companyID, $customerType, $customerID, $orderId, $lineId, $tenant);
        }
        catch(ClientException $e)
        {
            return response()->json(["error_text" =>"Invalid Request"],400);
        }
        catch(ServerException $e)
        {
            return response()->json(["error_text" =>"Internal Server Error"],500);
        }
    }
    public function depot_print_dc(Request $request, $orderId)
    {
        $tenant = null;
        if($request->query->has('tenant'))
        {
            $setting = Setting::where('key','stock_point_tenants')->first();
            $tenant = collect(json_decode($setting->value))->where('name','=',$request->get('tenant'))->first();
        }
        try
        {
            return $this->depotService->print_dc($orderId, $tenant);
        }
        catch(ClientException $e)
        {
            Log::info(strval($e->getResponse()->getBody()));
            return response()->json(["error_text" =>"Invalid Request"],400);
        }
        catch(ServerException $e)
        {
            Log::info(strval($e->getResponse()->getBody()));
            return response()->json(["error_text" =>"Internal Server Error"],500);
        }
    }
}