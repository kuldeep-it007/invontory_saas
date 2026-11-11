<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
interface ItemCardIntransitServiceInterface
{
	public function update(Company $company,Array $params);
	public function create(Company $company,Array $params);
	public function get(Company $company,String $navId);
}