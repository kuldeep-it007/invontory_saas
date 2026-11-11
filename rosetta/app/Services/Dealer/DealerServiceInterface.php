<?php
namespace Rosetta\Services\Dealer;

use Rosetta\Models\Company;
use Rosetta\Models\Distributor;
use Rosetta\Models\User;

Interface DealerServiceInterface
{
	public function create(User $user, Array $params, Company $company, Distributor $distributor);
	public function update(User $user, $company,$dealer,Array $params);
	public function getDealers(Company $company, Distributor $distributor, Array $params);
	//public function createTempDealer(User $user, $companyId, $distributorId, Array $params);
	public function createTempDealer(User $user, Array $params);
	public function getTempDealers($company, $distributorId, Array $params);
	public function getRejectedDealers($company, $distributorId, Array $params);
	public function findTempDealer($company, $id);
	public function createDealerUnderDistributor(User $user, $companyId, $distributorId, Array $params);
	public function getInventory(int $dealerID, Array $params);
	public function getInventoryDetails(int $dealerID, int $productID);
	public function createShippingAddresses(int $companyId,int $dealerId,Array $params);
	public function updateShippingAddress(int $companyID, int $dealerId, $addressCode, Array $params);
	public function getShippingAddresses(int $companyId,int $dealerId);
	public function deleteAddress(int $companyID, int $dealerId, $addressCode);
	public function find(int $id);
	public function sync(User $user,Company $company, $nav_id);
	public function findForDistributor(User $user,$companyID,$distributorID,$dealerID);
	public function rejectDealer(User $user, $company, $dealer, Array $params);
	public function createCustomers($companyId, $dealerId,Array $params, User $user);
	public function updateCustomer($dealerId, $addressCode, Array $params);
	public function getCustomers($dealerId);
	public function deleteCustomer($dealerId, $addressCode);
	public function createEngineers($companyId, $dealerId,Array $params, User $user);
	public function getEngineers($dealerId);
	public function setPaymentType($dealer, Array $params);
	public function exportDealers($company, $distributor, $distributors);//#813
	public function updateViaWhatsApp($company,$dealer,Array $params);
	public function createDealerFromWhatsApp(Array $params, Company $company, Distributor $distributor);
}