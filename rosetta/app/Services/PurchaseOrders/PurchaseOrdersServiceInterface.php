<?php
namespace Rosetta\Services\PurchaseOrders;

use Rosetta\Models\Company;
use Rosetta\Models\Transaction;
use Rosetta\Models\User;
interface PurchaseOrdersServiceInterface
{
	public function create(Company $company, $customer, $customerType, Array $params, User $user, $company_lines, $blanketOrder, $stockPointId,bool $online = false);
	public function createDudOrder(Company $company, $customer, Array $params, User $user, $distributor_lines, $blanketOrderId, $stockPointId);
	public function update(Company $company, $customer, $orderId);
	public function refresh($UUId);
	public function all($company, $customer, Array $params = []);
	public function delete($company, $orderId, $reason, User $user, $customerId, $customerType);
	public function listCompanyPOs($companyId, Array $params = []);
	public function deleteLine($company, $customerType, $customer, $purchaseOrder, $purchaseLine, User $user);
	//public function updateLine($company, $customerType, $customer, $orderId, $lineId, Array $params);
	public function updateLine($company, $customerType, $customer, $purchaseOrder, $purchaseLine, Array $params,  User $user);
	public function print_dc($orderId);
	/**
	 * Create Blanket Order.
	 *
	 * @param Company $company
	 * @param Distributor/Dealer $customer
	 * @param Array $params
	 * @param User $user
	 * @param String $customerType
	 * @param boolean $online
	 * @return Array
	 */
	public function createBlanketOrder(Company $company, $customer, Array $params, User $user, $customerType,bool $online=false,Transaction $transation=null);

	/**
	 * Start online payment process for cart/blocked items. 
	 *
	 * @param User $user
	 * @param Company $company
	 * @param Distributor|Dealer $customer
	 * @param Array $params
	 * @return \Rosetta\Services\PaymentGateway\Models\CreateResponse
	 * @throws InvalidLinesPresentException
	 */
	public function startOnlinePaymentOrder(User $user,Company $company,$customer, Array $params);
	public function findOrder(User $loggedInUser, $orderId);
	public function find($company, $customer, $orderId);
	public function updateShipment($company, $customer, $orderId,Array $params);

	/**
	 * Calculate how payment can be split
	 *
	 * @param \Rosetta\Models\Distributor | \Rosetta\Models\Dealer $customer
	 * @param float $amount
	 * @return void
	 */
	public function calculatePayment($customer, float $amount);

}