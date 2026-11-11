<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Price;
use Rosetta\Models\Product\Product;
use Rosetta\Services\NAV\ItemCardIntransitServiceInterface;

class SyncProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $itemCardIntransitService;
    public function __construct(ItemCardIntransitServiceInterface $itemCardIntransitService)
    {
        $this->itemCardIntransitService = $itemCardIntransitService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $companyId = config('nav.default_company');
        $company = Company::find($companyId);
        $items = $this->itemCardIntransitService->getProducts($company);
        foreach($items as $item)
        {
            if($item->getDescription() != null && $item->getItem_Category_Code() != null)
            {
                $productData = Product::where('nav_id','=',$item->getNo());
                $productCount = clone $productData;
                $productsData = clone $productData;
                $productCount = $productCount->count();
                $product = $productsData->first();

                if($productCount > 1)
                {
                    $products = clone $productData;
                    $products = $products->orderBy('id','desc')->skip(1)->get();
                    foreach($products as $p)
                    {
                        $p->delete();
                    }
                }

                if(!$product)
                {
                    $product = new Product;
                }
                $product->nav_id = $item->getNo();
                $product->name = $item->getDescription();
                $product->collection_code = $item->getProduct_Group_Code();
                $product->colour_code = $item->getColour();
                $product->category_code = $item->getItem_Category_Code();
                $product->base_unit_of_measure = $item->getBase_Unit_of_Measure();
                $product->purchase_unit_of_measure = $item->getPurch_Unit_of_Measure();
                $product->sales_unit_of_measure = $item->getSales_Unit_of_Measure();
                $product->save();
            }
            else
            {
                echo "ERROR " . $item->getNo() . "\n";
            }
        }
        //$products = Product::all();
    }
}
