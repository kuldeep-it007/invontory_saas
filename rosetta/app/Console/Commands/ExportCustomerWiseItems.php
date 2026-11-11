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

class ExportCustomerWiseItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:customer_wise_items {companyId} {start_date} {end_date}';
   
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export Item Sales per Customer. start_date and end_date should be start and end of the previous month. If you are executing the repport on 1st April 2023 then start_date will be 01-03-2023 and end_date will be 31-03-2023';

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
        //end oof border style
        
        $params = [];
        if($this->argument('start_date'))
            $params['start_date'] = Carbon::parse($this->argument('start_date'))->startOfMonth()->format('U');
        if($this->argument('end_date'))
            $params['end_date'] = Carbon::parse($this->argument('end_date'))->endOfMonth()->format('U');
        
        $companyId = $this->argument('companyId');
        $distributors = Distributor::whereNull('deleted_at')->where('company_id', '=', $companyId)->get();
        $current_date = Carbon::now();
        $prev_mon_1 = $current_date->format('F');
        $start_of_prev_month = Carbon::now()->subMonth()->format('F');
        $prev_yr_1 = $current_date->format('Y');

        $spreadsheetList = [];
        foreach($distributors as $distributor)
        {
            $spreadsheet = new Spreadsheet(); 
            $spreadsheet->getActiveSheet()->setTitle('Customer_Wise_Items');
            $dealers = $this->reportsService->getDealersWithOrders($distributor, $params);
           
            $activeSheet = $spreadsheet->getActiveSheet();
            $activeSheet->setCellValue('A1' , 'Dealer Business Name')->getStyle('A1')->getFont()->setBold(true);
            $activeSheet->setCellValue('B1' , 'Category')->getStyle('B1')->getFont()->setBold(true);
            $activeSheet->setCellValue('C1' , 'Collection')->getStyle('C1')->getFont()->setBold(true);
            $activeSheet->setCellValue('D1' , 'Colour')->getStyle('D1')->getFont()->setBold(true);
            $activeSheet->setCellValue('E1' , 'Quantity')->getStyle('E1')->getFont()->setBold(true);
            $activeSheet->setCellValue('F1' , 'Amount')->getStyle('F1')->getFont()->setBold(true);
        
            $row = 2;
            $data = \DB::select('select d.nav_id Dealer_Id, d.name Dealer, p.name Product,  p.category_code Category, p.collection_code Collection, p.colour_code Colour,  sum(l.quantity) Quantity,sum(l.amount) Amount from products p inner join purchase_lines l on p.id = l.product_id inner join purchase_orders o on o.id = l.purchase_order_id inner join blanket_orders b on o.blanket_order_id = b.id inner join dealers d on d.id = b.orderable_id inner join distributors dist on dist.id = d.distributor_id inner join companies c on c.id = b.company_id where c.id = ? and b.orderable_type = ? and b.distributor_id = ? and b.order_status not in (?) and o.nav_invoice_date >= ? and o.nav_invoice_date <= ? and o.navision_order_number = "" and o.stock_point_id is null and o.id in (select related_po_id from blanket_orders where related_po_id is not null and distributor_id is null) and b.deleted_at is null group by Dealer_Id, Dealer,Product, Category, Collection, Colour order by Dealer, Quantity desc', [$companyId, 'Rosetta\\Models\\Dealer',$distributor->id, 'Cancelled', $params['start_date'],$params['end_date']]);

         //   var_dump(collect($data)->groupBy('Dealer_Id'));
        //    $ddd  = "";
            foreach($data as $d)
            {
        /*       if($ddd != $d->Dealer)
                {
                    $displayDealer = $ddd = $d->Dealer;
                }
                else
                {
                    $displayDealer = "";
                }
                $activeSheet->setCellValue('A'.$row, $displayDealer);*/
                $activeSheet->setCellValue('A'.$row, $d->Dealer);
                $activeSheet->setCellValue('B'.$row, $d->Category);
                $activeSheet->setCellValue('C'.$row, $d->Collection);
                $activeSheet->setCellValue('D'.$row, $d->Colour);
                $activeSheet->setCellValue('E'.$row, $d->Quantity);
                $activeSheet->setCellValue('F'.$row, $d->Amount);
                $row++;
            }
           // dd($data);
            for($col = 'A'; $col !== 'F'; $col++) 
            {      
               $activeSheet->getColumnDimension($col)->setAutoSize(true);
            }
            $writer = new Xlsx($spreadsheet);
            $file = storage_path() . '/app/'.$distributor->name.'.xlsx';
            $spreadsheetList[] = $file;
            $writer->save($file);
            //email
        }
        $merge_spreadsheet = new Spreadsheet();
        foreach($spreadsheetList as $file)
        {
            $reader = IOFactory::createReader("Xlsx");
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file);

            foreach($spreadsheet->getSheetNames() as $sheet_name)
            {
                $distributor_name = basename($file,'.xlsx');
                if(strlen($distributor_name) > 30)
                    $distributor_name = substr($distributor_name, 0, 30);
                $clonedWorksheet = clone $spreadsheet->getSheetByName($sheet_name);
                $clonedWorksheet->setTitle($distributor_name);
                $merge_spreadsheet->addSheet($clonedWorksheet);
            }
        }
        $merge_spreadsheet->removeSheetByIndex(
            $merge_spreadsheet->getIndex(
                $merge_spreadsheet->getSheetByName('Worksheet')
            )
        );
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($merge_spreadsheet,'Xlsx');
      //  $writer->save(storage_path() . '/app/Customer_Wise_Items_Sales_Combined.xlsx');
        $writer->save(storage_path() . '/app/Customer_Wise_Items_'.$start_of_prev_month.'_'.$prev_yr_1.'.xlsx');
    }
}
