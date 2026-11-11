<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Payment;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Services\PurchaseOrders\PurchaseOrdersServiceInterface;

class MigratePurchaseOrdersToBlanketOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:purchase_orders_to_blanket_orders {companyId} {stockPointId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate Purchase Orders in ROS to Blanket Orders as part of Release 1.0.8 Multiple Stock Point implementation';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $purchaseOrdersService;
    public function __construct(PurchaseOrdersServiceInterface $purchaseOrdersService)
    {
        $this->purchaseOrdersService = $purchaseOrdersService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $stockPointId = $this->argument('stockPointId');
        $companyId = $this->argument('companyId');
        $company = Company::find($companyId);
        $purchaseOrders = $company->purchaseOrders()->whereNull('blanket_order_id')->whereNull('stock_point_id')->get();
        /*$purchaseOrders = PurchaseOrder::where('company_id', '=', '3')
                        ->whereNull('blanket_order_id')
                        ->whereNull('stock_point_id')
                        ->get();*/
        foreach($purchaseOrders as $purchaseOrder)
        {
            $blanketOrder = new BlanketOrder;
            $blanketOrder->orderable_id = $purchaseOrder->orderable_id;
            $blanketOrder->orderable_type = $purchaseOrder->orderable_type;
            $blanketOrder->order_number = $purchaseOrder->order_number;
            $blanketOrder->company_id = $purchaseOrder->company_id;
            $blanketOrder->distributor_id = $purchaseOrder->distributor_id;
            $blanketOrder->end_customer_id = $purchaseOrder->end_customer_id;
            $blanketOrder->engineer_id = $purchaseOrder->engineer_id;
            $blanketOrder->cancelation_reason = $purchaseOrder->cancelation_reason;
            $blanketOrder->canceled_by = $purchaseOrder->canceled_by;
            $blanketOrder->invoice_id = $purchaseOrder->invoice_id;
            $blanketOrder->created_at = $purchaseOrder->created_at;
            $blanketOrder->updated_at = $purchaseOrder->updated_at;
            $blanketOrder->deleted_at = $purchaseOrder->deleted_at;

            if($purchaseOrder->distributor_id != null)//DUD order
            {
                if($purchaseOrder->status == 'Shipped')
                {
                    $blanketOrder->shipped = true;
                }
            }
            $blanketOrder->save();
            if($blanketOrder)
            {
                $purchaseOrder->blanket_order_id = $blanketOrder->id;
                $purchaseOrder->stock_point_id = $stockPointId;
                $purchaseOrder->save();

                $customer = $purchaseOrder->orderable;
                if($customer->distributor_id != null)
                {
                    $payments = $purchaseOrder->payments;
                    foreach ($payments as $payment)
                    {
                        $blanketOrderPayment = new Payment;
                        $blanketOrderPayment->paymentable_id = $blanketOrder->id;
                        $blanketOrderPayment->paymentable_type = 'Rosetta\Models\BlanketOrder';
                        $blanketOrderPayment->customer_type = $payment->customer_type;
                        $blanketOrderPayment->customer_id = $payment->customer_id;
                        $blanketOrderPayment->company_id = $payment->company_id;
                        $blanketOrderPayment->distributor_id = $payment->distributor_id;
                        $blanketOrderPayment->amount = $payment->amount;
                        $blanketOrderPayment->payment_mode = $payment->payment_mode;
                        $blanketOrderPayment->created_at = $payment->created_at;
                        $blanketOrderPayment->updated_at = $payment->updated_at;
                        $blanketOrderPayment->save();
                        $payment->delete();
                    }
                    $overdueAmount = $customer->overdue_amounts()->where('order_id', '=', $purchaseOrder->id)->first();
                    if($overdueAmount)
                    {
                        $overdueAmount->order_id = $blanketOrder->id;
                        $overdueAmount->save();
                    }
                }
            }
        }
    }
}
