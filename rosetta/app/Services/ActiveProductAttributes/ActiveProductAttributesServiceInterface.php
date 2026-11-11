<?php
namespace Rosetta\Services\ActiveProductAttributes;
interface ActiveProductAttributesServiceInterface
{
	public function list(Array $params = [], $company, $customerType, $customerId);
	public function update(Array $params = [], $company, $attributeId);
	public function delete(Array $params = [], $company, $attributeId);
	public function deleteAttributesWithColours(Array $params = [], $company, $attributeId);
	public function add(Array $params = [], $company);
	public function loadNew($companyId, $customerType, $customerId, $category, $collection, $colour);
	public function load($companyId, $customerType, $customerId, $parentDistributorId, $parentSuperStockistId);
	//public function find($company, $customer, $category_code, $collection_code, $colour_code);
	public function find($attribute, $customer);
	public function getActiveCategoriesForCustomer($company, $customerType, $customerId);
	public function getActiveCollectionsForCustomer($company, $customerType, $customerId,$category);
	public function getActiveColoursForCustomer($company, $customerType, $customerId,$category,$collection);
}