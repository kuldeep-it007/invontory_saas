<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use stdClass;
class TechSpecTransformer extends TransformerAbstract
{
	public function transform(stdClass $techSpec)
	{
		return [
			"attribute_id" => $techSpec->id,
			"title" => $techSpec->title,
		];
	}
}