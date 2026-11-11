<?php
namespace Rosetta\Transformers\Transaction;
use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;

class CustomerTransformer extends TransformerAbstract
{
    public function transform($customer)
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'nav_id' => $customer->nav_id,
            'type' => get_class($customer) == Distributor::class ? 'Distributor' : 'Dealer',
        ];
    }
}