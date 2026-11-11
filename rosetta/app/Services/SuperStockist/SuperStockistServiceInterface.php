<?php
namespace Rosetta\Services\SuperStockist;

use Rosetta\Models\User;
use Rosetta\Models\Company;
use Illuminate\Http\UploadedFile;
use Rosetta\Models\SuperStockist\SuperStockist;

interface SuperStockistServiceInterface
{
	public function getSuperStockists(Company $company,Array $params);
	public function sync(User $user,Company $company, $nav_id);
	public function create(User $user,Company $company,Array $params);
	public function find(int $id);
	public function createShippingAddresses(int $companyId,int $superStockistId,Array $params);
	public function updateShippingAddress(int $companyID, int $superStockistId, $addressCode, Array $params);
	public function getShippingAddresses(int $companyId,int $superStockistId);
	public function deleteAddress(int $companyID, int $superStockistId, $addressCode);
	public function getInventory(int $superStockistId, Array $params);
	public function adjustInventory(int $superStockistId,int $inventoryID,string $operation,float $quantity);
	public function addStock($companyId, $superStockistId, Array $params);
	public function deleteStock($superStockistId, $stockId);
	public function exportStock(int $superStockistId, Array $params);
	public function getSSInventory(int $superStockistId, Array $params);
	public function getMyBlockedStock(SuperStockist $superStockist, Array $params);
	public function importStock($superStockist, UploadedFile $file);
	public function unBlockMyStock($itemId, $superStockistId);
	public function getAllSSInventory(Array $params);
}