<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\User;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;


class AddNewActiveProductAttributesForAllCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:customer_new_active_product_attributes_all_customers {companyId} {category} {collection} {colour}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adds New Active Product Attributes for Distributors and Dealers for a newly added product';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $activeProductAttributesService;
    public function __construct(ActiveProductAttributesServiceInterface $activeProductAttributesService)
    {
        $this->activeProductAttributesService = $activeProductAttributesService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $companyId = $this->argument('companyId');
        $category = $this->argument('category');
        $collection = $this->argument('collection');
        $colour = $this->argument('colour');
        $company = Company::find($companyId);
        $distributorIds = $company->distributors->pluck('id');
        foreach($distributorIds as $distributorId)
        {
            $this->activeProductAttributesService->loadNew($companyId, 'distributor', $distributorId, $category, $collection, $colour);
        }
        $dealerIds = $company->dealers->pluck('id');
        foreach($dealerIds as $dealerId)
        {
            $this->activeProductAttributesService->loadNew($companyId, 'dealer', $dealerId, $category, $collection, $colour);
        }
    }
}
