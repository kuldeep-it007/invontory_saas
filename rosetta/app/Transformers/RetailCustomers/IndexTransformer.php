<?php
namespace Rosetta\Transformers\RetailCustomers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\RetailCustomer;
use Rosetta\Transformers\Company\IndexTransformer as CompanyTransformer;
class IndexTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['cart','company'];
	protected $defaultIncludes = ['company'];
	public function transform(RetailCustomer $retailCustomer)
	{
		return [
			"id" => $retailCustomer->id,
			"nav_id" => $retailCustomer->nav_id,
			"name" => $retailCustomer->name,
		];
	}	
	public function includeCompany(RetailCustomer $retailCustomer)
	{
		return $this->item($retailCustomer->company,new CompanyTransformer);
	}
}