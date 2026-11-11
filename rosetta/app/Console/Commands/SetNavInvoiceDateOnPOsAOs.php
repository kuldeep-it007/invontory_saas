<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\AdvanceOrder\AdvanceOrder;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Services\PurchaseOrders\PurchaseOrdersServiceInterface;

class SetNavInvoiceDateOnPOsAOs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'udpate:pos_aos_nav_inv_date';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update NAV Invoice Date on Purchase & Advance Orders';

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
        $pos = PurchaseOrder::whereNotNull('nav_invoice_date')->whereNull('distributor_id')->get();
        echo "Updating Purchase Orders\n";
        foreach($pos as $po)
        {
            $bo = $po->blanket_order;
            $relatedPO = $bo->relatedOrder;
            if($relatedPO)
            {
                if($relatedPO->nav_invoice_number != null)
                {
                    echo "Updating {$relatedPO->id}\n";
                    $relatedPO->nav_invoice_date = $po->nav_invoice_date;
                    $relatedPO->save();
                }
            }
        }

        $aos = AdvanceOrder::whereNotNull('nav_invoice_date')->whereNull('distributor_id')->get();
        echo "Updating Advance Orders\n";
        foreach($aos as $ao)
        {
            $bo = $ao->advance_blanket_order;
            $relatedAO = $bo->relatedOrder;
            if($relatedAO)
            {
                if($relatedAO->nav_invoice_number != null)
                {
                    echo "Updating {$relatedAO->id}\n";
                    $relatedAO->nav_invoice_date = $ao->nav_invoice_date;
                    $relatedAO->save();
                }
            }
        }
    }
}
