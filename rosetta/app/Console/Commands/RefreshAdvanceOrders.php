<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\AdvanceOrder\AdvanceOrder;
use Rosetta\Services\AdvanceOrders\AdvanceOrdersServiceInterface;

class RefreshAdvanceOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh:advance_orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Advance Orders from Navision';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $advanceOrdersService;
    public function __construct(AdvanceOrdersServiceInterface $advanceOrdersService)
    {
        $this->advanceOrdersService = $advanceOrdersService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $advanceOrders = AdvanceOrder::whereNotIn('status', ['Complete', 'Cancelled'])->get();
      //  $advanceOrders = AdvanceOrder::whereIn('status', ['Approved'])->get();
        foreach($advanceOrders as $advanceOrder)
        {
            echo "Updating {$advanceOrder->id}\n";
            $company = $advanceOrder->company()->get()->first();
            $customer = $advanceOrder->orderable()->get()->first();
            $advanceOrder = $this->advanceOrdersService->update($company, $customer, $advanceOrder->id);
        }
    }
}
