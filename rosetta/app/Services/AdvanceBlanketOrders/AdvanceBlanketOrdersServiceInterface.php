<?php
namespace Rosetta\Services\AdvanceBlanketOrders;

Interface AdvanceBlanketOrdersServiceInterface
{
	public function all($company, $customer, Array $params = []);
	public function listCompanyBlanketOrders($companyId, Array $params = []);
	public function distributorBlanketOrders($distributorId, Array $params = []);
}
