<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\ItemCategories\Create;
use Rosetta\NAV\WS\ItemCategories\Item_Categories;
use Rosetta\NAV\WS\ItemCategories\Item_Categories_Service;
use Rosetta\NAV\WS\ItemCategories\ReadMultiple;
use Rosetta\NAV\WS\ItemCategories\getItem_Categories;
use Rosetta\NAV\WS\ItemCategories\getReadMultiple_Result;
use Rosetta\Services\NAV\ItemCategoriesServiceInterface;
use Rosetta\Traits\Nav;
class ItemCategoriesService implements ItemCategoriesServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Item_Categories';
	const WSDL = 'Item_Categories';

	public function getItemCategories(Company $company)
	{
		$itemCategoriesService = new Item_Categories_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$readMultiple = new ReadMultiple(null,null,null);
		$itemCategories = $itemCategoriesService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getItem_Categories();
		if($itemCategories == null)
			return [];
		if(!is_array($itemCategories))
			return [$itemCategories];
		return $itemCategories;
	}
	public function createCategory($company, $params)
	{
		$itemCategoriesService = new Item_Categories_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$itemCategory = new Item_Categories;
		$itemCategory->setCode($params['code']);
		$itemCategory->setDescription($params['description']);
		$itemCategoryCreate = new Create($itemCategory);
		$itemCategoriesService->Create($itemCategoryCreate);
	}
}