<?php
namespace Rosetta\Services\Payments;

use Auth;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Distributor;
use Rosetta\Models\Payment;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\Transaction;
use Rosetta\Services\NAV\BankReceiptServiceInterface;
use Rosetta\Services\NAV\PaymentStatusServiceInterface;
use Rosetta\Services\Payments\PaymentsServiceInterface;
use Rosetta\Services\PurchaseOrders\PurchaseOrdersServiceInterface;

class PaymentsService implements PaymentsServiceInterface
{
    protected $bankReceiptService;
    protected $paymentStatusService;
    protected $purchaseOrdersService;

    public function __construct(BankReceiptServiceInterface $bankReceiptService, PaymentStatusServiceInterface $paymentStatusService, PurchaseOrdersServiceInterface $purchaseOrdersService)
    {
        $this->bankReceiptService = $bankReceiptService;
        $this->paymentStatusService = $paymentStatusService;
        $this->purchaseOrdersService = $purchaseOrdersService;
    }
    public function recordPayment(BlanketOrder $blanketOrder, Transaction $transaction)
    {
        /**
         * 1. Create bank receipt for each PO at nav
         * 2. update PO 
         */
        $settings = $transaction->getSettingsForType('order');
        
        foreach($blanketOrder->purchase_orders as $po)
        {
            $bankReceiptResult = $this->bankReceiptService->create($blanketOrder->company,$settings->get('payment.ref'),$po->navision_order_number,
            $blanketOrder->orderable->nav_id,$po->Amount);
            $bankReceipt = $bankReceiptResult->getBankreceipt();
            $po->nav_bank_receipt_id = $bankReceipt->getKey();
            $po->save();
        }
        
        $payment = new Payment;
        $payment->amount = $settings->get('amount');
        $payment->payment_mode = 'online';
        $payment->payment_source = $transaction->gateway;
        $payment->payment_ref = $settings->get('payment.ref');
        $payment->company_id = $blanketOrder->company_id;
        $payment->customer_type = get_class($blanketOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
        $payment->customer_id = $blanketOrder->orderable_id;
        $blanketOrder->payments()->save($payment);
    }
    public function getPaymentStatus($company,$blanketOrder)//#766
    {
      //  list($blanketOrder,$alert) = $this->purchaseOrdersService->findOrder(Auth::user(),$blanketOrder->id);
        $purchaseOrders = $blanketOrder->purchase_orders;
        foreach($purchaseOrders as $po)
        {
            if($po->nav_invoice_number == null)
            {
                if($po->distributor_id == null)
                {
                    list($po,$alert) = $this->purchaseOrdersService->update($po->orderable->company, $po->orderable, $po->id);
                }
                else
                {
                    $relatedBO = BlanketOrder::where('related_po_id','=',$po->id)->first();
                    if($relatedBO)
                    {
                        $relatedPO = $relatedBO->purchase_orders()->first();
                        if($relatedPO->navision_order_number)
                        {
                            list($relatedPO,$alert) = $this->purchaseOrdersService->update($relatedPO->orderable->company, $relatedPO->orderable, $relatedPO->id);
                        }
                    }
                }
            }
            if($po->nav_invoice_number != null || $po->nav_invoice_number != "")
            {
                $navPayment = $this->paymentStatusService->getPaymentStatus($company,$po->nav_invoice_number);
                if($navPayment)
                {
                    $NAVPaymentRecord = $po->payments()->where('payment_source','=','NAV')->first();
                    if($NAVPaymentRecord)
                    {
                        $NAVPaymentRecord->amount = $navPayment->getAmount_Paid();
                        $NAVPaymentRecord->save();
                    }
                    else
                    {
                        $payment = new Payment;
                        $payment->amount = $navPayment->getAmount_Paid_LCY();//getPaid_Amount();
                        $payment->payment_source = 'NAV';
                        $payment->company_id = $blanketOrder->company_id;
                        $payment->customer_type = get_class($blanketOrder->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
                        $payment->customer_id = $blanketOrder->orderable_id;
                        $po->payments()->save($payment);
                    }
                    $po->remaining_amount = $navPayment->getRemaining_Amt_LCY();
                    $po->paid_amount = $navPayment->getAmount_Paid_LCY();
                    if($navPayment->getRemaining_Amt_LCY() == 0)
                        $po->payment_status = 'Paid';
                    elseif($navPayment->getRemaining_Amt_LCY() < $navPayment->getAmount_LCY()) 
                        $po->payment_status = 'Partially Paid';
                    $po->save();            
                }
            }
        } 
    }
}