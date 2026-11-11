<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Collection;
use Rosetta\Services\NAV\ProductGroupsServiceInterface;

class SyncCollectionCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:collection_codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Item Collection Code from Navision';

    /**
     * Create a new command instance.
     *
     * @return void
     */

    protected $collectionCodeService;
    public function __construct(ProductGroupsServiceInterface $collectionCodeService)
    {
        $this->collectionCodeService = $collectionCodeService;
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
        $navCollectionCodes = $this->collectionCodeService->getCollectionCodes($company);
        foreach ($navCollectionCodes as $navCollectionCode)
        {
            if($navCollectionCode->getItem_Category_Code() != null && $navCollectionCode->getCode() != null)
            {
                $collectionCode = Collection::where('code','=',$navCollectionCode->getCode())->where('category_code', '=',$navCollectionCode->getItem_Category_Code())->first();
                if(!$collectionCode)
                {
                    $collectionCode = new Collection;
                }
                $collectionCode->code = $navCollectionCode->getCode();
                $collectionCode->description = $navCollectionCode->getDescription();
                $collectionCode->category_code = $navCollectionCode->getItem_Category_Code();
                $collectionCode->save();
            }
        }
    }
}
