<?php
namespace Rosetta\Services\CustomerDiscount;

interface CustomerDiscountServiceInterface
{
	public function update(Array $params, int $id);
	public function create(Array $params);
	public function delete($id);
	public function listCustomerDiscount(Array $params);
	public function getCustomerDiscount($customer_type, $customer, $product, $distributor_id);
}