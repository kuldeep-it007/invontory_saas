<?php

namespace Rosetta\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Rosetta\Models\Company;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;

class ProcessActiveProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $productAttribute, $company;
    public function __construct(ActiveProductAttributes $productAttribute, Company $company)
    {
        $this->productAttribute = $productAttribute;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
    //    var_dump("here...");
             
        $activeProductAttributesService = resolve(ActiveProductAttributesServiceInterface::class);
        $customers = $this->company->distributors;
        $customers = $customers->concat($this->company->dealers);
        foreach ($customers as $customer)
        {
            $attr = $this->productAttribute->replicate();   
            list($recordExists, $noRecord) = $activeProductAttributesService->find($attr, $customer);
            if($noRecord)
            {
                $customer->activeProductAttributes()->save($attr);
            }
        }
    }
}
