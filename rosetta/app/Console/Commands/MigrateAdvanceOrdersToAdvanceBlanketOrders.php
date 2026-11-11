<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Payment;
use Rosetta\Services\AdvanceOrders\AdvanceOrdersServiceInterface;

class MigrateAdvanceOrdersToAdvanceBlanketOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
  //  protected $signature = 'migrate:advance_orders_to_advance_blanket_orders {companyId} {stockPointId}';
      protected $signature = 'migrate:advance_orders_to_advance_blanket_orders {companyId}';  

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate Advance Orders in ROS to Advance Blanket Orders as part of Release 1.0.22 In Transit implementation';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $advanceOrdersService;
    public function __construct(AdvanceOrdersServiceInterface $advanceOrdersService)
    {
        $this->advanceOrdersService = $advanceOrdersService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
      //  $stockPointId = $this->argument('stockPointId');
        $companyId = $this->argument('companyId');
        $company = Company::find($companyId);
        $advanceOrders = $company->advanceOrders()->whereNull('blanket_order_id')->whereNull('stock_point_id')->get();

        foreach($advanceOrders as $advanceOrder)
        {
            $blanketOrder = new AdvanceBlanketOrder;
            $blanketOrder->orderable_id = $advanceOrder->orderable_id;
            $blanketOrder->orderable_type = $advanceOrder->orderable_type;
            $blanketOrder->order_number = $advanceOrder->order_number;
            $blanketOrder->company_id = $advanceOrder->company_id;
            $blanketOrder->distributor_id = $advanceOrder->distributor_id;
            $blanketOrder->end_customer_id = $advanceOrder->end_customer_id;
        //    $blanketOrder->engineer_id = $advanceOrder->engineer_id;
            $blanketOrder->cancelation_reason = $advanceOrder->cancelation_reason;
            $blanketOrder->canceled_by = $advanceOrder->canceled_by;
            $blanketOrder->invoice_id = $advanceOrder->invoice_id;
            $blanketOrder->created_at = $advanceOrder->created_at;
            $blanketOrder->updated_at = $advanceOrder->updated_at;
            $blanketOrder->deleted_at = $advanceOrder->deleted_at;

            if($advanceOrder->distributor_id != null)//DUD order
            {
                if($advanceOrder->status == 'Shipped')
                {
                    $blanketOrder->shipped = true;
                }
            }
            $blanketOrder->save();
            if($blanketOrder)
            {
                $advanceOrder->blanket_order_id = $blanketOrder->id;
             //   $advanceOrder->stock_point_id = $stockPointId;
                $advanceOrder->save();

                $customer = $advanceOrder->orderable;
                if($customer->distributor_id != null)
                {
                    $payments = $advanceOrder->payments;
                    foreach ($payments as $payment)
                    {
                        $blanketOrderPayment = new Payment;
                        $blanketOrderPayment->paymentable_id = $blanketOrder->id;
                        $blanketOrderPayment->paymentable_type = 'Rosetta\Models\AdvanceBlanketOrder';
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
                    $overdueAmount = $customer->overdue_amounts()->where('order_id', '=', $advanceOrder->id)->first();
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
