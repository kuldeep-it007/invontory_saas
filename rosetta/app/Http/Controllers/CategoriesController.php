<?php

namespace Rosetta\Http\Controllers;

use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Rosetta\Models\Product\Category;
use Rosetta\Services\Categories\CategoryServiceInterface;
use Rosetta\Transformers\Product\CategoryTransformer;
use Spatie\Fractalistic\ArraySerializer;
class CategoriesController extends Controller
{
	protected $categoryService;
	public function __construct(CategoryServiceInterface $categoryService)
	{
		$this->categoryService = $categoryService;
	}
    public function index(Request $request, $customer_type = null, $customerId = null)
    {
        $uniqueCategories = [];
        list($uniqueCategories,$user_type) = $this->categoryService->getActiveCategories(Auth::user(), $customerId, $customer_type,$request->has('clonly'));
        return fractal()
        ->collection($uniqueCategories)
        ->transformWith(new CategoryTransformer(null, null, $user_type))
        ->toArray();
    }
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "code"    => 'required'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $category = $this->categoryService->create(Auth::user(),$request->all());
        return fractal()
            ->item($category)
            ->transformWith(new CategoryTransformer())
            ->toArray();
    }
    public function update(Request $request, $code)
    {
        $validator = Validator::make($request->all(), [
            "code"    => 'required'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $category = $this->categoryService->update(Auth::user(),$request->all(), $code);
        return fractal()
            ->item($category)
            ->transformWith(new CategoryTransformer())
            ->toArray();
    }
}
