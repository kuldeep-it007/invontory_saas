<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\VATProductPostingGroup\ReadMultiple;
use Rosetta\NAV\WS\VATProductPostingGroup\VAT_Product_Posting_Groups_Service;
use Rosetta\NAV\WS\VATProductPostingGroup\getReadMultiple_Result;
use Rosetta\NAV\WS\VATProductPostingGroup\getVAT_Product_Posting_Groups;
use Rosetta\Services\NAV\VATProductPostingGroupServiceInterface;
use Rosetta\Traits\Nav;
class VATProductPostingGroupService implements VATProductPostingGroupServiceInterface
{
	use Nav;
	const LOCATION = 'Page/VAT_Product_Posting_Groups';
	const WSDL = 'VAT_Product_Posting_Groups';

	public function getVatProductPostingGroups(Company $company)
	{
		$getVatProductPostingGroupService = new VAT_Product_Posting_Groups_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$readMultiple = new ReadMultiple(null,null,null);
		$vatProductPostingGroups = $getVatProductPostingGroupService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getVAT_Product_Posting_Groups();
		if($vatProductPostingGroups == null)
			return [];
		if(!is_array($vatProductPostingGroups))
			return [$vatProductPostingGroups];
		return $vatProductPostingGroups;
	}
}