<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Customer;
class CustomerTransformer extends TransformerAbstract
{
	public function transform(Customer $customer)
	{
		
		return [
			"id" => $customer->id,
			"name" => $customer->name,
			"phone_no" => $customer->phone_no,
		/*	"address" => $customer->address,
			"address_2" => $customer->address_2,
			"post_code" => $customer->post_code,
			"city" => $customer->city,
			"country_region_code" => $customer->country_region_code,	
			"contact" => $customer->contact,
			"state" => $customer->state*/
			
		];
	}
}