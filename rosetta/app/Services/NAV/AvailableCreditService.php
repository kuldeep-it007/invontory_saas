<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\AvailableCredit\Available_Credit_Fields;
use Rosetta\NAV\WS\AvailableCredit\Available_Credit_Filter;
use Rosetta\NAV\WS\AvailableCredit\Available_Credit_Service;
use Rosetta\NAV\WS\AvailableCredit\ReadMultiple;
use Rosetta\Services\NAV\AvailableCreditServiceInterface;
use Rosetta\Traits\Nav;
class AvailableCreditService implements AvailableCreditServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Available_Credit';
	const WSDL = 'Available_Credit';
	public function chechAvailableCredit(Company $company, $customer)
	{
		$availableCreditService = new Available_Credit_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$availableCreditFilter = new Available_Credit_Filter(Available_Credit_Fields::No, $customer);
		$readMultiple = new ReadMultiple($availableCreditFilter,null,null);
		
		$availableCredit = $availableCreditService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getAvailable_Credit();
		//dd($availableCredit->getCalcAvailableCreditUI());
		return $availableCredit;
	}
}