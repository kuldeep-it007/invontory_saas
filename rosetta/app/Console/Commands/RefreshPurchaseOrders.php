<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Services\PurchaseOrders\PurchaseOrdersServiceInterface;

class RefreshPurchaseOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh:purchase_orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Purchase Orders from Navision';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $purchaseOrdersService;
    public function __construct(PurchaseOrdersServiceInterface $purchaseOrdersService)
    {
        $this->purchaseOrdersService = $purchaseOrdersService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $purchaseOrders = PurchaseOrder::whereNotIn('status', ['Complete', 'Cancelled'])->where('navision_order_number','!=','')->get();
        foreach($purchaseOrders as $purchaseOrder)
        {
            echo "Updating {$purchaseOrder->id}\n";
            $company = $purchaseOrder->company()->get()->first();
            $customer = $purchaseOrder->orderable()->get()->first();
            $purchaseOrder = $this->purchaseOrdersService->update($company, $customer, $purchaseOrder->id);
        }
    }
}
