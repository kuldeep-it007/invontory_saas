<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
interface ItemCategoriesServiceInterface
{
	public function createCategory($company, $params);
}