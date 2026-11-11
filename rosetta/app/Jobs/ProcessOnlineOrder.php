<?php

namespace Rosetta\Jobs;

use Illuminate\Bus\Queueable;
use Rosetta\Models\Distributor;
use Rosetta\Models\Transaction;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Rosetta\Services\Payments\PaymentsServiceInterface;
use Rosetta\Services\PurchaseOrders\PurchaseOrdersServiceInterface;

class ProcessOnlineOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $transaction;
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(PurchaseOrdersServiceInterface $purchaseOrderService,PaymentsServiceInterface $paymentsService)
    {
        $params = [];
        $s = $this->transaction->getSettingsForTypes(['shipping_address','shipping_method','special_request'])->all();
        foreach ($s as $key => $value) 
        {
            array_set($params, $key, $value);
        }
        $params['order_from'] = 'blocked';
        $blocked = $this->transaction->blockedItems;
        $params['purchase_lines'] = [];
        foreach($blocked as $item)
        {
            $discount = $item->discount + $item->onlineDiscount;
            $amount = $item->unit_price - ($item->unit_price * $discount/100);
            $params['purchase_lines'][] = [
                "item_id"        => $item->id,
                "product_id"     => $item->product_id,
                "rate_type"      => $item->rate_type,
                "quantity"       => $item->order_quantity,
                "batch_no"       => $item->batch_no,
                "unit_price"     => $item->unit_price,
                "discount"       => $discount,
                "onlineDiscount" => $item->onlineDiscount,
                "stock_point_id" => $item->stock_point_id,
                "amount"         => round($amount,2)
            ];
        }

        $customerType = 'dealers';
        if(get_class($this->transaction->transactionable) == Distributor::class)
        {
            $customerType = 'distributors';
        }
       // print_r($params);

        list($blanketOrder,$allInvalidLines, $message) = $purchaseOrderService->createBlanketOrder($this->transaction->company,$this->transaction->transactionable,$params,$this->transaction->author,$customerType,true,$this->transaction);
        if($blanketOrder)
        {
            /*$this->transaction->blanketOrder()->associate($blanketOrder);
            $this->transaction->save();*/
            $this->transaction->refresh();
            $paymentsService->recordPayment($blanketOrder,$this->transaction);
        }
    }
}
