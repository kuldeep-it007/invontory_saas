<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\User;
class AuthorTransformer extends TransformerAbstract
{
	public function transform(User $user)
	{
		return [
			"id" => $user->id,
			"name" => $user->name,
			"email" => $user->email,
		];
	}
}