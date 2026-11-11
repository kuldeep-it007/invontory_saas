<?php
namespace Rosetta\Transformers\SuperStockists;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Spatie\Permission\Models\Permission;

class AbilitiesTransformer extends TransformerAbstract
{
	public function transform(SuperStockist $superStockist)
	{
		$finalPermissions = [];
		$superStockistPemissions = $superStockist->getAllPermissions()->pluck('name')->toArray();
		foreach($superStockistPemissions as $perm)
		{
			$finalPermissions[$perm] = true;
		}//#807
		return $finalPermissions;
	}
}