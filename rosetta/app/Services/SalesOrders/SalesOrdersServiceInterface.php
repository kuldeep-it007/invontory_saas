<?php
namespace Rosetta\Services\SalesOrders;

use Rosetta\Models\Company;
use Rosetta\Models\User;
interface SalesOrdersServiceInterface
{
	public function all($distributorId, Array $params = []);
	public function delete($distributorId, $orderId, $reason, User $loggedInUser);
	public function deleteLine($distributorId, $orderId, $lineId, User $user);
	public function updateLine($distributorId, $orderId, $lineId, Array $params, User $user);
	public function addPayment($companyId, $distributorId, $orderId, Array $params, User $user);
	public function ship($companyId, $distributorId, $orderId, Array $params, User $user);
	public function addReturns($distributorId, $dealerId, $lineId, $quantity, User $user);
	public function getReturns($distributorId, Array $params);
	public function manageReturns($distributorId, $returnItemId, Array $params, User $user);
	public function findOrder(User $loggedInUser, $orderId);
	public function updateLineForSuperStockist($superStockistId, $orderId, $lineId, Array $params,  User $user);
}