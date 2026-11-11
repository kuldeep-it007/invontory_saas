<?php
namespace Rosetta\Services\SSDiscount;

interface SSDiscountServiceInterface
{
	public function update(Array $params, int $id);
	public function create(Array $params);
	public function delete($id);
	public function listSSDiscount(Array $params);
	public function getSSDiscount($customer_type, $customer, $product, $super_stockist_id);
}