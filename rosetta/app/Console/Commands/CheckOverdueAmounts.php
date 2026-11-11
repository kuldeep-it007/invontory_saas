<?php

namespace Rosetta\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Rosetta\Models\OverdueAmount;
use Rosetta\Services\SalesOrders\SalesOrdersServiceInterface;

class CheckOverdueAmounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:overdue_amounts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks records from overdue_amounts table and delete record if overdue_date is before today, further add that amount to Dealers overdue_balance';

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
        $date = Carbon::today()->subDay()->endOfDay()->format('U');//yesterday midnight
        $overdueAmounts = OverdueAmount::where('overdue_date', '<=',$date)->get();
        foreach($overdueAmounts as $overdueAmount)
        {
            $dealer = $overdueAmount->overdueable;
            if($dealer)
            {
                $dealer->overdue_balance += $overdueAmount->amount;
                $dealer->save();
                $overdueAmount->delete();  
            }
        }
    }
}
