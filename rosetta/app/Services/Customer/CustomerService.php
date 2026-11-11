<?php
namespace Rosetta\Services\Customer;

use Illuminate\Support\Facades\DB;
use Rosetta\Models\Company;
use Rosetta\Models\Customer;
use Rosetta\Models\Dealer;
use Rosetta\Models\User;
use Rosetta\Services\Customer\CustomerServiceInterface;

class CustomerService implements CustomerServiceInterface
{
	public function createCustomer($companyId, $dealerId, Array $params, $user)
	{
		$dealer = Dealer::find($dealerId);
		$customer = new Customer;
		if(array_has($params, 'address'))
 			$customer->address = $params['address'];
 		if(array_has($params, 'address_2'))
 			$customer->address_2 = $params['address_2'];
 		if(array_has($params, 'post_code'))
 			$customer->post_code = $params['post_code'];
 		if(array_has($params, 'city'))
 			$customer->city = $params['city'];
 		if(array_has($params, 'state'))
 			$customer->state = $params['state'];
 		if(array_has($params, 'country_region_code'))
 			$customer->country_region_code = $params['country_region_code'];
 		if(array_has($params, 'contact'))
			$customer->contact = $params['contact'];
		$customer->name = $params['name'];
 		$customer->phone_no = $params['phone_no'];
 		$customer->company_id = $companyId;
 		$customer->author()->associate($user);
 		$dealer->customers()->save($customer);
 		return $customer;
	}
	public function updateCustomer($dealerId, $customerId, Array $params)
	{

	}
	public function getAllCustomers($dealerId)
	{
		$dealer = Dealer::find($dealerId);
		$customers = $dealer->customers;
		if($customers === null)
			return [];
		//if(!is_array($customers))
		//	return [$customers];
		return $customers;
	}
	public function deleteCustomer($dealerId, $customerId)
	{
		$dealer = Dealer::find($dealerId);
		$customer = $dealer->customers->where('id', '=', $customerId)->first();
		$customer->delete();
	}
}