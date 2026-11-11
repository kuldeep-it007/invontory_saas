<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Product\AvailableStockThreshold;
use Rosetta\Models\Product\Product;
use Rosetta\Services\NAV\ItemCardIntransitServiceInterface;

class SyncAvailableStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:availableStock {companyId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync available Stock NAV';
    protected $itemCardIntransitService;
    /**
     * Create a new command instance.
     *
     * @return void
     */
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
        $company = Company::find($this->argument('companyId'));
        $availableStock = AvailableStockThreshold::all();

        foreach($availableStock as $stock)
        {
            try
            {
                $itemCard = $this->itemCardIntransitService->get($company,$stock->product->nav_id);
            }
            catch(\Exception $e)
            {
                echo "NAV Error for $stock->product->nav_id. "."\n";
                continue;
            } 
            if(!$itemCard)
            {
                echo "NAV Error for $stock->product->nav_id. "."\n";
                continue;
            }
            $location_code = $stock->stock_point->code;

            $inventory_method = 'get'.$location_code.'_Inventory';
            $inventory = (float)$itemCard->$inventory_method();

            if($inventory)
            {
                $stock->available_qty = $inventory;
                $stock->save();
            }
        }
    }
}
