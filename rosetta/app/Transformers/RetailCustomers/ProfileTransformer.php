<?php
namespace Rosetta\Transformers\RetailCustomers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\RetailCustomer;
use Rosetta\Models\State;
use Rosetta\Transformers\Company\CompanyTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\TaxInformationTransformer;
class ProfileTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['company','tax_information','state'];
	protected $defaultIncludes = ['company'];
	public function transform(RetailCustomer $retailCustomer)
	{
		return [
			"id" => $retailCustomer->id,
			"nav_id" => $retailCustomer->nav_id,
			"name" => $retailCustomer->name,
			"address" => $retailCustomer->nav->getAddress(),
			"address2" => $retailCustomer->nav->getAddress_2(),
			"city" => $retailCustomer->nav->getCity(),
			"zipcode" => $retailCustomer->nav->getPost_Code(),
			"email" => $retailCustomer->nav->getE_Mail(),
			"phone" => $retailCustomer->nav->getPhone_No(),
			"fax" => $retailCustomer->nav->getFax_No(),
		];
	}
	public function includeCompany(RetailCustomer $retailCustomer)
	{
		return $this->item($retailCustomer->company,new CompanyTransformer);
	}	
	public function includeTaxInformation(RetailCustomer $retailCustomer)
	{
		return $this->item($retailCustomer->nav,new TaxInformationTransformer($retailCustomer->company));
	}
	public function includeState(RetailCustomer $retailCustomer)
	{
		$state = State::find($retailCustomer->nav->getState_Code());
		return $this->item($state,new StateTransformer);
	}
}