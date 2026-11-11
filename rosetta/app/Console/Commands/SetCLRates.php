<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\CLRate;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Category;

class SetCLRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cl:rates:set';

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
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $categories = Category::where('allow_cl','=',true)->whereNotNull('cl_percent')->get();
        foreach($categories as $category)
        {
            $companies = Company::all();
            foreach($companies as $company)
            {
                $cl = new CLRate;
                $cl->category()->associate($category);
                $cl->related_type = 'DISTRIBUTO';
                $cl->percent = $category->cl_percent;
                $company->cl_rates()->save($cl);

                $cl = new CLRate;
                $cl->category()->associate($category);
                $cl->related_type = 'DEALER';
                $cl->percent = $category->cl_percent;
                $company->cl_rates()->save($cl);

                foreach($company->distributors as $distributor)
                {
                    $cl = new CLRate;
                    $cl->category()->associate($category);
                    $cl->related_type = 'DISTRIBUTO';
                    $cl->percent = $category->cl_percent;
                    $distributor->cl_rates()->save($cl);

                    $cl = new CLRate;
                    $cl->category()->associate($category);
                    $cl->related_type = 'DEALER';
                    $cl->percent = $category->cl_percent;
                    $distributor->cl_rates()->save($cl);
                }
            }
        }
    }
}
