<?php
namespace Rosetta\Services\StockPoints;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rosetta\Exceptions\StockPoint\StockPointExistsException;
use Rosetta\Models\Company;
use Rosetta\Models\Company\CustomerStockPoint;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Services\StockPoints\StockPointServiceInterface;


class StockPointService implements StockPointServiceInterface
{
	public function all(User $user,$companyID)
	{
		$company = Company::find($companyID);
		return $company->stock_points;
	}
	public function getCustomers(User $user,$companyID,$stockPointID,Array $params)
	{
		$company = Company::find($companyID);
		$stockPoint = StockPoint::where('id','=',$stockPointID)
			->where('company_id','=',$companyID)
			->first();

		$query = $stockPoint->customer_stock_points();
		if(array_has($params,'customer_type'))
		{
			if($params['customer_type'] == 'DEALER')
			{
				$query->where('customerable_type','=',Dealer::class);
			}
			if($params['customer_type'] == 'DISTRIBUTO')
			{
				$query->where('customerable_type','=',Distributor::class);
			}
			if($params['customer_type'] == 'SUPER_STOCKIST')
			{
				$query->where('customerable_type','=',SuperStockist::class);
			}
			if(array_has($params,'customer_id'))
			{
				$query->where('customerable_id','=',$params['customer_id']);
			}
		}

		if(array_has($params,'page'))
		{
			return $query->paginate($params['per_page']);
		}
		return $query->get();
	}
	public function create(User $user, $companyID, Array $params)
	{
		$company = Company::find($companyID);
		$stockPoint = StockPoint::where('company_id','=',$companyID)
			->where('code','=',$params['stock_point'])
			->first();
		if($stockPoint)
		{
			throw new StockPointExistsException;
		}
		$stockPoint = new StockPoint;
		$stockPoint->code = $params['stock_point'];
		$stockPoint->name = $params['stock_point'];
		$stockPoint->company()->associate($company);
		$stockPoint->save();
		$customerStockPoint = new CustomerStockPoint;
		$customerStockPoint->stock_point()->associate($stockPoint);
		$customerStockPoint->save();
		$company->stock_points()->save($customerStockPoint);
		return $customerStockPoint;
	}
	public function deleteCustomer(User $user,$companyID,$stockPointID,$customerType,$customerID)
	{
		$company = Company::find($companyID);
		$customer = null;
		if($customerType == 'dealers')
		{
			$customer = Dealer::find($customerID);
		}
		else
		{
			$customer = Distributor::find($customerID);
		}
		$stockPoint = $customer->stock_points()->where('stock_point_id','=',$stockPointID)->first();
		if($stockPoint)
		{
			$stockPoint->delete();
		}
		else
		{
			return new ModelNotFoundException;
		}
	}
	public function addCustomerToStockPoint(User $user,$companyID,$stockPointID,$customerType,$customerID)
	{
		$customer = null;
		if($customerType == 'distributors')
		{
			$customer = Distributor::find($customerID);
		}
		else if($customerType == 'super_stockists')
		{
			$customer = SuperStockist::find($customerID);
		}
		else
		{
			$customer = Dealer::find($customerID);
		}
		$customerStockPoint = $customer->stock_points()->where('stock_point_id','=',$stockPointID)->first();
		if($customerStockPoint)
			throw new StockPointExistsException;
		$stockPoint = StockPoint::find($stockPointID);
		$customerStockPoint = new CustomerStockPoint;
		$customerStockPoint->stock_point()->associate($stockPoint);
		$customerStockPoint->save();
		$customer->stock_points()->save($customerStockPoint);
		return $customerStockPoint;

	}
}