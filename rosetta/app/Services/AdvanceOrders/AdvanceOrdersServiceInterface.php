<?php
namespace Rosetta\Services\AdvanceOrders;

use Rosetta\Models\Company;
use Rosetta\Models\User;
interface AdvanceOrdersServiceInterface
{
	public function create(Company $company, $customer, Array $params, User $user);
	public function update(Company $company, $customer, $orderId);
	public function updateLine(Company $company, $customer, $customerType, $orderId, $lineId, User $user, Array $params);
	public function listCompanyAOs($companyId, Array $params = []);
	public function all($company, $customer, Array $params = []);
	public function getAdvanceItems($customer);
	//public function getAdvanceItems($customer, Array $params);
	public function refresh($UUId);
	public function deleteAdvanceItems(Array $params, $customer, User $loggedInUser, $customerType);
	public function deleteCustomerAdvanceItems($company, $customerType, Array $params, User $loggedInUser);
	public function addToAdvanceCart($customer, $company, Array $params);
	public function addMessage($company, $customer, $customerType, $itemId, Array $params, User $user);
	public function getMessages($company, $customer, $itemId, User $user);
	public function getLineMessages($orderId, $lineId);
	public function addLineMessage($company, $customer, $customerType, $orderId, $lineId, Array $params, User $user);
	public function updateAdvanceCartItem($company, $customer, $customerType, $itemId, Array $params, User $user);
	public function delete($company, $orderId, $reason, User $user, $customerId, $customerType);
	public function deleteLine($company, $customerType, $customer, $advanceOrder, $advanceLine, User $user);
	public function updateLineQtyDisc($company, $customer_type, $customer,  $advanceOrder, $advanceLine, Array $params,  User $user);
	public function createAdvanceBlanketOrder(Company $company, $customer, Array $params, User $user, $customerType,bool $online=false,Transaction $transaction = null);
	public function getBlockedAdvanceItems($customer);
}