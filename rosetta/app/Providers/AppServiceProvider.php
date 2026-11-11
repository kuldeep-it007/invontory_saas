<?php

namespace Rosetta\Providers;

use Illuminate\Support\ServiceProvider;
use OwenIt\Auditing\Models\Audit;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Models\AdvanceOrder\AdvanceOrder;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Observers\AdvanceBlanketOrderObserver;
use Rosetta\Observers\AdvanceOrderObserver;
use Rosetta\Observers\BlanketOrderObserver;
use Rosetta\Observers\PurchaseOrderObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        BlanketOrder::observe(BlanketOrderObserver::class);
        AdvanceBlanketOrder::observe(AdvanceBlanketOrderObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        AdvanceOrder::observe(AdvanceOrderObserver::class);
        Audit::creating(function (Audit $model) { $model->request_source = config('rosetta.request_source'); }); //#719

        // Append quantity to the request and share it globally
        if (strpos(request()->path(), 'inventory') !== false) {
            if (in_array(request()->quantity, ["%3E1", "%253E1", "%3E%3D1", ">=1"])) {
                request()->merge(['quantity' => ">=0"]);
            }
            if (!array_has(request()->all(), 'quantity')) {
                request()->merge(['quantity' => ">=0"]);
            }
        }
        
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}