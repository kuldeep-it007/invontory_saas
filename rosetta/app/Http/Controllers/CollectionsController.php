<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Rosetta\Models\Product\Collection;
use Rosetta\Services\Collection\CollectionServiceInterface;
use Rosetta\Transformers\Collections\TechspecTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Spatie\Fractalistic\ArraySerializer;


class CollectionsController extends Controller
{
	protected $collectionService;
	public function __construct(CollectionServiceInterface $collectionService)
	{
		$this->collectionService = $collectionService;
	}
    public function search(Request $request, $category_code, $customer_type = null, $customerId = null)
    {
        $uniqueCollections = [];
        list($uniqueCollections,$user_type) = $this->collectionService->getActiveCollections(Auth::user(), $category_code, $customer_type, $customerId);
        if($uniqueCollections)
            return fractal()
            ->collection($uniqueCollections)
            ->transformWith(new CollectionTransformer(null, null, $user_type))
            ->toArray();
    }
    public function create(Request $request, $category_code)
    {
        $validator = Validator::make($request->all(), [
            "code"    => 'required'
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $collection = $this->collectionService->create(Auth::user(),$request->all(), urldecode($category_code));
        return fractal()
            ->item($collection)
            ->transformWith(new CollectionTransformer())
            ->toArray();
    }
    public function techspecs(Request $request,$category_code,$collection_code)
    {
        $collection_code = urldecode($collection_code);
        $techspecs = $this->collectionService->getTechSpecs($category_code,$collection_code);
        return fractal()
            ->collection($techspecs)
            ->transformWith(new TechspecTransformer())
            ->toArray();
    }
}
