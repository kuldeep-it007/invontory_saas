<?php 
namespace Rosetta\Transformers\Dealers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Dealer;
use Rosetta\Models\State;
use Rosetta\Transformers\Company\CompanyTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\TaxInformationTransformer;

class ProfileTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['distributor','tax_information','state'];
	protected $defaultIncludes = ['company'];
	public function transform(Dealer $dealer)
	{
		return [
			"id" => $dealer->id,
			"nav_id" => $dealer->nav_id,
			"name" => $dealer->name,
			"contact" => $dealer->nav->getContact(),
			"address" => $dealer->nav->getAddress(),
			"address2" => $dealer->nav->getAddress_2(),
			"city" => $dealer->nav->getCity(),
			"zipcode" => $dealer->nav->getPost_Code(),
			"email" => $dealer->nav->getE_Mail(),
			"phone" => $dealer->nav->getPhone_No(),
			"fax" => $dealer->nav->getFax_No(),
		];
	}
	public function includeCompany(Dealer $dealer)
	{
		return $this->item($dealer->company,new CompanyTransformer);
	}
	public function includeDistributor(Dealer $dealer)
	{
		if($dealer->distributor)
			return $this->item($dealer->distributor,new IndexTransformer);
	}
	public function includeTaxInformation(Dealer $dealer)
	{
		return $this->item($dealer->nav,new TaxInformationTransformer($dealer->company));
	}
	public function includeState(Dealer $dealer)
	{
		$state = State::find($dealer->nav->getState_Code());
		return $this->item($state,new StateTransformer);
	}
}
