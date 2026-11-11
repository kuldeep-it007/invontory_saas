<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Rosetta\Exceptions\ActiveProductAttributes\RecordAlreadyExistsException;
use Rosetta\Models\CustomerDiscount;
use Rosetta\Models\Distributor;
use Rosetta\Services\CustomerDiscount\CustomerDiscountServiceInterface;
use Rosetta\Transformers\CustomerDiscount\CustomerDiscountTransformer;

class CustomerDiscountController extends Controller
{
	protected $customerDiscountService;
	public function __construct(CustomerDiscountServiceInterface $customerDiscountService)
	{
		$this->customerDiscountService = $customerDiscountService;
	}
	public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "discount"  => 'required'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $distributor = Distributor::find($request['distributor_id']);
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permissions to add this data"],401);
            }
        }//End of #723
        try
        {
            $discount = $this->customerDiscountService->create($request->all());
        }
        catch(RecordAlreadyExistsException $e)
        {
            return response()->json(["error" =>"Record Already Exists!"],409);
        }
        return fractal()
            ->item($discount)
            ->transformWith(new CustomerDiscountTransformer())
            ->toArray();
    }
    public function update(Request $request, $id)
    {
        try
        {
            $validator = Validator::make($request->all(), [
      //      "customer_type" => 'required',
            "discount"  => 'required'
            ]);
            if ($validator->fails())
            {
                return response()->json($validator->errors(),400);
            }
            $discount = CustomerDiscount::find($id);
            if($discount)
            {
                //#723
                $distributor = Distributor::find($discount->distributor_id);
                $loggedInUser = Auth::user();
                $loggedInUsersDist = $loggedInUser->distributor;
                if($loggedInUsersDist)
                {
                    if($distributor->id != $loggedInUsersDist->id)
                    {
                        return response()->json(["error" =>"You do not have permissions to update this data"],401);
                    }
                }//End of #723
                $discount = $this->customerDiscountService->update($request->all(), $id);
                return fractal()
                    ->item($discount)
                    ->transformWith(new CustomerDiscountTransformer())
                    ->toArray();
            }
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("Record not found",404);
        }
    }
    public function delete($id)
    {
        $discount = CustomerDiscount::find($id);
        if($discount)
        {
            //#723
            $distributor = Distributor::find($discount->distributor_id);
            $loggedInUser = Auth::user();
            $loggedInUsersDist = $loggedInUser->distributor;
            if($loggedInUsersDist)
            {
                if($distributor->id != $loggedInUsersDist->id)
                {
                    return response()->json(["error" =>"You do not have permissions to delete this data"],401);
                }
            }//End of #723
            try
            {
                $this->customerDiscountService->delete($id);
            }
            catch(ModelNotFoundException $e)
            {
                return response()->json("Record not found",404);
            }
        }
    }
    public function index(Request $request)
    {
        //#723
        $distributor = Distributor::find($request['distributor_id']);
        $loggedInUser = Auth::user();
        $loggedInUsersDist = $loggedInUser->distributor;
        if($loggedInUsersDist)
        {
            if($distributor->id != $loggedInUsersDist->id)
            {
                return response()->json(["error" =>"You do not have permissions to view this data"],401);
            }
        }//End of #723
        $discount = $this->customerDiscountService->listCustomerDiscount($request->all());

        $discountArray =  fractal()
            ->collection($discount)
            ->transformWith(new CustomerDiscountTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $discount->total(),
                "page"  => $discount->currentPage(),
                "discounts" => $discountArray,
            ];
        }
        else
            return $discountArray;
    }
}