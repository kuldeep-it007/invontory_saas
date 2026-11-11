<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Rosetta\Services\Discount\DiscountsServiceInterface;
use Rosetta\Transformers\Discount\DiscountTransformer;

class DiscountsController extends Controller
{
	protected $discountsService;
	public function __construct(DiscountsServiceInterface $discountsService)
	{
		$this->discountsService = $discountsService;
	}
	public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "region_id"  => 'required',
            "customer_type" => 'required',
            "discount"  => 'required'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $discount = $this->discountsService->create($request->all());
        return fractal()
            ->item($discount)
            ->transformWith(new DiscountTransformer())
            ->toArray();
    }
    public function update(Request $request, $id)
    {
        //not being used, since edit discount nnot provided for superadmin UI, udpate is done to add modal itself.
        try
        {
            $validator = Validator::make($request->all(), [
       //     "region_id"  => 'required',//#860
         //   "customer_type" => 'required',//#860
            "discount"  => 'required'
            ]);
            if ($validator->fails())
            {
                return response()->json($validator->errors(),400);
            }
            $discount = $this->discountsService->update($request->all(), $id);
            return fractal()
                ->item($discount)
                ->transformWith(new DiscountTransformer())
                ->toArray();
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("Record not found",404);
        }
    }
    public function delete($id)
    {
        try
        {
            $this->discountsService->delete($id);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("Record not found",404);
        }
    }
    public function index(Request $request)
    {
        $discounts = $this->discountsService->listDiscounts($request->all());

        $discountsArray =  fractal()
            ->collection($discounts)
            ->transformWith(new DiscountTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $discounts->total(),
                "page"  => $discounts->currentPage(),
                "discounts" => $discountsArray,
            ];
        }
        else
            return $discountsArray;
    }
}