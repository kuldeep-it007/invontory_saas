<?php
namespace Rosetta\Transformers\NAV;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\State;
use Rosetta\NAV\WS\STA\STA;
use Rosetta\Transformers\Location\StateTransformer;
class AddressTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['state'];
	protected $defaultIncludes = ['state'];
	public function transform(STA $address)
	{
		
		return [
			"code" => $address->getCode(),
			"address" => $address->getAddress(),
			"address_2" => $address->getAddress_2(),
			"post_code" => $address->getPost_Code(),
			"city" => $address->getCity(),
			"country_region_code" => $address->getCountry_Region_Code(),
			//"state" => ["code"=> $state->code,"name" => $state->name],
			"phone_no" => $address->getPhone_No(),
			"name" => $address->getName(),
			"contact" => $address->getContact(),
			
		];
	}
	public function includeState(STA $address)
	{
		$state = State::where("code",'=',$address->getState())->first();
		if($state)
			return $this->item($state,new StateTransformer);
		return null;
	}
}