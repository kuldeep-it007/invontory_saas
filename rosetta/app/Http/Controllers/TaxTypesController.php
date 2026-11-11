<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Rosetta\Services\TaxTypes\TaxTypeServiceInterface;
use Rosetta\Transformers\TaxTypeTransformer;

class TaxTypesController extends Controller
{
	protected $taxTypeService;
	public function __construct(TaxTypeServiceInterface $taxTypeService)
	{
		$this->taxTypeService = $taxTypeService;
	}
    public function index(Request $request)
    {
        if(!Gate::allows('tax_type_lookup'))
            return response()->json("", 403);
        $taxTypes = $this->taxTypeService->get($request->all());

        $taxTypesArray = fractal()
                ->collection($taxTypes)
                ->transformWith(New TaxTypeTransformer)
                ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $taxTypes->total(),
                "page" => $taxTypes->currentPage(),
                "taxTypes" => $taxTypesArray
            ];
        }
        return $taxTypesArray;
    }
}
