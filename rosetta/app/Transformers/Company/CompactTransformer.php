<?php
namespace Rosetta\Transformers\Company;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Company;
use Rosetta\Transformers\Region\IndexTransformer;
class CompactTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['region'];
	protected $defaultIncludes = ['region'];
	public function transform(Company $company)
	{
		return [
			"id" => $company->id,
			"name" => $company->name,
		];
	}
	public function includeRegion(Company $company)
	{
		return $this->item($company->region,new IndexTransformer);
	}
}