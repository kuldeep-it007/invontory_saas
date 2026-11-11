<?php
namespace Rosetta\Services\CoDistributor;

use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
interface CoDistributorDiscountServiceInterface
{
	public function getDiscount(Distributor $coDistributor,Product $product,Distributor $distributor);
	public function listCustomerDiscount(Array $params);
	public function delete($id);
	public function create(Array $params);
	public function update(Array $params, int $id);
}