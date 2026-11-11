<?php
namespace Rosetta\Services\Dashboard;

use Rosetta\Models\Company;
use Rosetta\Services\Dashboard\DashboardServiceInterface;
use Rosetta\Services\NAV\AvailableCreditServiceInterface;

class DashboardService implements DashboardServiceInterface
{
	protected $availableCreditService;
	public function __construct(AvailableCreditServiceInterface $availableCreditService)
	{
		$this->availableCreditService = $availableCreditService;
	}
	public function getAvailableCredit(Company $company, $customer)
	{
		$availableCredit = $this->availableCreditService->chechAvailableCredit($company, $customer->nav_id);
		$balanceDue = $availableCredit->getBalance_Due_LCY();
		$creditLimit = $availableCredit->getCalcAvailableCreditUI();
		return  [
            "balanceDue" => $balanceDue,
            "creditLimit" => $creditLimit
        ];
	}
}