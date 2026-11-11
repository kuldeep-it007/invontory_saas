<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\InventoryPostingGroup\Inventory_Posting_Groups_Service;
use Rosetta\NAV\WS\InventoryPostingGroup\ReadMultiple;
use Rosetta\NAV\WS\InventoryPostingGroup\getInventory_Posting_Groups;
use Rosetta\NAV\WS\InventoryPostingGroup\getReadMultiple_Result;
use Rosetta\Services\NAV\InventoryPosingGroupServiceInterface;
use Rosetta\Traits\Nav;
class InventoryPosingGroupService implements InventoryPosingGroupServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Inventory_Posting_Groups';
	const WSDL = 'Inventory_Posting_Group';

	public function getInventoryPostingGroups(Company $company)
	{
		$inventoryPostingGroupService = new Inventory_Posting_Groups_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$readMultiple = new ReadMultiple(null,null,null);
		$inventoryPostingGroups = $inventoryPostingGroupService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getInventory_Posting_Groups();
		if($inventoryPostingGroups == null)
			return [];
		if(!is_array($inventoryPostingGroups))
			return [$inventoryPostingGroups];
		return $inventoryPostingGroups;
	}
}