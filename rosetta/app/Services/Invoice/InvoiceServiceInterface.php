<?php
namespace Rosetta\Services\Invoice;

use Rosetta\Models\User;

Interface InvoiceServiceInterface
{
	public function dealerBlanketOrders(Array $params = []);
	public function dealerBlanketOrder($dealerBOId);
	public function mark_printed($companyId, $orderId, Array $params, User $user);
}