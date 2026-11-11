<?php
namespace Rosetta\Transformers\Inventory;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;
class CoLocationTransformer extends TransformerAbstract
{
	public function transform(Distributor $distributor)
	{
		return [
			"id" => $distributor->id,
			"name" => $distributor->name,
		];
	}

	public function includeCoLocation(ILE $ile)
	{
		if($ile->getCoLocation())
		{
			return $this->item($ile->getCoLocation(),new CoLocationTransformer);
		}
		return null;
	}
}