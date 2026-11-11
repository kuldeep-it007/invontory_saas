<?php
namespace Rosetta\Transformers\User;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\User;
use Rosetta\Transformers\Company\IndexTransformer as CompanyTransformer;
use Rosetta\Transformers\Dealers\IndexTransformer as DealerTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer as DistributorTransformer;
use Rosetta\Transformers\SuperStockists\IndexTransformer as SuperStockistTransformer;
use Rosetta\Transformers\User\AbilitiesTransformer;

class IndexTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['distributor', 'dealer', 'company', 'superStockist'];
	public function transform(User $user)
	{
		return [
			"id"         => $user->id,
			"name"       => $user->name,
			"phone"      => $user->phone,
			"email"      => $user->email,
			"active"     => (bool)$user->active,
		];
	}
	public function includeAbilities(User $user)
	{
		return $this->item($user, new AbilitiesTransformer);
	}
	public function includeDistributor(User $user)
	{
		if($user->distributor)
			return $this->item($user->distributor,new DistributorTransformer);
		return null;
	}
	public function includeSuperStockist(User $user)
	{
		if($user->superStockist)
			return $this->item($user->superStockist,new SuperStockistTransformer);
		return null;
	}
	public function includeDealer(User $user)
	{
		if($user->dealer)
			return $this->item($user->dealer,new DealerTransformer);
		return null;
	}
	public function includeCompany(User $user)
	{
		if($user->company)
			return $this->item($user->company,new CompanyTransformer);
		return null;
	}
}