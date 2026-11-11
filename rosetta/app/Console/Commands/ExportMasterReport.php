<?php

namespace Rosetta\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Services\Reports\ReportsServiceInterface;

class ExportMasterReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:master_report {companyId} {month} {year}';
   
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export Master Report. month and year should be last month and corresponding year. If you are executing the repport on 1st April 2023 then month will be Mar and year will be 2023. If run on 01-01-2023 then month will be Dec and year willbe 2022.';

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
        //styling for even row
        $evenRow = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '669933'
                ],
            ],
        ];
        //end of styling for even row        
        //styling for odd row
        $oddRow = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '66FF66'
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
        //end of border style
        
        $companyId = $this->argument('companyId');
        $input_month = $this->argument('month');
        $input_year = $this->argument('year');
        $tenants_company = Company::find($companyId);
        $tenant_name = $tenants_company->getSettingsForType('current_tenant')->get('tenant_name');
        $company = Company::find($companyId);

        $current_date = Carbon::now();
        $prev_mon_1 = $current_date->formatLocalized('%b');
        $prev_yr_1 = $current_date->format('Y');

        $formatted_start_of_prev_month = Carbon::now()->subMonth()->format('F');
      
        $start_of_prev_month = Carbon::now()->subMonth()->startOfMonth();

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

        $fisc_mon_st = $fiscal_year_start->formatLocalized('%b');
        $fisc_yr_st = $fiscal_year_start->format('Y');
          
        $spreadsheet = new Spreadsheet();  
        $title = $tenant_name.'_Master_Report_'.$formatted_start_of_prev_month;
        if(strlen($title) > 30)
            $title = substr($title, 0, 30);
        $spreadsheet->getActiveSheet()->setTitle($title);
        $activeSheet = $spreadsheet->getActiveSheet(0);

        $activeSheet->setCellValue('A2' , $tenant_name)->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->getStyle('A2')->getFont()->setSize(16);
        $activeSheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $activeSheet->mergeCells("A2:N2");
        $activeSheet->getStyle('A2:N2')->applyFromArray($tableHead);
        $activeSheet->getStyle('A2:N2')->applyFromArray($borderStyle);

        $activeSheet->setCellValue('B4' , 'Avg Growth in Sales since '.$fisc_mon_st. ' ' .$fisc_yr_st)->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B5' , 'Avg Growth in Number of Orders since '.$fisc_mon_st. ' ' .$fisc_yr_st)->getStyle('B5')->getFont()->setBold(true);
        $activeSheet->setCellValue('B6' , 'Avg Growth in Active Dealers under Company since '.$fisc_mon_st. ' ' .$fisc_yr_st)->getStyle('B6')->getFont()->setBold(true);
        $activeSheet->setCellValue('B7' , 'Avg Growth in Number of Dealers under Company since '.$fisc_mon_st. ' ' .$fisc_yr_st)->getStyle('B7')->getFont()->setBold(true);
        $activeSheet->setCellValue('B8' , 'Avg Growth in Active Dealers under Distributors since '.$fisc_mon_st. ' ' .$fisc_yr_st)->getStyle('B8')->getFont()->setBold(true);
        $activeSheet->setCellValue('B9' , 'Avg Growth in Number of Dealers under Distributors since '.$fisc_mon_st. ' ' .$fisc_yr_st)->getStyle('B9')->getFont()->setBold(true);
        $activeSheet->setCellValue('B10' , 'Avg Growth in Active Retail Customers since '.$fisc_mon_st. ' ' .$fisc_yr_st)->getStyle('B10')->getFont()->setBold(true);
        $activeSheet->setCellValue('B11' , 'Avg Growth in Number of Retail Customers since '.$fisc_mon_st. ' ' .$fisc_yr_st)->getStyle('B11')->getFont()->setBold(true);

        $activeSheet->getStyle('B4:B11')->applyFromArray($evenRow);
        $activeSheet->getStyle('B4:B11')->applyFromArray($borderStyle);

        $avg_record = collect(\DB::select('select avg_sales_growth, avg_growth_in_orders, avg_growth_in_active_dlrs, avg_growth_no_of_dlrs, avg_growth_in_active_duds, avg_growth_no_of_duds, avg_growth_in_active_rtc, avg_growth_no_of_rtc from master_report_avg_growth where month = ? and year = ? and company_id = ?', [$input_month, $input_year, $companyId]))->first();

        if($avg_record)
        {
            $activeSheet->setCellValue('C4', $avg_record->avg_sales_growth.'%');
            $activeSheet->setCellValue('C5', $avg_record->avg_growth_in_orders.'%');
            $activeSheet->setCellValue('C6', $avg_record->avg_growth_in_active_dlrs.'%');
            $activeSheet->setCellValue('C7', $avg_record->avg_growth_no_of_dlrs.'%');
            $activeSheet->setCellValue('C8', $avg_record->avg_growth_in_active_duds.'%');
            $activeSheet->setCellValue('C9', $avg_record->avg_growth_no_of_duds.'%');
            $activeSheet->setCellValue('C10', $avg_record->avg_growth_in_active_rtc.'%');
            $activeSheet->setCellValue('C11', $avg_record->avg_growth_no_of_rtc.'%');
        }

        $activeSheet->getStyle('C4:C11')->applyFromArray($evenRow);
        $activeSheet->getStyle('C4:C11')->applyFromArray($borderStyle);

        $activeSheet->setCellValue('A13' , $company->name)->getStyle('A13')->getFont()->setBold(true);
        $activeSheet->getStyle('A13')->getFont()->setSize(16);
        $activeSheet->getStyle('A13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $activeSheet->mergeCells("A13:N13");
        $activeSheet->getStyle('A13:N13')->applyFromArray($tableHead);
        $activeSheet->getStyle('A13:N13')->applyFromArray($borderStyle);

        $activeSheet->setCellValue('A14' , 'Sr No')->getStyle('A14')->getFont()->setBold(true);
        $activeSheet->setCellValue('A15' , '1');
        $activeSheet->setCellValue('A16' , '2');
        $activeSheet->setCellValue('A17' , '3');
        $activeSheet->setCellValue('A18' , '4');
        $activeSheet->setCellValue('A19' , '5');
        $activeSheet->setCellValue('A20' , '6');
        $activeSheet->setCellValue('A21' , '7');
        $activeSheet->setCellValue('A22' , '8');
        $activeSheet->setCellValue('A23' , '9');
        $activeSheet->setCellValue('A24' , '10');
        $activeSheet->setCellValue('A25' , '11');
        $activeSheet->setCellValue('A26' , '12');
        $activeSheet->setCellValue('A27' , '13');
        $activeSheet->setCellValue('A28' , '14');
        $activeSheet->setCellValue('A29' , '15');
        $activeSheet->setCellValue('A30' , '16');
        $activeSheet->setCellValue('A31' , '17');
        $activeSheet->setCellValue('A32' , '18');
        $activeSheet->setCellValue('A33' , '19');
        $activeSheet->setCellValue('A34' , '20');
        $activeSheet->setCellValue('A35' , '21');
        $activeSheet->setCellValue('A36' , '22');

        $activeSheet->setCellValue('B14' , 'Particulars')->getStyle('B14')->getFont()->setBold(true);
        $activeSheet->setCellValue('B15' , 'Target')->getStyle('B15')->getFont()->setBold(true);
        $activeSheet->setCellValue('B16' , 'Actual Sales')->getStyle('B16')->getFont()->setBold(true);
        $activeSheet->setCellValue('B17' , '%'.' Growth in Sales')->getStyle('B17')->getFont()->setBold(true);
        $activeSheet->setCellValue('B18' , 'Distributor Sales')->getStyle('B18')->getFont()->setBold(true);
        $activeSheet->setCellValue('B19' , 'Dealer under Company Sales')->getStyle('B19')->getFont()->setBold(true);
        $activeSheet->setCellValue('B20' , 'Dealer under Distributor Sales')->getStyle('B20')->getFont()->setBold(true);
        $activeSheet->setCellValue('B21' , 'Retail Customer Sales')->getStyle('B21')->getFont()->setBold(true);
        $activeSheet->setCellValue('B22' , 'No of Distributors')->getStyle('B22')->getFont()->setBold(true);
        $activeSheet->setCellValue('B23' , 'No of Dealers under Company')->getStyle('B23')->getFont()->setBold(true);
        $activeSheet->setCellValue('B24' ,  '%'.' Growth in Dealers under Company')->getStyle('B24')->getFont()->setBold(true);
        $activeSheet->setCellValue('B25' , 'No of Active Dealers under Company')->getStyle('B25')->getFont()->setBold(true);
        $activeSheet->setCellValue('B26' ,  '%'.' Growth in Active Dealers under Company')->getStyle('B26')->getFont()->setBold(true);
        $activeSheet->setCellValue('B27' , 'No of Dealers under Distributors')->getStyle('B27')->getFont()->setBold(true);
        $activeSheet->setCellValue('B28' ,  '%'.' Growth in Dealers under Distributors')->getStyle('B28')->getFont()->setBold(true);
        $activeSheet->setCellValue('B29' , 'No of Active Dealers under Distributors')->getStyle('B29')->getFont()->setBold(true);
        $activeSheet->setCellValue('B30' ,  '%'.' Growth in Active Dealers under Distributors')->getStyle('B30')->getFont()->setBold(true);
        $activeSheet->setCellValue('B31' , 'No of Retail Customers')->getStyle('B31')->getFont()->setBold(true);
        $activeSheet->setCellValue('B32' ,  '%'.' Growth in Retail Customers')->getStyle('B32')->getFont()->setBold(true);
        $activeSheet->setCellValue('B33' , 'No of Active Retail Customers')->getStyle('B33')->getFont()->setBold(true); 
        $activeSheet->setCellValue('B34' ,  '%'.' Growth in Active Retail Customers')->getStyle('B34')->getFont()->setBold(true);
        $activeSheet->setCellValue('B35' , 'No of Orders per month')->getStyle('B35')->getFont()->setBold(true);
        $activeSheet->setCellValue('B36' , '%'.' Growth in Orders')->getStyle('B36')->getFont()->setBold(true);
  
        $row = 14;  
        $mon = $fisc_mon_st; 
        $yr = $fisc_yr_st;
        for($col = 'C'; $col < 'O' ;$col++)
        {    
            $mon = $fiscal_year_start->formatLocalized('%b');
            if($mon == 'Jan' || $mon == 'Feb' || $mon == 'Mar')
            {
                $yr++;
            }
            $data = collect(\DB::select('select target, sales_amount, growth_percentage, dist_sales_amount, dealer_sales_amount, dud_sales_amount, retail_sales_amount, no_of_distributors, no_of_dealers, percent_growth_dealers, no_of_active_dealers, percent_growth_active_dealers, no_of_duds, percent_growth_duds, no_of_active_duds, percent_growth_active_duds, no_of_retail_customers, percent_growth_retail_customers, no_of_active_retail_customers, percent_growth_active_retail_customers, no_of_orders, percent_growth_orders from master_reports where reportable_type = ? and reportable_id = ? and month = ? and year = ?', ['Rosetta\\Models\\Company', $companyId, $mon,$yr]))->first();
            if($data)
            {
                $activeSheet->setCellValue("{$col}14", $mon.'-'.$yr)->getStyle("{$col}14")->getFont()->setBold(true);
                $activeSheet->setCellValue("{$col}15", $data->target);
                $activeSheet->setCellValue("{$col}16", $data->sales_amount);
                $activeSheet->setCellValue("{$col}17", $data->growth_percentage.'%');
                $activeSheet->setCellValue("{$col}18", $data->dist_sales_amount);
                $activeSheet->setCellValue("{$col}19", $data->dealer_sales_amount);
                $activeSheet->setCellValue("{$col}20", $data->dud_sales_amount);
                $activeSheet->setCellValue("{$col}21", $data->retail_sales_amount);
                $activeSheet->setCellValue("{$col}22", $data->no_of_distributors);
                $activeSheet->setCellValue("{$col}23", $data->no_of_dealers);
                $activeSheet->setCellValue("{$col}24", $data->percent_growth_dealers.'%');
                $activeSheet->setCellValue("{$col}25", $data->no_of_active_dealers);
                $activeSheet->setCellValue("{$col}26", $data->percent_growth_active_dealers.'%');
                $activeSheet->setCellValue("{$col}27", $data->no_of_duds);
                $activeSheet->setCellValue("{$col}28", $data->percent_growth_duds.'%');
                $activeSheet->setCellValue("{$col}29", $data->no_of_active_duds);
                $activeSheet->setCellValue("{$col}30", $data->percent_growth_active_duds.'%');
                $activeSheet->setCellValue("{$col}31", $data->no_of_retail_customers);
                $activeSheet->setCellValue("{$col}32", $data->percent_growth_retail_customers.'%');
                $activeSheet->setCellValue("{$col}33", $data->no_of_active_retail_customers);
                $activeSheet->setCellValue("{$col}34", $data->percent_growth_active_retail_customers.'%');
                $activeSheet->setCellValue("{$col}35", $data->no_of_orders);
                $activeSheet->setCellValue("{$col}36", $data->percent_growth_orders.'%');
            }        
            if($mon == 'Jan' || $mon == 'Feb' || $mon == 'Mar')
            {
                $yr--;
            }
            $fiscal_year_start->addMonth();
        }
    

          $row = 38;

        $distributors = Distributor::whereNull('deleted_at')->where('company_id', '=', $companyId)->get();
        foreach($distributors as $distributor)
        {
            $mon = $fisc_mon_st; 
            $yr = $fisc_yr_st;
            for($col = 'C'; $col < 'O' ;$col++)
            {    
                $mon = $fiscal_year_start->formatLocalized('%b');
                if($mon == 'Jan' || $mon == 'Feb' || $mon == 'Mar')
                {
                    $yr++;
                }
                $data = collect(\DB::select('select target, sales_amount, growth_percentage, no_of_duds, percent_growth_duds, no_of_active_duds, percent_growth_active_duds, no_of_retail_customers, percent_growth_retail_customers, no_of_active_retail_customers, percent_growth_active_retail_customers, no_of_orders, percent_growth_orders from master_reports where reportable_type = ? and reportable_id = ? and month = ? and year = ?', ['Rosetta\\Models\\Distributor', $distributor->id, $mon,$yr]))->first();
               
                if($data)
                {
                    $r = $row; 

                    $activeSheet->setCellValue("A{$r}" , $distributor->name)->getStyle("A{$r}")->getFont()->setBold(true);
                    $activeSheet->getStyle("A{$r}")->getFont()->setSize(16);
                    $activeSheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $activeSheet->mergeCells("A{$r}:N{$r}");
                    $activeSheet->getStyle("A{$r}:N{$r}")->applyFromArray($tableHead);
                    $activeSheet->getStyle("A{$r}:N{$r}")->applyFromArray($borderStyle);
                    $r++;

                    $activeSheet->setCellValue("A{$r}" , 'Sr No')->getStyle("A{$r}")->getFont()->setBold(true);
                    $activeSheet->setCellValue("{$col}"."{$r}", $mon.'-'.$yr)->getStyle("{$col}"."{$r}")->getFont()->setBold(true);
                    $activeSheet->setCellValue("B{$r}" , 'Particulars')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '1');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->target);
                    $activeSheet->setCellValue("B{$r}" , 'Target')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '2');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->sales_amount);
                    $activeSheet->setCellValue("B{$r}" , 'Sales')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '3');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->growth_percentage.'%');
                    $activeSheet->setCellValue("B{$r}" , '%'.' Growth in Sales')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '4');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->no_of_duds);
                    $activeSheet->setCellValue("B{$r}" , 'No of Dealers')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '5');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->percent_growth_duds.'%');
                    $activeSheet->setCellValue("B{$r}" , '%'.' Growth in Dealers')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '6');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->no_of_active_duds);
                    $activeSheet->setCellValue("B{$r}" , 'No of Active Dealers')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '7');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->percent_growth_active_duds.'%');
                    $activeSheet->setCellValue("B{$r}" , '%'.' Growth in Active Dealers')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '8');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->no_of_retail_customers);
                    $activeSheet->setCellValue("B{$r}" , 'No of Retail Customers')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '9');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->percent_growth_retail_customers.'%');
                    $activeSheet->setCellValue("B{$r}" , '%'.' Growth in Retail Customers')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '10');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->no_of_active_retail_customers);
                    $activeSheet->setCellValue("B{$r}" , 'No of Active Retail Customers')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '11');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->percent_growth_active_retail_customers.'%');
                    $activeSheet->setCellValue("B{$r}" , '%'.' Growth in Active Retail Customers')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '12');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->no_of_orders);
                    $activeSheet->setCellValue("B{$r}" , 'No of Orders per month')->getStyle("B{$r}")->getFont()->setBold(true);
                    $r++;
                    $activeSheet->setCellValue("A{$r}" , '13');
                    $activeSheet->setCellValue("{$col}"."{$r}", $data->percent_growth_orders.'%');
                    $activeSheet->setCellValue("B{$r}" , '%'.' Growth in Orders')->getStyle("B{$r}")->getFont()->setBold(true);
                    
                }
                if($mon == 'Jan' || $mon == 'Feb' || $mon == 'Mar')
                {
                    $yr--;
                }
                $fiscal_year_start->addMonth();
            }
            $row = $r + 2;
        }

    //    $activeSheet->getStyle('A1:E20')->getAlignment()->setWrapText(true);

        for($col = 'A'; $col !== 'O'; $col++) 
        {
            $activeSheet->getColumnDimension($col)->setAutoSize(true);
            $activeSheet->getStyle($col)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        foreach ($activeSheet->getColumnIterator() as $column) {
            $activeSheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }
        $writer = new Xlsx($spreadsheet);
        $file = storage_path() . '/app/'.$tenant_name.'_Master_Report_'.$formatted_start_of_prev_month.'_'.$prev_yr_1.'.xlsx';
        $writer->save($file);
        //email
    }
}
