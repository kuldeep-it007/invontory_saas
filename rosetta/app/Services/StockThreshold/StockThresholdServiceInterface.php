<?php
namespace Rosetta\Services\StockThreshold;

use Illuminate\Http\UploadedFile;
use Rosetta\Models\User;

interface StockThresholdServiceInterface
{
	public function create($product_id, $company_id, $stock_point_id, $tenant, $threshold, $lc, $uom);
	public function getStockThreshold(Array $params = []);
	public function sync($company_id, User $user, $ip = null);
	public function exportStock(int $companyId, Array $params);
	public function deleteStock(User $user,int $company_id,Array $ids);
	public function updateThreshold(User $user, $companyId, $thresholdId, Array $params);
	public function importThreshold($company_id,$tenant,$stockpointId,UploadedFile $file,User $user = null, $ip = null);
}