<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\Inventory\Inventory_Fields;
use Rosetta\NAV\WS\Inventory\Inventory_Filter;
use Rosetta\NAV\WS\Inventory\Inventory_Service;
use Rosetta\NAV\WS\Inventory\ReadMultiple;
use Rosetta\Services\NAV\InventoryServiceInterface;
use Rosetta\Traits\Nav;
class InventoryService implements InventoryServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Inventory';
	const WSDL = 'Inventory';
	public function getInventory(Company $company)
	{
		$inventoryService = new Inventory_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$inventoryFilter = new Inventory_Filter(Inventory_Fields::Inventory,'> 0');
		$readMultiple = new ReadMultiple($inventoryFilter,null,null);
		
		
		$inventory = $inventoryService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getInventory();
		return $inventory;
	}
}