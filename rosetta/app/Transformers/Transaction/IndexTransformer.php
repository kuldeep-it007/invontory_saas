<?php
namespace Rosetta\Transformers\Transaction;

use Rosetta\Models\Transaction;
use League\Fractal\TransformerAbstract;
use Rosetta\Transformers\Transaction\OrderTransformer;

class IndexTransformer extends TransformerAbstract
{
    protected $availableIncludes = ['order','customer'];
    public function transform(Transaction  $transaction)
    {
        $settings = $transaction->getSettingsForType('order');
        return [
            'id' => $transaction->id,
            'gateway' => $transaction->gateway,
            'status' => $transaction->status,
            'created_at' => $transaction->created_at->format('U'),
            'ref' => $settings->get('payment.ref'),
            'amount' => $settings->get('amount'),
        ];
    }
    public function includeOrder(Transaction $transaction)
    {
        if($transaction->blanketOrder)
        {
            return $this->item($transaction->blanketOrder,new OrderTransformer);
        }
        return null;
    }

    public function includeCustomer(Transaction $transaction)
    {
        if($transaction->transactionable)
        {
            return $this->item($transaction->transactionable, new CustomerTransformer);
        }
        return null;
    }
}