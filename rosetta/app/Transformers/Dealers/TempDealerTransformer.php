<?php 
namespace Rosetta\Transformers\Dealers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\TempDealer;
use Rosetta\Transformers\Company\CompanyTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer;

class TempDealerTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['company', 'distributor'];
	protected $defaultIncludes = ['company', 'distributor'];
	public function transform(TempDealer $dealer)
	{
		return [
			"id" => $dealer->id,
			"name" => $dealer->name,
			"data" => json_decode($dealer->text)
		];
	}
	public function includeCompany(TempDealer $dealer)
	{
		return $this->item($dealer->company,new CompanyTransformer);
	}
	public function includeDistributor(TempDealer $dealer)
	{
		return $this->item($dealer->distributor,new IndexTransformer);
	}
}
