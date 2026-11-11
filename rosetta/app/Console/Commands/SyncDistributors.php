<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Distributor;
use Rosetta\Services\NAV\CustomerServiceInterface;

class SyncDistributors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:distributors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync distributors from Navision for all companies';

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
        $companies = Company::all();
        foreach($companies as $company)
        {
            $distributors = [];
            $navDistributors = $this->customerService->getDistributors($company);
            foreach($navDistributors as $navDistributor)
            {
                $distributor = $company->distributors()->where('nav_id','=',$navDistributor->getNo())->first();
                $distributors[] = $navDistributor->getNo();
                if(!$distributor)
                {
                    $distributor = new Distributor;
                    $distributor->company()->associate($company);
                }
                $distributor->nav_id = $navDistributor->getNo();
                $distributor->name = $navDistributor->getName();
                $distributor->city_code = $navDistributor->getCity();
                $distributor->state_code = $navDistributor->getState_Code();
                $distributor->post_code = $navDistributor->getPost_Code();
                $distributor->save();

            }
            $company->distributors()->whereNotIn('nav_id',$distributors)->delete();
        }
    }
}
