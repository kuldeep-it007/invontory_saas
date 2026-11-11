<?php
namespace Rosetta\Services\NAV;
use Rosetta\Models\Company;
use Rosetta\NAV\WS\PSI\PSI_Fields;
use Rosetta\NAV\WS\PSI\PSI_Filter;
use Rosetta\NAV\WS\PSI\PSI_Service;
use Rosetta\NAV\WS\PSI\ReadMultiple;
use Rosetta\NAV\WS\PSI\getReadMultiple_Result;
use Rosetta\Services\NAV\PSIServiceInterface;
use Rosetta\Traits\Nav;
class PSIService implements PSIServiceInterface
{
	use Nav;
	const LOCATION = 'Page/PSI';
	const WSDL = 'PSI';
	public function getSO(Company $company, $no)
	{
		$psiService = new PSI_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$readMultiple = new ReadMultiple(
			[
				new PSI_Filter(PSI_Fields::Order_No,$no)
			],
			null,
			null
		);
		$psi = $psiService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getPSI();
		if($psi)
		return true;
	}
	public function getSI(Company $company, $no)
	{
		$psiService = new PSI_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$readMultiple = new ReadMultiple(
			[
				new PSI_Filter(PSI_Fields::Order_No,$no)
			],
			null,
			null
		);
		$psi = $psiService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getPSI();
		return $psi;
	}
}