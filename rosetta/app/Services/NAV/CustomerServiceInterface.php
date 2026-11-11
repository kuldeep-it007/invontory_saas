<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\RetailCustomer;
interface CustomerServiceInterface
{
	public function createDistributor(Company $company,Array $params);
	public function updateDistributor(Company $company, Distributor $distributor,Array $params, $superStockist);
	public function createDealer(Company $company, Array $params,Distributor $distributor=null);
	public function updateDealer(Company $company, Dealer $dealer,Array $params, $distributor);
	public function createRetailCustomer(Company $company,Array $params);
	public function updateRetailCustomer(Company $company, RetailCustomer $retailCustomer,Array $params);
	public function get(Company $company,String $navId);
}