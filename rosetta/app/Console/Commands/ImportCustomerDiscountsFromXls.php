<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Rosetta\Models\CustomerDiscount;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;

class ImportCustomerDiscountsFromXls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:customer_discounts {customer_type} {file} {distributorId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Customer Discounts for products from xls';

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
        $file = $this->argument('file');
        $customerType = $this->argument('customer_type');  
        $distributorId = $this->argument('distributorId');   
        $distributor = Distributor::find($distributorId);

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
            echo "----- Processing row $i ----- \n";
            
            $nav_id = $worksheet->getCell('A'.$i)->getValue();
           // $discountPercent = $worksheet->getCell('F'.$i)->getCalculatedValue();
            $discountedPrice = $worksheet->getCell('F'.$i)->getCalculatedValue();
            $product = Product::where('nav_id','=',$nav_id)->first();
            if(!$product)
            {
                echo "----- Product not found $nav_id ----- \n";
                continue;
            }
            $companyPrice = $product->prices()->where('company_id','=',$distributor->company_id)->first();
            if(!$companyPrice)
            {
                echo "----- Price not found $nav_id ----- \n";
                continue;
            }
            $unitPrice = $companyPrice->price;
            $discountPercent = round((($unitPrice - $discountedPrice)/$unitPrice) * 100, 2);
            /*find existing discount if any */
            echo "$nav_id, $discountPercent\n";
            $discountQuery = CustomerDiscount::where('customer_type','=',$customerType)
                ->whereNull('customer_id')
                ->where('distributor_id', '=', $distributorId)
                ->where('category_code','=',$product->category->code)
                ->where('collection_code','=',$product->collection_code);
            if($product->colour_code)
            {
                $collection = $product->category->collections()->find($product->collection_code);
                $colour = $collection->colours()->find($product->colour_code);
                if($colour)
                {
                    $discountQuery->where('colour_code','=',$colour->code);
                }
            }
            $discount = $discountQuery->first();
            if(!$discount)
            {
                if($discountPercent > 100)
                {
                    echo "$nav_id, discount percent can not be greater than 100\n";
                }
                else
                {
                    $discount = new CustomerDiscount;
                    $discount->customer_type = $customerType;
                    $discount->distributor_id = $distributorId;
                    $discount->category()->associate($product->category);
                    $discount->collection()->associate($collection);
                    if($discountPercent == null)
                        $discount->discount = 0;
                    else
                        $discount->discount = $discountPercent;
                    if($colour)
                    {
                        $discount->colour()->associate($colour);
                    }
                    $discount->save();    
                }
            }
            else
            {
                $discount->discount = $discountPercent;
                $discount->save();
            }
        }
    }
}
