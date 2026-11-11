<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Rosetta\Services\Companies\CompanyServiceInterface;
use Rosetta\Services\RetailCustomer\RetailCustomerServiceInterface;
use Rosetta\Services\Users\UserServiceInterface;
use Rosetta\Transformers\NAV\AddressTransformer;
use Rosetta\Transformers\RetailCustomers\IndexTransformer;
use Rosetta\Transformers\RetailCustomers\ProfileTransformer;
use Rosetta\Transformers\RetailCustomers\RetailCustomerTransformer;

class RetailCustomersController extends Controller
{
	protected $companyService, $userService, $retailCustomerService;
	public function __construct(CompanyServiceInterface $companyService,RetailCustomerServiceInterface $retailCustomerService, UserServiceInterface $userService)
	{
		$this->companyService = $companyService;
		$this->retailCustomerService = $retailCustomerService;
        $this->userService = $userService;
	}
    public function index(Request $request,$companyID)
    {
    	if(!Gate::allows('viewRetailCustomers'))
        {
            return response()->json("", 403);
        }
        $company = $this->companyService->find($companyID);
    	$retailCustomers = $this->retailCustomerService->getRetailCustomers($company,$request->all());
        $retailCustomersArray =  fractal()
            ->collection($retailCustomers)
            ->transformWith(new IndexTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $retailCustomers->total(),
                "page"  => $retailCustomers->currentPage(),
                "retailCustomers" => $retailCustomersArray,
            ];
        }
        else
            return $retailCustomersArray;
    }
    public function create(Request $request,$companyID)
    {
        if(!Gate::allows('createRetailCustomers'))
        {
            return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
            "name"    => 'required',
            "user.email" => 'unique:users,email'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $company = $this->companyService->find($companyID);
        $retailCustomer = $this->retailCustomerService->create(Auth::user(),$company,$request->all());
      //  $this->userService->create(Auth::user(), $request['user'], 'retailCustomer', $retailCustomer);
        return fractal()
            ->item($retailCustomer)
            ->transformWith(new RetailCustomerTransformer())
            ->toArray();
    }
    public function update(Request $request,$companyID,$retailCustomerID)
    {
        if(!Gate::allows('updateRetailCustomers'))
        {
            return response()->json("", 403);
        }
        $company = $this->companyService->find($companyID);
        $retailCustomer = $company->retailCustomers()->find($retailCustomerID);
        $retailCustomer = $this->retailCustomerService->update(Auth::user(),$company,$retailCustomer,$request->all());
        return fractal()
            ->item($retailCustomer)
            ->transformWith(new RetailCustomerTransformer())
            ->toArray();
    }
    public function get(Request $request, $compnayID, $retailCustomerID)
    {
        $retailCustomer = $this->retailCustomerService->findForCompany($compnayID,$retailCustomerID);
        /*return fractal()
            ->item($retailCustomer)
            ->transformWith(new ProfileTransformer())
            ->parseIncludes(['tax_information','state'])
            ->toArray();*/
        return fractal()
            ->item($retailCustomer)
            ->transformWith(new RetailCustomerTransformer())
            ->parseIncludes(['tax_information','state'])
            ->toArray();
    }
    public function getShippingAddresses(Request $request,$companyID,$retailCustomerID)
    {
         $addresses = $this->retailCustomerService->getShippingAddresses($companyID,$retailCustomerID);
         return fractal()
            ->collection($addresses)
            ->transformWith(new AddressTransformer())
            ->toArray();
    }
    public function createShippingAddresses(Request $request,$companyID,$retailCustomerID)
    {
        $address = $this->retailCustomerService->createShippingAddresses($companyID,$retailCustomerID,$request->all());
        return fractal()
            ->item($address)
            ->transformWith(new AddressTransformer())
            ->toArray();
    }
    public function updateShippingAddress(Request $request, $companyID, $retailCustomerID, $addressCode)
    {
        if(!Gate::allows('updateRetailCustomers'))
        {
            return response()->json("", 403);
        }
        $address = $this->retailCustomerService->updateShippingAddress($companyID, $retailCustomerID, $addressCode, $request->all());
        return fractal()
            ->item($address)
            ->transformWith(new AddressTransformer())
            ->toArray();
    }
    public function deleteShippingAddress($companyID, $retailCustomerID, $addressCode)
    {
        if(!Gate::allows('deleteRetailCustomers'))
        {
            return response()->json("", 403);
        }
        $this->retailCustomerService->deleteAddress($companyID, $retailCustomerID, $addressCode);
    }
}
