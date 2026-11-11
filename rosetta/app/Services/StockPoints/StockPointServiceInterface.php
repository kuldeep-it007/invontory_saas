<?php
namespace Rosetta\Services\StockPoints;

use Rosetta\Models\User;
Interface StockPointServiceInterface
{
	public function all(User $user,$companyID);
	public function create(User $user, $companyID, Array $params);
	public function deleteCustomer(User $user,$companyID,$stockPointID,$customerType,$customerID);
	public function addCustomerToStockPoint(User $user,$companyID,$stockPointID,$customerType,$customerID);
}