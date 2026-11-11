<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Invoice\GeneralProductPostingGroup;
use Rosetta\Services\NAV\GeneralProductPostingGroupServiceInterface;

class SyncGeneralProductPostingGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:general_product_posting_group';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync General Product Posting Group from Navision';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $generalProductPostingGroupService;
    public function __construct(GeneralProductPostingGroupServiceInterface $generalProductPostingGroupService)
    {
        $this->generalProductPostingGroupService = $generalProductPostingGroupService;
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
        $navGeneralProductPostingGroups = $this->generalProductPostingGroupService->getGeneralProductPostingGroups($company);
        foreach ($navGeneralProductPostingGroups as $navGeneralProductPostingGroup)
        {
            if($navGeneralProductPostingGroup->getCode() != null)
            {
                $generalProductPostingGroup = GeneralProductPostingGroup::where('code','=',$navGeneralProductPostingGroup->getCode())->first();
                if(!$generalProductPostingGroup)
                {
                    $generalProductPostingGroup = new GeneralProductPostingGroup;
                }
                $generalProductPostingGroup->code = $navGeneralProductPostingGroup->getCode();
                $generalProductPostingGroup->description = $navGeneralProductPostingGroup->getDescription();
                $generalProductPostingGroup->save();
            }
        }
    }
}
