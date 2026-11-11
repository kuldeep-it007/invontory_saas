<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Rosetta\Exceptions\ActiveProductAttributes\RecordAlreadyExistsException;
use Rosetta\Models\SSDiscount;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Services\SSDiscount\SSDiscountServiceInterface;
use Rosetta\Transformers\SSDiscount\SSDiscountTransformer;

class SSDiscountController extends Controller
{
	protected $ssDiscountService;
	public function __construct(SSDiscountServiceInterface $ssDiscountService)
	{
		$this->ssDiscountService = $ssDiscountService;
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
        $superStockist = SuperStockist::find($request['super_stockist_id']);
        $loggedInUser = Auth::user();
        $loggedInUsersSS = $loggedInUser->superStockist;
        if($loggedInUsersSS)
        {
            if($superStockist->id != $loggedInUsersSS->id)
            {
                return response()->json(["error" =>"You do not have permissions to add this data"],401);
            }
        }//End of #723
        try
        {
            $discount = $this->ssDiscountService->create($request->all());
        }
        catch(RecordAlreadyExistsException $e)
        {
            return response()->json(["error" =>"Record Already Exists!"],409);
        }
        return fractal()
            ->item($discount)
            ->transformWith(new SSDiscountTransformer())
            ->toArray();
    }
    public function update(Request $request, $id)
    {
        try
        {
            $validator = Validator::make($request->all(), [
            "discount"  => 'required'
            ]);
            if ($validator->fails())
            {
                return response()->json($validator->errors(),400);
            }
            $discount = SSDiscount::find($id);
            if($discount)
            {
                //#723
                $superStockist = SuperStockist::find($discount->super_stockist_id);
                $loggedInUser = Auth::user();
                $loggedInUsersSS = $loggedInUser->superStockist;
                if($loggedInUsersSS)
                {
                    if($superStockist->id != $loggedInUsersSS->id)
                    {
                        return response()->json(["error" =>"You do not have permissions to update this data"],401);
                    }
                }//End of #723
                $discount = $this->ssDiscountService->update($request->all(), $id);
                return fractal()
                    ->item($discount)
                    ->transformWith(new SSDiscountTransformer())
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
        $discount = SSDiscount::find($id);
        if($discount)
        {
            //#723
            $superStockist = SuperStockist::find($discount->super_stockist_id);
            $loggedInUser = Auth::user();
            $loggedInUsersSS = $loggedInUser->superStockist;
            if($loggedInUsersSS)
            {
                if($superStockist->id != $loggedInUsersSS->id)
                {
                    return response()->json(["error" =>"You do not have permissions to delete this data"],401);
                }
            }//End of #723
            try
            {
                $this->ssDiscountService->delete($id);
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
        $superStockist = SuperStockist::find($request['super_stockist_id']);
        $loggedInUser = Auth::user();
        $loggedInUsersSS = $loggedInUser->superStockist;
        if($loggedInUsersSS)
        {
            if($superStockist->id != $loggedInUsersSS->id)
            {
                return response()->json(["error" =>"You do not have permissions to view this data"],401);
            }
        }//End of #723
        $discount = $this->ssDiscountService->listSSDiscount($request->all());

        $discountArray =  fractal()
            ->collection($discount)
            ->transformWith(new SSDiscountTransformer())
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