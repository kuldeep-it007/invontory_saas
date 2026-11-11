<?php
namespace Rosetta\Services\RetailCustomer;

use Illuminate\Support\Facades\DB;
use Rosetta\Models\Company;
use Rosetta\Models\RetailCustomer;
use Rosetta\Models\User;
use Rosetta\Services\NAV\CustomerServiceInterface;
use Rosetta\Services\NAV\STAServiceInterface;
use Rosetta\Services\RetailCustomer\RetailCustomerServiceInterface;

class RetailCustomerService implements RetailCustomerServiceInterface
{
	protected $customerService;
	protected $staService;
	public function __construct(CustomerServiceInterface $customerService,STAServiceInterface $staService)
	{
		$this->customerService = $customerService;
		$this->staService = $staService;
	}
	public function getRetailCustomers(Company $company,Array $params= [])
	{
	/*	if(array_has($params,'page'))
		{
			return $company->retailCustomers()->paginate($params['per_page'],['*'],'',$params['page']);
		}
		return $company->retailCustomers;


*/
		if(array_has($params,'page'))
		{
			$retailCustomers = $company->retailCustomers()->when($params, function ($query) use ($params){
				if(array_has($params, 'customer_name'))
				{
					//$query->where('name', '=', $params['customer_name']);
					$query->where('name', 'like', '%' . $params['customer_name'] . '%');
				}
				if(array_has($params,'customer_id'))
				{
					$query->where('nav_id', '=', $params['customer_id']);
				}
			})->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		return $retailCustomers;
	}
	public function create(User $user,Company $company,Array $params)
	{
		$navRetailCustomer = $this->customerService->createRetailCustomer($company, $params);
		$retailCustomer = new RetailCustomer;
		$retailCustomer->name = $params['name'];
		$retailCustomer->post_code = $params['zipcode'];
		$retailCustomer->state_code = $params['state'];
		$retailCustomer->city_code = $params['city'];
		$retailCustomer->nav_id = $navRetailCustomer->getNo();
		$retailCustomer->company()->associate($company);
		$retailCustomer->author()->associate($user);
		$retailCustomer->save();
		$retailCustomer->setCustomerCard($navRetailCustomer);
		return $retailCustomer;
	}
	public function update(User $user, Company $company, RetailCustomer $retailCustomer, Array $params)
	{
		$navRetailCustomer = $this->customerService->updateRetailCustomer($company, $retailCustomer,$params);
		if(array_has($params, 'name'))
		{
			$retailCustomer->name = $params['name'];
			$retailCustomer->save();
		}
		$retailCustomer->setCustomerCard($navRetailCustomer);
		return $retailCustomer;
	}
	public function find(int $id)
	{
		$retailCustomer = RetailCustomer::find($id);
		if(!$retailCustomer)
		{
			throw new ModelNotFoundException;
		}
		return $retailCustomer;
	}
	public function findForCompany(int $companyId,int $retailCustomerId)
	{
		$company = Company::find($companyId);
		$retailCustomer = $company->retailCustomers()->find($retailCustomerId);
		return $retailCustomer;
	}
	public function getShippingAddresses(int $companyId,int $retailCustomerId)
	{
		$company = Company::find($companyId);
		$retailCustomer = $company->retailCustomers()->find($retailCustomerId);
		return $this->staService->getAllAddresses($company,$retailCustomer->nav_id);
	}
	public function createShippingAddresses(int $companyId,int $retailCustomerId,Array $params)
	{
		$company = Company::find($companyId);
		$retailCustomer = $company->retailCustomers()->find($retailCustomerId);
		return $this->staService->createAddress($company,$retailCustomer->nav_id,$params);
	}
	public function updateShippingAddress(int $companyID, int $retailCustomerID, $addressCode, Array $params)
	{
		$company = Company::find($companyID);
		$retailCustomer = $company->retailCustomers()->find($retailCustomerID);
		return $this->staService->updateAddress($company,$retailCustomer->nav_id, $addressCode, $params);
	}
	public function deleteAddress(int $companyID, int $retailCustomerID, $addressCode)
	{
		$company = Company::find($companyID);
		$retailCustomer = $company->retailCustomers()->find($retailCustomerID);
		return $this->staService->deleteAddress($company,$retailCustomer->nav_id, $addressCode);
	}
}