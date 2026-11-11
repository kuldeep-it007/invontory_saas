<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\Product\Product;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;

class AddNewActiveProductAttributesForAllCustomersForSpecificCategoryCollectionColours extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:customer_active_product_attributes_for_specific_category_collection_colours {companyId} {category} {collection} {starting_nav_id} {ending_nav_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adds Active Product Attributes for a Company based on Active flag in master attribute tables and for specific category collection and colours';

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
        $category_code = $this->argument('category');
        $collection_code = $this->argument('collection');
        $starting_nav_id = $this->argument('starting_nav_id');
        $ending_nav_id = $this->argument('ending_nav_id');
        $company = Company::find($companyId);
        $colour_codes = Product::whereBetween('nav_id', [$starting_nav_id, $ending_nav_id])->get(['colour_code'])->pluck('colour_code')->toArray();
        $distributorIds = $company->distributors->pluck('id');
        foreach($distributorIds as $distributorId)
        {
            foreach ($colour_codes as $colour_code)
            {
                $this->activeProductAttributesService->loadNew($companyId, 'distributor', $distributorId, $category_code, $collection_code, $colour_code);
            }
        }
        $dealerIds = $company->dealers->pluck('id');
        foreach($dealerIds as $dealerId)
        {
            foreach ($colour_codes as $colour_code)
            {
                $this->activeProductAttributesService->loadNew($companyId, 'dealer', $dealerId, $category_code, $collection_code, $colour_code);
            }
        }
    }
}
