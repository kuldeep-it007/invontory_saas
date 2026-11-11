<?php
namespace Rosetta\Services\Distributor;

use Rosetta\Models\Company;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
Interface DistributorServiceInterface
{
	//public function getDistributors(Company $company);#894
	public function getDistributors(Company $company, Array $params, SuperStockist $SuperStockist=null);//#894
	public function create(User $user,Company $company,Array $params, SuperStockist $SuperStockist=null);
	public function update(User $user, Company $company, Distributor $distributor, Array $params);
	public function resetPermissions(User $user, Company $company, Distributor $distributor, Array $params);
	public function find(int $id);
	public function createShippingAddresses(int $companyId,int $distributorID,Array $params);
	public function updateShippingAddress(int $companyID, int $distributorID, $addressCode, Array $params);
	public function getShippingAddresses(int $companyId,int $distributorID);
	public function deleteAddress(int $companyID, int $distributorID, $addressCode);
	public function getInventory(int $distributorID, Array $params);
	public function getInventoryDetails(int $distributorID, int $productID);
	public function sync(User $user,Company $company, $nav_id);
	public function getDistributorInventoryDetails($distributorID, $productID, Array $params);
	public function getEngineers($distributorID);
	public function getMyBlockedStock($distributor, Array $params);
	public function unBlockMyStock($itemId, $distributorID);
	public function addStock($companyId, $distributorId, Array $params);
	public function exportStock(int $distributorId, Array $params);
	public function setPaymentType($distributor, Array $params);
	public function exportDistributors($company, $SuperStockist, $SuperStockists);//#813
	public function createDistributorUnderSuperStockist(User $user, $companyId, $SuperStockistId, Array $params);//#894
	public function findForSuperStockist(User $user,$companyID,$superStockistId,$distributorId);
	public function getDistributorInventory(int $distributorID, Array $params);
	public function getAllDistributorInventory(Array $params);
}