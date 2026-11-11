<?php
namespace Rosetta\Services\Companies;

use Rosetta\Models\User;
interface CompanyServiceInterface
{
	public function create(User $user, Array $params);
	public function find(int $id);
	public function update(User $user,int $id, Array $params);
	public function all(Array $params = []);
	public function createBillingLocation(User $user, Array $params, $companyId);
	public function getAdvanceItems(Array $params = []);
	public function exportOrders(int $companyId, Array $params);//#814
	public function getCompanyTenants(int $companyId);
	public function getCompanyTenantStockpoints(int $companyId, String $tenant);
}