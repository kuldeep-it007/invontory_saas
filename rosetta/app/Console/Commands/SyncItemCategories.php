<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Category;
use Rosetta\Services\NAV\ItemCategoriesServiceInterface;

class SyncItemCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:item_categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Item Category Code from Navision';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $itemCategoriesService;
    public function __construct(ItemCategoriesServiceInterface $itemCategoriesService)
    {
        $this->itemCategoriesService = $itemCategoriesService;
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
        $navItemCategories = $this->itemCategoriesService->getItemCategories($company);
        foreach ($navItemCategories as $navItemCategory)
        {
            if($navItemCategory->getCode() != null)
            {
                $categoryCode = Category::find($navItemCategory->getCode());
                if(!$categoryCode)
                {
                    $categoryCode = new Category;
                }
                $categoryCode->code = $navItemCategory->getCode();
                $categoryCode->description = $navItemCategory->getDescription();
                $categoryCode->save();
            }
        }
    }
}
