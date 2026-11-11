<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Engineer;

class EngineerTransformer extends TransformerAbstract
{
	public function transform(Engineer $engineer)
	{
		
		return [
			"id" => $engineer->id,
			"name" => $engineer->name,
			"phone_no" => $engineer->phone_no,			
		];
	}
}