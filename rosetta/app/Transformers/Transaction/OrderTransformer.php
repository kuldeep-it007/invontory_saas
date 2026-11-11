<?php
namespace Rosetta\Transformers\Transaction;

use Rosetta\Models\BlanketOrder;
use League\Fractal\TransformerAbstract;

class OrderTransformer extends TransformerAbstract
{
    public function transform(BlanketOrder $order)
    {
        return [
            'id' => $order->id,
            'number' => $order->order_number,
        ];
    }
}