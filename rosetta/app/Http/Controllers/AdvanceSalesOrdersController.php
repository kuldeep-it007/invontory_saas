<?php 
namespace Rosetta\Http\Controllers;

use Auth;
use Exception;
use Illuminate\Http\Request;
use Rosetta\Exceptions\Order\ViewOrderPermissionDeniedException;
use Rosetta\Exceptions\RedisException;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Distributor;
use Rosetta\Services\AdvanceBlanketOrders\AdvanceBlanketOrdersServiceInterface;
use Rosetta\Services\AdvanceSalesOrders\AdvanceSalesOrdersServiceInterface;
use Rosetta\Transformers\AdvanceOrder\BlanketOrderListTransformer;
use Rosetta\Transformers\AdvanceOrder\BlanketOrderTransformer;

class AdvanceSalesOrdersController extends Controller
{
    protected $advanceBlanketOrdersService, $advanceSalesOrdersService;
	public function __construct(AdvanceBlanketOrdersServiceInterface $advanceBlanketOrdersService, AdvanceSalesOrdersServiceInterface $advanceSalesOrdersService)
	{
        $this->advanceSalesOrdersService = $advanceSalesOrdersService;
        $this->advanceBlanketOrdersService = $advanceBlanketOrdersService;
	}
    public function index(Request $request, $companyID, $distributorId)
    {
        $distributor = Distributor::find($distributorId);
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
        $blanketOrders = $this->advanceBlanketOrdersService->distributorBlanketOrders($distributorId, $request->all());

        $blanketOrdersArray = fractal()
            ->collection($blanketOrders)
            ->transformWith(new BlanketOrderListTransformer())
            ->toArray();
        if($request->get('page'))
        {
            return [
                "total" => $blanketOrders->total(),
                "page"  => $blanketOrders->currentPage(),
                "advanceSalesOrders" => $blanketOrdersArray,
            ];
        }
        else
            return $blanketOrdersArray;
    }
    public function getOrder(Request $request,$orderId)
    {
        try
        {
            $blanketOrder = $this->advanceSalesOrdersService->findOrder(Auth::user(),$orderId);
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
            ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
            ->transformWith(new BlanketOrderTransformer(($loggedInUser)))
            ->toArray();
        }
        return ['ao' => $blanketOrderData];  
    }
    public function delete(Request $request,$companyID, $distributorId, $orderId)
    {
        $distributor = Distributor::find($distributorId);
        //#723
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permissions to cancel order"],401);
            }
        }//End of #723
        $company = Company::find($companyID);
        $blanketOrder = AdvanceBlanketOrder::find($orderId);
        $advanceOrders = $blanketOrder->advance_orders;
        foreach ($advanceOrders as $advanceOrder)
        {
            if($advanceOrder->navision_order_number)
            {
                list($updatedadvanceOrder,$alert) = $this->advanceOrdersService->update($company, $advanceOrder->orderable, $advanceOrder->id);
            }
        }
        $blanketOrder->fresh();
        $loggedInUser = Auth::user();
        if($blanketOrder->status == 'Partially Shipped' || $blanketOrder->status == 'Shipped')
        {
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer($loggedInUser))
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Order could not be deleted as Order Status has changed."], 412);
        }
        try 
        {
            $blanketOrder = $this->advanceSalesOrdersService->delete($distributorId, $orderId, $request['cancelation_reason'], Auth::user());
            return fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour', 'advance_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer($loggedInUser))
                ->toArray();       
        }
        catch(RedisException $e)
        {
            $blanketOrder = $this->advanceSalesOrdersService->findOrder(Auth::user(),$orderId);
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','advance_lines','advance_lines.product','advance_lines.product.category','advance_lines.product.collection','advance_lines.product.colour','advance_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer($loggedInUser))
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Order deleted, however Email was not sent to Customer."], 503);
        }
    }
}