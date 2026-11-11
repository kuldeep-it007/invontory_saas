<?php
namespace Rosetta\Services\StockThreshold;

use Auth;
use Rosetta\Models\Log;
use Rosetta\Models\User;
use Rosetta\Models\Company;
use Illuminate\Http\UploadedFile;
use Rosetta\Models\Product\Product;
use Rosetta\Jobs\SyncStockThreshold;
use Rosetta\Models\Company\StockPoint;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Rosetta\Exceptions\NAVErrorException;
use Rosetta\Models\Product\AvailableStockThreshold;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rosetta\Exceptions\FileImport\FileNotFoundException;
use Rosetta\Services\NAV\ItemCardIntransitServiceInterface;
use Rosetta\Services\StockThreshold\StockThresholdServiceInterface;

class StockThresholdService implements StockThresholdServiceInterface
{
    protected $itemCardIntransitService;
	public function __construct(ItemCardIntransitServiceInterface $itemCardIntransitService)
	{
		$this->itemCardIntransitService = $itemCardIntransitService;
	}
	public function create($product_id, $company_id, $stock_point_id, $tenant, $threshold, $lc, $uom)
	{
		$product_threshold = new AvailableStockThreshold;
		$product_threshold->company_id = $company_id;
		$product_threshold->tenant = $tenant;
        $product_threshold->product_id = $product_id;
        $product_threshold->stock_point_id = $stock_point_id;
        $product_threshold->threshold_qty = $threshold;
        $product_threshold->lc = $lc;
        $product_threshold->uom = $uom;
		$product_threshold->save();
	}
	public function sync($company_id, User $user = null, $ip = null)
	{
		$company = Company::find($company_id);
        $availableStock = AvailableStockThreshold::all();

        foreach($availableStock as $stock)
        {
            try
            {
                $itemCard = $this->itemCardIntransitService->get($company,$stock->product->nav_id);
            }
            catch(\Exception $e)
            {
            	$log = new Log;
				$log->action = 'NAV - Sync Threshold';
				if($user)
					$log->author()->associate($user);
				$log->ip_address = $ip;
				$log->comment = 'NAV Error - Product: ' .$stock->product->nav_id;
				$stock->logs()->save($log);
                continue;
            } 
            if(!$itemCard)
            {
            	$log = new Log;
				$log->action = 'NAV - Sync Threshold';
				if($user)
					$log->author()->associate($user);
				$log->ip_address = $ip;
				$log->comment = 'NAV Error - Empty Response: ' .$stock->product->nav_id;
				$stock->logs()->save($log);
                continue;
            }
            $location_code = $stock->stock_point->code;

            $inventory_method = 'get'.$location_code.'_Inventory';
            $inventory = (float)$itemCard->$inventory_method();

            if($inventory)
            {
                $stock->available_qty = $inventory;
                $stock->reorder_qty = $stock->threshold_qty - $inventory;
                $stock->save();
            }
        }
	}
	public function getStockThreshold(Array $params = [])
    {
    	$query = null;
    	if(array_has($params,'nav_id'))
    	{
    		$query = AvailableStockThreshold::whereHas('product',function($q) use($params){
					$q->where('nav_id','=',$params['nav_id']);
				});
    	}
    	if(array_has($params,'stockpoint'))
    	{
    		$query = AvailableStockThreshold::where('stock_point_id', '=',$params['stockpoint']);
    	}
    	if(array_has($params,'category'))
    	{
    		$query = AvailableStockThreshold::whereHas('product',function($q) use($params){
					$q->where('category_code','=',$params['category']);
					if(array_has($params,'collection'))
					{
						$q->where('collection_code','=',$params['collection']);
					}
					if(array_has($params,'colour'))
					{
						$q->where('colour_code','=',$params['colour']);
					}

				});
    	}
		if($query)
		{
			return $query->get();
		}
		return AvailableStockThreshold::all();
    }
	public function exportStock(int $companyId, array $params)
	{
		$category = null;
		$collection = null;
		$colour = null;
		//$query = AvailableStockThreshold::all();
		$query = null;
		$file = '';
		if (array_has($params['params'], 'filters') && count($params['params']['filters'])) {
			$filters = $params['params']['filters'];
			if (array_has($filters, 'category')) {
				$query = AvailableStockThreshold::whereHas('product', function ($q) use ($filters) {
					$q->where('category_code', '=', $filters['category']);
					if (array_has($filters, 'collection')) {
						$q->where('collection_code', '=', $filters['collection']);
					}
					if (array_has($filters, 'colour')) {
						$q->where('colour_code', '=', $filters['colour']);
					}
				});
			}
			if (array_has($filters, 'stockpoint')) {
				if ($query) {
					$query->where('stock_point_id', '=', $filters['stockpoint']);
				} else {
					$query = AvailableStockThreshold::where('stock_point_id', '=', $filters['stockpoint']);
				}
			}
			/*if(array_has($filters,'quantity'))
			{
				preg_match('/([=<>])(\d+)/',urldecode($filters['quantity']),$matches);
				$operator = $matches[1];
				$quantity = $matches[2];
				if($query)
					$query->where('quantity',$operator,$quantity);
				else
					$query = AvailableStockThreshold::where('quantity',$operator,$quantity);
				}
			}*/
			$inventory = $query->get();
			if ($inventory) {
				$spreadsheet = new Spreadsheet();
				$activeSheet = $spreadsheet->getActiveSheet()->setTitle("Available_Threshold_Stock");;
				$activeSheet->setCellValue('A1', 'Stock Point')->getStyle('A1')->getFont()->setBold(true);
				$activeSheet->setCellValue('B1', 'NAV ID')->getStyle('B1')->getFont()->setBold(true);
				$activeSheet->setCellValue('C1', 'Product')->getStyle('C1')->getFont()->setBold(true);
				$activeSheet->setCellValue('D1', 'Category')->getStyle('D1')->getFont()->setBold(true);
				$activeSheet->setCellValue('E1', 'Collection')->getStyle('E1')->getFont()->setBold(true);
				$activeSheet->setCellValue('F1', 'Colour')->getStyle('F1')->getFont()->setBold(true);
				$activeSheet->setCellValue('G1', 'Base UOM')->getStyle('G1')->getFont()->setBold(true);
				$activeSheet->setCellValue('H1', 'LC')->getStyle('H1')->getFont()->setBold(true);
				$activeSheet->setCellValue('I1', 'Threshold Quantity')->getStyle('I1')->getFont()->setBold(true);
				$activeSheet->setCellValue('J1', 'Available Quantity')->getStyle('J1')->getFont()->setBold(true);
				$activeSheet->setCellValue('K1', 'Reorder Quantity')->getStyle('K1')->getFont()->setBold(true);
				$row = 2;
				foreach ($inventory as $stock) {
					$activeSheet->setCellValue('A' . $row, $stock->stock_point->name);
					$activeSheet->setCellValue('B' . $row, $stock->product->nav_id);
					$activeSheet->setCellValue('C' . $row, $stock->product->name);
					$activeSheet->setCellValue('D' . $row, $stock->product->category->code);
					$activeSheet->setCellValue('E' . $row, $stock->product->collection->code);
					if ($stock->product->colour)
						$activeSheet->setCellValue('F' . $row, $stock->product->colour->code);
					$activeSheet->setCellValue('G' . $row, $stock->uom);
					$activeSheet->setCellValue('H' . $row, $stock->lc);
					$activeSheet->setCellValue('I' . $row, $stock->threshold_qty);
					$activeSheet->setCellValue('J' . $row, $stock->available_qty);
					$activeSheet->setCellValue('K' . $row, $stock->reorder_qty);
					$row++;
				}
				for ($col = 'A'; $col !== 'K'; $col++) {
					$activeSheet->getColumnDimension($col)->setAutoSize(true);
				}
				$writer = new Xlsx($spreadsheet);
				$file = storage_path() . '/app/StockThreshold_' . $companyId . '.xlsx';
				$writer->save($file);
				return $file;
			}
		}
		return $file;
	}
	public function deleteStock(User $user,int $company_id,Array $ids)
	{
		return AvailableStockThreshold::where('company_id',$company_id)->whereIn('id',$ids)->delete();
	}
	public function updateThreshold(User $user, $companyId, $thresholdId, Array $params)
	{
		$threshold = AvailableStockThreshold::where('id',$thresholdId)->where('company_id',$companyId)->first();
		if(!$threshold)
			throw new ModelNotFoundException();
		$threshold->lc = $params['lc'];
		$threshold->threshold_qty = $params['threshold'];
		$threshold->uom = $params['uom'];
		$threshold->reorder_qty = $threshold->threshold_qty - $threshold->available_qty;
		$threshold->save();
		return $threshold;
	}
	public function importThreshold($company_id,$tenant,$stockpointId,UploadedFile $file,User $user = null, $ip = null)
	{
		$company = Company::find($company_id);
        $stock_point = StockPoint::find($stockpointId);
        $spreadsheet = IOFactory::load($file);
		$worksheet = $spreadsheet->getActiveSheet();
        $maxRow = $worksheet->getHighestRow();
        for($i=2;$i<=$maxRow;$i++)
        {
            try
            {
                $item_no = $worksheet->getCell('A'.$i)->getValue();
                $threshold = $worksheet->getCell('C'.$i)->getValue();
                $lc = $worksheet->getCell('B'.$i)->getValue();
                $uom = $worksheet->getCell('D'.$i)->getValue();
                if($item_no != null && $threshold != null)
                {
                    $product = Product::where('nav_id','=',$item_no)->first();
                    if($product) 
                    {
                        $thresholdStock = AvailableStockThreshold::where('product_id','=',$product->id)->where('tenant', '=',$tenant)->where('company_id', '=',$company->id)->where('stock_point_id', '=',$stock_point->id)->first();
                        if($thresholdStock)
                        {
                            $thresholdStock->threshold = $threshold;
                            $thresholdStock->save();
                        }
                        else
                        {
                            $this->create($product->id, $company->id, $stock_point->id, $tenant, $threshold, $lc, $uom);
                        }
                    }
                }
                else
                {
                }
            }
            catch(\Exception $e)
            {
                
            } 
        }
		$this->refreshStockThreshold($company_id,$user,$ip);
	}
	public function refreshStockThreshold($companyId, User $user = null, $ip = null)
	{
		$queuePrefix = config('brand.queue_prefix');
        $queue = $queuePrefix.'.jobs';
        SyncStockThreshold::dispatch($companyId,$user,$ip)->onQueue($queue);
	}
}