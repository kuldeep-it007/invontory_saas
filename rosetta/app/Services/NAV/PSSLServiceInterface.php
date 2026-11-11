<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;

interface PSSLServiceInterface
{
	public function getShipment(Company $company, $no, $lineNo);
}