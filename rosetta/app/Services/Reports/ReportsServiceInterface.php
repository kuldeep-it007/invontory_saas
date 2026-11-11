<?php
namespace Rosetta\Services\Reports;

interface ReportsServiceInterface
{
	public function getDealersWithOrders($distributor, $params);
	public function getDealerOrdersAmount($dealer, $start_date, $end_date);
	public function getOnBehalfCnt($dealer, $start_date, $end_date);
	public function getAppOrdersCnt($dealer, $start_date, $end_date);
	public function getCustomerWiseOrderDetails($dealer, $start_date, $end_date);
}