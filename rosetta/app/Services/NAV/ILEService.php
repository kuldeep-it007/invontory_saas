<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\ILE\ILE_Fields;
use Rosetta\NAV\WS\ILE\ILE_Filter;
use Rosetta\NAV\WS\ILE\ILE_Service;
use Rosetta\NAV\WS\ILE\ReadMultiple;
use Rosetta\Services\NAV\ILEServiceInterface;
use Rosetta\Traits\Nav;
class ILEService implements ILEServiceInterface
{
	use Nav;
	const LOCATION = 'Page/ILE';
	const WSDL = 'ILE';
	public function getLedgerEntries(Company $company,$item_no, $location_codes)
	{
		$ileService = new ILE_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$readMultiple = new ReadMultiple(
			[
				new ILE_Filter(ILE_Fields::Item_No,$item_no),
				new ILE_Filter(ILE_Fields::Remaining_Quantity,'>=1'),//#752 changed filter to >= from >
				new ILE_Filter(ILE_Fields::Location_Code,$location_codes)
			],
			null,
			null
		);
		$result = $ileService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getILE();
		if($result == null)
			return [];
		if(!is_array($result))
		{
			return [$result];
		}
		return $result;
	}
	public function getBatchSpecificStock(Company $company,$item_no, $batch_no,$deductSO = false,$locationCode = null)
	{
		$ileService = new ILE_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$filters = [
			new ILE_Filter(ILE_Fields::Item_No,$item_no),
			new ILE_Filter(ILE_Fields::Remaining_Quantity,'>=1'),//#759
			new ILE_Filter(ILE_Fields::Lot_No, "'$batch_no'"),
			//new ILE_Filter(ILE_Fields::Location_Code, 'WAREHOUSE')
		];
		if($locationCode !== null)
		{
			$filters[] = new ILE_Filter(ILE_Fields::Location_Code, $locationCode);
		}

		$readMultiple = new ReadMultiple(
			$filters,
			null,
			null
		);
		$stock  = $ileService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getILE();
		$quantity = 0;
		if($stock)
		{
			if(is_array($stock))
			{
				$reservedQty = 0;
				foreach($stock as $s)
				{
					$reservedQty = (float)$s->getReserved_Qty();
					$quantity+= (float)$s->getRemaining_Quantity();
				}
				if($deductSO)
				{
					$quantity+=$reservedQty;
				}
				return $quantity;
			}
			if($deductSO)
				return (float)$stock->getRemaining_Quantity()+(float)$stock->getReserved_Qty();	
			else
				return (float)$stock->getRemaining_Quantity();	
		}
		
		return 0;
	}
}