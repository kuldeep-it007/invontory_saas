<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Services\PaymentGateway\PaymentGatewayServiceInterface;
use Rosetta\Services\Payments\PaymentsServiceInterface;
use Rosetta\Transformers\Payment\POPaymentsTransformer;
use Rosetta\Transformers\Payment\PaymentTransformer;

class PaymentGatewayController extends Controller
{
    protected $paymentGatewayService;
    public function __construct(PaymentGatewayServiceInterface $paymentGatewayService, PaymentsServiceInterface $paymentsService)
    {
        $this->paymentGatewayService = $paymentGatewayService;
        $this->paymentsService = $paymentsService;
        
    }
    public function checkOrder(Request $request,$transaction_id)
    {
        list($order,$transaction) = $this->paymentGatewayService->getTransaction($transaction_id);
        $response = Arr::except($order->all(),'original');
        if($transaction->blanketOrder)
        {
            $response['blanketOrder'] =[
                'id' => $transaction->BlanketOrder->id,
                'number' => $transaction->BlanketOrder->order_number,
            ];
        }
        if($transaction->blanketOrder)
            return response()->json($response,201);
        
        return response()->json($response,202);
    }
 /*   public function getPaymentStatus(Request $request, $companyID, $customerType, $customerID, $blanketOrderId)
    {
        if($customerType == "distributors")
            $customer = Distributor::find($customerID);
        elseif($customerType == "dealers")
            $customer = Dealer::find($customerID);
        $company = Company::find($companyID);
        $blanketOrder = BlanketOrder::find($blanketOrderId);
        $this->paymentsService->getPaymentStatus($company,$customer,$blanketOrder);
        $blanketOrder->refresh();
        $purchaseOrders = $blanketOrder->purchase_orders;
        $purchaseOrdersArray = fractal()
            ->collection($purchaseOrders)
            ->transformWith(new POPaymentsTransformer())
            ->toArray();

        return $purchaseOrdersArray;
    }//#766*/
    public function getPaymentStatus(Request $request, $companyID, $blanketOrderId)
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
}
