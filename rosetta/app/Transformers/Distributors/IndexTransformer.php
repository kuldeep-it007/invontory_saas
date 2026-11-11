<?php
namespace Rosetta\Transformers\Distributors;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;
use Rosetta\Models\State;
use Rosetta\Transformers\Company\CompactTransformer as CompanyTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\TaxInformationTransformer;
class IndexTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['cart','company', 'tax_information', 'state','paymentTypes'];
	protected $defaultIncludes = ['company','tax_information', 'state'];
	// public $displayName;
	// public function __construct($displayName = null)
	// {
	// 	$this->displayName = $displayName;
	// }

	public function transform(Distributor $distributor)
	{
		if(!empty($distributor->showStockPoint) && $distributor->showStockPoint='yes'){
			if(!empty($distributor->display_name)){
				$name = $distributor->display_name;
			}else{
				$name = $distributor->name;
			}
		}else{
			$name = $distributor->name;
		}
		//$name = (!empty($distributor->display_name) && $this->displayName=='yes')?$distributor->display_name:$distributor->name;
		return [
			"id" => $distributor->id,
			"nav_id" => $distributor->nav_id,
			"name" => $name,
			"type" => 'Distributor',
			"address" => $distributor->nav->getAddress(),
			"address2" => $distributor->nav->getAddress_2(),
			"city" => $distributor->nav->getCity(),
			"zipcode" => $distributor->nav->getPost_Code(),
			"email" => $distributor->nav->getE_Mail(),
			"phone" => $distributor->nav->getPhone_No(),
			"fax" => $distributor->nav->getFax_No(),
			"super_stockist_id" => $distributor->super_stockist_id,
		];
	}	
	public function includeTaxInformation(Distributor $distributor)
	{
		return $this->item($distributor->nav,new TaxInformationTransformer($distributor->company));
	}
	public function includeCart(Distributor $distributor)
	{
		return $this->item($distributor,new CartSummaryTransformer);
	}
	public function includeCompany(Distributor $distributor)
	{
		return $this->item($distributor->company,new CompanyTransformer);
	}
	public function includeState(Distributor $distributor)
	{
		$state = State::find($distributor->nav->getState_Code());
		if(!$state)
			return null;
		return $this->item($state,new StateTransformer);
	}
	public function includePaymentTypes(Distributor $distributor)
	{
		return $this->item($distributor, function($distributor){
			return [
				"offline" => ($distributor->payment_type & 1) == 1,
				"online" => ($distributor->payment_type & 2) == 2,
			];
		});
	}
}