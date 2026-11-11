<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\SalesLines\Delete;
use Rosetta\NAV\WS\SalesLines\Read;
use Rosetta\NAV\WS\SalesLines\SalesLines;
use Rosetta\NAV\WS\SalesLines\SalesLines_Service;
use Rosetta\NAV\WS\SalesLines\Update;
use Rosetta\Services\NAV\SalesLinesServiceInterface;
use Rosetta\Traits\Nav;
class SalesLinesService implements SalesLinesServiceInterface
{
	const LOCATION = 'Page/SalesLines';
	const WSDL = 'SalesLines';
	use Nav;
	public function getSalesLine(Company $company,$documentNo,$LineNo)
	{
		$read = new Read($documentNo,$LineNo);
		$salesLinesService = new SalesLines_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		return $salesLinesService->Read($read)->getSalesLines();
	}
	public function update(Company $company,SalesLines $salesLine)
	{
		$salesLinesService = new SalesLines_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$salesLinesService->update(new Update($salesLine));
	}
	public function delete(Company $company,SalesLines $salesLine)
	{
		$salesLinesService = new SalesLines_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$deleteSalesLine = new Delete($salesLine);
		//$salesLinesService->delete(new Delete($salesLine));
		return $status = $salesLinesService->Delete($deleteSalesLine)->getDelete_Result();
	}
}