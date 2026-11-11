<?php
namespace Rosetta\Transformers\User;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\User;
use Rosetta\Transformers\LoginResponseTransformer;
use Rosetta\Transformers\User\SelfTransformer;

class ImpersonateTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['login','self'];
	protected $defaultIncludes = ['login','self'];
	public function transform(User $user)
	{
		return [];
	}
	public function includeLogin(User $user)
	{
		return $this->item($user, new LoginResponseTransformer);
	}

	public function includeSelf(User $user)
	{
		return $this->item($user, new SelfTransformer);
	}
}