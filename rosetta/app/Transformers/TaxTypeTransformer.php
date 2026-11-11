<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\TaxType;
class TaxTypeTransformer extends TransformerAbstract
{
	public function transform(TaxType $taxType)
	{
		return [
			"code" => $taxType->code,
			"name" => $taxType->name,
		];
	}
}