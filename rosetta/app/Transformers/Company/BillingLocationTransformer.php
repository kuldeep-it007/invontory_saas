<?php
namespace Rosetta\Transformers\Company;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Company\BillingLocation;
class BillingLocationTransformer extends TransformerAbstract
{
	public function transform(BillingLocation $location)
	{
		return [
			"id" => $location->id,
			"code" => $location->code,
			"name" => $location->name,
		];
	}
}