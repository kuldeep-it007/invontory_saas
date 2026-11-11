<?php
namespace Rosetta\Transformers\Location;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Country;

class CountryTransformer extends TransformerAbstract
{
	public function transform(Country $country)
	{
		return [
			"code" => $country->code,
			"name" => $country->name,
			"created_at" => $country->created_at->format('U'),
			"updated_at" => $country->updated_at->format('U'),
		];
	}
}