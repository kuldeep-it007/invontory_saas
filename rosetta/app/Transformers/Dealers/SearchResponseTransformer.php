<?php 
namespace Rosetta\Transformers\Dealers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Dealer;
use Rosetta\Models\MasterData;
use Rosetta\Models\State;
use Rosetta\Transformers\Company\IndexTransformer as CompanyTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\TaxInformationTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer as DistributorTransformer;

class SearchResponseTransformer extends TransformerAbstract
{
	public function transform(Dealer $dealer)
	{
		$data = [
			"id" => $dealer->id,
			"nav_id" => $dealer->nav_id,
			"name" => $dealer->name,
		//	"type" => $dealer->type,//#816
			"type" => 'Dealer',
			"distributor_id" => $dealer->distributor_id,
		];
		return $data;
	}
}
