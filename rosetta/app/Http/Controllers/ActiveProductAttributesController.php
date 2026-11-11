<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Rosetta\Exceptions\ActiveProductAttributes\RecordAlreadyExistsException;
use Rosetta\Helpers\CustomHelper;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;
use Rosetta\Services\Companies\CompanyServiceInterface;
use Rosetta\Transformers\Product\ActiveProductAttributesTransformer;
use Rosetta\Transformers\Product\CustomerCategoryTransformer;

class ActiveProductAttributesController extends Controller
{
	protected $activeProductAttributesService, $companyService;
	public function __construct(CompanyServiceInterface $companyService, ActiveProductAttributesServiceInterface $activeProductAttributesService)
	{
		$this->activeProductAttributesService = $activeProductAttributesService;
        $this->companyService = $companyService;
	}
    public function list(Request $request, $companyId, $customerType = null, $customerId = null)
    {
        $company = $this->companyService->find($companyId);
        $productAttributes = $this->activeProductAttributesService->list($request->all(), $company, $customerType, $customerId);

        $productAttributesArray =  fractal()
            ->collection($productAttributes)
            ->transformWith(new ActiveProductAttributesTransformer())
            ->toArray();


        if($request->get('page'))
        {
            return [
                "total" => $productAttributes->total(),
                "page"  => $productAttributes->currentPage(),
                "productAttributes" => $productAttributesArray,
            ];
        }
        else
            return $productAttributesArray;
    }
    public function getAttributesForCustomer($companyId, $customerType = null, $customerId = null)
    {
        $company = $this->companyService->find($companyId);
        $productAttributes = $this->activeProductAttributesService->getActiveCategoriesForCustomer($company, $customerType, $customerId);
        $productAttributes = fractal()
            ->collection($productAttributes)
            ->transformWith(new CustomerCategoryTransformer())
            ->parseIncludes(['collections.colours'])
            ->toArray();
            $productAttributes = CustomHelper::arrayRemoveDuplicatesRecursive($productAttributes);
        return $productAttributes;
    }
    public function add(Request $request, $companyId)
    {
        try
        {
            $company = $this->companyService->find($companyId);
            $productAttribute = $this->activeProductAttributesService->add($request->all(), $company);
        }
        catch(RecordAlreadyExistsException $e)
        {
            return response()->json(["error" =>"Record Already Exists!"],409);
        }
    }
    public function update(Request $request, $companyId, $attributeId)
    {
        $company = $this->companyService->find($companyId);
        $productAttribute = $this->activeProductAttributesService->update($request->all(), $company, $attributeId);

        return fractal()
            ->item($productAttribute)
            ->transformWith(new ActiveProductAttributesTransformer())
            ->toArray();
    }
    public function delete(Request $request, $companyId, $attributeId)
    {
        $company = $this->companyService->find($companyId);
        $productAttribute = $this->activeProductAttributesService->delete($request->all(), $company, $attributeId);
    }
    public function deleteAttributesWithColours(Request $request, $companyId, $attributeId)
    {
        $company = $this->companyService->find($companyId);
        $productAttribute = $this->activeProductAttributesService->deleteAttributesWithColours($request->all(), $company, $attributeId);
    }
}



