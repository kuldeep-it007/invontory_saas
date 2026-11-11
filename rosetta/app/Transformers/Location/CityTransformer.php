<?php
namespace Rosetta\Transformers\Location;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\City;

class CityTransformer extends TransformerAbstract
{
	public function transform(City $city)
	{
		return [
			"code" => $city->code,
			"name" => $city->name,
			"state" => $city->state_code,
			"created_at" => $city->created_at->format('U'),
			"updated_at" => $city->updated_at->format('U'),
		];
	}
}