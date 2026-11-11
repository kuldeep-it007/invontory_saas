<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
use Rosetta\Services\Companies\CompanyServiceInterface;
use Rosetta\Services\Product\ProductsServiceInterface;
//use Rosetta\Transformers\Product\ActiveProductAttributesTransformer;
use Rosetta\Transformers\Product\BackOrderProductTransformer;
use Rosetta\Transformers\Product\IndexTransformer;
use Rosetta\Transformers\Product\NewProductTransformer;
use Rosetta\Transformers\Product\ProductTransformer;

class ProductsController extends Controller
{
	protected $productsService, $companyService;
	public function __construct(CompanyServiceInterface $companyService, ProductsServiceInterface $productsService)
	{
		$this->productsService = $productsService;
        $this->companyService = $companyService;
	}
    public function index(Request $request)
    {
        $user = Auth::user();
        $company = null;
        $company = $user->company;
        if($company)
            $products = $this->productsService->getActiveProducts($request->all(), $company);
        else
            $products = $this->productsService->getProducts($request->all());
        if($products == null)
        {
            if($request->get('page'))
            {
                return [
                    "total" => 0,
                    "page"  => 1,
                    "products" => [],
                ];
            }
            else
                return [];
        }
        else
        {
            $productsArray =  fractal()
                            ->collection($products)
                            ->parseIncludes(['category','collection','colour'])
                            ->transformWith(new IndexTransformer(null, $company))
                            ->toArray();

            if($request->get('page'))
            {
                return [
                    "total" => $products->total(),
                    "page"  => $products->currentPage(),
                    "products" => $productsArray,
                ];
            }
            else
                return $productsArray;
        }
    }
    public function getProducts(Request $request)
    {
        $params = $request->all();
        $company = $this->companyService->find($params['company']);
        $region = $company->region;
        $customerType = null;

        if(array_has($params,'cust_type'))
        {
            if($params['cust_type'] == 'DISTRIBUTO')
            {
                $customerType = 'DISTRIBUTO';
                $customer = $company->distributors()->find($params['customerId']);
            }
            if($params['cust_type'] == 'DEALER')
            {
                $customerType = 'DEALER';
                $customer = $company->dealers()->find($params['customerId']);
            }
        }
        $products = $this->productsService->getUserActiveProducts($request->all(), $company, $customer);
        $fractal = fractal()
            ->Collection($products);

        if($customerType)
        {
            $fractal->transformWith(new BackOrderProductTransformer($region, $company,$customerType,$customer));
            $fractal->parseIncludes(['category','collection','colour', 'price']);
        }
        else
        {
            $fractal->transformWith(new BackOrderProductTransformer($region, $company));
            $fractal->parseIncludes(['category','collection','colour', 'price']);
        }
        $productsArray =  $fractal->toArray();       
        if($request->get('page'))
        {
            return [
                "total" => $products->total(),
                "page"  => $products->currentPage(),
                "products" => $productArray,
            ];
        }
        else
            return $productsArray;
    }
    public function getProductPrice($companyId,$categoryCode,$collectionCode,$colourCode)
    {
        $price = $this->productsService->getProductPrice($companyId,$categoryCode,$collectionCode,$colourCode);
        if($price)
            return response()->json($price);
    }
    public function create(Request $request)
    {
        $product = $this->productsService->create($request->all());
        return fractal()
            ->item($product)
            ->transformWith(new NewProductTransformer())
            ->toArray();
    }
    public function update(Request $request, $id)
    {
        /*$validator = Validator::make($request->all(), [
            "name"  => 'required',
            "base_unit_of_measure" => 'required',
            "purchase_unit_of_measure" => 'required',
            "sales_unit_of_measure" => 'required',
            "category_code" => 'required',            
            "order_multiple" => 'required',
            "tax_group_code" => 'required',
            "suggest_selling_price" => 'required'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }*/
        $product = $this->productsService->update($id,$request->all());
        return fractal()
            ->item($product)
            ->transformWith(new NewProductTransformer())
            ->toArray();
    }
    public function get($id)
    {
        $product = $this->productsService->find($id);
        return fractal()
            ->item($product)
            ->transformWith(new ProductTransformer())
            ->toArray();
    }
 /*   public function listProductAttributes($companyId, $customerType = null, $customerId = null, Request $request)
    {
        $company = $this->companyService->find($companyId);
        $productAttributes = $this->productsService->getActiveProductAttributes($request->all(), $company, $customerType, $customerId);

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
    public function AddProductAttributes($companyId, Request $request)
    {
        $company = $this->companyService->find($companyId);
        $productAttribute = $this->productsService->addActiveProductAttributes($request->all(), $company);
    }
    public function updateProductAttributes($companyId, $attributeId, Request $request)
    {
        $company = $this->companyService->find($companyId);
        $productAttribute = $this->productsService->updateActiveProductAttributes($request->all(), $company, $attributeId);

        return fractal()
            ->item($productAttribute)
            ->transformWith(new ActiveProductAttributesTransformer())
            ->toArray();
    }
    public function deleteProductAttributes($companyId, $attributeId, Request $request)
    {
        $company = $this->companyService->find($companyId);
        $productAttribute = $this->productsService->deleteActiveProductAttributes($request->all(), $company, $attributeId);
    }
    public function deleteAttributeColours($companyId, $attributeId, Request $request)
    {
        $company = $this->companyService->find($companyId);
        $productAttribute = $this->productsService->deleteAttributeColours($request->all(), $company, $attributeId);
    }*/

public function toggleStatus(Request $request, $id)
    {
        $product = $this->productsService->updateStatus($id,$request->all());
        return fractal()
            ->item($product)
            ->transformWith(new NewProductTransformer())
            ->toArray();
    }        
}
