<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Rosetta\Exceptions\CoDistributors\AlreadyPresentException;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Services\BlanketOrders\BlanketOrdersServiceInterface;
use Rosetta\Services\CoDistributor\CoDistributorServiceInterface;
use Rosetta\Transformers\CoDistributors\CompactTransformer;
use Rosetta\Transformers\PurchaseOrder\BlanketOrderTransformer;
use Rosetta\Transformers\PurchaseOrder\CoDistributorBlanketOrderListTransformer;
use Rosetta\Transformers\PurchaseOrder\CoDistributorBlanketOrderTransformer;
use Rosetta\Transformers\PurchaseOrder\RelatedBlanketOrderTransformer;

class CoDistributorController extends Controller
{
	protected $coDistributorService;
    protected $blanketOrderService;
	public function __construct(CoDistributorServiceInterface $coDistributorService,BlanketOrdersServiceInterface $blanketOrderService)
	{
		$this->coDistributorService = $coDistributorService;
        $this->blanketOrderService = $blanketOrderService;
	}
    public function index(Request $request,int $companyId,int $distributorId)
    {
    	$company = Company::find($companyId);
    	$distributor = $company->distributors()->find($distributorId);
    	$coDistributors = $this->coDistributorService->getCoDistributors($company,$distributor,$request->query->all(),$request->get('type',1));
    	$coDistributorsArray =  fractal()
            ->collection($coDistributors)
            ->transformWith(new CompactTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $coDistributors->total(),
                "page"  => $coDistributors->currentPage(),
                "distributors" => $coDistributorsArray,
            ];
        }
        else
            return $coDistributorsArray;
    }
    public function update(Request $request,int $companyId,int $distributorId)
    {
        $company = Company::find($companyId);
        $distributor = $company->distributors()->find($distributorId);
        $this->coDistributorService->update($company,$distributor,$request->all());
    }
    public function orders(Request $request,int $companyId,int $distributorId)
    {
        $company = Company::find($companyId);
        $distributor = $company->distributors()->find($distributorId);
        $blanketOrders = $this->blanketOrderService->coDistributorBlanketOrders($distributor,$request->query->all(),$request->get('type',1));
        
        $includes = [];
        if($request->get('type',1) == 1)
        {
            $includes[] = 'co_distributor';
        }
        else
            $includes[] = 'distributor';
        $blanketOrdersArray = fractal()
            ->collection($blanketOrders)
            ->parseIncludes($includes)
            ->transformWith(new CoDistributorBlanketOrderListTransformer())
            ->toArray();
        if($request->get('page'))
        {
            return [
                "total" => $blanketOrders->total(),
                "page"  => $blanketOrders->currentPage(),
                "orders" => $blanketOrdersArray,
            ];
        }
        else
            return $blanketOrdersArray;
    }
    public function getOrder(Request $request,int $companyId,int $distributorId,int $orderID)
    {
        $company = Company::find($companyId);
        $distributor = $company->distributors()->find($distributorId);
        $includes = ['purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour','related_order'];
        if($request->get('type',1) == 1)
        {
            $includes[] = 'co_distributor';
       //       $includes[] = 'related_order';
        }
        else
            $includes[] = 'distributor'; 
        $blanketOrder = $this->blanketOrderService->getOrder(Auth::user(),$distributor,$orderID,$request->get('type',1),$request->all());
       
        return fractal()
            ->item($blanketOrder)
            ->parseIncludes($includes)
            ->transformWith(new CoDistributorBlanketOrderTransformer())
            ->toArray();
    }
    public function getRelatedOrder(Request $request,int $companyId,int $distributorId,int $orderID)
    {
        $company = Company::find($companyId);
        $distributor = $company->distributors()->find($distributorId);
        $includes = ['customer','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour','distributor'];
        $relatedBlanketOrder = $this->blanketOrderService->getRelatedOrder($distributor,$orderID);
        return fractal()
            ->item($relatedBlanketOrder)
            ->parseIncludes($includes)
            ->transformWith(new RelatedBlanketOrderTransformer())
            ->toArray();
    }
    public function deleteSalesOrder(Request $request,$companyID, $distributorId, $orderId)
    {
        $company = Company::find($companyID);
        $blanketOrder = BlanketOrder::find($orderId);
        $blanketOrder->fresh();
  /*      if($blanketOrder->status == 'Partially Shipped' || $blanketOrder->status == 'Shipped')
        {
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour','purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Order could not be deleted as Order Status has changed."], 412);
        }*/
        try 
        {
            $blanketOrder = $this->coDistributorService->deleteSalesOrder($distributorId, $orderId, $request['cancelation_reason'], Auth::user());
            return fractal()
                ->item($blanketOrder)
                ->parseIncludes(['distributor','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new CoDistributorBlanketOrderTransformer())
                ->toArray();       
        }
        catch(RedisException $e)
        {
         //   $blanketOrder = $this->salesOrdersService->findOrder(Auth::user(),$orderId);
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour','purchase_lines.stock_point'])
                ->transformWith(new CoDistributorBlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Order deleted, however Email was not sent to Customer."], 503);
        }
    }
    public function deleteSalesLine(Request $request,$companyID, $distributorId, $orderId, $lineId)
    {
        $company = Company::find($companyID);
        $blanketOrder = BlanketOrder::find($orderId);
        try 
        {
            $blanketOrder = $this->coDistributorService->deleteSalesLine($distributorId, $orderId, $lineId, Auth::user());
            $blanketOrderData = null;
            if($blanketOrder)
            {
                $blanketOrderData = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['distributor','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new CoDistributorBlanketOrderTransformer())
                ->toArray();   
            }    
            return ['po' => $blanketOrderData];
        }
        catch(RedisException $e)
        {
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour','purchase_lines.stock_point'])
                ->transformWith(new CoDistributorBlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Line deleted, however Email was not sent to Customer."], 503);
        }
    }
    public function shipSalesOrder(Request $request, $companyId, $distributorId, $orderId)
    {
        $includes = ['distributor','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour','related_order'];
    
        $blanketOrder = $this->coDistributorService->shipSalesOrder($companyId, $distributorId, $orderId, $request->all(), Auth::user());

        return fractal()
            ->item($blanketOrder)
            ->parseIncludes($includes)
            ->transformWith(new CoDistributorBlanketOrderTransformer())
            ->toArray();
    }
    public function payment(Request $request, $companyId, $distributorId, $orderId)
    {
        $payment = $this->coDistributorService->addPayment($companyId, $distributorId, $orderId, $request->all(), Auth::user());
    }
    public function create(Request $request,int $companyId,int $distributorId)
    {
        $company = Company::find($companyId);
        $distributor = $company->distributors()->find($distributorId);
        try{
            $coDistributors = $this->coDistributorService->create($company,$distributor,$request->all());       
        }
        catch(AlreadyPresentException $e)
        {
            return response()->json([],409);
        }
    }
    public function updateSalesLine(Request $request, $companyID, $distributorId, $orderId, $lineId)
    {
        $blanketOrder = BlanketOrder::find($orderId);
        $purchaseOrder = $blanketOrder->purchase_orders()->first();
        $purchaseLine = $purchaseOrder->purchase_lines()->find($lineId);
        

        $company = Company::find($companyID);
        $customer = $purchaseOrder->customer;
        $loggedInUser = Auth::user();
       
        if($request['quantity'] < $purchaseLine->shipped_quantity)
        {
           // $blanketOrder = $purchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new BlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Line could not be updated as Shipped Quantity has changed, please try again."], 412);
        }
        
        try
        {
            $updatedPurchaseOrder = $this->coDistributorService->updateSalesLine($distributorId, $orderId, $lineId, $request->all(), Auth::user());
            $blanketOrderData = null;

            if($updatedPurchaseOrder)
            {
                $blanketOrder = $purchaseOrder->blanket_order;
                if($blanketOrder)
                {
                    $blanketOrderData = fractal()
                    ->item($blanketOrder)
                    ->parseIncludes(['distributor','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour', 'purchase_lines.stock_point'])
                    ->transformWith(new CoDistributorBlanketOrderTransformer())
                    ->toArray();   
                }    
                return ['po' => $blanketOrderData];
            }
        }
        catch(RedisException $e)
        {
         //   $purchaseOrder = $this->salesOrdersService->findOrder(Auth::user(),$orderId);
            $blanketOrder = $purchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new CoDistributorBlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Line updated, however Email was not sent to Customer."], 503);
        }
        catch(\Excseption $e)
        {
            dd($e);
          //  $purchaseOrder = $this->salesOrdersService->findOrder(Auth::user(),$orderId);
            $blanketOrder = $purchaseOrder->blanket_order;
            $tempOrder = fractal()
                ->item($blanketOrder)
                ->parseIncludes(['customer','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour', 'purchase_lines.stock_point'])
                ->transformWith(new CoDistributorBlanketOrderTransformer())
                ->toArray(); 
            return response()->json([ 'order' => $tempOrder,'error_text' => "Internal server error."], 412);
        }
    }
}
