<?php
namespace Rosetta\Services\BlanketOrders;

Interface BlanketOrdersServiceInterface
{
	public function all($company, $customer, Array $params = []);
	public function listCompanyBlanketOrders($companyId, Array $params = []);
	public function listCoDistributorBlanketOrders($companyId, Array $params = []);
	public function distributorBlanketOrders($distributorId, Array $params = []);
	public function superStockistBlanketOrders($superStockistId, Array $params = []);
}