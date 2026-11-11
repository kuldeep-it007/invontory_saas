<?php
namespace Rosetta\Services\Product;
interface ProductsServiceInterface
{
	public function getProducts(Array $params = []);
	public function getActiveProducts(Array $params = [], $company);
	public function getUserActiveProducts(Array $params = [], $company, $customer);
	public function find(int $id);
	public function update(int $id, Array $params);
	public function create(Array $params);
	public function addCollectionPrice($company, $cagtegory_code, $collectioon_code,$price);
	public function updateStatus(int $id, Array $params);
}