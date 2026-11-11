<?php

namespace Rosetta\Services\NAV;

use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\Product\Product;
use Rosetta\Models\Product\Stock;
use Rosetta\Models\PurchaseOrder\PurchaseLine;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\NAV\WS\PortalCU\InsertTrackingSpecification;
use Rosetta\NAV\WS\PortalCU\PortalCU;
use Rosetta\NAV\WS\SalesOrder\Create;
use Rosetta\NAV\WS\SalesOrder\Delete;
use Rosetta\NAV\WS\SalesOrder\Read;
use Rosetta\NAV\WS\SalesOrder\Read_Result;
use Rosetta\NAV\WS\SalesOrder\Sales_Order;
use Rosetta\NAV\WS\SalesOrder\Sales_Order_Line;
use Rosetta\NAV\WS\SalesOrder\Sales_Order_Service;
use Rosetta\NAV\WS\SalesOrder\Update;
use Rosetta\NAV\WS\SalesOrder\getSales_Order;
use Rosetta\NAV\WS\SalesOrder\setNo;
use Rosetta\NAV\WS\SalesOrder\setSales_Order_Line;
use Rosetta\NAV\WS\SalesOrder\setSell_to_Customer_No;
use Rosetta\Services\NAV\ILEServiceInterface;
use Rosetta\Services\NAV\PendingPurchaseOrdersServiceInterface;
use Rosetta\Services\NAV\PortalCUServiceInterface;
use Rosetta\Services\NAV\SalesOrderServiceInterface;
use Rosetta\Traits\Nav;
use SoapFault;

