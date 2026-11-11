<?php
namespace Rosetta\Transformers\CoDistributors;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;

class CompactTransformer extends TransformerAbstract
{
	public function transform(Distributor $distributor)
	{
		return [
			"id"     => $distributor->id,
			'name'   => (!empty($distributor->display_name))?$distributor->display_name:$distributor->name,
			'nav_id' => $distributor->nav_id,
			'overdue_hold_days' => $distributor->pivot->overdue_hold_days,
			'overdue_balance' => (float)$distributor->pivot->overdue_balance,
			'credit_limit' => (float)$distributor->pivot->credit_limit,
		];
	}
}