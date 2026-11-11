<?php
namespace Rosetta\Transformers\Region;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Region;
class IndexTransformer extends TransformerAbstract
{
	public function transform(Region $region)
	{
		return [
			"id" => $region->id,
			"name" => $region->name,
			"currency_major" => $region->currency_major,
			"currency_minor" => $region->currency_minor,
			"country_code" => $region->country_code,
		];
	}
}