<?php

namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;

interface SalesOrderServiceInterface
{
	public function create(Company $company, $customer, $params, $callBackUrl, $batch_nos, $location_code);
	public function createAO(Company $company, $customer, $params, $callBackUrl);
	public function refresh(Company $company, $navOrderNumber);
	public function delete($company, $navOrderNumber, $orderStatus, $navLineNo);
	/**
	 * validate if stock is available at company
	 *
	 * @param Company $company
	 * @param Array $lines
	 * @param [type] $customer
	 * @param [type] $order_from
	 * @return Array
	 */
	public function validatePurchaseLines(Company $company, array $lines, $customer, $order_from, $stock_point = '');
	public function validateSalesOrder(Company $company, $lines, $customer, $order_from, $stock_point);
	public function deleteSalesLine($company, $navOrderNo, $navLineNo, $batch_no);
	public function updateSalesLine($company, $navKey, $navOrderNo, $navLineNo, $quantity, $unitPrice, $batch_no);
}
