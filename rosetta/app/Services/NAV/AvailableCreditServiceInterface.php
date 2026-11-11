<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;

interface AvailableCreditServiceInterface
{
	public function chechAvailableCredit(Company $company, $customer);
}