<?php
namespace Rosetta\Transformers\Company;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Company;
use Rosetta\Transformers\AuthorTransformer;
use Rosetta\Transformers\RegionTransformer;
class DashboardTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['author','region'];
	protected $defaultIncludes = ['author'];
	public function transform(Company $company)
	{
		return [
			"id" => $company->id,
			"name" => $company->name,
			"database" => $company->database,
			"distributors" => $company->distributors()->count(),
			"dealers" => $company->dealers()->count(),
			"retailCustomers" => $company->retailCustomers()->count(),
		];
	}
	public function includeAuthor(Company $company)
	{
		return $this->item($company->author,new AuthorTransformer);
	}
	public function includeRegion(Company $company)
	{
		return $this->item($company->region,new RegionTransformer);
	}
}