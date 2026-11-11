<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Services\NAV\CustomerServiceInterface;

class SyncCustomerGSTIN extends Command//#834
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:customer_gstin {companyId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Dealer and Distributor GSTIN from NAV';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $customerService;
    public function __construct(CustomerServiceInterface $customerService)
    {
        $this->customerService = $customerService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $company = Company::find($this->argument('companyId'));
      
        $dealers = $company->dealers()->get();
        foreach($dealers as $dealer)
        {
            if($dealer->nav_id !=  null)
            {
                $customerCard = $this->customerService->get($company, $dealer->nav_id);
                if($customerCard != null)
                {
                    if($dealer->gstin == null)
                        $dealer->gstin = $customerCard->getGST_Registration_No();
                    $dealer->save();
                }
            }
        }

        $distributors = $company->distributors()->get();
        foreach($distributors as $distributor)
        {
            if($distributor->nav_id !=  null)
            {
                $customerCard = $this->customerService->get($company, $distributor->nav_id);
                if($customerCard != null)
                {
                    if($distributor->gstin == null)
                        $distributor->gstin = $customerCard->getGST_Registration_No();
                    $distributor->save();
                }
            }
        }
    }
}
