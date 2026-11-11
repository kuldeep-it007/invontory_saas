<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\Models\Product\Product;
use Rosetta\NAV\WS\ItemCard\SalesPrice\Create;
use Rosetta\NAV\WS\ItemCard\SalesPrice\ReadMultiple;
use Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices;
use Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices_Fields;
use Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices_Filter;
use Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices_Service;
use Rosetta\NAV\WS\ItemCard\SalesPrice\Update;
use Rosetta\Services\NAV\SalesPriceServiceInterface;
use Rosetta\Traits\Nav;
use SoapFault;
class SalesPriceService implements SalesPriceServiceInterface
{
	use Nav;
	const LOCATION = 'Page/SalesPrices';
	const WSDL = 'SalesPrices';
	public function getProductPrices(Company $company,Product $product)
	{
		$salesPriceService = new SalesPrices_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		//
		$readMultiple = new ReadMultiple(
			[
				new SalesPrices_Filter(SalesPrices_Fields::Item_No,$product->nav_id),
				new SalesPrices_Filter(SalesPrices_Fields::Sales_Type,'All_Customers'),
			],
			null,
			null
		);
		try{
			$salesPrices = $salesPriceService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getSalesPrices();
		}
		catch(SoapFault $e)
		{
			$salesPrices = null;
		}
		if($salesPrices == null)
			return [];
		if(!is_array($salesPrices))
			return [$salesPrices];
		return $salesPrices;
	}
	public function addProductPrice(Company $company,Product $product,float $unitPrice)
	{
		$salesPrice = new SalesPrices;
		$salesPrice->setSales_Type('All_Customers');
		$salesPrice->setItem_No($product->nav_id);
		$salesPrice->setUnit_Price($unitPrice);
		$create = new Create($salesPrice);
		$salesPriceService = new SalesPrices_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		return $salesPriceService->Create($create)->getSalesPrices();
	}
	public function updateProductPrice(Company $company,SalesPrices $salesPrice,float $unitPrice)
	{
		$salesPrice->setUnit_Price($unitPrice);
		$update = new Update($salesPrice);
		$salesPriceService = new SalesPrices_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		return $salesPriceService->Update($update)->getSalesPrices();
	}
}