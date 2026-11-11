<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\ItemCardIntransit\Create;
use Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit;
use Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit_Fields;
use Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit_Filter;
use Rosetta\NAV\WS\ItemCardIntransit\Item_Card_Intransit_Service;
use Rosetta\NAV\WS\ItemCardIntransit\Read;
use Rosetta\NAV\WS\ItemCardIntransit\ReadMultiple;
use Rosetta\NAV\WS\ItemCardIntransit\Update;
use Rosetta\NAV\WS\ItemCardIntransit\getItem_Card_Intransit;
use Rosetta\Services\NAV\ItemCardIntransitServiceInterface;
use Rosetta\Traits\Nav;
class ItemCardIntransitService implements ItemCardIntransitServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Item_Card_Intransit';
	const WSDL = 'Item_Card_Intransit';
	public function getProducts(Company $company)
	{
		$itemCardIntransitService = new Item_Card_Intransit_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$readMultiple = new ReadMultiple(null,null,null);
		return $itemCardIntransitService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getItem_Card_Intransit();
	}
	public function get(Company $company,String $navId)
	{
		$itemCardIntransitService = new Item_Card_Intransit_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		return $itemCardIntransitService->Read(new Read($navId))->getItem_Card_Intransit();
	}
	public function create(Company $company,Array $params)
	{

		$itemCardIntransitService = new Item_Card_Intransit_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);

		$itemCard = new Item_Card_Intransit;

		$itemCard->setDescription($params['name']);

		if(array_has($params, 'description'))
			$itemCard->setDescription_2($params['description']);

		if(array_has($params, 'search_description'))
			$itemCard->setSearch_Description($params['search_description']);

		$itemCard->setItem_Category_Code($params['category']);
		$itemCard->setProduct_Group_Code($params['collection']);
		if(array_has($params, 'colour'))
			$itemCard->setColour($params['colour']);

		
		/*if(array_has($params, 'vat_prod_posting_group'))
			$itemCard->setVAT_Prod_Posting_Group($params['vat_prod_posting_group']);
		if(array_has($params, 'reordering_policy'))
			$itemCard->setReordering_Policy($params['reordering_policy']);*/
		
		$itemCard->setItem_Tracking_Code($params['tracking_code']);
		$itemCard->setBase_Unit_of_Measure($params['base_unit_of_measure']);
		$itemCard->setPurch_Unit_of_Measure($params['purchase_unit_of_measure']);
		$itemCard->setSales_Unit_of_Measure($params['sales_unit_of_measure']);
		$itemCard->setTechnical_Spec($params['techspecs']);
		if(array_has($params, 'gen_prod_posting_group'))//#758
			$itemCard->setGen_Prod_Posting_Group($params['gen_prod_posting_group']);//#736
		if(array_has($params, 'inv_posting_group'))//#758
			$itemCard->setInventory_Posting_Group($params['inv_posting_group']);//#736
		if(array_has($params, 'vat_prod_posting_group'))//#758
			$itemCard->setVAT_Prod_Posting_Group($params['vat_prod_posting_group']);//#736
		//$itemCard->setReorder_Point($params['reorder_point']);
		//$itemCard->setReorder_Quantity($params['reorder_quantity']);
		//$itemCard->setMaximum_Inventory($params['maximum_inventory']);
		//$itemCard->setMinimum_Order_Quantity($params['minimum_order_quantity']);
		//$itemCard->setMaximum_Order_Quantity($params['maximum_order_quantity']);
		//$itemCard->setOrder_Multiple($params['order_multiple']);
		//$itemCard->setTax_Group_Code($params['tax_group_code']);
		//$itemCard->setUnit_Price($params['unit_price']);
		$itemCardCreate = new Create($itemCard);
		return $itemCardIntransitService->Create($itemCardCreate)->getItem_Card_Intransit();
	}

	public function update(Company $company,Array $params)
	{
		$itemCardIntransitService = new Item_Card_Intransit_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$itemCard = $itemCardIntransitService->Read(new Read($params['nav_id']))->getItem_Card_Intransit();
		$itemCard->setDescription($params['name']);

		if(array_has($params, 'description'))
			$itemCard->setDescription_2($params['description']);

		if(array_has($params, 'search_description'))
			$itemCard->setSearch_Description($params['search_description']);

		$itemCard->setItem_Category_Code($params['category']);
		$itemCard->setProduct_Group_Code($params['collection']);
		if(array_has($params, 'colour'))
			$itemCard->setColour($params['colour']);
		$itemCard->setBase_Unit_of_Measure($params['base_unit_of_measure']);
		$itemCard->setPurch_Unit_of_Measure($params['purchase_unit_of_measure']);
		$itemCard->setSales_Unit_of_Measure($params['sales_unit_of_measure']);
		$itemCard->setTechnical_Spec($params['techspecs']);
		$itemCardUpdate = new Update($itemCard);
		return $itemCardIntransitService->Update($itemCardUpdate)->getItem_Card_Intransit();
	}

	public function getInventory(Company $company,Array $params)
	{

		$itemCardIntransitService = new Item_Card_Intransit_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$filters = [
			//new Item_Card_Intransit_Filter(Item_Card_Intransit_Fields::Inventory,'>0')
		];
		if(array_has($params,'quantity'))
		{
			$filters[] = new Item_Card_Intransit_Filter(Item_Card_Intransit_Fields::Inventory,urldecode($params['quantity']));
		}
		if(array_has($params,'category'))
		{
			$filters[] = new Item_Card_Intransit_Filter(Item_Card_Intransit_Fields::Item_Category_Code,"'".urldecode($params['category'])."'");
		}
		if(array_has($params,'collection'))
		{
			$filters[] = new Item_Card_Intransit_Filter(Item_Card_Intransit_Fields::Product_Group_Code,"'".urldecode($params['collection'])."'");
		}
		if(array_has($params,'colour'))
		{
			$filters[] = new Item_Card_Intransit_Filter(Item_Card_Intransit_Fields::Colour,"'".urldecode($params['colour'])."'");
		}
		$readMultiple = new ReadMultiple($filters,null,null);
		$data = $itemCardIntransitService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getItem_Card_Intransit();
		if($data == null)
			return [];
		if(!is_array($data))
			return [$data];
		return $data;
	}
}