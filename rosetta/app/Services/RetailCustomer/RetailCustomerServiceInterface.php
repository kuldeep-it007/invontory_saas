<?php
namespace Rosetta\Services\RetailCustomer;

use Rosetta\Models\Company;
use Rosetta\Models\RetailCustomer;
use Rosetta\Models\User;
Interface RetailCustomerServiceInterface
{
	public function getRetailCustomers(Company $company);
	public function create(User $user,Company $company,Array $params);
	public function update(User $user, Company $company, RetailCustomer $retailCustomer, Array $params);
	public function find(int $id);
	public function createShippingAddresses(int $companyId,int $retailCustomerID,Array $params);
	public function updateShippingAddress(int $companyID, int $retailCustomerID, $addressCode, Array $params);
	public function getShippingAddresses(int $companyId,int $retailCustomerID);
	public function deleteAddress(int $companyID, int $retailCustomerID, $addressCode);
}