<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Price;
use Rosetta\Models\Product\Product;
use Rosetta\Services\NAV\SalesPriceServiceInterface;

class SyncProductPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:product:price {company}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync product prices NAV';
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
        $products = Product::all();
        $company->productPrices()->delete();
        foreach($products as $product)
        {
            $salesPrices = $this->salesPriceService->getProductPrices($company,$product);
            foreach($salesPrices as $salesPrice)
            {
                $price = new Price;
                $price->company()->associate($company);
                $price->product()->associate($product);
              //  $price->nav_key = $salesPrice->getKey();
                $price->price = $salesPrice->getUnit_Price();
                //$price->nav_key = $salesPrice->getKey();
                $price->save();
            }
        }
    }
}
