<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Rosetta\Exceptions\Tax\RecordAlreadyExistsException;
use Rosetta\Services\Region\RegionServiceInterface;
use Rosetta\Transformers\Company\IndexTransformer as CompanyTransformer;
use Rosetta\Transformers\ProductTax\ProductTaxTransformer;
use Rosetta\Transformers\RegionTransformer;

class RegionsController extends Controller
{
	protected $regionService;
	public function __construct(RegionServiceInterface $regionService)
	{
		$this->regionService = $regionService;
	}
    public function create(Request $request)
    {
    	if(!Gate::allows('regions'))
        {
       		return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
            "name"	  => 'required',
            "country" => 'required'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $region = $this->regionService->create(Auth::user(),$request->all());
    	return fractal()
            ->item($region)
            ->transformWith(new RegionTransformer())
            ->toArray();
    }

    public function update(Request $request,$id)
    {
        if(!Gate::allows('regions'))
        {
            return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
            "name"    => 'required',
            "country" => 'required'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        try{
            $region = $this->regionService->update(Auth::user(),$id,$request->all());
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json([], 404);
        }
        return fractal()
            ->item($region)
            ->transformWith(new RegionTransformer())
            ->toArray();
    }

    public function get(Request $request,$id)
    {
        if(!Gate::allows('regions'))
        {
            return response()->json("", 403);
        }
        
        try{
            $region = $this->regionService->find($id);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json([], 404);
        }
        return fractal()
            ->item($region)
            ->transformWith(new RegionTransformer())
            ->toArray();
    }
    public function index(Request $request)
    {
    	if(!Gate::allows('regions'))
        {
            return response()->json("",403);
        }

        $regions = $this->regionService->all($request->all());
        $regionsArray =  fractal()
            ->collection($regions)
            ->transformWith(new RegionTransformer())
            ->toArray();


        if($request->get('page'))
        {
            return [
                "total" => $regions->total(),
                "page"  => $regions->currentPage(),
                "regions" => $regionsArray,
            ];
        }
        else
            return $regionsArray;
    }
    public function companies(Request $request, $regionID)
    {
        $region = $this->regionService->find($regionID);
        return fractal()
            ->collection($region->companies)
            ->transformWith(new CompanyTransformer)
            ->toArray();   
    }
    public function product_taxes(Request $request, $regionId)
    {
        $region_category_taxes = $this->regionService->product_taxes($regionId, $request->all());

        $region_category_taxes_array = fractal()
            ->collection($region_category_taxes)
            ->transformWith(new ProductTaxTransformer)
            ->toArray();   

        if($request->get('page'))
        {
            return [
                "total" => $region_category_taxes->total(),
                "page"  => $region_category_taxes->currentPage(),
                "taxes" => $region_category_taxes_array,
            ];
        }
        else
            return $region_category_taxes_array;
        
    }
    public function update_product_taxes(Request $request, $regionId)
    {
        $region_category_taxes = $this->regionService->update_product_taxes($regionId, $request->all());
    }
    public function add_product_taxes(Request $request, $regionId)
    {
        try
        {
            $region_category_taxes = $this->regionService->add_product_taxes($regionId, $request->all());
        }
        catch(RecordAlreadyExistsException $e)
        {
            return response()->json(["error" =>"Record Already Exists!"],409);
        }
    }
    public function delete_product_taxes($regionId, $taxId)
    {
        $this->regionService->delete_product_taxes($regionId, $taxId);
    }
}
