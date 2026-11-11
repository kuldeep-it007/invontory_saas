<?php
namespace Rosetta\Transformers\User;

use Rosetta\Models\User;
use League\Fractal\TransformerAbstract;
use Spatie\Permission\Models\Permission;

class AbilitiesTransformer extends TransformerAbstract
{
	public function transform(User $user)
	{
		$allPermissions = Permission::all()->pluck('name')->toArray();
		$finalPermissions = [];
		foreach($allPermissions as $perm)
		{
			$finalPermissions[$perm] = false;
		}
		$userPemissions = $user->getAllPermissions()->pluck('name')->toArray();
		foreach($userPemissions as $perm)
		{
			$finalPermissions[$perm] = true;
		}
		$distributor = $user->distributor;
		if($distributor)
		{
			$distributorPemissions = $distributor->getAllPermissions()->pluck('name')->toArray();
			foreach($distributorPemissions as $perm)
			{
				$finalPermissions[$perm] = true;
			}
		}//#807
		return $finalPermissions;
		/*$permissions = $user->ability([],Permission::all()->pluck('name')->toArray(),['validate_all' => false,'return_type'=>'array']);
		return $permissions['permissions'];*/
		return [];
	}
}