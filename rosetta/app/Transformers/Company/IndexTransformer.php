<?php
namespace Rosetta\Transformers\Company;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Company;
use Rosetta\Transformers\RegionTransformer;
class IndexTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['region'];
	public function transform(Company $company)
	{
		return [
			"id" => $company->id,
			"name" => $company->name,
		];
	}
	public function includeRegion(Company $company)
	{
		return $this->item($company->region,new RegionTransformer);
	}
}