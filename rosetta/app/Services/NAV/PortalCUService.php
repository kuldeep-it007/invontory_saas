<?php
namespace Rosetta\Services\NAV;
use Rosetta\Models\Company;
use Rosetta\NAV\WS\PortalCU\DeleteSOLine;
use Rosetta\NAV\WS\PortalCU\EditSO;
use Rosetta\NAV\WS\PortalCU\InsertTrackingSpecification;
use Rosetta\NAV\WS\PortalCU\InsertTrackingSpecification_Result;
use Rosetta\NAV\WS\PortalCU\PortalCU;
use Rosetta\Services\NAV\PortalCUServiceInterface;
use Rosetta\Traits\Nav;
class PortalCUService implements PortalCUServiceInterface
{
	use Nav;
	const LOCATION = 'Codeunit/PortalCU';
	const WSDL = 'PortalCU';
	public function insertBatchNo(Company $company, $no, $line_id, $quantity, $batch_no)
	{
		$PortalCUService = new PortalCU(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);

		$insertTrackingSpecification = new InsertTrackingSpecification($no, $line_id, $quantity, "", $batch_no, false);
		$insertTrackingSpecification->setDocNo($no);
		$insertTrackingSpecification->setDocLineNo($line_id);
		$insertTrackingSpecification->setQty($quantity);
		//$insertTrackingSpecification->setSerialNo("");
		$insertTrackingSpecification->setLotNo($batch_no);
		$insertTrackingSpecification->setReserve(false);
		$result = $PortalCUService->InsertTrackingSpecification($insertTrackingSpecification);
		return $result;
	}
	public function updateSalesLine(Company $company, $no, $navLineNo, $quantity, $unit_price, $batch_no)
	{
		$PortalCUService = new PortalCU(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$editSO = new EditSO($no, $navLineNo, $quantity, $batch_no, $unit_price, "");
		$editSO->setDocNo($no);
		$editSO->setDocLineNo($navLineNo);
		$editSO->setQty($quantity);
		$editSO->setUnitprice($unit_price);
		if($batch_no != null)
		{
			$editSO->setLotNo($batch_no);
			$editSO->setNewlot($batch_no);
		}
		$result = $PortalCUService->EditSO($editSO);
		return $result;
	}
	public function deleteSalesLine(Company $company, $no, $navLineNo, $batch_no)
	{
		$PortalCUService = new PortalCU(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$deleteSOLine = new DeleteSOLine($no, $navLineNo, $batch_no);
		$deleteSOLine->setDocNo($no);
		$deleteSOLine->setDocLineNo($navLineNo);
		$deleteSOLine->setLotNo($batch_no);
		$result = $PortalCUService->DeleteSOLine($deleteSOLine);
		return $result;
	}
}