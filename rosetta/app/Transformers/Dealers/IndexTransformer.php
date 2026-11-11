<?php 
namespace Rosetta\Transformers\Dealers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Dealer;
use Rosetta\Models\MasterData;
use Rosetta\Models\State;
use Rosetta\Transformers\Company\CompactTransformer as CompanyTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\TaxInformationTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer as DistributorTransformer;

class IndexTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['company', 'tax_information', 'state', 'distributor','paymentTypes'];
	protected $defaultIncludes = ['company', 'tax_information', 'state', 'distributor'];
	public function transform(Dealer $dealer)
	{
		$paymentTerms = null;
		if($dealer->nav->getPayment_Terms_Code())
			$paymentTerms = MasterData::where('type','=','payment_terms')->where('key','=',$dealer->nav->getPayment_Terms_Code())->first();

		$data = [
			"id" => $dealer->id,
			"nav_id" => $dealer->nav_id,
			"name" => $dealer->name,
			"contact" => $dealer->nav->getContact(),
			"type" => $dealer->type,//#816
			"distributor_id" => $dealer->distributor_id,
			"address" => $dealer->nav->getAddress(),
			"address2" => $dealer->nav->getAddress_2(),
			"city" => $dealer->nav->getCity(),
			"zipcode" => $dealer->nav->getPost_Code(),
			"email" => $dealer->nav->getE_Mail(),
			"phone" => $dealer->nav->getPhone_No(),
			"fax" => $dealer->nav->getFax_No(),
		];
		if($paymentTerms)
		{
			$data["paymentTerms"] = [
				"key" => $paymentTerms->key,
				"value" => $paymentTerms->value
			];
		}
		else
		{
			$data["paymentTerms"] = [
				"key" => "",
				"value" => ""
			];
		}
		return $data;
	}
	public function includeTaxInformation(Dealer $dealer)
	{
		return $this->item($dealer->nav,new TaxInformationTransformer($dealer->company));
	}
	public function includeCompany(Dealer $dealer)
	{
		return $this->item($dealer->company,new CompanyTransformer);
	}
	public function includeState(Dealer $dealer)
	{
		$state = State::find($dealer->nav->getState_Code());
		if(!$state)
			return null;
		return $this->item($state,new StateTransformer);
	}
	public function includeDistributor(Dealer $dealer)
	{
		if($dealer->distributor)
			return $this->item($dealer->distributor,new DistributorTransformer);
	}
	public function includePaymentTypes(Dealer $dealer)
	{
		return $this->item($dealer, function($dealer){
			return [
				"offline" => ($dealer->payment_type & 1) == 1,
				"online" => ($dealer->payment_type & 2) == 2,
			];
		});
	}
}
