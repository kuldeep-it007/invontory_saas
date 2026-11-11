<?php
namespace Rosetta\Transformers\SuperStockists;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\State;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Transformers\Company\CompactTransformer as CompanyTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\TaxInformationTransformer;
class IndexTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['cart','company', 'tax_information', 'state','paymentTypes'];
	protected $defaultIncludes = ['company','tax_information', 'state'];
	public function transform(SuperStockist $superStockist)
	{
		return [
			"id" => $superStockist->id,
			"nav_id" => $superStockist->nav_id,
			"name" => (!empty($superStockist->display_name))?$superStockist->display_name:$superStockist->name,
			"type" => 'superStockist',
			"address" => $superStockist->nav->getAddress(),
			"address2" => $superStockist->nav->getAddress_2(),
			"city" => $superStockist->nav->getCity(),
			"zipcode" => $superStockist->nav->getPost_Code(),
			"email" => $superStockist->nav->getE_Mail(),
			"phone" => $superStockist->nav->getPhone_No(),
			"fax" => $superStockist->nav->getFax_No(),
		];
	}	
	public function includeTaxInformation(SuperStockist $superStockist)
	{
		return $this->item($superStockist->nav,new TaxInformationTransformer($superStockist->company));
	}
	public function includeCart(SuperStockist $superStockist)
	{
		return $this->item($superStockist,new CartSummaryTransformer);
	}
	public function includeCompany(SuperStockist $superStockist)
	{
		return $this->item($superStockist->company,new CompanyTransformer);
	}
	public function includeState(SuperStockist $superStockist)
	{
		$state = State::find($superStockist->nav->getState_Code());
		if(!$state)
			return null;
		return $this->item($state,new StateTransformer);
	}
	public function includePaymentTypes(SuperStockist $superStockist)
	{
		return $this->item($superStockist, function($superStockist){
			return [
				"offline" => ($superStockist->payment_type & 1) == 1,
				"online" => ($superStockist->payment_type & 2) == 2,
			];
		});
	}
}