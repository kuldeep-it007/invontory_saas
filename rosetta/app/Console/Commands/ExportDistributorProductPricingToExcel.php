<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\Product\Price;
use Rosetta\Models\Product\Product;

class ExportDistributorProductPricingToExcel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:price {distributorId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export product pricing for Distributor';

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
        $activeSheet->setCellValue('C1' , 'category_code')->getStyle('C1')->getFont()->setBold(true);
        $activeSheet->setCellValue('D1' , 'collection_code')->getStyle('D1')->getFont()->setBold(true);
        $activeSheet->setCellValue('E1' , 'colour_code')->getStyle('E1')->getFont()->setBold(true);
        $activeSheet->setCellValue('F1' , 'unit_price')->getStyle('F1')->getFont()->setBold(true);
        $activeSheet->setCellValue('G1' , 'discount')->getStyle('G1')->getFont()->setBold(true);
        $activeSheet->setCellValue('H1' , 'discounted_price')->getStyle('H1')->getFont()->setBold(true);

        $row = 2;
        $distributor = Distributor::find($this->argument('distributorId')); 
        $companyId = $distributor->company_id;
        $discountService = resolve('Rosetta\Services\Discount\DiscountsServiceInterface');
        $categoryCodes = [];
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

        foreach ($products as $product)
        {
            $activeSheet->setCellValue('A'.$row, $product->id);
            $activeSheet->setCellValue('B'.$row, $product->nav_id);
            $activeSheet->setCellValue('C'.$row, $product->category_code);
            $activeSheet->setCellValue('D'.$row, $product->collection_code);
            $activeSheet->setCellValue('E'.$row, $product->colour_code);
            $price = $product->prices()->where('company_id','=',$companyId)->first();
            if($price)
                $activeSheet->setCellValue('F'.$row, $price->price);
            $discount = $discountService->getDiscount('DISTRIBUTO',$distributor,$product);
            $activeSheet->setCellValue('G'.$row, $discount);
            if($discount && $price)
            {
                $discountedPrice = round($price->price - ($price->price * ($discount / 100)), 2);
                $activeSheet->setCellValue('H'.$row, $discountedPrice);
            }
            $row++;
        }
        for($col = 'A'; $col !== 'H'; $col++) 
        {
            
                $activeSheet->getColumnDimension($col)
                    ->setAutoSize(true);
        }
        $writer = new Xlsx($spreadsheet);
        $writer->save('DistributorProductPricing.xlsx');
    }
}
