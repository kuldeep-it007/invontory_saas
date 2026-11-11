<?php
namespace Rosetta\Transformers\Collections;

use League\Fractal\TransformerAbstract;
use stdClass;
class TechspecTransformer extends TransformerAbstract
{
	public function transform(stdClass $techSpec)
	{
		return [
			"id"=> $techSpec->id,
			"attribute_id" => $techSpec->attribute_id,
			"value" => $techSpec->value,
			"title" => $techSpec->title,
		];
	}
}