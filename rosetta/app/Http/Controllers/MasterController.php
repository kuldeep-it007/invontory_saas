<?php

namespace Rosetta\Http\Controllers;
use Auth;
use Illuminate\Http\Request;
use Rosetta\Models\Setting;
use Rosetta\Services\MasterData\MasterDataServiceInterface;
use Rosetta\Transformers\ImageClassTransformer;
use Rosetta\Transformers\MasterDataTransformer;
use Rosetta\Transformers\TechSpecTransformer;
use Rosetta\Transformers\TenantsTransformer;
use stdClass;

class MasterController extends Controller
{
	protected $masterDataService;
	public function __construct(MasterDataServiceInterface $masterDataService)
	{
		$this->masterDataService = $masterDataService;
	}
  	public function index(Request $request)
  	{
  		return $masterData = $this->masterDataService->get();
  	}
    public function notice(Request $request)
    {
      $notice = Setting::where('type','=','notice')->where('key','=','notice')->first();
      if(!$notice)
      {
        return response()->json([],404);//#817
      }
      $tenant_notice = json_decode($notice->value);
      return ['message' => $tenant_notice->message];
    }
  	public function techspecs(Request $request)
  	{
  		$techspecs = $this->masterDataService->getTechSpecs();
  		return fractal()
          ->Collection($techspecs)
          ->transformWith(new TechSpecTransformer())
          ->toArray();
  	}
    public function imageclasses(Request $request)
    {
        $techspecs = $this->masterDataService->getImageClasses();
        return fractal()
          ->Collection($techspecs)
          ->transformWith(new ImageClassTransformer())
          ->toArray();
    }
    public function tenants(Request $request)
    {
      $tenants = $this->masterDataService->getTenants($request->get('query'));
      return fractal()
          ->collection($tenants)
          ->transformWith(new TenantsTransformer())
          ->toArray();
    }
    public function stock_point_tenants(Request $request)//#776
    {
      $stock_point_tenants = $this->masterDataService->getStock_point_tenants($request->get('query'));
      return fractal()
          ->collection($stock_point_tenants)
          ->transformWith(new TenantsTransformer())
          ->toArray();
    }
}