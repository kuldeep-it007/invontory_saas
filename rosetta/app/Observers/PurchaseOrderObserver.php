<?php
namespace Rosetta\Observers;

use Carbon\Carbon;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;

class PurchaseOrderObserver
{
	public function created(PurchaseOrder $order)
	{
		$year = Carbon::now()->format('Y');
		$id = str_pad(strval($order->id),6,'0',STR_PAD_LEFT);
		$order->order_number = "PO/{$year}/{$id}";
		$order->save();
	}
}