<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Address;
class AddressTransformer extends TransformerAbstract
{
	public function transform(Address $address)
	{
		
		return [
		//	"code" => $address->getCode(),
			"address" => $address->address,
			"address_2" => $address->address2,
			"post_code" => $address->post_code,
			"city" => $address->city,
			"country_region_code" => $address->country_region_code,
			//"state" => ["code"=> $state->code,"name" => $state->name],
			"phone_no" => $address->phone_no,
			// "name" => $address->name,
			"contact" => $address->contact,
			"state" => $address->state_code
			
		];
	}
}