<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
interface ItemCardServiceInterface
{
	public function update(Company $company,Array $params);
	public function create(Company $company,Array $params);
	public function getInventory(Company $company,Array $params);
}