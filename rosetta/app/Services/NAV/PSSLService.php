<?php
namespace Rosetta\Services\NAV;
use Rosetta\Models\Company;
use Rosetta\NAV\WS\PSSLines\PSS_Fields;
use Rosetta\NAV\WS\PSSLines\PSS_Filter;
use Rosetta\NAV\WS\PSSLines\PSS_Service;
use Rosetta\NAV\WS\PSSLines\ReadMultiple;
use Rosetta\NAV\WS\PSSLines\getPSS;
use Rosetta\NAV\WS\PSSLines\getReadMultiple_Result;
use Rosetta\Services\NAV\PSSLServiceInterface;
use Rosetta\Traits\Nav;
class PSSLService implements PSSLServiceInterface
{
	use Nav;
	const LOCATION = 'Page/PSS';
	const WSDL = 'PSS_Lines';
	public function getShipment(Company $company, $no, $lineNo)
	{
		$psslService = new PSS_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$readMultiple = new ReadMultiple(
			[
				new PSS_Filter(PSS_Fields::Order_No,$no),
				new PSS_Filter(PSS_Fields::Order_Line_No,$lineNo),
			],
			null,
			null
		);
		//dd($psslService->ReadMultiple($readMultiple)->getReadMultiple_Result());
		return $psslService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getPSS();
	}
}