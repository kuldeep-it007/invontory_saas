<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Rosetta\Models\Product\Colour;
use Rosetta\Services\Colours\RosColourServiceInterface;
use Rosetta\Transformers\Product\ColourTransformer;

class ColoursController extends Controller
{
  protected $colourService;
  public function __construct(RosColourServiceInterface $colourService)
  {
    $this->colourService = $colourService;
  }
  public function search($category_code, $collection_code, $customer_type = null, $customerId = null)
  {
    $collection_code = urldecode($collection_code);
    $uniqueColours = [];
    list($uniqueColours,$user_type) = $this->colourService->getActiveColours(Auth::user(), $category_code, $collection_code, $customer_type, $customerId);

    return fractal()
    ->collection($uniqueColours)
    ->transformWith(new ColourTransformer(null, null, $user_type, $category_code))
    ->toArray();
  }
  public function create(Request $request, $collection_code)
  {
      $validator = Validator::make($request->all(), [
          "code"    => 'required'
      ]);
      if ($validator->fails())
      {
          return response()->json($validator->errors(),400);
      }
      $colour = $this->colourService->create(Auth::user(),$request->all(), urldecode($collection_code));
      return fractal()
          ->item($colour)
          ->transformWith(new ColourTransformer())
          ->toArray();
  }
}
