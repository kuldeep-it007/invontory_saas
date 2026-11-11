<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Services\NAV\CustomerServiceInterface;

class SyncCustomerAddress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:customer_address {companyId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Dealer and Distributor Address and Address 2 from NAV';

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
                if($customerCard)
                {
                    if($dealer->address == null)
                        $dealer->address = $customerCard->getAddress();
                    if($dealer->address_2 == null)
                        $dealer->address_2 = $customerCard->getAddress_2();
                    if($dealer->city_code == null)
                        $dealer->city_code = $customerCard->getCity();
                    if($dealer->state_code == null)
                        $dealer->state_code = $customerCard->getState_Code();
                    if($dealer->post_code == null)
                        $dealer->post_code = $customerCard->getPost_Code();
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
                if($customerCard)
                {
                    if($distributor->address == null)
                        $distributor->address = $customerCard->getAddress();
                    if($distributor->address_2 == null)
                        $distributor->address_2 = $customerCard->getAddress_2();
                    if($distributor->city_code == null)
                        $distributor->city_code = $customerCard->getCity();
                    if($distributor->state_code == null)
                        $distributor->state_code = $customerCard->getState_Code();
                    if($distributor->post_code == null)
                        $distributor->post_code = $customerCard->getPost_Code();
                    $distributor->save();
                }
            }
        }
    }
}
