<?php
namespace Rosetta\Transformers\SalesManager;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Region;
class RegionTransformer extends TransformerAbstract
{
	public function transform($region)
	{
		dd($region);
		return [
			"id" => $region->id,
			"name" => $region->name
		];
	}
}