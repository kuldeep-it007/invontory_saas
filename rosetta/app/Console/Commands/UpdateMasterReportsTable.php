<?php

namespace Rosetta\Console\Commands;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Rosetta\Models\Dealer;
use Rosetta\Models\Company;
use Illuminate\Console\Command;
use Rosetta\Models\Distributor;
use Illuminate\Support\Facades\DB;
use Rosetta\Services\Reports\ReportsServiceInterface;

class UpdateMasterReportsTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:master_reports_table {companyId} {month} {year}';
   
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Master Reports Table. {month} can be Jan and {year} can be 2023. month and year should be last month and corresponding year. If you are executing the repport on 1st April 2023 then month will be Mar and year will be 2023. If run on 01-01-2023 then month will be Dec and year willbe 2022.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $reportsService;
    public function __construct(ReportsServiceInterface $reportsService)
    {
        $this->reportsService = $reportsService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    
    
    public function handle()
    {     
        $params = [];
        $companyId = $this->argument('companyId');
        $input_month = $this->argument('month');
        $input_year = $this->argument('year');
        $start_of_prev_month = Carbon::createFromFormat('d-M-Y',"1-{$input_month}-{$input_year}")->subMonth()->startOfMonth();
        $end_of_prev_month = Carbon::createFromFormat('d-M-Y',"1-{$input_month}-{$input_year}")->subMonth()->endOfMonth();
        $fiscal_year_end = null;
        if($start_of_prev_month->month > 3)
        {
            $fiscal_year_start = Carbon::createFromDate($start_of_prev_month->year,4,1)->startOfMonth();
            $fiscal_year_end = Carbon::createFromDate($start_of_prev_month->year+1,3, 31)->endOfMonth();
        }
        else
        {
            $fiscal_year_start = Carbon::createFromDate($start_of_prev_month->year - 1, 4, 1)->startOfMonth();
            $fiscal_year_end = Carbon::createFromDate($start_of_prev_month->year,3, 31)->endOfMonth();
        }
        $base_date = Carbon::createFromDate($fiscal_year_start->year,3,31);
     //   dd($base_date);
        $period = CarbonPeriod::create($fiscal_year_start->format('Y-m-d'), '1 month', $end_of_prev_month->format('Y-m-d'));
        $month_range = [];
        foreach ($period as $dt) 
        {
            $temp = $dt;
            $month_range[] = [ 
                "start" => $dt->startOfMonth()->format('U'),
                "end" => $dt->endOfMonth()->format('U'),
                //"month" => ()$dt->format('j'),
                "year" => $dt->format('Y'),
                "month" => $dt->format('M'),
                "month_short" => $dt->formatLocalized('%b'),
                //"year_label" => $dt->format('y')
                "back_date" => ($temp->subMonthsNoOverflow(2))->startOfMonth()->format('U'),
            ];
        }
        $avg_growth_percent_array = [];
        $avg_growth_in_orders_array = [];
        $avg_growth_in_active_dlrs_array = [];
        $avg_growth_no_of_dlrs_array = [];
        $avg_growth_in_active_duds_array = [];
        $avg_growth_no_of_duds_array = [];
        $avg_growth_in_active_rtc_array = [];
        $avg_growth_no_of_rtc_array = [];
        $base_order_cnt = 0;
        $base_dlr_cnt = 0;
        $base_rtc_cnt = 0;
        $base_dud_cnt = 0;
        $iterator = 0;
        $avg_sales_growth = 0;
        $avg_growth_in_orders = 0;

        $rec = collect(\DB::select('select * from master_reports where reportable_type = ? and reportable_id = ? and month = ? and year = ?', ['Rosetta\\Models\\Company', $companyId, $base_date->format('M'),$base_date->format('Y')]))->first();

        if($rec)
        {
            $base_order_cnt = $rec->no_of_orders;
            $base_dlr_cnt = $rec->no_of_dealers;//base cnt for next month is current month cnt
            $base_dud_cnt = $rec->no_of_duds;
            $base_rtc_cnt = $rec->no_of_retail_customers;
        }
        foreach($month_range as $m)
        {
            $record = collect(\DB::select('select * from master_reports where reportable_type = ? and reportable_id = ? and month = ? and year = ?', ['Rosetta\\Models\\Company', $companyId, $m['month'],$m['year']]))->first();

            if(!$record)
            {
                $company = Company::find($companyId);
                $dist_cnt = $company->distributors()->whereNull('deleted_at')->where('created_at', '<=', $m['end'])->count();
                $dlr_cnt = $company->companyDealers()->whereNull('deleted_at')->where('created_at', '<=', $m['end'])->count();
                $duds = Dealer::where('company_id', '=', $companyId)->whereNull('deleted_at')->where('type', '=', 'Dealer')->where('created_at', '<=', $m['end'])->whereHas('distributor', function($q){
                        $q->whereNull('deleted_at');
                    });
                $dud_cnt = $duds->count();

                $rtcs = Dealer::where('company_id', '=', $companyId)->whereNull('deleted_at')->where('type', '=', 'Retail Customer')->where('created_at', '<=', $m['end'])->whereHas('distributor', function($q){
                        $q->whereNull('deleted_at');
                    });
                $rtc_cnt = $rtcs->count();

             //   $dud_cnt = $company->distributorDealers()->where('type', '=', 'Dealer')->whereNull('deleted_at')->where('created_at', '<=', $m['end'])->count();
             //   $rtc_cnt = $company->distributorDealers()->where('type', '=', 'Retail Customer')->whereNull('deleted_at')->where('created_at', '<=', $m['end'])->count();

                $activeDealers = $company->companyDealers()->whereNull('deleted_at')->whereHas('orders', function($query) use($m){
                    if(array_has($m, 'back_date'))
                    {
                        $query->where('nav_invoice_date', '>=', $m['back_date']);
                    }
                    if(array_has($m, 'end'))
                    {
                        $query->where('nav_invoice_date', '<=', $m['end']);
                    }
                    $query->where('status','=','Complete');
                });
                $act_dlr_cnt = $activeDealers->count();

                $DealersOrderingCurMon = $company->companyDealers()->whereNull('deleted_at')->whereHas('orders', function($query) use($m){
                    if(array_has($m, 'start'))
                    {
                        $query->where('nav_invoice_date', '>=', $m['start']);
                    }
                    if(array_has($m, 'end'))
                    {
                        $query->where('nav_invoice_date', '<=', $m['end']);
                    }
                    $query->where('status','=','Complete');
                });
                $dlr_ordering_cur_mon_cnt = $DealersOrderingCurMon->count();

                $activeDuds = $company->distributorDealers()->whereNull('deleted_at')->where('type', '=', 'Dealer')->whereHas('orders', function($query) use($m){
                    if(array_has($m, 'back_date'))
                    {
                        $query->where('nav_invoice_date', '>=', $m['back_date']);
                    }
                    if(array_has($m, 'end'))
                    {
                        $query->where('nav_invoice_date', '<=', $m['end']);
                    }
                    $query->where('status','=','Complete');
                });
                $act_dud_cnt = $activeDuds->count();

                $DudsOrderingCurMon = $company->distributorDealers()->whereNull('deleted_at')->where('type', '=', 'Dealer')->whereHas('orders', function($query) use($m){
                    if(array_has($m, 'start'))
                    {
                        $query->where('nav_invoice_date', '>=', $m['start']);
                    }
                    if(array_has($m, 'end'))
                    {
                        $query->where('nav_invoice_date', '<=', $m['end']);
                    }
                    $query->where('status','=','Complete');
                });
                $dud_ordering_cur_mon_cnt = $DudsOrderingCurMon->count();

                $activeRTCs = $company->distributorDealers()->whereNull('deleted_at')->where('type', '=', 'Retail Customer')->whereHas('orders', function($query) use($m){
                    if(array_has($m, 'back_date'))
                    {
                        $query->where('nav_invoice_date', '>=', $m['back_date']);
                    }
                    if(array_has($m, 'end'))
                    {
                        $query->where('nav_invoice_date', '<=', $m['end']);
                    }
                    $query->where('status','=','Complete');
                });
                $act_rtc_cnt = $activeRTCs->count();

                $RTCsOrderingCurMon = $company->distributorDealers()->whereNull('deleted_at')->where('type', '=', 'Retail Customer')->whereHas('orders', function($query) use($m){
                    if(array_has($m, 'start'))
                    {
                        $query->where('nav_invoice_date', '>=', $m['start']);
                    }
                    if(array_has($m, 'end'))
                    {
                        $query->where('nav_invoice_date', '<=', $m['end']);
                    }
                    $query->where('status','=','Complete');
                });
                $rtc_ordering_cur_mon_cnt = $RTCsOrderingCurMon->count();

                $dist_2C_direct_sales = collect(\DB::select('select IFNULL(sum(l.amount), 0) Amount from purchase_lines l inner join purchase_orders o on o.id = l.purchase_order_id inner join blanket_orders b on o.blanket_order_id = b.id inner join distributors d on d.id = b.orderable_id inner join companies c on c.id = b.company_id where c.id = ? and b.orderable_type = ? and b.distributor_id is NULL and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.navision_order_number is not null and o.stock_point_id is not null and o.distributor_id is null and b.deleted_at is null and b.related_po_id is null', [$companyId, 'Rosetta\\Models\\Distributor', 'Cancelled', $m['start'],$m['end']]))->first()->Amount;
                
                $dist_2C_B2B_sales_dud = collect(\DB::select('select IFNULL(sum(l.amount), 0) Amount from purchase_lines l inner join purchase_orders o on o.id = l.purchase_order_id inner join blanket_orders b on o.blanket_order_id = b.id inner join distributors d on d.id = b.orderable_id inner join companies c on c.id = b.company_id inner join purchase_orders related_po on related_po.id = b.related_po_id inner join dealers dlr on dlr.id = related_po.orderable_id where c.id = ? and b.orderable_type = ? and b.distributor_id is NULL and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.stock_point_id is not null and o.navision_order_number is not null and o.distributor_id is null and related_po_id is not null and b.deleted_at is null and dlr.type = ?', [$companyId, 'Rosetta\\Models\\Distributor', 'Cancelled', $m['start'],$m['end'], 'Dealer']))->first()->Amount;

                $dist_2C_B2B_sales_rtc = collect(\DB::select('select IFNULL(sum(l.amount), 0) Amount from purchase_lines l inner join purchase_orders o on o.id = l.purchase_order_id inner join blanket_orders b on o.blanket_order_id = b.id inner join distributors d on d.id = b.orderable_id inner join companies c on c.id = b.company_id inner join purchase_orders related_po on related_po.id = b.related_po_id inner join dealers dlr on dlr.id = related_po.orderable_id where c.id = ? and b.orderable_type = ? and b.distributor_id is NULL and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.stock_point_id is not null and o.navision_order_number is not null and o.distributor_id is null and related_po_id is not null and b.deleted_at is null and dlr.type = ?', [$companyId, 'Rosetta\\Models\\Distributor', 'Cancelled', $m['start'],$m['end'], 'Retail Customer']))->first()->Amount;

                $dlr_2C_direct_sales = collect(\DB::select('select IFNULL(sum(l.amount), 0) Amount from purchase_lines l inner join purchase_orders o on o.id = l.purchase_order_id inner join blanket_orders b on o.blanket_order_id = b.id inner join dealers d on d.id = b.orderable_id inner join companies c on c.id = b.company_id where c.id = ? and b.orderable_type = ? and b.distributor_id is NULL and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.stock_point_id is not null and o.navision_order_number is not null and o.distributor_id is null and b.deleted_at is null', [$companyId, 'Rosetta\\Models\\Dealer', 'Cancelled', $m['start'],$m['end']]))->first()->Amount;

                $dist_order_cnt = collect(\DB::select('select count(*) as cnt from purchase_orders o inner join blanket_orders b on o.blanket_order_id = b.id inner join distributors d on d.id = b.orderable_id inner join companies c on c.id = b.company_id where c.id = ? and b.orderable_type = ? and b.distributor_id is NULL and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.stock_point_id is not null and o.navision_order_number is not null and o.distributor_id is null and b.deleted_at is null', [$companyId, 'Rosetta\\Models\\Distributor', 'Cancelled', $m['start'],$m['end']]))->first()->cnt;

                $dlr_order_cnt = collect(\DB::select('select count(*) as cnt from purchase_orders o inner join blanket_orders b on o.blanket_order_id = b.id inner join dealers d on d.id = b.orderable_id inner join companies c on c.id = b.company_id where c.id = ? and b.orderable_type = ? and b.distributor_id is NULL and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.stock_point_id is not null and o.navision_order_number is not null and o.distributor_id is null and b.deleted_at is null', [$companyId, 'Rosetta\\Models\\Dealer', 'Cancelled', $m['start'],$m['end']]))->first()->cnt;
                  
                $dud_order_cnt = collect(\DB::select('select count(distinct l.purchase_order_id) cnt from purchase_lines l inner join purchase_orders o on o.id = l.purchase_order_id inner join blanket_orders b on o.blanket_order_id = b.id inner join dealers d on d.id = b.orderable_id inner join distributors dist on dist.id = d.distributor_id inner join companies c on c.id = b.company_id where c.id = ? and b.orderable_type = ? and b.distributor_id is not null and d.type = ? and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.navision_order_number = "" and o.distributor_id is not null and o.stock_point_id is null and o.id in (select related_po_id from blanket_orders where related_po_id is not null and distributor_id is null) and b.deleted_at is null and dist.deleted_at is null', [$companyId, 'Rosetta\\Models\\Dealer', 'Dealer', 'Cancelled', $m['start'],$m['end']]))->first()->cnt;

                $sales_amt = $dist_2C_direct_sales + $dist_2C_B2B_sales_dud + $dist_2C_B2B_sales_rtc + $dlr_2C_direct_sales;
                $order_cnt = $dist_order_cnt + $dlr_order_cnt; 
  
                $targets = $company->targets()->where('month','=', $m['month_short'])->where('year','=', $m['year'])->first();
                $growth_percent = 0;
                if($targets)
                    $target = $targets->target;
                else
                    $target = 0;
                if($target != 0)
                    $growth_percent = (($sales_amt - $target) / $target) * 100;
                $avg_growth_percent_array[] = $growth_percent;//chk with Adnan

                $order_growth_percent = 0;
                $dealers_growth_percent = 0;
                $active_dlrs_growth_percent = 0;
                $duds_growth_percent = 0;
                $active_duds_growth_percent = 0;
                $rtc_growth_percent = 0;
                $active_rtc_growth_percent = 0;
                $percent_duds_ordering_cur_mon = 0;
                $percent_dlrs_ordering_cur_mon = 0;
                $percent_rtc_ordering_cur_mon = 0;

            //if first month of Fiscal Year has 0 orders then we take the base count from that month since when the business started (i.e. when the order cnt is non 0). In case of MD tenant business started in June 2022, so order cnt for Apr and May was 0. And if we have 0 base cnt then avg growth can never be calculated as division by 0 is not possible.*/
                if($base_order_cnt != 0)
                    $order_growth_percent = (($order_cnt - $base_order_cnt) / $base_order_cnt) * 100;
                $avg_growth_in_orders_array[] = $order_growth_percent;

                if($base_dlr_cnt != 0)
                    $dealers_growth_percent = (($dlr_cnt - $base_dlr_cnt) / $base_dlr_cnt) * 100;
                if($dlr_cnt != 0)
                {
                    $active_dlrs_growth_percent = $act_dlr_cnt / $dlr_cnt * 100;
                    $percent_dlrs_ordering_cur_mon = $dlr_ordering_cur_mon_cnt / $dlr_cnt * 100;
                }
                $avg_growth_no_of_dlrs_array[] = $dealers_growth_percent;
                $avg_growth_in_active_dlrs_array[] = $active_dlrs_growth_percent;      

                if($base_dud_cnt != 0)
                    $duds_growth_percent = (($dud_cnt - $base_dud_cnt) / $base_dud_cnt) * 100;
                if($dud_cnt != 0)
                {
                    $active_duds_growth_percent = $act_dud_cnt / $dud_cnt * 100;
                    $percent_duds_ordering_cur_mon = $dud_ordering_cur_mon_cnt / $dud_cnt * 100;
                }
                $avg_growth_no_of_duds_array[] = $duds_growth_percent;
                $avg_growth_in_active_duds_array[] = $active_duds_growth_percent;   

                if($base_rtc_cnt != 0)
                    $rtc_growth_percent = (($rtc_cnt - $base_rtc_cnt) / $base_rtc_cnt) * 100;
                if($rtc_cnt != 0)
                {
                    $active_rtc_growth_percent = $act_rtc_cnt / $rtc_cnt * 100;
                    $percent_rtc_ordering_cur_mon = $rtc_ordering_cur_mon_cnt / $rtc_cnt * 100;
                }
                $avg_growth_no_of_rtc_array[] = $rtc_growth_percent;
                $avg_growth_in_active_rtc_array[] = $active_rtc_growth_percent;      
    
                $iterator++;
                $data = \DB::insert('INSERT into master_reports (reportable_id, reportable_type, month, year, sales_amount, dist_sales_amount, dealer_sales_amount, dud_sales_amount, retail_sales_amount, company_id, no_of_distributors, no_of_dealers, no_of_active_dealers, no_of_dealers_ordering_cur_mon, no_of_duds, no_of_active_duds, no_of_duds_ordering_cur_mon, no_of_retail_customers, no_of_active_retail_customers, no_of_retail_customers_ordering_cur_mon, no_of_orders, no_of_dud_orders, growth_percentage, percent_growth_orders, percent_growth_dealers, percent_growth_active_dealers, percent_growth_duds, percent_growth_active_duds, percent_growth_retail_customers, percent_growth_active_retail_customers, percent_dealers_ordering_cur_mon, percent_duds_ordering_cur_mon, percent_retail_customers_ordering_cur_mon, target, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$companyId, 'Rosetta\\Models\\Company' , $m['month'],$m['year'], $sales_amt, $dist_2C_direct_sales, $dlr_2C_direct_sales, $dist_2C_B2B_sales_dud, $dist_2C_B2B_sales_rtc, $companyId, $dist_cnt, $dlr_cnt, $act_dlr_cnt, $dlr_ordering_cur_mon_cnt, $dud_cnt, $act_dud_cnt, $dud_ordering_cur_mon_cnt, $rtc_cnt, $act_rtc_cnt, $rtc_ordering_cur_mon_cnt, $order_cnt, $dud_order_cnt, $growth_percent, $order_growth_percent, $dealers_growth_percent, $active_dlrs_growth_percent, $duds_growth_percent, $active_duds_growth_percent, $rtc_growth_percent, $active_rtc_growth_percent, $percent_dlrs_ordering_cur_mon, $percent_duds_ordering_cur_mon, $percent_rtc_ordering_cur_mon, $target, Carbon::now()->Format('U')]);

                $base_order_cnt = $order_cnt;
                $base_dlr_cnt = $dlr_cnt;//base cnt for next month is current month cnt
                $base_dud_cnt = $dud_cnt;
                $base_rtc_cnt = $rtc_cnt;
            }
            else
            {
                $base_order_cnt = $record->no_of_orders;
                $base_dlr_cnt = $record->no_of_dealers;//base cnt for next month is current month cnt
                $base_dud_cnt = $record->no_of_duds;
                $base_rtc_cnt = $record->no_of_retail_customers;
            }
        }
        
        if($avg_growth_percent_array)
            $avg_sales_growth = array_sum($avg_growth_percent_array)/count($avg_growth_percent_array);
        if($avg_growth_in_orders_array)
            $avg_growth_in_orders = array_sum($avg_growth_in_orders_array)/count($avg_growth_in_orders_array);
        if($avg_growth_in_active_dlrs_array)
            $avg_growth_in_active_dlrs = array_sum($avg_growth_in_active_dlrs_array)/count($avg_growth_in_active_dlrs_array);
        if($avg_growth_no_of_dlrs_array)
            $avg_growth_no_of_dlrs = array_sum($avg_growth_no_of_dlrs_array)/count($avg_growth_no_of_dlrs_array);

        if($avg_growth_in_active_duds_array)
            $avg_growth_in_active_duds = array_sum($avg_growth_in_active_duds_array)/count($avg_growth_in_active_duds_array);
        if($avg_growth_no_of_duds_array)
            $avg_growth_no_of_duds = array_sum($avg_growth_no_of_duds_array)/count($avg_growth_no_of_duds_array);

        if($avg_growth_in_active_rtc_array)
            $avg_growth_in_active_rtc = array_sum($avg_growth_in_active_rtc_array)/count($avg_growth_in_active_rtc_array);
        if($avg_growth_no_of_rtc_array)
            $avg_growth_no_of_rtc = array_sum($avg_growth_no_of_rtc_array)/count($avg_growth_no_of_rtc_array);

        $avg_record = collect(\DB::select('select id from master_report_avg_growth where month = ? and year = ?', [$input_month, $input_year]))->first();

        if(!$avg_record)
        {
            $avg_data = \DB::insert('INSERT into master_report_avg_growth (month, year, company_id, avg_sales_growth, avg_growth_in_orders, avg_growth_in_active_dlrs, avg_growth_no_of_dlrs, avg_growth_in_active_duds, avg_growth_no_of_duds, avg_growth_in_active_rtc, avg_growth_no_of_rtc, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$input_month,$input_year, $companyId, $avg_sales_growth, $avg_growth_in_orders, $avg_growth_in_active_dlrs??0, $avg_growth_no_of_dlrs??0, $avg_growth_in_active_duds??0, $avg_growth_no_of_duds??0, $avg_growth_in_active_rtc??0, $avg_growth_no_of_rtc??0, Carbon::now()->Format('U')]);
        }

        $distributors = Distributor::whereNull('deleted_at')->where('company_id', '=', $companyId)->get();
        $itr = 0;
        $base_order_cnt = 0;


        foreach($distributors as $distributor)
        {
            $rec = collect(\DB::select('select * from master_reports where reportable_type = ? and reportable_id = ? and month = ? and year = ?', ['Rosetta\\Models\\Distributor', $distributor->id, $base_date->format('M'),$base_date->format('Y')]))->first();

            if($rec)
            {
                $base_order_cnt = $rec->no_of_orders;
                $base_dud_cnt = $rec->no_of_duds;
                $base_rtc_cnt = $rec->no_of_retail_customers;
            }
            foreach($month_range as $m)
            {
                $record = collect(\DB::select('select * from master_reports where reportable_type = ? and reportable_id = ? and month = ? and year = ?', ['Rosetta\\Models\\Distributor', $distributor->id, $m['month'],$m['year']]))->first();

                if(!$record)
                {
                    $targets = $distributor->targets()->where('month','=', $m['month_short'])->where('year','=', $m['year'])->first();

                    $dud_cnt = $distributor->dealers()->where('type', '=', 'Dealer')->whereNull('deleted_at')->where('created_at', '<=', $m['end'])->count();

                    $rtc_cnt = $distributor->dealers()->where('type', '=', 'Retail Customer')->whereNull('deleted_at')->where('created_at', '<=', $m['end'])->count();

                    $newly_added_duds_cnt = $distributor->dealers()->where('type', '=', 'Dealer')->whereNull('deleted_at')->where('created_at', '>=', $m['start'])->where('created_at','<=', $m['end'])->count();
                
                    $activeDealers = $distributor->dealers()->where('type', '=', 'Dealer')->whereHas('orders', function($query) use($m){
                        if(array_has($m, 'back_date'))
                        {
                            $query->where('nav_invoice_date', '>=', $m['back_date']);
                        }
                        if(array_has($m, 'end'))
                        {
                            $query->where('nav_invoice_date', '<=', $m['end']);
                        }
                        $query->where('status','=','Complete');
                    });
                    $act_dud_cnt = $activeDealers->count();

                    $DealersOrderingCurMon = $distributor->dealers()->where('type', '=', 'Dealer')->whereHas('orders', function($query) use($m){
                        if(array_has($m, 'start'))
                        {
                            $query->where('nav_invoice_date', '>=', $m['start']);
                        }
                        if(array_has($m, 'end'))
                        {
                            $query->where('nav_invoice_date', '<=', $m['end']);
                        }
                        $query->where('status','=','Complete');
                    });
                    $dud_ordering_cur_mon_cnt = $DealersOrderingCurMon->count();
                    $activeRTCs = $distributor->dealers()->where('type', '=', 'Retail Customer')->whereHas('orders', function($query) use($m){
                        if(array_has($m, 'back_date'))
                        {
                            $query->where('nav_invoice_date', '>=', $m['back_date']);
                        }
                        if(array_has($m, 'end'))
                        {
                            $query->where('nav_invoice_date', '<=', $m['end']);
                        }
                        $query->where('status','=','Complete');
                    });
                    $act_rtc_cnt = $activeRTCs->count();

                    $RTCsOrderingCurMon = $distributor->dealers()->where('type', '=', 'Retail Customer')->whereHas('orders', function($query) use($m){
                        if(array_has($m, 'start'))
                        {
                            $query->where('nav_invoice_date', '>=', $m['start']);
                        }
                        if(array_has($m, 'end'))
                        {
                            $query->where('nav_invoice_date', '<=', $m['end']);
                        }
                        $query->where('status','=','Complete');
                    });
                    $rtc_ordering_cur_mon_cnt = $RTCsOrderingCurMon->count();

                    $sales_amt = collect(\DB::select('select sum(l.amount) Amount from purchase_lines l inner join purchase_orders o on o.id = l.purchase_order_id inner join blanket_orders b on o.blanket_order_id = b.id inner join dealers d on d.id = b.orderable_id inner join distributors dist on dist.id = d.distributor_id inner join companies c on c.id = b.company_id where c.id = ? and b.orderable_type = ? and b.distributor_id = ? and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.navision_order_number = "" and o.distributor_id is not null and o.stock_point_id is null and o.id in (select related_po_id from blanket_orders where related_po_id is not null and distributor_id is null) and b.deleted_at is null', [$companyId, 'Rosetta\\Models\\Dealer', $distributor->id, 'Cancelled', $m['start'],$m['end']]))->first()->Amount;

                    $order_cnt = collect(\DB::select('select count(distinct l.purchase_order_id) cnt from purchase_lines l inner join purchase_orders o on o.id = l.purchase_order_id inner join blanket_orders b on o.blanket_order_id = b.id inner join dealers d on d.id = b.orderable_id inner join distributors dist on dist.id = d.distributor_id inner join companies c on c.id = b.company_id where c.id = ? and b.orderable_type = ? and b.distributor_id = ? and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.navision_order_number = "" and o.distributor_id is not null and o.stock_point_id is null and o.id in (select related_po_id from blanket_orders where related_po_id is not null and distributor_id is null) and b.deleted_at is null', [$companyId, 'Rosetta\\Models\\Dealer', $distributor->id, 'Cancelled', $m['start'],$m['end']]))->first()->cnt;

                    $dud_order_cnt = collect(\DB::select('select count(distinct l.purchase_order_id) cnt from purchase_lines l inner join purchase_orders o on o.id = l.purchase_order_id inner join blanket_orders b on o.blanket_order_id = b.id inner join dealers d on d.id = b.orderable_id inner join distributors dist on dist.id = d.distributor_id inner join companies c on c.id = b.company_id where c.id = ? and b.orderable_type = ? and b.distributor_id = ? and d.type = ? and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.navision_order_number = "" and o.distributor_id is not null and o.stock_point_id is null and o.id in (select related_po_id from blanket_orders where related_po_id is not null and distributor_id is null) and b.deleted_at is null', [$companyId, 'Rosetta\\Models\\Dealer', $distributor->id, 'Dealer', 'Cancelled', $m['start'],$m['end']]))->first()->cnt;

                    $dist_comp_sales_amt = collect(\DB::select('select IFNULL(sum(l.amount), 0) Amount from purchase_lines l inner join purchase_orders o on o.id = l.purchase_order_id inner join blanket_orders b on o.blanket_order_id = b.id inner join distributors d on d.id = b.orderable_id inner join companies c on c.id = b.company_id inner join purchase_orders related_po on related_po.id = b.related_po_id inner join dealers dlr on dlr.id = related_po.orderable_id where c.id = ? and b.orderable_type = ? and b.orderable_id = ? and b.distributor_id is NULL and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.stock_point_id is not null and o.navision_order_number is not null and o.distributor_id is null and related_po_id is not null and b.deleted_at is null', [$companyId, 'Rosetta\\Models\\Distributor', $distributor->id, 'Cancelled', $m['start'],$m['end']]))->first()->Amount;

                    $growth_percent = 0;
                    $duds_growth_percent = 0;
                    $active_duds_growth_percent = 0;
                    $rtc_growth_percent = 0;
                    $active_rtc_growth_percent = 0;
                    $order_growth_percent = 0;
                    $percent_duds_ordering_cur_mon = 0;
                    $percent_rtc_ordering_cur_mon = 0;

                    if($targets)
                        $target = $targets->target;
                    else
                        $target = 0;
                    if($target != 0)
                        $growth_percent = (($dist_comp_sales_amt - $target) / $target) * 100;
                    //$growth_percent = (($sales_amt - $target) / $target) * 100; Distributors maintain their own Invoicing softwae and hence there is no way for Business to verify Sales between Distributor and Dealer. Sales Amount from ROS reports is compared with NAV hence to calculate growth_percent for Distributors, Dist to Comp Sales Amount is considered instead of Dealer to Dist Sales.
           
                    if($base_order_cnt != 0)
                        $order_growth_percent = (($order_cnt - $base_order_cnt) / $base_order_cnt) * 100;

                    if($base_dud_cnt != 0)
                        $duds_growth_percent = (($dud_cnt - $base_dud_cnt) / $base_dud_cnt) * 100;
                    if($dud_cnt != 0)
                    {
                        $active_duds_growth_percent = $act_dud_cnt / $dud_cnt * 100;
                        $percent_duds_ordering_cur_mon = $dud_ordering_cur_mon_cnt / $dud_cnt * 100;
                    }
                    if($base_rtc_cnt != 0)
                        $rtc_growth_percent = (($rtc_cnt - $base_rtc_cnt) / $base_rtc_cnt) * 100;
                    if($rtc_cnt != 0)
                    {
                        $active_rtc_growth_percent = $act_rtc_cnt / $rtc_cnt * 100;
                        $percent_rtc_ordering_cur_mon = $rtc_ordering_cur_mon_cnt / $rtc_cnt * 100;
                    }
                    $itr++;
                    $data = \DB::insert('INSERT into master_reports (reportable_id, reportable_type, month, year, sales_amount, comp_dist_sales_amount, company_id, no_of_duds, no_of_active_duds, no_of_duds_ordering_cur_mon, no_of_retail_customers, no_of_active_retail_customers, no_of_retail_customers_ordering_cur_mon, no_of_orders, no_of_dud_orders, growth_percentage, percent_growth_orders, percent_growth_duds, percent_growth_active_duds, percent_growth_retail_customers, percent_growth_active_retail_customers, percent_duds_ordering_cur_mon, percent_retail_customers_ordering_cur_mon, no_of_newly_added_duds, target, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$distributor->id, 'Rosetta\\Models\\Distributor', $m['month'],$m['year'], $sales_amt, $dist_comp_sales_amt, $companyId, $dud_cnt, $act_dud_cnt, $dud_ordering_cur_mon_cnt, $rtc_cnt, $act_rtc_cnt, $rtc_ordering_cur_mon_cnt, $order_cnt, $dud_order_cnt, $growth_percent, $order_growth_percent, $duds_growth_percent, $active_duds_growth_percent, $rtc_growth_percent, $active_rtc_growth_percent, $percent_duds_ordering_cur_mon, $percent_rtc_ordering_cur_mon, $newly_added_duds_cnt, $target, Carbon::now()->Format('U')]);

                    $base_order_cnt = $order_cnt;
                    $base_dud_cnt = $dud_cnt;
                    $base_rtc_cnt = $rtc_cnt;
                }
                else
                {
                    $base_order_cnt = $record->no_of_orders;
                    $base_dud_cnt = $record->no_of_duds;
                    $base_rtc_cnt = $record->no_of_retail_customers;
                }
            }
        }
    }
}