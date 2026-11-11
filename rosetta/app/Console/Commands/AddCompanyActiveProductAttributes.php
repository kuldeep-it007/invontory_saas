<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;

class AddCompanyActiveProductAttributes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:company_active_product_attributes {companyId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adds Active Product Attributes for a Company based on Active flag in master attribute tables';

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
        $company = Company::find($companyId);
        $categories = Category::all()->where('active','=',1);
        foreach ($categories as $category) 
        {
            $categoryAttribute = new ActiveProductAttributes;
            $categoryAttribute->company_id = $company->id;
            $categoryAttribute->category_code = $category->code;
            $categoryAttribute->category_display = $category->code;
            $company->activeProductAttributes()->save($categoryAttribute);
            $collections = Collection::where('category_code', '=', $category->code)->where('active', '=', 1)->get();
            foreach ($collections as $collection)
            {
                $parentAttribute = new ActiveProductAttributes;
                $parentAttribute->company_id = $company->id;
                $parentAttribute->category_code = $category->code;
                $parentAttribute->category_display = $category->code;
                $parentAttribute->collection_code = $collection->code;
                $parentAttribute->collection_display = $collection->code;
                $company->activeProductAttributes()->save($parentAttribute);
                $colours = Colour::where('collection_code', '=', $collection->code)->where('active', '=', 1)->get();
                foreach ($colours as $colour)
                {
                    $attribute = new ActiveProductAttributes;
                    $attribute->company_id = $company->id;
                    $attribute->category_code = $category->code;
                    $attribute->category_display = $category->code;
                    $attribute->collection_code = $collection->code;
                    $attribute->collection_display = $collection->code;
                    $attribute->colour_code = $colour->code;
                    $attribute->colour_display = $colour->code;
                    $company->activeProductAttributes()->save($attribute);
                }
            }
        }
    }
}
