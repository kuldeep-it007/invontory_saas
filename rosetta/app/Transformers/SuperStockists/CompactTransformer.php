<?php
namespace Rosetta\Transformers\SuperStockists;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\State;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Transformers\Company\IndexTransformer as CompanyTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\SuperStockists\AbilitiesTransformer;
use Rosetta\Transformers\TaxInformationTransformer;
class CompactTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['company','abilities'];
	protected $defaultIncludes = ['company','abilities'];
	public function transform(SuperStockist $superStockist)
	{
		if(!empty($superStockist->showStockPoint) && $superStockist->showStockPoint='yes'){
			if(!empty($superStockist->display_name)){
				$name = $superStockist->display_name;
			}else{
				$name = $superStockist->name;
			}
		}else{
			$name = $superStockist->name;
		}

		return [
			"id" => $superStockist->id,
			"nav_id" => $superStockist->nav_id,
			"name" => $name,//(!empty($superStockist->display_name))?$superStockist->display_name:$superStockist->name,
			"display_name" => (!empty($superStockist->display_name))?$superStockist->display_name:'N/A',
			"type" => 'superStockist',
			"state_code" => $superStockist->state_code,
		];
	}	
	public function includeCompany(SuperStockist $superStockist)
	{
		return $this->item($superStockist->company,new CompanyTransformer);
	}
	public function includeAbilities(SuperStockist $superStockist)
	{
		return $this->item($superStockist,new AbilitiesTransformer);
	}
}