<?php
namespace Rosetta\Services\PaymentGateway;

use Rosetta\Jobs\ProcessOnlineOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Setting;
use Rosetta\Models\Transaction;
use Rosetta\Services\PaymentGateway\Models\Customer;
use Rosetta\Services\PaymentGateway\Models\OrderResponse;
use Rosetta\Services\PaymentGateway\PaymentGatewayServiceInterface;

class PaymentGatewayService implements PaymentGatewayServiceInterface
{
    public function initiate(Company $company, $customer, float $amount,String $transaction_id)
    {
        $settings = $company->getSettingsForType('payment_gateway');
        $pgCustomer = new Customer;
        $pgCustomer->name = $customer->name;
        return (new PaymentGatewayManager(app()))
            ->gateway($settings->get('gateway'))
            ->init($settings)
            ->create($pgCustomer,$amount,$transaction_id,[
                'success' => config('app.url') . "pg/{$transaction_id}",
                'decline' => config('app.url') . "pg/{$transaction_id}",
                'cancel'  => config('app.url') . "pg/{$transaction_id}",
            ]);
    }

    public function getTransaction($transaction_id)
    {
        $transaction = Transaction::where('UUID','=',$transaction_id)->first();
        if($transaction->status == 'paid' || $transaction->status == 'cancelled')
        {
            $settings = $transaction->getSettingsForType('order')->all();
            $array = array();
            foreach ($settings as $key => $value) 
            {
                array_set($array, $key, $value);
            }
            return [new OrderResponse($array),$transaction];
        }
        
        $company = $transaction->company;
        $settings = $company->getSettingsForType('payment_gateway');
        $order = (new PaymentGatewayManager(app()))
            ->gateway($settings->get('gateway'))
            ->init($settings)
            ->getTransaction($transaction->external_reference);
        $transaction->status = $order->get('status');
        $transaction->save();
        $settingsArray = [];

        $settingsArray[] =  new Setting([
            'key' => 'status',
            'value' => $order->get('status'),
            'type' => 'order'
        ]);

        $settingsArray[] =  new Setting([
            'key' => 'amount',
            'value' => $order->get('amount'),
            'type' => 'order'
        ]);
        $settingsArray[] =  new Setting([
            'key' => 'currency',
            'value' => $order->get('currency'),
            'type' => 'order'
        ]);
        if($value = $order->get('payment.method'))
        {
            $settingsArray[] =  new Setting([
                'key' => 'payment.method',
                'value' => $value,
                'type' => 'order'
            ]);
        }

        if($value = $order->get('payment.ref'))
        {
            $settingsArray[] =  new Setting([
                'key' => 'payment.ref',
                'value' => $value,
                'type' => 'order'
            ]);
        }

        if($value = $order->get('payment.card.type'))
        {
            $settingsArray[] =  new Setting([
                'key' => 'payment.card.type',
                'value' => $value,
                'type' => 'order'
            ]);
        }

        if($value = $order->get('payment.card.number'))
        {
            $settingsArray[] =  new Setting([
                'key' => 'payment.card.number',
                'value' => $value,
                'type' => 'order'
            ]);
        }
        if($value = $order->get('payment.message'))
        {
            $settingsArray[] =  new Setting([
                'key' => 'message',
                'value' => $value,
                'type' => 'order'
            ]);
        }

        $settingsArray[] =  new Setting([
            'key' => 'original',
            'value' => json_encode($order->get('original')),
            'type' => 'order'
        ]);

        $transaction->settings()->saveMany($settingsArray);

        if($transaction->status == 'paid')
        {
            ProcessOnlineOrder::dispatch($transaction)->onQueue(config('brand.queue_prefix') . '.pgorders');
        }
        else if($transaction->status == 'declined' || $transaction->status == 'cancelled')
        {
            $transaction->blockedItems()->update(['transaction_id' => null]);
        }
        return [$order,$transaction];
    }
}