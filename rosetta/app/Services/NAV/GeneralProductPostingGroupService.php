<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\GeneralProductPostingGroup\Gen_Product_Posting_Groups_Service;
use Rosetta\NAV\WS\GeneralProductPostingGroup\ReadMultiple;
use Rosetta\NAV\WS\GeneralProductPostingGroup\getGen_Product_Posting_Groups;
use Rosetta\NAV\WS\GeneralProductPostingGroup\getReadMultiple_Result;
use Rosetta\Services\NAV\GeneralProductPostingGroupServiceInterface;
use Rosetta\Traits\Nav;
class GeneralProductPostingGroupService implements GeneralProductPostingGroupServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Gen_Product_Posting_Groups';
	const WSDL = 'Gen_Product_Posting_Groups';

	public function getGeneralProductPostingGroups(Company $company)
	{
		$getGeneralProductPostingGroupService = new Gen_Product_Posting_Groups_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$readMultiple = new ReadMultiple(null,null,null);
		$generalProductPostingGroups = $getGeneralProductPostingGroupService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getGen_Product_Posting_Groups();
		if($generalProductPostingGroups == null)
			return [];
		if(!is_array($generalProductPostingGroups))
			return [$generalProductPostingGroups];
		return $generalProductPostingGroups;
	}
}