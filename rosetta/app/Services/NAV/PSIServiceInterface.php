<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;

interface PSIServiceInterface
{
	public function getSO(Company $company, $no);
	public function getSI(Company $company, $no);
}