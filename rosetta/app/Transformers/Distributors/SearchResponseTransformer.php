<?php 
namespace Rosetta\Transformers\Distributors;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;

class SearchResponseTransformer extends TransformerAbstract
{
	public function transform(Distributor $distributor)
	{
		$data = [
			"id" => $distributor->id,
			"nav_id" => $distributor->nav_id,
			"name" => $distributor->name,
			"type" => 'Distributor',
			"super_stockist_id" => $distributor->super_stockist_id,
		];
		return $data;
	}
}
