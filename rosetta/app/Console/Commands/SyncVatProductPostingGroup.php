<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Invoice\VatProductPostingGroup;
use Rosetta\Services\NAV\VATProductPostingGroupServiceInterface;

class SyncVatProductPostingGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:vat_product_posting_group';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Vat Product Posting Group from Navision';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $vatProductPostingGroupService;

    public function __construct(VATProductPostingGroupServiceInterface $vatProductPostingGroupService)
    {
        $this->vatProductPostingGroupService = $vatProductPostingGroupService;
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
        $navVatProductPostingGroups = $this->vatProductPostingGroupService->getVatProductPostingGroups($company);
        foreach ($navVatProductPostingGroups as $navVatProductPostingGroup)
        {
            if($navVatProductPostingGroup->getCode() != null)
            {
                $vatProductPostingGroup = VatProductPostingGroup::where('code','=',$navVatProductPostingGroup->getCode())->first();
                if(!$vatProductPostingGroup)
                {
                    $vatProductPostingGroup = new VatProductPostingGroup;
                }
                $vatProductPostingGroup->code = $navVatProductPostingGroup->getCode();
                $vatProductPostingGroup->description = $navVatProductPostingGroup->getDescription();
                $vatProductPostingGroup->save();
            }
        }

    }
}
