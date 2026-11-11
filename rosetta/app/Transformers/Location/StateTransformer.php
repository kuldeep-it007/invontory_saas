<?php
namespace Rosetta\Transformers\Location;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\State;

class StateTransformer extends TransformerAbstract
{
	public function transform(State $state)
	{
		return [
			"code" => $state->code,
			"name" => $state->name,
			"country" =>$state->country_code,
			"created_at" => $state->created_at->format('U'),
			"updated_at" => $state->updated_at->format('U'),
		];
	}
}