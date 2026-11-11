<?php
namespace Rosetta\Services\Dashboard;

use Rosetta\Models\Company;

interface DashboardServiceInterface
{
	public function getAvailableCredit(Company $company, $customer);
}