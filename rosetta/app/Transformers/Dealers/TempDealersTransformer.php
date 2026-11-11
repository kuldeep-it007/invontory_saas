<?php 
namespace Rosetta\Transformers\Dealers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\TempDealer;
use Rosetta\Transformers\Company\CompanyTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer;

class TempDealersTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['company', 'distributor'];
	protected $defaultIncludes = ['company', 'distributor'];
	public function transform(TempDealer $dealer)
	{
		return [
			"id" => $dealer->id,
			"name" => $dealer->name,
			"status" => $dealer->status,
			"rejection_reason" => $dealer->rejection_reason,
			"created_at" => $dealer->created_at->format('U'),
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
