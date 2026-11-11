<?php

namespace Rosetta\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Services\Reports\ReportsServiceInterface;
use Illuminate\Support\Facades\DB;

class ExportCompanySales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:company_sales {companyId} {start_date} {end_date}';
   
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export B2B orders and direct orders placed by Distributor and Dealer under Company. start_date and end_date should be start and end of the previous month. If you are executing the repport on 1st April 2023 then start_date will be 01-03-2023 and end_date will be 31-03-2023';

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
        
        $params = [];
        if($this->argument('start_date'))
            $params['start_date'] = Carbon::parse($this->argument('start_date'))->startOfMonth()->format('U');
        if($this->argument('end_date'))
            $params['end_date'] = Carbon::parse($this->argument('end_date'))->endOfMonth()->format('U');
        
        $current_date = Carbon::now();
        $prev_mon_1 = $current_date->format('F');
        $start_of_prev_month = Carbon::now()->subMonth()->format('F');
        $prev_yr_1 = $current_date->format('Y');

        $companyId = $this->argument('companyId');

        $dist_2_c_orders = \DB::select('select d.name Distributor, sp.name Location, p.category_code Category, p.collection_code Collection, p.colour_code Colour, sum(l.quantity) Quantity, sum(l.amount) Amount from products p inner join purchase_lines l on p.id = l.product_id inner join purchase_orders o on o.id = l.purchase_order_id inner join blanket_orders b on o.blanket_order_id = b.id inner join distributors d on d.id = b.orderable_id inner join companies c on c.id = b.company_id inner join stock_points sp on sp.id = o.stock_point_id where c.id = ? and b.orderable_type = ? and b.distributor_id is NULL and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.stock_point_id is not null and o.navision_order_number is not null and o.distributor_id is null and b.deleted_at is null group by Distributor, Category, Collection, Colour, Location order by Distributor, Location, Quantity desc', [$companyId, 'Rosetta\\Models\\Distributor', 'Cancelled', $params['start_date'],$params['end_date']]);

        $dlr_2_c_orders = \DB::select('select d.name Dealer, sp.name Location, p.category_code Category, p.collection_code Collection, p.colour_code Colour, sum(l.quantity) Quantity, sum(l.amount) Amount from products p inner join purchase_lines l on p.id = l.product_id inner join purchase_orders o on o.id = l.purchase_order_id inner join blanket_orders b on o.blanket_order_id = b.id inner join dealers d on d.id = b.orderable_id inner join companies c on c.id = b.company_id inner join stock_points sp on sp.id = o.stock_point_id where c.id = ? and b.orderable_type = ? and b.distributor_id is NULL and b.order_status != ? and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.stock_point_id is not null and o.navision_order_number is not null and o.distributor_id is null and b.deleted_at is null group by Dealer, Category, Collection, Colour, Location order by Dealer, Location, Quantity desc', [$companyId, 'Rosetta\\Models\\Dealer', 'Cancelled', $params['start_date'],$params['end_date']]);

        $spreadsheet = new Spreadsheet(); 
        $spreadsheet->getActiveSheet()->setTitle('Company_Sale_'.$prev_mon_1.'_'.$prev_yr_1);
        $activeSheet = $spreadsheet->getActiveSheet();
        $activeSheet->setCellValue('A1' , 'Customer')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1' , 'Customer_Type')->getStyle('B1')->getFont()->setBold(true);
        $activeSheet->setCellValue('C1' , 'Location')->getStyle('C1')->getFont()->setBold(true);
        $activeSheet->setCellValue('D1' , 'Category')->getStyle('D1')->getFont()->setBold(true);
        $activeSheet->setCellValue('E1' , 'Collection')->getStyle('E1')->getFont()->setBold(true);
        $activeSheet->setCellValue('F1' , 'Colour')->getStyle('F1')->getFont()->setBold(true);
        $activeSheet->setCellValue('G1' , 'Quantity')->getStyle('G1')->getFont()->setBold(true);
        $activeSheet->setCellValue('H1' , 'Amount')->getStyle('H1')->getFont()->setBold(true);
    
        $row = 2;
        foreach($dist_2_c_orders as $d)
        {
            $activeSheet->setCellValue('A'.$row, $d->Distributor);
            $activeSheet->setCellValue('B'.$row, 'Distributor');
            $activeSheet->setCellValue('C'.$row, $d->Location);
            $activeSheet->setCellValue('D'.$row, $d->Category);
            $activeSheet->setCellValue('E'.$row, $d->Collection);
            $activeSheet->setCellValue('F'.$row, $d->Colour);
            $activeSheet->setCellValue('G'.$row, $d->Quantity);
            $activeSheet->setCellValue('H'.$row, $d->Amount);
            $row++;
        }
        foreach($dlr_2_c_orders as $d)
        {
            $activeSheet->setCellValue('A'.$row, $d->Dealer);
            $activeSheet->setCellValue('B'.$row, 'Dealer');
            $activeSheet->setCellValue('C'.$row, $d->Location);
            $activeSheet->setCellValue('D'.$row, $d->Category);
            $activeSheet->setCellValue('E'.$row, $d->Collection);
            $activeSheet->setCellValue('F'.$row, $d->Colour);
            $activeSheet->setCellValue('G'.$row, $d->Quantity);
            $activeSheet->setCellValue('H'.$row, $d->Amount);
            $row++;
        }
        for($col = 'A'; $col !== 'H'; $col++) 
        {      
           $activeSheet->getColumnDimension($col)->setAutoSize(true);
        }
        $writer = new Xlsx($spreadsheet);
        $file = storage_path() . '/app/Company_Sale_'.$start_of_prev_month.'_'.$prev_yr_1.'.xlsx';
        $writer->save($file);
        //email
    }
}
