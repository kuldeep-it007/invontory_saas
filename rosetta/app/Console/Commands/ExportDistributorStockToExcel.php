<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Rosetta\Models\Company;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\Product\Price;
use Rosetta\Models\Product\Product;

class ExportDistributorStockToExcel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    //protected $signature = 'export:stock {companyId} {distributorId} {category=""} {collection=""} {colour=""}';
    protected $signature = 'export:stock {companyId} {distributorId} {category?} {collection?} {colour?}';
   // protected $signature = 'export:stock {companyId} {distributorId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export Distributor Stock';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $spreadsheet = new Spreadsheet();
        $activeSheet = $spreadsheet->getActiveSheet();
        $activeSheet->setCellValue('A1' , 'id')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1' , 'nav_id')->getStyle('B1')->getFont()->setBold(true);
        $activeSheet->setCellValue('C1' , 'batch_no')->getStyle('C1')->getFont()->setBold(true);
        $activeSheet->setCellValue('D1' , 'quantity')->getStyle('D1')->getFont()->setBold(true);
        
        $row = 2;
        $distributor = Distributor::find($this->argument('distributorId')); 
        //$companyId = $distributor->company_id;
        $companyId = Company::find($this->argument('companyId'));
        $distributorService = resolve('Rosetta\Services\Distributor\DistributorServiceInterface');
 /*       $categoryCodes = [];
        $collectionCodes = [];
        $colourCodes = [];

        $categories = Category::where('active','=', '1')->get();
        foreach ($categories as $category ) 
        {
            $categoryCodes[] = $category->code;
        }
        $collections = Collection::whereIn('category_code', $categoryCodes)->where('active','=', '1')->get();
        foreach ($collections as $collection ) 
        {
            $collectionCodes[] = $collection->code;
        }
        $colours = Colour::whereIn('collection_code', $collectionCodes)->where('active','=', '1')->get();
        foreach ($colours as $colour ) 
        {
            $colourCodes[] = $colour->code;
        }
        $products = Product::whereIn('category_code', $categoryCodes)->whereIn('collection_code', $collectionCodes)
                            ->whereIn('colour_code', $colourCodes)->get();
*/
       

        $params = [];
        $params['params']['filters'] ='';
        if($this->argument('category'))
            $params['params']['filters']['category'] = $this->argument('category');
        //else
       //     $params['params']['filters']['category'] = '';
        if($this->argument('collection'))
            $params['params']['filters']['collection'] = $this->argument('collection');
       // else
         //   $params['params']['filters']['collection'] = '';
        if($this->argument('colour'))
            $params['params']['filters']['colour'] = $this->argument('colour');
        //else
          //  $params['params']['filters']['colour'] = '';
        $params['params']['per_page'] = 1000;
        $params['params']['page'] = 1;
        $inventory = $distributorService->getInventory($distributor->id,$params);

        foreach ($inventory as $stock)
        {
            $activeSheet->setCellValue('A'.$row, $stock->id);
            $activeSheet->setCellValue('B'.$row, $stock->nav_id);
            $activeSheet->setCellValue('C'.$row, $stock->batch_no);
            $activeSheet->setCellValue('D'.$row, $stock->quantity);
         /*   $price = $product->prices()->where('company_id','=',$companyId)->first();
            if($price)
                $activeSheet->setCellValue('F'.$row, $price->price);
            $discount = $discountService->getDiscount('DISTRIBUTO',$distributor,$product);
            $activeSheet->setCellValue('G'.$row, $discount);
            if($discount && $price)
            {
                $discountedPrice = round($price->price - ($price->price * ($discount / 100)), 2);
                $activeSheet->setCellValue('H'.$row, $discountedPrice);
            }*/
            $row++;
        }
        for($col = 'A'; $col !== 'D'; $col++) 
        {
            
                $activeSheet->getColumnDimension($col)
                    ->setAutoSize(true);
        }
        $writer = new Xlsx($spreadsheet);
        $writer->save('/rosetta/storage/app/DistributorStock.xlsx');
    }
}
