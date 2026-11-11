<?php
namespace Rosetta\Services\CartItems;

use Rosetta\Models\Company;
interface CartItemsServiceInterface
{
	public function create(Array $params, $customer);
	public function update(Array $params, $customer);
	public function get($customer);
	public function block(Array $params, $customer);
	public function unBlock(Array $params, $customer);
	public function getBlockedItems($customer);
	public function deleteFromCart(Array $params, $customer);
	public function findAndDeleteCartItems($attribute, $company, $customer);
	public function unBlock_acrossTenants(Array $params, $companyId);
}