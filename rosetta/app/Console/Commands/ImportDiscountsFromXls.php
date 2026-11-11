<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Rosetta\Models\Discount;
use Rosetta\Models\Product\Product;
use Rosetta\Models\Region;

class ImportDiscountsFromXls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:discounts {region} {customer_type} {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Discounts for products from xls';

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
        $region = Region::find($this->argument('region'));
        $file = $this->argument('file');
        $customerType = $this->argument('customer_type');   
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
            $discountPercent = round($worksheet->getCell('J'.$i)->getCalculatedValue()*100);
            $product = Product::where('nav_id','=',$nav_id)->first();
            if(!$product)
                continue;
            /*find existing discount if any */
            echo "$nav_id, $discountPercent\n";
            $discountQuery = Discount::where('region_id','=',$region->id)
                ->where('customer_type','=',$customerType)
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
                $discount = new Discount;
                $discount->region()->associate($region);
                $discount->customer_type = $customerType;
                $discount->category()->associate($product->category);
                $discount->collection()->associate($collection);
                $discount->discount = $discountPercent;
                if($colour)
                {
                    $discount->colour()->associate($colour);
                }    
            }
            else
            {
                $discount->discount = $discountPercent;
            }
            $discount->save();
        }

    }
}