class SalesOrderService implements SalesOrderServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Sales_Order';
	const WSDL = 'Sales_Order';

	protected $ILEService;
	protected $pendingPurchaseOrdersService;

	public function __construct(ILEServiceInterface $ILEService, PendingPurchaseOrdersServiceInterface $pendingPurchaseOrdersService)
	{
		$this->ILEService = $ILEService;
		$this->pendingPurchaseOrdersService = $pendingPurchaseOrdersService;
	}

	public function create(Company $company, $customer, $params, $callBackUrl, $batch_nos, $location_code)
	{
		$salesOrder = new Sales_Order();
		$salesOrder->setSell_to_Customer_No($customer->nav_id);
		$salesOrder->setRemarks($params['special_request']);
		/*if($params['shipping_address']['code'] != null)
		{
			$salesOrder->setShip_to_Code($params['shipping_address']['code']);
		}
		else
		{*/
		$salesOrder->setShip_to_Address($params['shipping_address']['address']);
		$salesOrder->setShip_to_Address_2($params['shipping_address']['address_2']);
		$salesOrder->setShip_to_Post_Code($params['shipping_address']['post_code']);
		$salesOrder->setShip_to_City($params['shipping_address']['city']);
		$salesOrder->setShip_to_Country_Region_Code($params['shipping_address']['country_region_code']);
		$salesOrder->setGST_Ship_to_State_Code($params['shipping_address']['state']);
		if ($params['shipping_address']['phone_no'] != null)
			$contactDetails = $params['shipping_address']['contact'] . ' - ' . $params['shipping_address']['phone_no'];
		else
			$contactDetails = $params['shipping_address']['contact'];
		$salesOrder->setShip_to_Contact($contactDetails);
		$salesOrder->setShip_to_Name($params['shipping_address']['name']);
		//	$salesOrder->setShip_to_Contact($params['shipping_address']['contact']);	
		//}

		$salesOrder->setLocation_Code($location_code);
		$salesOrder->setTax_Liable(true); //#677

		//	$salesOrder->setCallbackUrl($callBackUrl);
		$OrderLines = $params['purchase_lines'];
		foreach ($OrderLines as $OrderLine) {
			$salesOrderLine = new Sales_Order_Line();
			$salesOrderLine->setType('Item');
			$product = Product::find($OrderLine['product_id']);
			$salesOrderLine->SetNo($product->nav_id);
			$salesOrderLine->setRate_Type($OrderLine['rate_type']);
			$salesOrderLine->setQuantity($OrderLine['quantity']);
			$unitPrice = (float)$OrderLine['unit_price'];
			$unitPrice = round($unitPrice - ($unitPrice * $OrderLine['discount'] / 100), 2);
			$salesOrderLine->setUnit_Price($unitPrice);
			//$salesOrderLine->setTax_Liable(true);//#677
			//	$salesOrderLine->setLocation_Code($location_code);
			$salesOrderLines[] = $salesOrderLine;
		}
		$salesOrder->setSalesLines($salesOrderLines);
		$createSalesOrder = new Create($salesOrder);
		$salesOrderService = new Sales_Order_Service(["location" => $this->getWSLocation($company) . SELF::LOCATION], SELF::WSDL);
		//dd('ppppppppppp', $createSalesOrder);
		$salesOrder = $salesOrderService->Create($createSalesOrder)->getSales_Order();

		$orderLines = $salesOrder->getSalesLines()->getSales_Order_Line();
		if (!is_array($orderLines)) {
			$orderLines = [$orderLines];
		}
		//multiple stock point #404
		/*	$salesOrder->setLocation_Code($location_code);
		foreach ($orderLines as $sales_Order_Line) 
		{
			$sales_Order_Line->setLocation_Code($location_code);
		}
		$this->update($company, $salesOrder);
*/
		$portalCU_Service = resolve('Rosetta\Services\NAV\PortalCUServiceInterface');
		foreach ($orderLines as $orderLine) {
			$no = $orderLine->getNo();
			$line_id = $orderLine->getLine_No();
			$batch_no = $batch_nos[$line_id];
			$quantity = $orderLine->getQuantity();
			$itemTrackingSpecification = $portalCU_Service->insertBatchNo($company, $salesOrder->getNo(), $line_id, $quantity, $batch_no);
		}
		$salesLinesService = resolve('Rosetta\Services\NAV\SalesLinesServiceInterface');
		$salesLine = $salesLinesService->getSalesLine($company, $orderLines[0]->getDocument_No(), $orderLines[0]->getLine_No());
		$salesLine->setSend_for_approval(true);
		$salesLinesService->update($company, $salesLine);
		$salesOrder = $this->refresh($company, $salesOrder->getNo());
		return $salesOrder;
	}
	public function createAO(Company $company, $customer, $params, $callBackUrl)
	{
		$salesOrder = new Sales_Order();
		$salesOrder->setSell_to_Customer_No($customer->nav_id);
		$salesOrder->setRemarks($params['special_request']);
		$salesOrder->setTax_Liable(true); //#677
		//$salesOrder->setShip_to_Code($params['shipping_address']['code']);
		//$salesOrder->setCallbackUrl($callBackUrl);
		$OrderLines = $params['advance_lines'];
		foreach ($OrderLines as $OrderLine) {
			$salesOrderLine = new Sales_Order_Line();
			$salesOrderLine->setType('Item');
			$product = Product::find($OrderLine['product_id']);
			$salesOrderLine->SetNo($product->nav_id);
			$salesOrderLine->setRate_Type($OrderLine['rate_type']);
			$salesOrderLine->setQuantity($OrderLine['quantity']);
			$unitPrice = (float)$OrderLine['unit_price'];
			$unitPrice = round($unitPrice - ($unitPrice * $OrderLine['discount'] / 100), 2);
			$salesOrderLine->setUnit_Price($unitPrice);
			//$salesOrderLine->setTax_Liable(true);//#677
			$salesOrderLine->setPurchasing_Code('SALES ORDE');
			$salesOrderLines[] = $salesOrderLine;
		}
		$salesOrder->setSalesLines($salesOrderLines);
		$createSalesOrder = new Create($salesOrder);
		$salesOrderService = new Sales_Order_Service(["location" => $this->getWSLocation($company) . SELF::LOCATION], SELF::WSDL);
		$salesOrder = $salesOrderService->Create($createSalesOrder)->getSales_Order();

		$orderLines = $salesOrder->getSalesLines()->getSales_Order_Line();
		if (!is_array($orderLines)) {
			$orderLines = [$orderLines];
		}
		$salesOrder->setLocation_Code('BACKORDERS');
		foreach ($orderLines as $sales_Order_Line) {
			$sales_Order_Line->setLocation_Code('BACKORDERS');
		}
		$this->update($company, $salesOrder);
		$salesLinesService = resolve('Rosetta\Services\NAV\SalesLinesServiceInterface');
		$salesLine = $salesLinesService->getSalesLine($company, $orderLines[0]->getDocument_No(), $orderLines[0]->getLine_No());
		$salesLine->setSend_for_approval(true);
		$salesLinesService->update($company, $salesLine);
		$salesOrder = $this->refresh($company, $salesOrder->getNo());
		return $salesOrder;
	}
	public function createIntransitOrder(Company $company, $customer, $params, $callBackUrl, $batch_nos, $location_code)
	{
		$salesOrder = new Sales_Order();
		$salesOrder->setSell_to_Customer_No($customer->nav_id);
		$salesOrder->setRemarks($params['special_request']);
		/*	if($params['shipping_address']['code'] != null)
		{
			$salesOrder->setShip_to_Code($params['shipping_address']['code']);
		}
		else
		{*/
		$salesOrder->setShip_to_Address($params['shipping_address']['address']);
		$salesOrder->setShip_to_Address_2($params['shipping_address']['address_2']);
		$salesOrder->setShip_to_Post_Code($params['shipping_address']['post_code']);
		$salesOrder->setShip_to_City($params['shipping_address']['city']);
		$salesOrder->setShip_to_Country_Region_Code($params['shipping_address']['country_region_code']);
		$salesOrder->setGST_Ship_to_State_Code($params['shipping_address']['state']);
		if ($params['shipping_address']['phone_no'] != null)
			$contactDetails = $params['shipping_address']['contact'] . ' - ' . $params['shipping_address']['phone_no'];
		else
			$contactDetails = $params['shipping_address']['contact'];
		$salesOrder->setShip_to_Contact($contactDetails);
		$salesOrder->setShip_to_Name($params['shipping_address']['name']);
		//	}

		$salesOrder->setTax_Liable(true); //#677
		$salesOrder->setLocation_Code($location_code);
		$OrderLines = $params['advance_lines'];
		foreach ($OrderLines as $OrderLine) {
			$salesOrderLine = new Sales_Order_Line();
			$salesOrderLine->setType('Item');
			$product = Product::find($OrderLine['product_id']);
			$salesOrderLine->SetNo($product->nav_id);
			$salesOrderLine->setRate_Type($OrderLine['rate_type']);
			$salesOrderLine->setQuantity($OrderLine['quantity']);
			$unitPrice = (float)$OrderLine['unit_price'];
			$unitPrice = round($unitPrice - ($unitPrice * $OrderLine['discount'] / 100), 2);
			$salesOrderLine->setUnit_Price($unitPrice);
			$salesOrderLine->setPurchasing_Code('SALES ORDE');
			$salesOrderLine->setPO_No($OrderLine['batch_no']);
			$salesOrderLine->setLocation_Code($location_code);
			$salesOrderLines[] = $salesOrderLine;
		}
		$salesOrder->setSalesLines($salesOrderLines);
		$createSalesOrder = new Create($salesOrder);
		$salesOrderService = new Sales_Order_Service(["location" => $this->getWSLocation($company) . SELF::LOCATION], SELF::WSDL);
		$salesOrder = $salesOrderService->Create($createSalesOrder)->getSales_Order();

		$orderLines = $salesOrder->getSalesLines()->getSales_Order_Line();
		if (!is_array($orderLines)) {
			$orderLines = [$orderLines];
		}
		/*	foreach ($orderLines as $sales_Order_Line) 
		{
			$sales_Order_Line->setLocation_Code('WAREHOUSE');
		}
		$this->update($company, $salesOrder);*/
		$salesLinesService = resolve('Rosetta\Services\NAV\SalesLinesServiceInterface');
		$salesLine = $salesLinesService->getSalesLine($company, $orderLines[0]->getDocument_No(), $orderLines[0]->getLine_No());
		$salesLine->setSend_for_approval(true);
		$salesLinesService->update($company, $salesLine);
		$salesOrder = $this->refresh($company, $salesOrder->getNo());
		return $salesOrder;
	}
	public function validateSalesOrder(Company $company, $lines, $customer, $order_from, $stock_point)
	{
		//$collection = collect($data['purchase_lines']);
		$collection = collect($lines);
		$groupedByProduct = $collection->groupBy('product_id');
		$grouped = [];
		$validated = [
			'valid' => [],
			'invalid' => [],
		];
		foreach ($groupedByProduct as $product_id => $purchase_lines) {
			$purchaseLinesSorted = $purchase_lines->groupBy('batch_no');
			$group = [
				'product' => Product::find($product_id),
				'lines' => [],
			];
			foreach ($purchaseLinesSorted as $lot_no => $pLines) {
				$group['lines'][$lot_no] = $pLines->sortByDesc('quantity');
			}
			$grouped[] = $group;
		}
		foreach ($grouped as $group) {
			$product = $group['product'];
			foreach ($group['lines'] as $lot_no => $lines) {
				$quantity = 0;
				$stock = round((float)$this->ILEService->getBatchSpecificStock($company, $product->nav_id, $lot_no, true, $stock_point), 2);
				$blockedQuntityQuery = BlockedItem::active()->where('product_id', '=', $product->id)
					->where('batch_no', '=', $lot_no)
					->where('stock_point_id', '=', $stock_point);
				if ($order_from == 'blocked') {
					$blockedQuntityQuery->where('blockable_id', '!=', $customer->id)
						->where('blockable_type', '=', get_class($customer));
				}

				$blockedQuntity = $blockedQuntityQuery->sum('order_quantity');
				$stock -= $blockedQuntity;
				foreach ($lines as $line) {
					if ($line['quantity'] <= ($stock - $quantity)) {
						$quantity += $line['quantity'];
						$validated['valid'][] = $line;
					} else {
						$validated['invalid'][] = $line;
					}
				}
			}
		}
		return $validated;
	}
	public function validateInTransitOrder(Company $company, $lines, $customer, $order_from, $stock_point)
	{
		$collection = collect($lines);
		$groupedByProduct = $collection->groupBy('product_id');
		$grouped = [];
		$validated = [
			'valid' => [],
			'invalid' => [],
		];
		foreach ($groupedByProduct as $product_id => $advance_lines) {
			$advanceLinesSorted = $advance_lines->groupBy('batch_no');
			$group = [
				'product' => Product::find($product_id),
				'lines' => [],
			];
			foreach ($advanceLinesSorted as $lot_no => $aLines) {
				$group['lines'][$lot_no] = $aLines->sortByDesc('quantity');
			}
			$grouped[] = $group;
		}
		foreach ($grouped as $group) {
			$product = $group['product'];
			foreach ($group['lines'] as $lot_no => $lines) {
				$quantity = 0;
				$stock = round((float)$this->pendingPurchaseOrdersService->getPOSpecificStock($company, $product->nav_id, $lot_no, true, $stock_point), 2);

				/*	$blockedQuntityQuery = BlockedItem::active()->where('product_id','=',$product->id)
															->where('batch_no', '=', $lot_no)
															->where('stock_point_id', '=', $stock_point);
				if($order_from == 'blocked')
				{
					$blockedQuntityQuery->where('blockable_id','!=',$customer->id)
						->where('blockable_type','=',get_class($customer));
				}

				$blockedQuntity = $blockedQuntityQuery->sum('order_quantity');
				$stock -= $blockedQuntity;*/
				foreach ($lines as $line) {
					if ($line['quantity'] <= ($stock - $quantity)) {
						$quantity += $line['quantity'];
						$validated['valid'][] = $line;
					} else {
						$validated['invalid'][] = $line;
					}
				}
			}
		}
		return $validated;
	}
	public function validatePurchaseLines(Company $company, array $lines, $customer, $order_from, $stock_point = '')
	{
		
		if ($customer->super_stockist_id) {
			$customerDetail = SuperStockist::find($customer->super_stockist_id);
		} elseif ($stock_point) {
			$customerDetail = Distributor::find($stock_point);
		} elseif ($customer->distributor_id) {
			$customerDetail = Distributor::find($customer->distributor_id);
		} else {
			$customerDetail = $customer;
		}
		
		//$collection = collect($data['purchase_lines']);
		$collection = collect($lines);
		$groupedByProduct = $collection->groupBy('product_id');
		$grouped = [];
		$validated = [
			'valid' => [],
			'invalid' => [],
		];
		foreach ($groupedByProduct as $product_id => $purchase_lines) {
			$purchaseLinesSorted = $purchase_lines->groupBy('batch_no');
			$group = [
				'product' => Product::find($product_id),
				'lines' => [],
			];
			foreach ($purchaseLinesSorted as $lot_no => $pLines) {
				$group['lines'][$lot_no] = $pLines->sortByDesc('quantity');
			}
			$grouped[] = $group;
		}
		foreach ($grouped as $group) {
			$product = $group['product'];
			foreach ($group['lines'] as $lot_no => $lines) {
				$quantity = 0;
				$stock = $customerDetail->stocks->where('product_id', '=', $product->id)
					->where('batch_no', '=', $lot_no)
					->sum('quantity');

				$blockedQuntityQuery = BlockedItem::active()->where('product_id', '=', $product->id)
					->where('batch_no', '=', $lot_no)
					->where('distributor_id', '=', $customer->distributor_id);
				if ($order_from == 'blocked') {
					$blockedQuntityQuery->where('blockable_id', '!=', $customer->id)
						->where('blockable_type', '=', get_class($customer));
				}

				$blockedQuntity = $blockedQuntityQuery->sum('order_quantity');

				$stock -= $blockedQuntity;

				foreach ($lines as $line) {
					if ($line['quantity'] <= ($stock - $quantity)) {
						$quantity += $line['quantity'];
						$validated['valid'][] = $line;
					} else {
						$validated['invalid'][] = $line;
					}
				}
			}
		}
		return $validated;
	}
	public function refresh(Company $company, $navOrderNumber)
	{
		$salesOrderService = new Sales_Order_Service(["location" => $this->getWSLocation($company) . SELF::LOCATION], SELF::WSDL);
		$readSalesOrder = new Read($navOrderNumber);
		$salesOrder = $salesOrderService->Read($readSalesOrder);
		if ($salesOrder)
			return $salesOrder->getSales_Order();
		return null;
	}
	public function update(Company $company, Sales_Order $salesOrder)
	{
		$update = new Update($salesOrder);
		$salesOrderService = new Sales_Order_Service(["location" => $this->getWSLocation($company) . SELF::LOCATION], SELF::WSDL);
		return $salesOrderService->update($update)->getSales_Order();
	}
	public function delete($company, $navOrderNumber, $orderStatus, $navLineNo)
	{
		$salesOrderService = new Sales_Order_Service(["location" => $this->getWSLocation($company) . SELF::LOCATION], SELF::WSDL);
		$salesOrder = $this->refresh($company, $navOrderNumber);
		if ($salesOrder) {
			if ($salesOrder->getStatus() == 'Open' || $salesOrder->getStatus() == 'Pending_Approval') {
				$navKey = $salesOrder->getKey();
				$deleteSalesOrder = new Delete($navKey);
				return $status = $salesOrderService->Delete($deleteSalesOrder)->getDelete_Result();
			} elseif ($salesOrder->getStatus() == 'Released') {
				$salesLinesService = resolve('Rosetta\Services\NAV\SalesLinesServiceInterface');
				$salesLine = $salesLinesService->getSalesLine($company, $navOrderNumber, $navLineNo);
				$salesLine->setReopen(true);
				$salesOrder = $salesLinesService->update($company, $salesLine);
				$salesOrder = $this->refresh($company, $navOrderNumber);
				$navKey = $salesOrder->getKey();
				$deleteSalesOrder = new Delete($navKey);
				return $status = $salesOrderService->Delete($deleteSalesOrder)->getDelete_Result();
			}
		}
		return null;
	}
	public function deleteSalesLine($company, $navOrderNo, $navLineNo, $batch_no)
	{
		$portalCU_Service = resolve('Rosetta\Services\NAV\PortalCUServiceInterface');
		$deleteSalesLine = $portalCU_Service->deleteSalesLine($company, $navOrderNo, $navLineNo, $batch_no);
		return true;
	}
	public function updateSalesLine($company, $navKey, $navOrderNo, $navLineNo, $quantity, $unitPrice, $batch_no)
	{
		$portalCU_Service = resolve('Rosetta\Services\NAV\PortalCUServiceInterface');
		$updateSalesLine = $portalCU_Service->updateSalesLine($company, $navOrderNo, $navLineNo, $quantity, $unitPrice, $batch_no);
		return true;
	}
}
