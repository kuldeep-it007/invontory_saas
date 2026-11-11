<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Invoice\InventoryPostingGroup;
use Rosetta\Services\NAV\InventoryPosingGroupServiceInterface;

class SyncInventoryPostingGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:inventory_posting_group';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Inventory Posting Group from Navision';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $inventoryPostingGroupService;
    public function __construct(InventoryPosingGroupServiceInterface $inventoryPostingGroupService)
    {
        $this->inventoryPostingGroupService = $inventoryPostingGroupService;
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
        $navInventoryPostingGroups = $this->inventoryPostingGroupService->getInventoryPostingGroups($company);
        foreach ($navInventoryPostingGroups as $navInventoryPostingGroup)
        {
            if($navInventoryPostingGroup->getCode() != null)
            {
                $inventoryPostingGroup = InventoryPostingGroup::where('code','=',$navInventoryPostingGroup->getCode())->first();
                if(!$inventoryPostingGroup)
                {
                    $inventoryPostingGroup = new InventoryPostingGroup;
                }
                $inventoryPostingGroup->code = $navInventoryPostingGroup->getCode();
                $inventoryPostingGroup->description = $navInventoryPostingGroup->getDescription();
                $inventoryPostingGroup->save();
            }
        }
    }
}
