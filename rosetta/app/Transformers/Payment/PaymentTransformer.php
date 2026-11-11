<?php
namespace Rosetta\Transformers\Payment;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Payment;
use Rosetta\Transformers\Dealers\IndexTransformer as DealerTransformer;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;

class PaymentTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['customer'];
	protected $defaultIncludes = ['customer'];
	
	public function transform(Payment $payment)
	{
		return [
			"id" => $payment->id,
			"amount" => $payment->amount,
		];
	}
	public function includeCustomer(Payment $payment)
	{
		if(!$payment->customer_id)
		{
			return null;
		}
		if($payment->customer_type == 'DEALER')
		{
			return $this->item($payment->dealer,new DealerTransformer);
		}
	}
}