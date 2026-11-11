<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\ProductGroups\Create;
use Rosetta\NAV\WS\ProductGroups\Product_Groups_Item_Cat;
use Rosetta\NAV\WS\ProductGroups\Product_Groups_Item_Cat_Service;
use Rosetta\NAV\WS\ProductGroups\ReadMultiple;
use Rosetta\NAV\WS\ProductGroups\getProduct_Groups_Item_Cat;
use Rosetta\NAV\WS\ProductGroups\getReadMultiple_Result;
use Rosetta\Services\NAV\ProductGroupsServiceInterface;
use Rosetta\Traits\Nav;
class ProductGroupsService implements ProductGroupsServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Product_Groups_Item_Cat';
	const WSDL = 'Product_Groups_Item_Cat';

	public function getCollectionCodes(Company $company)
	{
		$productGroupService = new Product_Groups_Item_Cat_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$readMultiple = new ReadMultiple(null,null,null);
		$collectionCodes = $productGroupService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getProduct_Groups_Item_Cat();
		if($collectionCodes == null)
			return [];
		if(!is_array($collectionCodes))
			return [$collectionCodes];
		return $collectionCodes;
	}
	public function createCollection($company, $params, $category_code)
	{
		$productGroupService = new Product_Groups_Item_Cat_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$collectionCode = new Product_Groups_Item_Cat;
		$collectionCode->setCode($params['code']);
		$collectionCode->setDescription($params['description']);
		$collectionCode->setItem_Category_Code($category_code);
		$collectionCodeCreate = new Create($collectionCode);
		$productGroupService->Create($collectionCodeCreate);
	}
}