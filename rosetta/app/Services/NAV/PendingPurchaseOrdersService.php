<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders_Fields;
use Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders_Filter;
use Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders_Service;
use Rosetta\NAV\WS\PendingPurchaseOrders\ReadMultiple;
use Rosetta\NAV\WS\PendingPurchaseOrders\getReadMultiple_Result;
use Rosetta\Services\NAV\PendingPurchaseOrdersServiceInterface;
use Rosetta\Traits\Nav;
class PendingPurchaseOrdersService implements PendingPurchaseOrdersServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Pending_Purchase_Orders';
	const WSDL = 'Pending_Purchase_Orders';
	public function getPendingPurchaseOrders(Company $company,$item_no,$location_codes)
	{
		$pendingPurchaseOrdersService = new Pending_Purchase_Orders_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);

		$readMultiple = new ReadMultiple(
			[
				new Pending_Purchase_Orders_Filter(Pending_Purchase_Orders_Fields::Item_No,$item_no),
				new Pending_Purchase_Orders_Filter(Pending_Purchase_Orders_Fields::Outstanding_Qty,'>=1'),//#752 changed filter to >= from >
				new Pending_Purchase_Orders_Filter(Pending_Purchase_Orders_Fields::Location_Code,$location_codes)
			],
			null,
			null
		);
		$result = $pendingPurchaseOrdersService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getPending_Purchase_Orders();
		if($result == null)
			return [];
		if(!is_array($result))
		{
			return [$result];
		}
		return $result;
	}
	public function getPOSpecificStock(Company $company,$item_no, $batch_no,$deductSO = false,$locationCode = null)
	{
		$pendingPurchaseOrdersService = new Pending_Purchase_Orders_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$filters = [
			new Pending_Purchase_Orders_Filter(Pending_Purchase_Orders_Fields::Item_No,$item_no),
			new Pending_Purchase_Orders_Filter(Pending_Purchase_Orders_Fields::Outstanding_Qty,'>=1'),//#759
			new Pending_Purchase_Orders_Filter(Pending_Purchase_Orders_Fields::Document_No, "'$batch_no'"),
			//new ILE_Filter(Pending_Purchase_Orders_Fields::Location_Code, 'WAREHOUSE')
		];
		if($locationCode !== null)
		{
			$filters[] = new Pending_Purchase_Orders_Filter(Pending_Purchase_Orders_Fields::Location_Code, $locationCode);
		}

		$readMultiple = new ReadMultiple(
			$filters,
			null,
			null
		);
		$stock  = $pendingPurchaseOrdersService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getPending_Purchase_Orders();
		$quantity = 0;
		if($stock)
		{
			if(is_array($stock))
			{
				$reservedQty = 0;
				foreach($stock as $s)
				{
					$reservedQty = (float)$s->getReserved_Qty();
					$quantity+= (float)$s->getOutstanding_Qty();
				}
				if($deductSO)
				{
					$quantity-=$reservedQty;
				}
				return $quantity;
			}
			if($deductSO)
				return (float)$stock->getRemaining_Quantity()-(float)$stock->getReserved_Qty();	
			else
				return (float)$stock->getRemaining_Quantity();	
		}
		
		return 0;
	}
}