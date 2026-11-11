<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Rosetta\Exceptions\ActiveProductAttributes\RecordAlreadyExistsException;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Services\CoDistributor\CoDistributorDiscountServiceInterface;
use Rosetta\Transformers\CoDistributorDiscount\CoDistributorDiscountTransformer;
use Rosetta\Transformers\PurchaseOrder\BlanketOrderTransformer;

class CoDistributorDiscountController extends Controller
{
	protected $coDistributorDiscountService;
	public function __construct(CoDistributorDiscountServiceInterface $coDistributorDiscountService)
	{
		$this->coDistributorDiscountService = $coDistributorDiscountService;
	}
    public function index(Request $request)
    {
    	$discount = $this->coDistributorDiscountService->listCustomerDiscount($request->all());

        $discountArray =  fractal()
            ->collection($discount)
            ->transformWith(new CoDistributorDiscountTransformer())
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
    public function delete($id)
    {
        try
        {
            $this->coDistributorDiscountService->delete($id);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("Record not found",404);
        }
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
        try
        {
            $discount = $this->coDistributorDiscountService->create($request->all());
        }
        catch(RecordAlreadyExistsException $e)
        {
            return response()->json(["error" =>"Record Already Exists!"],409);
        }
        return fractal()
            ->item($discount)
            ->transformWith(new CoDistributorDiscountTransformer())
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
            $discount = $this->coDistributorDiscountService->update($request->all(), $id);
            return fractal()
                ->item($discount)
                ->transformWith(new CoDistributorDiscountTransformer())
                ->toArray();
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("Record not found",404);
        }
    }
}
