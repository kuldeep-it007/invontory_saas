<?php
namespace Rosetta\Transformers\Distributors;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;
use Rosetta\Models\User;
use Spatie\Permission\Models\Permission;

class AbilitiesTransformer extends TransformerAbstract
{
	public function transform(Distributor $distributor)
	{
		$finalPermissions = [];
		$distributorPemissions = $distributor->getAllPermissions()->pluck('name')->toArray();
		foreach($distributorPemissions as $perm)
		{
			$finalPermissions[$perm] = true;
		}//#807
		return $finalPermissions;
	}
}