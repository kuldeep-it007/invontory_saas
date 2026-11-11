<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\Product\Product;

class AddCompanyActiveProductAttributesForSpecificCategoryCollectionColours extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:company_active_product_attributes_for_specific_category_collection_colours {companyId} {category} {collection} {starting_nav_id} {ending_nav_id}';

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
        foreach ($colour_codes as $colour_code)
        {
            $attribute = new ActiveProductAttributes;
            $attribute->company_id = $company->id;
            $attribute->category_code = $category_code;
            $attribute->category_display = $category_code;
            $attribute->collection_code = $collection_code;
            $attribute->collection_display = $collection_code;
            $attribute->colour_code = $colour_code;
            $attribute->colour_display = $colour_code;
            $company->activeProductAttributes()->save($attribute);
        }
    }
}
