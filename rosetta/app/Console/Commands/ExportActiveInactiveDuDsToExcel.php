<?php

namespace Rosetta\Console\Commands;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Rosetta\Mail\MasterReport;
use Rosetta\Mail\SalesReport;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Services\Reports\ReportsServiceInterface;

class ExportActiveInactiveDuDsToExcel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:DuDs {company_Id} {start_date} {end_date}';
   
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export Active Inactive DuDs for Sales (Field) people';

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
        //styling array
        $tableHead = [
            'font' =>[
                'color' => [
                    'rgb' => 'FFFFFF'
                ],
            ], 
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '538ED5'
                ],
            ],
        ];
        //end of styling array
        //styling SUB HEAD 1
        $tableSubHead1 = [
            'font' =>[
                'color' => [
                    'rgb' => 'FFFFFF'
                ],
            ], 
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '53A4EC'//'2596BE'
                ],
            ],
        ];
        //end of SUB HEAD 1
         //styling SUB HEAD
        $tableSubHead = [
            'font' =>[
                'color' => [
                    'rgb' => 'FFFFFF'
                ],
            ], 
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '64ADEE'//'3BA1C5'
                ],
            ],
        ];
        //end of SUB HEAD
        $ColHighlight = [
            'font' =>[
                'color' => [
                    'rgb' => 'FFFFFF'
                ],
            ], 
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '78B725'//'3BA1C5'
                ],
            ],
        ];
        //styling for even row
        $evenRow = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '00BDFF'
                ],
            ],
        ];
        //end of styling for even row        
        //styling for odd row
        $oddRow = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'CBE4F9'//'D3EAF2'
                ],
            ],
        ];
        //end of styling for odd row        
        //border style
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];

        //end oof border style
        $companyId = $this->argument('company_Id');
        $distributors = Distributor::whereNull('deleted_at')->whereIn('id', [137,139,11])->where('company_id', '=', $companyId)->get();

        $params = [];
        if($this->argument('start_date'))
            $params['start_date'] = Carbon::parse($this->argument('start_date'))->startOfMonth()->format('U');
        if($this->argument('end_date'))
            $params['end_date'] = Carbon::parse($this->argument('end_date'))->endOfMonth()->format('U');
        
        $current_date = Carbon::parse($this->argument('start_date'))->startOfMonth();
        $fiscal_year_start = null;
        $fiscal_year_end = null;
        $start_of_prev_month = $current_date->copy()->subMonth()->startOfMonth();
        $end_of_prev_month = $current_date->copy()->subMonth()->endOfMonth();

        if($start_of_prev_month->month > 3)
        {
            $fiscal_year_start = Carbon::createFromDate($start_of_prev_month->year,4,1)->startOfMonth();
            $fiscal_year_end = Carbon::createFromDate($start_of_prev_month->year+1,3, 31)->endOfMonth();
            $prev_fiscal_year_start = Carbon::createFromDate($start_of_prev_month->year,4,1)->startOfMonth();
            $prev_fiscal_year_end = Carbon::createFromDate($start_of_prev_month->year+1,3, 31)->endOfMonth();
        }
        else
        {
            $fiscal_year_start = Carbon::createFromDate($start_of_prev_month->year - 1, 4, 1)->startOfMonth();
            $fiscal_year_end = Carbon::createFromDate($start_of_prev_month->year,3, 31)->endOfMonth();
            $prev_fiscal_year_start = Carbon::createFromDate($start_of_prev_month->year - 1, 4, 1)->startOfMonth();
            $prev_fiscal_year_end = Carbon::createFromDate($start_of_prev_month->year,3, 31)->endOfMonth();
        }
        $prev_fiscal_year_start = $prev_fiscal_year_start->subYear();
        $prev_fiscal_year_end = $prev_fiscal_year_end->subYear();
        $prev_fisc_yr_short = $prev_fiscal_year_start->format('y').'-'.$prev_fiscal_year_end->format('y');
        $fisc_yr_short = $fiscal_year_start->format('y').'-'.$fiscal_year_end->format('y');
     
        $month_diff = $current_date->copy()->diffInMonths($fiscal_year_start);
        $st_prev_mon = ($current_date->subMonthsNoOverflow())->startOfMonth()->format('U');
        $end_prev_mon = ($current_date)->endOfMonth()->format('U');
        $prev_mon_1 = $current_date->format('F');
        $prev_yr_1 = $current_date->format('Y');

        $prev_mon_short = $current_date->formatLocalized('%b');

        $st_2_mon_bk = ($current_date->subMonthsNoOverflow())->startOfMonth()->format('U');
        $end_2_mon_bk = ($current_date)->endOfMonth()->format('U');
        $prev_mon_2 = $current_date->format('F');
        $prev_yr_2 = $current_date->format('Y');

        $st_3_mon_bk = ($current_date->subMonthsNoOverflow())->startOfMonth()->format('U');
        $end_3_mon_bk = ($current_date)->endOfMonth()->format('U');
        $prev_mon_3 = $current_date->format('F');
        $prev_yr_3 = $current_date->format('Y');
 
        $st_4_mon_bk = ($current_date->subMonthsNoOverflow())->startOfMonth()->format('U');
        $end_4_mon_bk = ($current_date)->endOfMonth()->format('U');
        $prev_mon_4 = $current_date->format('F');
        $prev_yr_4 = $current_date->format('Y');
 
        $st_mon_fisc = $fiscal_year_start->format('U');
        $end_mon_fisc = $end_prev_mon;


        $prev_period = CarbonPeriod::create($prev_fiscal_year_start->format('Y-m-d'), '1 month', $prev_fiscal_year_end);
        $prev_month_range = [];
        foreach ($prev_period as $dt) 
        {
            $prev_month_range[] = [ 
                "start" => $dt->startOfMonth()->format('U'),
                "end" => $dt->endOfMonth()->format('U'),
                //"month" => ()$dt->format('j'),
                "year" => $dt->format('Y'),
                "month" => $dt->format('M'),
                "month_short" => $dt->formatLocalized('%b'),
                "year_short" => $dt->format('y')
            ];
        }

        $period = CarbonPeriod::create($fiscal_year_start->format('Y-m-d'), '1 month', $end_of_prev_month->format('Y-m-d'));
        $month_range = [];
        foreach ($period as $dt) 
        {
            $month_range[] = [ 
                "start" => $dt->startOfMonth()->format('U'),
                "end" => $dt->endOfMonth()->format('U'),
                //"month" => ()$dt->format('j'),
                "year" => $dt->format('Y'),
                "month" => $dt->format('M'),
                "month_short" => $dt->formatLocalized('%b'),
                "year_short" => $dt->format('y')
            ];
        }
        $spreadsheetList = [];
        foreach($distributors as $key=>$distributor)
        {
            $spreadsheet = new Spreadsheet();
          
            $spreadsheet->getActiveSheet()->setTitle('Summary');
            $summary_sheet = $spreadsheet->getActiveSheet(0);

            //Summary sheet for distributor
           
            $summary_sheet->setCellValue('A2' , 'Summary For '.$prev_mon_1.' '.$prev_yr_1)->getStyle('A2')->getFont()->setBold(true);
            $summary_sheet->getStyle('A2')->getFont()->setSize(16);
            $summary_sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $summary_sheet->mergeCells("A2:K2");
            $summary_sheet->getStyle('A2:K2')->applyFromArray($tableHead);
            $summary_sheet->getStyle('A2:K2')->applyFromArray($borderStyle);

            $summary_sheet->setCellValue('A4' , 'Sales')->getStyle('A4')->getFont()->setBold(true);
            $summary_sheet->getStyle('A4')->getFont()->setSize(14);
            $summary_sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $summary_sheet->mergeCells("A4:H4");
            $summary_sheet->getStyle('A4:H4')->applyFromArray($tableHead);
            $summary_sheet->getStyle('A4:H4')->applyFromArray($borderStyle);

            $summary_sheet->setCellValue('B5' ,  'Last Fiscal Year ('.$prev_fisc_yr_short.')')->getStyle('B5')->getFont()->setBold(true);
            $summary_sheet->setCellValue('C5' ,  'Target - Current Year ('.$fisc_yr_short.')')->getStyle('C5')->getFont()->setBold(true);
            $summary_sheet->setCellValue('D5' ,  'Achieved - Current Year ('.$fisc_yr_short.')')->getStyle('D5')->getFont()->setBold(true);
            $summary_sheet->setCellValue('E5' ,  '%'.' of Growth '.$fisc_yr_short)->getStyle('E5')->getFont()->setBold(true);
            $summary_sheet->getStyle('A5:H5')->applyFromArray($tableSubHead);
            $summary_sheet->getStyle('A5:H5')->applyFromArray($borderStyle);

            $row = 6;
            $prev_yr_sales_total = 0;
            $cur_yr_sales_total = 0;
            $cur_yr_target_total = 0;
            foreach($prev_month_range as $m)
            {
                $summary_sheet->setCellValue("A{$row}", $m['month_short'])->getStyle("A{$row}")->getFont()->setBold(true);
                $data = collect(\DB::select('select comp_dist_sales_amount from master_reports where reportable_type = ? and reportable_id = ? and month = ? and year = ?', ['Rosetta\\Models\\Distributor', $distributor->id, $m['month_short'],$m['year']]))->first();

                if($data)
                {
                    $summary_sheet->setCellValue("B{$row}" , $data->comp_dist_sales_amount);
                    $prev_yr_sales_total = $prev_yr_sales_total + $data->comp_dist_sales_amount;
                }
                if($row % 2 != 0)
                {
                    $summary_sheet->getStyle("A{$row}:H{$row}")->applyFromArray($oddRow);
                }
                $summary_sheet->getStyle("A{$row}:H{$row}")->applyFromArray($borderStyle);
                $row++;
            }
            $summary_sheet->setCellValue("A{$row}", 'Total')->getStyle("A{$row}")->getFont()->setBold(true);
            $summary_sheet->setCellValue("B{$row}" , $prev_yr_sales_total)->getStyle("B{$row}")->getFont()->setBold(true);
            $row = 6;
            foreach($month_range as $m)
            {
                $data = collect(\DB::select('select comp_dist_sales_amount, target, growth_percentage from master_reports where reportable_type = ? and reportable_id = ? and month = ? and year = ?', ['Rosetta\\Models\\Distributor', $distributor->id, $m['month_short'],$m['year']]))->first();

                if($data)
                {
                    $summary_sheet->setCellValue("C{$row}" , $data->target);
                    $summary_sheet->setCellValue("D{$row}" , $data->comp_dist_sales_amount);
                    $summary_sheet->setCellValue("E{$row}" , $data->growth_percentage.'%');
                    $cur_yr_sales_total = $cur_yr_sales_total + $data->comp_dist_sales_amount;
                    $cur_yr_target_total = $cur_yr_target_total + $data->target;
                }
                $row++;
            }
            $summary_sheet->setCellValue("C18" , $cur_yr_target_total)->getStyle("C18")->getFont()->setBold(true);
            $summary_sheet->setCellValue("D18" , $cur_yr_sales_total)->getStyle("D18")->getFont()->setBold(true);
            $summary_sheet->getStyle('A18:H18')->applyFromArray($tableSubHead);
            $summary_sheet->getStyle('A18:H18')->applyFromArray($borderStyle);
            $summary_sheet->getStyle('D5:D18')->applyFromArray($ColHighlight);

            $summary_sheet->setCellValue('A20' , 'Dealers')->getStyle('A20')->getFont()->setBold(true);
            $summary_sheet->getStyle('A20')->getFont()->setSize(14);
            $summary_sheet->getStyle('A20')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $summary_sheet->mergeCells("A20:K20");
            $summary_sheet->getStyle('A20:K20')->applyFromArray($tableHead);
            $summary_sheet->getStyle('A20:K20')->applyFromArray($borderStyle);
            $summary_sheet->setCellValue('B21' ,  'Count - Dealers Mapped')->getStyle('B21')->getFont()->setBold(true);
            $summary_sheet->setCellValue('C21' ,  'Count - Dealer Orders')->getStyle('C21')->getFont()->setBold(true);
            $summary_sheet->setCellValue('D21' ,  'Count - Unique Dealers Ordering')->getStyle('D21')->getFont()->setBold(true);
            $summary_sheet->setCellValue('E21' ,  '%'.' of Unique Dealers Ordering')->getStyle('E21')->getFont()->setBold(true);
            $summary_sheet->setCellValue('F21' ,  'Count - Active Dealers')->getStyle('F21')->getFont()->setBold(true);
            $summary_sheet->setCellValue('G21' ,  'Active Dealers'.' %'.' - Ordering once in 3 months')->getStyle('G21')->getFont()->setBold(true);
            $summary_sheet->setCellValue('H21' ,  'Count - Inactive Dealers')->getStyle('H21')->getFont()->setBold(true);

            $summary_sheet->setCellValue('I21' ,  'Inactive Dealers '.'%'.' - Zero orders in 3 months')->getStyle('I21')->getFont()->setBold(true);
            
            $summary_sheet->setCellValue('J21' ,  '%'.' Growth in Dealers since last month')->getStyle('J21')->getFont()->setBold(true);
            $summary_sheet->setCellValue('K21' ,  'Newly Added Dealers')->getStyle('K21')->getFont()->setBold(true);

            $summary_sheet->getStyle('A21:K21')->applyFromArray($tableSubHead);
            $summary_sheet->getStyle('A21:K21')->applyFromArray($borderStyle);
            $row = 22;
            foreach($month_range as $m)
            {
                $summary_sheet->setCellValue("A{$row}", $m['month_short'].'-'.$m['year_short'])->getStyle("A{$row}")->getFont()->setBold(true);

                $data = collect(\DB::select('select no_of_duds, no_of_dud_orders, no_of_duds_ordering_cur_mon, percent_duds_ordering_cur_mon, no_of_active_duds, percent_growth_duds, no_of_newly_added_duds from master_reports where reportable_type = ? and reportable_id = ? and month = ? and year = ?', ['Rosetta\\Models\\Distributor', $distributor->id, $m['month_short'],$m['year']]))->first();

                if($data)
                {
                    $summary_sheet->setCellValue("B{$row}" , $data->no_of_duds);
                    $summary_sheet->setCellValue("C{$row}" , $data->no_of_dud_orders);
                    $summary_sheet->setCellValue("D{$row}" , $data->no_of_duds_ordering_cur_mon);
                    $summary_sheet->setCellValue("E{$row}" , $data->percent_duds_ordering_cur_mon.'%');
                    $summary_sheet->setCellValue("F{$row}" , $data->no_of_active_duds);
                    $no_of_inactive_duds = $data->no_of_duds - $data->no_of_active_duds;
                    if($data->no_of_duds != 0)
                    {
                        $active_dud_percent = $data->no_of_active_duds / $data->no_of_duds * 100;
                        $inactive_dud_percent = $no_of_inactive_duds / $data->no_of_duds * 100;
                    }
                    else
                    {
                        $active_dud_percent = 0;
                        $inactive_dud_percent = 0;
                    }
                    $summary_sheet->setCellValue("G{$row}" , round($active_dud_percent).'%');
                    $summary_sheet->setCellValue("H{$row}" , $no_of_inactive_duds);
                    $summary_sheet->setCellValue("I{$row}" , round($inactive_dud_percent).'%');
                    $summary_sheet->setCellValue("J{$row}" , $data->percent_growth_duds.'%');
                    $summary_sheet->setCellValue("K{$row}" , $data->no_of_newly_added_duds);
                }
                if($row % 2 != 0)
                {
                    $summary_sheet->getStyle("A{$row}:K{$row}")->applyFromArray($oddRow);
                }
                $row++;
            }
            $summary_sheet->getStyle('E21:E33')->applyFromArray($ColHighlight);
            $summary_sheet->getStyle('G21:G33')->applyFromArray($ColHighlight);
            $summary_sheet->getStyle('A21:K33')->applyFromArray($borderStyle);
           
            $summary_sheet->setCellValue('A36' , 'APP Usage')->getStyle('A36')->getFont()->setBold(true);
            $summary_sheet->getStyle('A36')->getFont()->setSize(14);
            $summary_sheet->getStyle('A36')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $summary_sheet->mergeCells("A36:I36");
            $summary_sheet->getStyle('A36:I36')->applyFromArray($tableHead);
            $summary_sheet->getStyle('A36:I36')->applyFromArray($borderStyle);
            $summary_sheet->setCellValue('B37' ,  'Count - Dealers Mapped')->getStyle('B37')->getFont()->setBold(true);
            $summary_sheet->setCellValue('C37' ,  'Count - Dealer Orders')->getStyle('C37')->getFont()->setBold(true);
            $summary_sheet->setCellValue('D37' ,  'Count - Unique Dealers Ordering')->getStyle('D37')->getFont()->setBold(true);
            $summary_sheet->setCellValue('E37' ,  '%'.' of Unique Dealers Ordering')->getStyle('E37')->getFont()->setBold(true);
            $summary_sheet->setCellValue('F37' ,  'Count - Self Ordering Dealers')->getStyle('F37')->getFont()->setBold(true);
            $summary_sheet->setCellValue('G37' ,  'Self Ordering Dealers'.' %')->getStyle('G37')->getFont()->setBold(true);
            $summary_sheet->setCellValue('H37' ,  'Count - Distributor Ordering on behalf of Dealers')->getStyle('H37')->getFont()->setBold(true);
            $summary_sheet->setCellValue('I37' ,  '%'.' Distributor Ordering on behalf of Dealers')->getStyle('I37')->getFont()->setBold(true);
            
            $summary_sheet->getStyle('A37:I37')->applyFromArray($tableSubHead);
            $summary_sheet->getStyle('A37:I37')->applyFromArray($borderStyle);
            $row = 38;
            foreach($month_range as $m)
            {
                $summary_sheet->setCellValue("A{$row}", $m['month_short'].'-'.$m['year_short'])->getStyle("A{$row}")->getFont()->setBold(true);
                $data = collect(\DB::select('select no_of_duds, no_of_dud_orders, no_of_duds_ordering_cur_mon, percent_duds_ordering_cur_mon from master_reports where reportable_type = ? and reportable_id = ? and month = ? and year = ?', ['Rosetta\\Models\\Distributor', $distributor->id, $m['month_short'],$m['year']]))->first();
                $no_of_duds = $data->no_of_duds??0;
                $percent_duds_ordering_cur_mon = $data->percent_duds_ordering_cur_mon??0;
                $summary_sheet->setCellValue("B{$row}" , $no_of_duds);
                $summary_sheet->setCellValue("C{$row}" , $data->no_of_dud_orders??0);
                $summary_sheet->setCellValue("D{$row}" , $data->no_of_duds_ordering_cur_mon??0);
                $summary_sheet->setCellValue("E{$row}" , $percent_duds_ordering_cur_mon.'%');

                $query = PurchaseOrder::select('orderable_id')->where('orderable_type','=','Rosetta\\Models\\Dealer')->where('status','=','Complete')->where('nav_invoice_date', '>=', $m['start'])->where('nav_invoice_date', '<=', $m['end'])->where('distributor_id','=', $distributor->id);
                $query->whereHas('blanket_order',function($q) {
                     $q->where('on_behalf','!=','Dealer');
                  });
                $query->groupBy('orderable_id')->pluck('orderable_id');
                $self = $query->get();
                $self_cnt = Dealer::wherein('id', $self)->where('type', '=', 'Dealer')->count();

                $summary_sheet->setCellValue("F{$row}" , $self_cnt);
                if($no_of_duds != 0)
                    $self_dud_percent = $self_cnt / $no_of_duds * 100;
                else
                    $self_dud_percent = 0;
                $summary_sheet->setCellValue("G{$row}" , round($self_dud_percent).'%');

                $query = PurchaseOrder::select('orderable_id')->where('orderable_type','=','Rosetta\\Models\\Dealer')->where('status','=','Complete')->where('nav_invoice_date', '>=', $m['start'])->where('nav_invoice_date', '<=', $m['end'])->where('distributor_id','=', $distributor->id);
                $query->whereHas('blanket_order',function($q) {
                     $q->where('on_behalf','=','Dealer');
                  });
                $query->groupBy('orderable_id')->pluck('orderable_id');
                $on_behalf = $query->get();
                $on_behalf_cnt = Dealer::wherein('id', $on_behalf)->where('type', '=', 'Dealer')->count();

                $summary_sheet->setCellValue("H{$row}" , $on_behalf_cnt);
                if($no_of_duds != 0)
                    $on_behalf_dud_percent = $on_behalf_cnt / $no_of_duds * 100;
                else
                    $on_behalf_dud_percent = 0;
                $summary_sheet->setCellValue("I{$row}" , round($on_behalf_dud_percent).'%');

                if($row % 2 != 0)
                {
                    $summary_sheet->getStyle("A{$row}:I{$row}")->applyFromArray($oddRow);
                }
                $row++;
            }
            $summary_sheet->getStyle('F37:F49')->applyFromArray($ColHighlight);
            $summary_sheet->getStyle('A37:I49')->applyFromArray($borderStyle);

            $summary_sheet->setCellValue('A51' , $prev_mon_1.' Sales Details')->getStyle('A51')->getFont()->setBold(true);
            $summary_sheet->getStyle('A51')->getFont()->setSize(14);
            $summary_sheet->getStyle('A51')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $summary_sheet->mergeCells("A51:G51");
            $summary_sheet->getStyle('A51:G51')->applyFromArray($tableHead);
            $summary_sheet->getStyle('A51:G51')->applyFromArray($borderStyle);

            $summary_sheet->setCellValue('A52' , 'Dealer Name')->getStyle('A52')->getFont()->setBold(true);
            $summary_sheet->setCellValue('B52' , 'Order Value')->getStyle('B52')->getFont()->setBold(true);
            $summary_sheet->setCellValue('C52' , 'No Of Orders')->getStyle('C52')->getFont()->setBold(true);
            $summary_sheet->setCellValue('D52' , 'No Of On Behalf Orders')->getStyle('D52')->getFont()->setBold(true);
            $summary_sheet->setCellValue('E52' , 'No Of Self Orders')->getStyle('E52')->getFont()->setBold(true);
            $summary_sheet->setCellValue('F52' , 'Newly Added')->getStyle('F52')->getFont()->setBold(true);
            $summary_sheet->setCellValue('G52' , 'Dealer / Retail Customer')->getStyle('G52')->getFont()->setBold(true);
            $summary_sheet->getStyle('A52:G52')->applyFromArray($tableSubHead);
            $summary_sheet->getStyle('A52:G52')->applyFromArray($borderStyle);
            $row = 53;

            $c = Dealer::where('distributor_id','=',$distributor->id)->whereNull('deleted_at');
            $customers = $c->get();
            $customerData = [];
            foreach($customers as $dealer)
            {
                $customerRecord = [];
                list($amount, $count) = $this->reportsService->getDealerOrdersAmount($dealer, $st_prev_mon, $end_prev_mon);
                $customerRecord['amount'] = $amount;
                $customerRecord['count'] = $count;
                $customerRecord['dealer'] = $dealer;
                $customerRecord['onBehalfCnt'] = $this->reportsService->getOnBehalfCnt($dealer, $st_prev_mon, $end_prev_mon);
                $customerRecord['selfCnt'] = $this->reportsService->getSelfCnt($dealer, $st_prev_mon, $end_prev_mon);
                $customerData[] = (Object)$customerRecord;
            }
            usort($customerData, function($a,$b){
                return $b->amount <=> $a->amount;
            });
            foreach($customerData as $rec)
            {
                $summary_sheet->setCellValue('A'.$row, $rec->dealer->name);
                $summary_sheet->setCellValue('B'.$row, $rec->amount);
                $summary_sheet->setCellValue('C'.$row, $rec->count);
                $summary_sheet->setCellValue('D'.$row, $rec->onBehalfCnt);
                $summary_sheet->setCellValue('E'.$row, $rec->selfCnt);
                $summary_sheet->setCellValue('F'.$row, $rec->dealer->created_at->format('U') >= $st_prev_mon ? 'Y' : 'N',);
                $summary_sheet->setCellValue('G'.$row, $rec->dealer->type);
                if($row % 2 != 0)
                {
                    $summary_sheet->getStyle("A{$row}:G{$row}")->applyFromArray($oddRow);
                }
                $summary_sheet->getStyle("A{$row}:G{$row}")->applyFromArray($borderStyle);
                $row++;
            }
            $summary_sheet->getColumnDimension('A')->setAutoSize(false);
            $summary_sheet->getColumnDimension('A')->setWidth(30);
            $summary_sheet->getStyle('A')->getAlignment()->setWrapText(true);
            for($col = 'B'; $col !== 'L'; $col++) 
            {
                $summary_sheet->getColumnDimension($col)->setAutoSize(false);
                $summary_sheet->getColumnDimension($col)->setWidth(12);
                $summary_sheet->getStyle($col)->getAlignment()->setWrapText(true);
                $summary_sheet->getStyle($col)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            //end of summary sheet for distributors
         

            $spreadsheet->createSheet();   
            $spreadsheet->setActiveSheetIndex(1);
            $spreadsheet->getActiveSheet()->setTitle('Active_Inactive_Dealers');

            $dealers = $this->reportsService->getDealersWithOrders($distributor, $params);//orders check removed as we want all dealers who might not have ordered this month but have in the past
            $activeSheet = $spreadsheet->getActiveSheet();
            $activeSheet->setCellValue('A1' , 'Sr No')->getStyle('A1')->getFont()->setBold(true);
            $activeSheet->setCellValue('B1' , 'NAV ID')->getStyle('B1')->getFont()->setBold(true);
            $activeSheet->setCellValue('C1' , 'Dealer Business Name')->getStyle('C1')->getFont()->setBold(true);
            $activeSheet->setCellValue('D1' , 'Contact Person')->getStyle('D1')->getFont()->setBold(true);
            $activeSheet->setCellValue('E1' , 'Phone')->getStyle('E1')->getFont()->setBold(true);
            $activeSheet->setCellValue('F1' , 'Email')->getStyle('F1')->getFont()->setBold(true);
            $activeSheet->setCellValue('G1' , 'Area')->getStyle('G1')->getFont()->setBold(true);
            $activeSheet->setCellValue('H1' , 'City')->getStyle('H1')->getFont()->setBold(true);
            $activeSheet->setCellValue('I1' , 'State')->getStyle('I1')->getFont()->setBold(true);
            $activeSheet->setCellValue('J1' , 'Category')->getStyle('J1')->getFont()->setBold(true);
            $activeSheet->setCellValue('K1' , $prev_fisc_yr_short.' Per Month Avg')->getStyle('K1')->getFont()->setBold(true);
            $activeSheet->setCellValue('L1' , $fisc_yr_short.' Per Month Avg')->getStyle('L1')->getFont()->setBold(true);
            $activeSheet->setCellValue('M1' , $prev_mon_4. ' '.$prev_yr_4.' Sales')->getStyle('M1')->getFont()->setBold(true);
            $activeSheet->setCellValue('N1' , $prev_mon_3. ' '.$prev_yr_3.' Sales')->getStyle('N1')->getFont()->setBold(true);
            $activeSheet->setCellValue('O1' , $prev_mon_2. ' '.$prev_yr_2.' Sales')->getStyle('O1')->getFont()->setBold(true);
            $activeSheet->setCellValue('P1' , $prev_mon_1. ' '.$prev_yr_1.' Sales')->getStyle('P1')->getFont()->setBold(true);
            $activeSheet->setCellValue('Q1' , 'Active/Inactive')->getStyle('Q1')->getFont()->setBold(true);
            $activeSheet->setCellValue('R1' , 'Total Orders via APP')->getStyle('R1')->getFont()->setBold(true);
            $activeSheet->setCellValue('S1' , 'Total Orders On Behalf of Dealer')->getStyle('S1')->getFont()->setBold(true);
            $activeSheet->setCellValue('T1' , 'Total Orders by Dealer')->getStyle('T1')->getFont()->setBold(true);
            $activeSheet->setCellValue('U1' , 'Newly Added (Y/N) in '.$prev_mon_1)->getStyle('U1')->getFont()->setBold(true);
            $activeSheet->setCellValue('V1' , 'Updated Folder Sets Given (Y/N)')->getStyle('V1')->getFont()->setBold(true);
            $activeSheet->setCellValue('W1' , 'App explained & Activated')->getStyle('W1')->getFont()->setBold(true);
            $activeSheet->setCellValue('X1' , 'Notes')->getStyle('X1')->getFont()->setBold(true);   
            $data = [];

            foreach ($dealers as $dealer)
            {
                $activeSheet->setCellValue('A'.$row, $row - 1);
                list($amountPrevFiscal,) = $this->reportsService->getDealerOrdersAmount($dealer,  $prev_fiscal_year_start->format('U'),  $prev_fiscal_year_end->format('U'));
                list($amountFiscal,) = $this->reportsService->getDealerOrdersAmount($dealer,  $st_mon_fisc,  $end_mon_fisc);
                list($amount4Month,) = $this->reportsService->getDealerOrdersAmount($dealer,  $st_4_mon_bk,  $end_4_mon_bk);
                list($amount3Month, ) = $this->reportsService->getDealerOrdersAmount($dealer,  $st_3_mon_bk,  $end_3_mon_bk);
                list($amount2Month,) = $this->reportsService->getDealerOrdersAmount($dealer,  $st_2_mon_bk,  $end_2_mon_bk);
                list($amountPrevMonth, $countPrevMonth) = $this->reportsService->getDealerOrdersAmount($dealer,  $st_prev_mon,  $end_prev_mon);
                $app_order_cnt = $this->reportsService->getAppOrdersCnt($dealer, $st_prev_mon, $end_prev_mon);
                $on_behalf_cnt = $this->reportsService->getOnBehalfCnt($dealer, $st_prev_mon, $end_prev_mon);
                $self_cnt = $app_order_cnt - $on_behalf_cnt;
                $active_inactive = 'Inactive';
                if($amount3Month != 0 || $amount2Month != 0 || $amountPrevMonth != 0)
                {
                    $active_inactive = 'Active';
                }
                $prev_avg_sale = round($amountPrevFiscal / 12, 2); 
                $avg_sale = round($amountFiscal / $month_diff, 2);
                $data[] = (Object)[
                    'nav_id'          => $dealer->nav_id,
                    'name'            => $dealer->name,
                    'contact'         => $dealer->contact,
                    'phone'           => $dealer->phone,
                    'email'           => $dealer->email,
                    'area'            => $dealer->area,
                    'city_code'       => $dealer->city_code,
                    'state_code'      => $dealer->state_code,
                    'type'            => $dealer->type,
                    'amountPrevFiscal'=> $prev_avg_sale,
                    'amountFiscal'    => $avg_sale,
                    'amount4Month'    => $amount4Month,
                    'amount3Month'    => $amount3Month,
                    'amount2Month'    => $amount2Month,
                    'amountPrevMonth' => $amountPrevMonth,
                    'active_inactive' => $active_inactive,
                    'app_order_cnt'   => $app_order_cnt,
                    'on_behalf_cnt'   => $on_behalf_cnt,
                    'self_cnt'        => $self_cnt,
                    'newly_added'     => $dealer->created_at->format('U') >= $st_prev_mon ? 'Y' : 'N',
                    'folder_given'    => $dealer->updated_folder_given == true ? 'Y' : 'N',
                    'app_explained'   => $dealer->app_explained == true ? 'Y' : 'N',
                    'notes'           => $dealer->notes,
                ];                 
            }
            usort($data,function($a,$b){
                return $b->amountPrevMonth <=> $a->amountPrevMonth;
            });
            $row = 2;
            foreach ($data as $rec)
            {
                $activeSheet->setCellValue('A'.$row, $row - 1);
                $activeSheet->setCellValue('B'.$row, $rec->nav_id);
                $activeSheet->setCellValue('C'.$row, $rec->name);
                $activeSheet->setCellValue('D'.$row, $rec->contact);
                $activeSheet->setCellValue('E'.$row, $rec->phone);
                $activeSheet->setCellValue('F'.$row, $rec->email);
                $activeSheet->setCellValue('G'.$row, $rec->area);
                $activeSheet->setCellValue('H'.$row, $rec->city_code);
                $activeSheet->setCellValue('I'.$row, $rec->state_code);
                $activeSheet->setCellValue('J'.$row, $rec->type);
                $activeSheet->setCellValue('K'.$row, $rec->amountPrevFiscal);
                $activeSheet->setCellValue('L'.$row, $rec->amountFiscal);
                $activeSheet->setCellValue('M'.$row, $rec->amount4Month);
                $activeSheet->setCellValue('N'.$row, $rec->amount3Month);
                $activeSheet->setCellValue('O'.$row, $rec->amount2Month);
                $activeSheet->setCellValue('P'.$row, $rec->amountPrevMonth);
                $activeSheet->setCellValue('Q'.$row, $rec->active_inactive);
                $activeSheet->setCellValue('R'.$row, $rec->app_order_cnt);
                $activeSheet->setCellValue('S'.$row, $rec->on_behalf_cnt);
                $activeSheet->setCellValue('T'.$row, $rec->self_cnt);
                $activeSheet->setCellValue('U'.$row, $rec->newly_added); 
                $activeSheet->setCellValue('V'.$row, $rec->folder_given);
                $activeSheet->setCellValue('W'.$row, $rec->app_explained);
                $activeSheet->setCellValue('X'.$row, $rec->notes);        

                if($row % 2 != 0)
                {
                    $activeSheet->getStyle("A{$row}:X{$row}")->applyFromArray($oddRow);
                }
                $activeSheet->getStyle("A{$row}:X{$row}")->applyFromArray($borderStyle);             
                $row++;
            }
            for($col = 'A'; $col !== 'S'; $col++) 
            {
                $activeSheet->getColumnDimension($col)->setAutoSize(true);
            }
            foreach ($activeSheet->getColumnIterator() as $column) {
                $activeSheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }
            $activeSheet->getStyle('A1:X1')->applyFromArray($tableHead);
            $activeSheet->getStyle('A1:X1')->applyFromArray($borderStyle);
            $writer = new Xlsx($spreadsheet);
            $file = storage_path() . '/app/'.str_slug($distributor->name).'-'.$distributor->id.$key.'.xlsx';
            $spreadsheetList[] = $file;
            $writer->save($file);
            unset($spreadsheet);
            unset($activeSheet);
            unset($summary_sheet);
            //Distributor Email
                $queuePrefix = config('brand.queue_prefix');
                $queue = $queuePrefix.'.emails';
                $toEmailIds = $distributor->to_email;
                $ccEmailIds = $distributor->cc_email;
                try
                {
                    if(!empty($toEmailIds))
                    {
                        $recipients = explode(',',$toEmailIds);
                        $recipients = array_map(function($a){
                            return (Object)[
                                'email' => $a, 
                                'name'  => ''
                            ];
                        }, $recipients);
                        $cc_recipients = explode(',',$ccEmailIds);
                        $cc_recipients = array_map(function($b){
                            return (Object)[
                                'email' => $b, 
                                'name'  => ''
                            ];
                        }, $cc_recipients);
                       // foreach($recipients as $recipient)
                        //{
                            $subject = $distributor->name.' - Sales Report '.$prev_mon_1.' '.$prev_yr_1;
                            $Qmessage = (new SalesReport($distributor, $prev_mon_1, $prev_yr_1, $subject, $file))->onQueue($queue);
                            if(!empty($ccEmailIds))
                                Mail::to($recipients)->cc($cc_recipients)->queue($Qmessage); 
                            else
                                Mail::to($recipients)->queue($Qmessage); 
                        //}   
                    }
                }
                catch(ConnectionException $e)
                {}
            //end of Distributor Email
        }
        $merge_spreadsheet = new Spreadsheet();
        
        $tenants_company = Company::find($companyId);
        $tenant_name = $tenants_company->getSettingsForType('current_tenant')->get('tenant_name');
        /*$summary_worksheet = clone $merge_spreadsheet->getSheetByName('Worksheet');
        $summary_worksheet->setTitle($tenant_name.' Summary');
        $merge_spreadsheet->addSheet($summary_worksheet,0);  
        $merge_spreadsheet->removeSheetByIndex(
            $merge_spreadsheet->getIndex(
                $merge_spreadsheet->getSheetByName('Worksheet')
            )
        );*/
        $activeSheet1 = $merge_spreadsheet->getActiveSheet();
        $activeSheet1->setCellValue('A2' , 'Summary For '.$prev_mon_1.' '.$prev_yr_1)->getStyle('A2')->getFont()->setBold(true);
        $activeSheet1->getStyle('A2')->getFont()->setSize(16);
        $activeSheet1->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $activeSheet1->mergeCells("A2:H2");
        $activeSheet1->getStyle('A2:H2')->applyFromArray($tableHead);
        $activeSheet1->getStyle('A2:H2')->applyFromArray($borderStyle);

        $activeSheet1->setCellValue('A4' , 'Sales')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet1->getStyle('A4')->getFont()->setSize(14);
        $activeSheet1->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $activeSheet1->mergeCells("A4:H4");
        $activeSheet1->getStyle('A4:H4')->applyFromArray($tableHead);
        $activeSheet1->getStyle('A4:H4')->applyFromArray($borderStyle);

        $activeSheet1->setCellValue('B5' ,  'Target - Current Year ('.$fisc_yr_short.')')->getStyle('B5')->getFont()->setBold(true);
        $activeSheet1->setCellValue('C5' ,  'Achieved - Current Year ('.$fisc_yr_short.')')->getStyle('C5')->getFont()->setBold(true);
        $activeSheet1->setCellValue('D5' , 'Distributor Sales')->getStyle('D5')->getFont()->setBold(true);
       
        $activeSheet1->setCellValue('E5' ,  'Count - DUD Orders')->getStyle('E5')->getFont()->setBold(true);
        $activeSheet1->setCellValue('F5' ,  'Count - DUDs')->getStyle('F5')->getFont()->setBold(true);
        $activeSheet1->setCellValue('G5' ,  '%'.' of Active DUDs ')->getStyle('G5')->getFont()->setBold(true);
        $activeSheet1->setCellValue('H5' ,  '%'.' of Growth '.$fisc_yr_short)->getStyle('H5')->getFont()->setBold(true);
        $activeSheet1->getStyle('A5:H5')->applyFromArray($tableSubHead);
        $activeSheet1->getStyle('A5:H5')->applyFromArray($borderStyle);

        $row = 6;
        $cur_yr_comp_sales_total = 0;
        $cur_yr_comp_target_total = 0;
        $cur_yr_dist_sales_total = 0;
        
        foreach($month_range as $m)
        {
            $activeSheet1->setCellValue("A{$row}", $m['month_short'])->getStyle("A{$row}")->getFont()->setBold(true);
            $data = collect(\DB::select('select target, sales_amount, growth_percentage, dist_sales_amount, no_of_dud_orders, dealer_sales_amount, dud_sales_amount, retail_sales_amount, no_of_distributors, no_of_dealers, percent_growth_dealers, no_of_active_dealers, percent_growth_active_dealers, no_of_duds, percent_growth_duds, no_of_active_duds, percent_growth_active_duds, no_of_retail_customers, percent_growth_retail_customers, no_of_active_retail_customers, percent_growth_active_retail_customers, no_of_orders, percent_growth_orders from master_reports where reportable_type = ? and reportable_id = ? and month = ? and year = ?', ['Rosetta\\Models\\Company', $companyId, $m['month_short'],$m['year']]))->first();

            if($data)
            {
                $activeSheet1->setCellValue("B{$row}" , $data->target);
                $activeSheet1->setCellValue("C{$row}" , $data->sales_amount);
                $total_dist_2_comp_sales = $data->dist_sales_amount + $data->dud_sales_amount + $data->retail_sales_amount;
                $activeSheet1->setCellValue("D{$row}" , $total_dist_2_comp_sales);
                $activeSheet1->setCellValue("E{$row}" , $data->no_of_dud_orders);
                $activeSheet1->setCellValue("F{$row}" , $data->no_of_duds);
                $activeSheet1->setCellValue("G{$row}" , $data->percent_growth_active_duds);
                $activeSheet1->setCellValue("H{$row}" , $data->growth_percentage.'%');
                $cur_yr_comp_sales_total = $cur_yr_comp_sales_total + $data->sales_amount;
                $cur_yr_comp_target_total = $cur_yr_comp_target_total + $data->target;
                $cur_yr_dist_sales_total = $cur_yr_dist_sales_total + $total_dist_2_comp_sales;
                if($row % 2 != 0)
                {
                    $activeSheet1->getStyle("A{$row}:H{$row}")->applyFromArray($oddRow);
                }
                $activeSheet1->getStyle("A{$row}:H{$row}")->applyFromArray($borderStyle);
            }
            $row++;
        }
        $activeSheet1->setCellValue("B18" , $cur_yr_comp_target_total)->getStyle("B18")->getFont()->setBold(true);
        $activeSheet1->setCellValue("C18" , $cur_yr_comp_sales_total)->getStyle("C18")->getFont()->setBold(true);
        $activeSheet1->setCellValue("D18" , $cur_yr_dist_sales_total)->getStyle("D18")->getFont()->setBold(true);
        $activeSheet1->getStyle('A18:H18')->applyFromArray($tableSubHead);
        $activeSheet1->getStyle('A18:H18')->applyFromArray($borderStyle);
        $activeSheet1->getStyle('D5:D18')->applyFromArray($ColHighlight);

        $activeSheet1->getColumnDimension('A')->setAutoSize(false);
        $activeSheet1->getColumnDimension('A')->setWidth(30);
        $activeSheet1->getStyle('A')->getAlignment()->setWrapText(true);
        for($col = 'B'; $col !== 'J'; $col++) 
        {
            $activeSheet1->getColumnDimension($col)->setAutoSize(false);
            $activeSheet1->getColumnDimension($col)->setWidth(12);
            $activeSheet1->getStyle($col)->getAlignment()->setWrapText(true);
            $activeSheet1->getStyle($col)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $activeSheet1->setTitle('Summary');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($merge_spreadsheet,'Xlsx');
        $writer->save(storage_path() . '/app/'.$tenant_name.' Master Report.xlsx');
        unset($merge_spreadsheet);
        unset($activeSheet1);
        //$reader = IOFactory::createReader("Xlsx");
        //$merge_spreadsheet = $reader->load(storage_path() . '/app/'.$tenant_name.' Master Report.xlsx');
        $merge_spreadsheet = IOFactory::load(storage_path() . '/app/'.$tenant_name.' Master Report.xlsx');
        foreach($spreadsheetList as $file)
        {
            $workbook = IOFactory::load($file);
            $worksheet = $workbook->getSheetByName('Summary');
            
            $distributor_name = basename($file,'.xlsx');
            if(strlen($distributor_name) > 30)
                $distributor_name = substr($distributor_name, 0, 30);
            $worksheet->setTitle($distributor_name);
            $worksheet->getStyle('1:1000')->applyFromArray([]);
            $merge_spreadsheet->addExternalSheet($worksheet);
            unset($worksheet);
            unset($workbook);
        }
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($merge_spreadsheet,'Xlsx');
        $file = storage_path() . '/app/'.$tenant_name.' Master Report.xlsx';
        $writer->save($file);
         //Superadmin Email
            $queuePrefix = config('brand.queue_prefix');
            $queue = $queuePrefix.'.emails';
            $toEmailIds = $tenants_company->getSettingsForType('current_tenant')->get('tenant_to_email');
            try
            {
                if(!empty($toEmailIds))
                {
                    $recipients = explode(',',$toEmailIds);
                    $recipients = array_map(function($a){
                        return (Object)[
                            'email' => $a, 
                            'name'  => ''
                        ];
                    }, $recipients);
                    $subject = $tenant_name.' - Master Report '.$prev_mon_1.' '.$prev_yr_1;
                    $Qmessage = (new MasterReport($tenant_name, $prev_mon_1, $prev_yr_1, $subject, $file))->onQueue($queue);
                    Mail::to($recipients)->queue($Qmessage); 
                }
            }
            catch(ConnectionException $e)
            {}
        //end of Superadmin Email
    }
}
