<?php
namespace Rosetta\Services\Depot;

use Rosetta\Models\Company;
use Rosetta\Models\User;
interface DepotItemsServiceInterface
{
	public function block(Array $params, $company);
	public function blockAdvanceItem(Array $params, $company);
	public function unBlock($UUId, $company);
	public function listDepotBlanketOrders(Array $params, $companyId);
	public function listInterDepotBlanketOrders(Array $params, $companyId);
	public function findCurrentDepotOrder($orderId);
	public function findInterDepotOrder($orderId, $tenant);
	public function getInterDepotOrderPaymentStatus($tenant, $companyId, $blanketOrderId);
	public function listDepotInvoices(Array $params = []);
	public function listInterDepotInvoices(Array $params, $companyId);
	public function findInterDepotInvoice($dealerBOId, $tenant);
	public function findCurrentDepotInvoice($dealerBOId);
	//public function mark_printed($companyId, $orderId, Array $params, User $user);
	public function current_depot_mark_printed($orderId, Array $params, User $user);
	public function inter_depot_mark_printed($orderId, Array $params, User $user, $tenant);
	public function CancelInterDepotOrder(Array $params, $companyID, $distributorId, $orderId, $tenant);
	public function CancelInterDepotPurchaseOrder(Array $params, $companyID, $customerType,$customerID, $orderId, $tenant);
	public function DeleteInterDepotPurchaseLine(Array $params, $companyID, $customerType,$customerID, $orderId, $lineId, $tenant);
	public function DeleteInterDepotSalesLine($companyID, $distributorId, $orderId, $lineId, $tenant);
	public function UpdateInterDepotPurchaseLine(Array $params, $companyID, $customerType, $customerID, $orderId, $lineId, $tenant);
	public function print_dc($orderId, $tenant);
	public function unBlockAdvanceItem($UUId, $company);
}