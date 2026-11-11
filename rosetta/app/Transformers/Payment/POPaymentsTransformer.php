<?php
namespace Rosetta\Transformers\Payment;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;

class POPaymentsTransformer extends TransformerAbstract
{
	public function transform(PurchaseOrder $purchaseOrder)//#766
	{
		$data = [
			"stock_point_name" => $purchaseOrder->stock_point->name,
			"si_number" => $purchaseOrder->nav_invoice_number,
			"amount" => $purchaseOrder->Amount,
			"paid_amount" => $purchaseOrder->paid_amount,
			"remaining_amount" => $purchaseOrder->remaining_amount,
		];
		return $data;
	}
}