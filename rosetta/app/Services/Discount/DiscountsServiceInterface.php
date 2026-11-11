<?php
namespace Rosetta\Services\Discount;

interface DiscountsServiceInterface
{
	public function update(Array $params, int $id);
	public function create(Array $params);
	public function delete($id);
	public function listDiscounts(Array $params);
	public function getDiscount($customer_type, $customer, $product);
}