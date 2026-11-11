<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Address;
class BillAddressTransformer extends TransformerAbstract
{
	public function transform($address)
	{
		return [
		//	"code" => $address->getCode(),
			"address" => $address->address,
			"address_2" => $address->address2,
			"post_code" => $address->post_code,
			"city" => $address->city_code,
			"state" => $address->state_code,
			"name" => $address->name,
			"id" => $address->id,
			"nav_id" => $address->nav_id,
			"phone" => $address->phone
		];
	}
}