<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Company\CustomerStockPoint;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Services\StockPoints\StockPointServiceInterface;

class LinkCustomersToStockPoint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'link:customers_to_stockPoints {companyId} {stockPointId} {customerType}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Links existing Customers to Stock Point. customerType should be either distributors or dealers';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $stockPointService;
    public function __construct(StockPointServiceInterface $stockPointService)
    {
        $this->stockPointService = $stockPointService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $stockPointId = $this->argument('stockPointId');
        $customerType = $this->argument('customerType');
        $companyId = $this->argument('companyId');
        $company = Company::find($companyId);
        
        $stockPoint = StockPoint::find($stockPointId);
        if($customerType == 'distributors')
        {
            $distributors = $company->distributors;
            foreach($distributors as $customer)
            {
                $customerStockPoint = $customer->stock_points()->where('stock_point_id','=',$stockPointId)->first();
                if(!$customerStockPoint)
                {
                    $stockPoint = StockPoint::find($stockPointId);
                    $customerStockPoint = new CustomerStockPoint;
                    $customerStockPoint->stock_point()->associate($stockPoint);
                    $customerStockPoint->save();
                    $customer->stock_points()->save($customerStockPoint);
                }

            }
        }
        if($customerType == 'dealers')
        {
            $dealers = $company->companyDealers;
            foreach($dealers as $customer)
            {
                $customerStockPoint = $customer->stock_points()->where('stock_point_id','=',$stockPointId)->first();
                if(!$customerStockPoint)
                {
                    $stockPoint = StockPoint::find($stockPointId);
                    $customerStockPoint = new CustomerStockPoint;
                    $customerStockPoint->stock_point()->associate($stockPoint);
                    $customerStockPoint->save();
                    $customer->stock_points()->save($customerStockPoint);
                }
            }
        }
    }
}
