<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Rosetta\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Rosetta\Transformers\NAV\InventoryTransformer;
use Rosetta\Services\Companies\CompanyServiceInterface;
use Rosetta\Services\Inventory\InventoryServiceInterface;

class ExportController extends Controller
{
    protected $companyService;
    protected $inventoryService;
    public function __construct(CompanyServiceInterface $companyService,InventoryServiceInterface $inventoryService)
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        $this->companyService = $companyService;
        $this->inventoryService = $inventoryService;
    }

    /**
     * Export stock details into Excel using PhpSpreadsheet
     */
public function exportStocks()
{
    // dd($this->getCompanyInvontory());
    // 1. Fetch products
    $products = DB::table('products')
        ->select('id','category_code','collection_code','colour_code','nav_id')
        ->where('status',1)
        ->get();

    // 2. Fetch only active super stockists and distributors
    $distributors = DB::table('distributors')
        ->whereNull('deleted_at')
        ->pluck('name','id')
        ->toArray();

    $superStockists = DB::table('super_stockists')
        ->whereNull('deleted_at')
        ->pluck('name','id')
        ->toArray();

    // 3. Fetch stock data
    $stocks = DB::table('stocks')
        ->select('product_id','stockable_id','stockable_type','quantity')
        ->where('quantity','>',0)
        ->get();

    // 4. Build dynamic headers
    $partyNames = array_values($superStockists) + array_values($distributors);

    $matrix = [];
    foreach ($products as $product) {
        $row = [
            'Category'      => $product->category_code,
            'Collection'    => $product->collection_code,
            'Color'         => $product->colour_code,
            'Code'          => $product->nav_id,
            'Company Stock' => 0,
        ];

        foreach ($partyNames as $party) {
            $row[$party] = 0;
        }

        $matrix[$product->id] = $row;
    }

    // 5. Fill quantities
    foreach ($stocks as $stock) {
        if (!isset($matrix[$stock->product_id])) continue;

        $partyName = null;
        if ($stock->stockable_type === "Rosetta\\Models\\Distributor") {
            $partyName = $distributors[$stock->stockable_id] ?? null;
        } elseif ($stock->stockable_type === "Rosetta\\Models\\SuperStockist\\SuperStockist") {
            $partyName = $superStockists[$stock->stockable_id] ?? null;
        }

        if ($partyName) {
            $matrix[$stock->product_id][$partyName] += $stock->quantity;
            // $matrix[$stock->product_id]['Company Stock'] += $stock->quantity;
        }
    }

    // 6. Add "Total" column for each row
    foreach ($matrix as $id => $row) {
        $total = 0;
        foreach ($row as $key => $val) {
            if (in_array($key, ['Category','Collection','Color','Code'])) continue;
            $total += $val;
        }
        $matrix[$id]['Total'] = $total;
    }

    // 7. Create spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Headers (now include Total)
    $headers = array_keys(reset($matrix));
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col.'1', $header);
        $col++;
    }

    // Rows
    $rowNum = 2;
    foreach ($matrix as $rowData) {
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.$rowNum, $rowData[$header] ?? 0);
            $col++;
        }
        $rowNum++;
    }

    // Auto-size
    foreach (range('A', $col) as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }

    // 8. Stream file
    $writer = new Xlsx($spreadsheet);
    return new StreamedResponse(function () use ($writer) {
        $writer->save("php://output");
    }, 200, [
        'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition' => 'attachment; filename="stocks_report.xlsx"',
        'Cache-Control'       => 'max-age=0',
    ]);
}

public function getCompanyInvontory(){
    $customer = null;
    $companyId = 3;
    $params = [];
    $company = $this->companyService->find($companyId);
    $inventory = $this->companyService->getInventory(User::find(1),$companyId,$params, $customer);
            $inventory = $this->inventoryService->calculateCompanyStock($inventory,$customer,'>0', $companyId, 0, '');

            $inventory = collect($inventory);
            $inventory = $inventory->filter(function($item){
                return $item->getInventory() > 0;
            });

            $fractal = fractal()
                ->Collection($inventory)
                ->transformWith(new InventoryTransformer($company, null, $company))
                ->parseIncludes(['price']);

        $inventoryArray =  $fractal->toArray();     
        $inventoryArray = array_filter($inventoryArray, function($item) {
            return $item['inventory'] > 0;
        });
        $inventoryArray = array_values($inventoryArray);
}

}
