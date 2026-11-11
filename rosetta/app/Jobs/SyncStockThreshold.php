<?php

namespace Rosetta\Jobs;

use Rosetta\Models\User;
use Rosetta\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;

class SyncStockThreshold implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $company_id;
    protected $user;
    protected $ip;
    public function __construct(int $company_id, User $user = null,$ip = null)
    {
        $this->company_id = $company_id;
        $this->user = $user;
        $this->ip = $ip;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $service = resolve('Rosetta\Services\StockThreshold\StockThresholdServiceInterface');
        $service->sync($this->company_id,$this->user,$this->ip);
    }
}
