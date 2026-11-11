<?php
namespace Rosetta\Observers;

use Carbon\Carbon;
use Rosetta\Models\AdvanceOrder\AdvanceOrder;

class AdvanceOrderObserver
{
	public function created(AdvanceOrder $order)
	{
		$year = Carbon::now()->format('Y');
		$id = str_pad(strval($order->id),6,'0',STR_PAD_LEFT);
		$order->order_number = "AO/{$year}/{$id}";
		$order->save();
	}
}