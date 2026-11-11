<?php
namespace Rosetta\Services\SSPurchaseOrders;

use Rosetta\Models\Company;
use Rosetta\Models\User;
use Rosetta\Models\Transaction;

interface SSPurchaseOrdersServiceInterface
{
	public function createBlanketOrder(Company $company, $customer, Array $params, User $user, $customerType);
	public function create(Company $company, $customer, $customerType, Array $params, User $user, $company_lines, $blanketOrder, $stockPointId);
	public function createDistToSSOrder(Company $company, $customer, Array $params, User $user, $ss_lines, $dudBlanketOrder, $stockPointId);
	public function validatePurchaseLineswithSSStock(Company $company,Array $lines,$customer, $order_from);
	public function createBlanketOrderForDistributor(Company $company, $customer, Array $params, User $user, $customerType);
}