<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Price;
use Rosetta\Models\Product\Product;
use Rosetta\Services\NAV\SalesPriceServiceInterface;

class SyncProductPricesFromXls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:product:price {company} {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Product Prices from Excel file';
    protected $salesPriceService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(SalesPriceServiceInterface $salesPriceService)
    {
        $this->salesPriceService = $salesPriceService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $company = Company::find($this->argument('company'));
        $file = $this->argument('file');
        $spreadsheet = IOFactory::load($file);
        if(!file_exists($file))
        {
            echo "file $file does not exist\n";
            exit(0);
        }
        $worksheet = $spreadsheet->getActiveSheet();
        $maxRow = $worksheet->getHighestRow();
        for($i=2;$i<=$maxRow;$i++)
        {
            $nav_id = $worksheet->getCell('A'.$i)->getValue();
            $unitPrice = round($worksheet->getCell('F'.$i)->getCalculatedValue(),2);
            $product = Product::where('nav_id','=',$nav_id)->first();

            if(!$product)
                continue;
            $navPrice = $this->salesPriceService->getProductPrices($company,$product);
            $productPrice = Price::where('company_id','=',$company->id)->where('product_id','=',$product->id)->first();
            if(!$productPrice)
            {
                $productPrice = new Price;
                $productPrice->company()->associate($company);
                $productPrice->product()->associate($product);
            }
            if(count($navPrice))
            {
                $navPrice = $navPrice[0];
            }
            else
            {
                $navPrice = null;
            }

            if($navPrice == null)
            {
                $navPrice = $this->salesPriceService->addProductPrice($company,$product,$unitPrice);
            }
            else
            {
                $navPrice = $this->salesPriceService->updateProductPrice($company,$navPrice,$unitPrice);
            }
            $productPrice->nav_key = $navPrice->getKey();
            $productPrice->price = $unitPrice;
            $productPrice->save();
        }
    }
}
