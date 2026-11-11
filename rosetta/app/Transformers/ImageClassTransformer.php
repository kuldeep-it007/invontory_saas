<?php
namespace Rosetta\Transformers;
use League\Fractal\TransformerAbstract;
use stdClass;

class ImageClassTransformer extends TransformerAbstract
{
	public function transform(stdClass $class)
	{
		return [
			"id" => $class->id,
			"title" => $class->title,
		];
	}
}