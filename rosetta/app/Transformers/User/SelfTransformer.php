<?php
namespace Rosetta\Transformers\User;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\User;
use Rosetta\Transformers\Company\IndexTransformer as CompanyTransformer;
use Rosetta\Transformers\Dealers\IndexTransformer as DealerTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer as DistributorTransformer;
use Rosetta\Transformers\SuperStockists\IndexTransformer as SuperStockistTransformer;
use Rosetta\Transformers\User\AbilitiesTransformer;
use Rosetta\Transformers\User\NoticeTransformer;
class SelfTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['abilities','distributor', 'dealer', 'company','notice','superStockist'];
	public function transform(User $user)
	{
		$data = [
			"id"         => $user->id,
			"name"       => $user->name,
			"phone"      => $user->phone,
			"email"      => $user->email,
			"created_at" => $user->created_at->format('U'),
			"updated_at" => $user->created_at->format('U'),
			"change_password" => (bool)$user->change_password,
		];
		if($user->distributor)
		{
			$data['cust_id'] = $user->distributor->nav_id;
			$data['type'] = 'Distributor';
			$data['id'] = $user->distributor->id;
			$data['display_name'] = (!empty($user->distributor->display_name))?$user->distributor->display_name:$user->distributor->name;
		}
		if($user->superStockist)
		{
			$data['cust_id'] = $user->superStockist->nav_id;
			$data['type'] = 'superStockist';
			$data['id'] = $user->superStockist->id;
			$data['display_name'] = (!empty($user->superStockist->display_name))?$user->superStockist->display_name:$user->superStockist->name;
		}
		if($user->dealer)
		{
			$data['cust_id'] = $user->dealer->nav_id;
			$data['type'] = 'dealer';
			$data['id'] = $user->dealer->id;
			$data['display_name'] = null;
		}
		return $data;
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
	public function includeNotice(User $user)
	{
		return $this->item($user,new NoticeTransformer);
	}
}