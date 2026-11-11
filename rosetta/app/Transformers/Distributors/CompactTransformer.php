<?php
namespace Rosetta\Transformers\Distributors;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;
use Rosetta\Models\State;
use Rosetta\Transformers\Company\IndexTransformer as CompanyTransformer;
use Rosetta\Transformers\Distributors\AbilitiesTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\TaxInformationTransformer;
class CompactTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['company','abilities'];
	protected $defaultIncludes = ['company','abilities'];
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

		return [
			"id" => $distributor->id,
			"nav_id" => $distributor->nav_id,
			"name" => $name, //(!empty($distributor->display_name))?$distributor->display_name:$distributor->name,
			"display_name" => (!empty($distributor->display_name))?$distributor->display_name:'N/A',
			"type" => 'Distributor',
			"super_stockist_id" => $distributor->super_stockist_id,
			"super_stockist_name" => $distributor->superStockist ? $distributor->superStockist->name : '',
			"super_stockist_nav_id" => $distributor->superStockist ? $distributor->superStockist->nav_id : '',
			"state_code" => $distributor->state_code,
			"contact" => $distributor->contact,//#838
			"phone" => $distributor->phone,//#838
			"email" => $distributor->email,//#838
		];
	}	
	public function includeCompany(Distributor $distributor)
	{
		return $this->item($distributor->company,new CompanyTransformer);
	}
	public function includeAbilities(Distributor $distributor)
	{
		return $this->item($distributor,new AbilitiesTransformer);
	}
}