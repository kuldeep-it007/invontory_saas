<?php

namespace Rosetta\Http\Controllers;

use Rosetta\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rosetta\Exceptions\OnlineDiscounts\DiscountExistsException;
use Rosetta\Transformers\OnlineDiscounts\IndexTransformer;
use Rosetta\Services\OnlineDiscounts\OnlineDiscountsServiceInterface;

class OnlineDiscountsController extends Controller
{
    protected $onlineDiscountsService;
    public function __construct(OnlineDiscountsServiceInterface $onlineDiscountsService)
    {
        $this->onlineDiscountsService = $onlineDiscountsService;
    }
    public function getOnlineDisconts(Request $request,$companyID)
    {
        $company = Company::find($companyID);
        if(!Gate::allows('viewOnlineDiscounts',[$company]))
        {
            return response()->json([], 403);
        }
        $discounts = $this->onlineDiscountsService->getAllForCompany($company,$request->all());
        $discountsArray = fractal()
            ->collection($discounts)
            ->parseIncludes(['category','collection','colour'])
            ->transformWith(new IndexTransformer)
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
    public function delete(Request $request,$companyID,$discountID)
    {
        $company = Company::find($companyID);
        if(!Gate::allows('deleteOnlineDiscount',[$company]))
        {
            return response()->json([], 403);
        }
        try{
            $this->onlineDiscountsService->delete(Auth::user(),$company,$discountID);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json([],404);
        }
        return response()->json([],200);
    }
    public function create(Request $request,$companyID)
    {
        $company = Company::find($companyID);
        $validator = Validator::make($request->all(), [
            "discount"    => 'required|numeric|between:0,100'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }

        if(!Gate::allows('addOnlineDiscount',[$company]))
        {
            return response()->json([], 403);
        }
        try{
            $discount = $this->onlineDiscountsService->create(Auth::user(),$company,$request->all());
        }
        catch(DiscountExistsException $e)
        {
            return response()->json([], 409);
        }

        return fractal()
            ->item($discount)
            ->parseIncludes(['category','collection','colour'])
            ->transformWith(new IndexTransformer)
            ->toArray();
    }

    public function update(Request $request,$companyID,$discountID)
    {
        $company = Company::find($companyID);
        $validator = Validator::make($request->all(), [
            "discount"    => 'required|numeric|between:0,100'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }

        if(!Gate::allows('addOnlineDiscount',[$company]))
        {
            return response()->json([], 403);
        }
        try{
            $discount = $this->onlineDiscountsService->update(Auth::user(),$company,(int)$discountID,$request->all());
        }
        catch(DiscountExistsException $e)
        {
            return response()->json([], 409);
        }

        return fractal()
            ->item($discount)
            ->parseIncludes(['category','collection','colour'])
            ->transformWith(new IndexTransformer)
            ->toArray();
    }
}
