<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;

interface PaymentStatusServiceInterface
{
	public function getPaymentStatus(Company $company, $si_no);
}