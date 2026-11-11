<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Services\NAV\CustomerServiceInterface;

class SyncDealers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:dealers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
            $dealers = [];
            $navDealers = $this->customerService->getDealers($company);
            foreach($navDealers as $navDealer)
            {
                //$dealer = Dealer::where('nav_id','=',$navDealer->getNo())->first();
                $dealer = $company->dealers()->where('nav_id','=',$navDealer->getNo())->first();//#353
                $dealers[] = $navDealer->getNo();
                if(!$dealer)
                {
                    $dealer = new Dealer;
                    $dealer->company()->associate($company);
                }
                $dealer->nav_id = $navDealer->getNo();
                if($navDealer->getName() === null)
                    $dealer->name = "";
                else
                    $dealer->name = $navDealer->getName();
                if($navDealer->getDistrubutor_Code())
                {
                    $distributor = $company->distributors()->where('nav_id','=',$navDealer->getDistrubutor_Code())->first();
                    if($distributor)
                    {
                        $dealer->distributor()->associate($distributor);
                    }
                }
                $dealer->save();

            }
            $company->dealers()->whereNotIn('nav_id',$dealers)->delete();
        }
    }
}
