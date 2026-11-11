<?php
namespace Rosetta\Services\Customer;

use Rosetta\Models\Company;
use Rosetta\Models\Customer;
use Rosetta\Models\User;
Interface CustomerServiceInterface
{
	public function createCustomer($companyId, $dealerId, Array $params, $user);
	public function updateCustomer($dealerId, $customerId, Array $params);
	public function getAllCustomers($dealerId);
	public function deleteCustomer($dealerId, $customerId);
}