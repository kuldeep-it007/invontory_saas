<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use stdClass;

class TenantsTransformer extends TransformerAbstract
{
	public function transform(stdClass $tenant)
	{
		return [
			"name" => $tenant->name,
			"url" => $tenant->url,
		];
	}
}