<?php

namespace Rosetta\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Rosetta\Jobs\SyncStockThreshold;
use Illuminate\Support\Facades\Validator;
use Rosetta\Transformers\Product\IndexTransformer;
use Rosetta\Transformers\StockThresholdTransformer;
use Rosetta\Services\StockThreshold\StockThresholdServiceInterface;

class StockThresholdController extends Controller
{
	protected $stockThresholdService;
	public function __construct(StockThresholdServiceInterface $stockThresholdService)
	{
		$this->stockThresholdService = $stockThresholdService;
	}
    public function index(Request $request)
    {
        $user = Auth::user();
        $company = null;
        $company = $user->company;
        $stock = $this->stockThresholdService->getStockThreshold($request->all());
        if($stock == null)
        {
            return [];
        }
        else
        {
            $stockArray =  fractal()
                            ->collection($stock)
                            ->parseIncludes(['product', 'product.category','product.collection','product.colour', 'stock_point'])
                            ->transformWith(new StockThresholdTransformer(null, $company))
                            ->toArray();

            return $stockArray;
        }
    }
    public function refresh(Request $request, $companyId)
    {
        $queuePrefix = config('brand.queue_prefix');
        $queue = $queuePrefix.'.jobs';
        SyncStockThreshold::dispatch($companyId,Auth::user(),\request()->ip())->onQueue($queue);
        return [];
        //$this->stockThresholdService->sync($companyId);
    }
    public function export(Request $request, $companyId)
    {
        $pathToFile = $this->stockThresholdService->exportStock($companyId, $request->all());
        if(!empty($pathToFile)){
            return response()->download($pathToFile,'AvailableStockReport '.Carbon::now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend(true);
        }        
    }
    public function deleteThreshold(Request $request, $companyId)
    {
        $this->stockThresholdService->deleteStock(Auth::user(),$companyId,$request->get('ids'));
        return response([]);
    }
    public function updateThreshold(Request $request, $companyId,$thresholdId)
    {
        $threshold = $this->stockThresholdService->updateThreshold(Auth::user(),$companyId,$thresholdId,$request->all());
        return fractal()
            ->item($threshold)
            ->parseIncludes(['product', 'product.category','product.collection','product.colour', 'stock_point'])
            ->transformWith(new StockThresholdTransformer(null))
            ->toArray();
    }
    public function importThreshold(Request $request, $companyId, $tenant)
    {
        $this->stockThresholdService->importThreshold($companyId,$tenant,$request->get('stockpoint'),$request->file('file'),Auth::user(),$request->ip());
    }
}
