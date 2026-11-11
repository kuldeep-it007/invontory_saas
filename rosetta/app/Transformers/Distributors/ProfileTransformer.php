<?php
namespace Rosetta\Transformers\Distributors;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;
use Rosetta\Models\State;
use Rosetta\Transformers\Company\CompanyTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\TaxInformationTransformer;
class ProfileTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['company','tax_information','state'];
	protected $defaultIncludes = ['company'];
	public function transform(Distributor $distributor)
	{
		return [
			"id" => $distributor->id,
			"nav_id" => $distributor->nav_id,
			"name" => $distributor->name,
			"address" => $distributor->nav->getAddress(),
			"address2" => $distributor->nav->getAddress_2(),
			"city" => $distributor->nav->getCity(),
			"zipcode" => $distributor->nav->getPost_Code(),
			"email" => $distributor->nav->getE_Mail(),
			"phone" => $distributor->nav->getPhone_No(),
			"fax" => $distributor->nav->getFax_No(),
		];
	}
	public function includeCompany(Distributor $distributor)
	{
		return $this->item($distributor->company,new CompanyTransformer);
	}	
	public function includeTaxInformation(Distributor $distributor)
	{
		return $this->item($distributor->nav,new TaxInformationTransformer($distributor->company));
	}
	public function includeState(Distributor $distributor)
	{
		$state = State::find($distributor->nav->getState_Code());
		return $this->item($state,new StateTransformer);
	}
}